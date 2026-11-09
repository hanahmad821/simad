<?php

namespace App\Services;

use App\Models\LetterType;
use App\Models\OutgoingLetter;
use Illuminate\Validation\Rule;

class LetterTemplateService
{
    public function preserveExistingLetters(LetterType $template): void
    {
        $snapshot = $this->snapshot($template);
        OutgoingLetter::where('letter_type_id', $template->id)->where('use_template', true)->whereNull('template_snapshot')
            ->orderBy('id')->chunkById(100, function ($letters) use ($snapshot) {
                foreach ($letters as $letter) {
                    $letterSnapshot = $snapshot;
                    if ($snapshot['engine'] === 'editor') {
                        $letterSnapshot['values'] = app(LetterTemplateContentService::class)->values($letter, $snapshot['fields']);
                    }
                    $letter->update(['template_snapshot' => $letterSnapshot]);
                }
            });
    }

    /** @return array<string, mixed> */
    public function snapshot(LetterType $template): array
    {
        return ['engine' => $template->body_html !== null ? 'editor' : 'legacy', 'name' => $template->name,
            'version' => $template->template_version ?? 1, 'fields' => $template->fields ?? [],
            'body_html' => $template->body_html, 'template_view' => $template->template_view, 'template_file' => $template->template_file,
            'uses_body' => $template->body_html === null && app(LetterTemplateCatalog::class)->usesBody($template->template_view)];
    }

    /** @return array<string, mixed> */
    public function definition(OutgoingLetter $letter): array
    {
        return $letter->template_snapshot ?? ($letter->letterType ? $this->snapshot($letter->letterType) : []);
    }

    public function capture(OutgoingLetter $letter, ?LetterType $template = null): void
    {
        $type = $template ?? $letter->letterType;
        $snapshot = $letter->template_snapshot;
        if ($snapshot === null) {
            if (! $type) {
                throw new \RuntimeException('Jenis surat untuk template tidak ditemukan.');
            }
            $snapshot = $this->snapshot($type);
            $catalog = app(LetterTemplateCatalog::class);
            if ($catalog->availableFor($type)) {
                $snapshot['fields'] = $catalog->fieldsFor($type);
                $snapshot['uses_body'] = $catalog->usesBody($type->template_view);
            }
        }
        if (($snapshot['template_view'] ?? null) === 'pdf.letters.tugas' && ($snapshot['engine'] ?? null) !== 'editor') {
            $snapshot['assignment'] = app(AssignmentLetterService::class)->resolve($letter);
        }
        if (($snapshot['engine'] ?? null) === 'editor') {
            $snapshot['values'] = app(LetterTemplateContentService::class)->values($letter, $snapshot['fields']);
        }
        $letter->template_snapshot = $snapshot;
    }

    /**
     * @param  array<int, array{name: string, label?: string, type?: string, required?: bool}>  $fields
     * @return array<string, array<int, mixed>>
     */
    public function letterRules(array $fields): array
    {
        $rules = [];
        foreach ($fields as $field) {
            $key = 'formData.'.$field['name'];
            $type = $field['type'] ?? 'text';
            $rules[$key] = [($field['required'] ?? false) ? 'required' : 'nullable'];
            if ($type === 'gtk_assignments') {
                $rules[$key] = array_merge($rules[$key], ['array', 'min:1', 'max:20']);
                $rules[$key.'.*'] = ['required', 'array:gtk_id,pangkat'];
                $rules[$key.'.*.gtk_id'] = ['required', 'integer', 'distinct', Rule::exists('gtks', 'id')->where('is_active', true)];
                $rules[$key.'.*.pangkat'] = ['nullable', 'string', 'max:100'];
            } elseif ($type === 'repeater') {
                $rules[$key] = array_merge($rules[$key], ['array', 'max:50']);
                $rules[$key.'.*'] = ['required', 'string', 'max:1000'];
            } elseif (in_array($type, ['student', 'gtk'], true)) {
                $rules[$key] = array_merge($rules[$key], ['integer', Rule::exists($type === 'student' ? 'students' : 'gtks', 'id')->where('is_active', true)]);
            } elseif ($type === 'date') {
                $rules[$key][] = 'date_format:Y-m-d';
            } else {
                $rules[$key] = array_merge($rules[$key], ['string', 'max:5000']);
            }
        }

        return $rules;
    }
}
