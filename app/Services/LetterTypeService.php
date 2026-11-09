<?php

namespace App\Services;

use App\Models\LetterType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LetterTypeService
{
    /** @param array<string, mixed> $input */
    public function save(User $actor, array $input, ?LetterType $type = null): LetterType
    {
        abort_unless($actor->isAdmin(), 403);

        return DB::transaction(function () use ($input, $type) {
            $current = $type ? LetterType::whereKey($type->id)->lockForUpdate()->firstOrFail() : null;
            $catalog = app(LetterTemplateCatalog::class);
            $input['name'] = is_string($input['name'] ?? null) ? trim($input['name']) : ($input['name'] ?? null);
            $input['code'] = is_string($input['code'] ?? null) ? strtoupper(trim($input['code'])) : ($input['code'] ?? null);
            $data = Validator::make($input, [
                'name' => ['required', 'string', 'max:150', Rule::unique('letter_types', 'name')->ignore($current?->id)],
                'code' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9.-]+$/'],
                'template_view' => ['nullable', 'string', Rule::in(array_keys($catalog->all()))],
                'is_active' => ['required', 'boolean'],
            ], [
                'code.regex' => 'Kode klasifikasi hanya boleh berisi huruf, angka, titik, dan tanda hubung.',
                'template_view.in' => 'Pilih template surat baku yang tersedia.',
            ])->validate();

            $data['template_view'] = ($data['template_view'] ?? '') ?: null;
            $data['fields'] = $catalog->find($data['template_view'])['fields'] ?? [];
            $data['body_html'] = null;
            $data['template_file'] = null;
            $data['template_version'] = $current ? ($current->template_version ?? 1) + 1 : 1;

            if ($current) {
                app(LetterTemplateService::class)->preserveExistingLetters($current);
                $current->update($data);

                return $current;
            }

            return LetterType::create($data);
        }, 3);
    }

    public function delete(User $actor, LetterType $type): void
    {
        abort_unless($actor->isAdmin(), 403);
        DB::transaction(function () use ($type) {
            $current = LetterType::whereKey($type->id)->lockForUpdate()->firstOrFail();
            if ($current->outgoingLetters()->exists()) {
                throw ValidationException::withMessages(['letter_type' => 'Jenis surat sudah digunakan. Nonaktifkan melalui Edit agar arsip tetap tersimpan.']);
            }
            $current->delete();
        });
    }
}
