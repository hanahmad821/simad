<?php

namespace App\Services;

use App\Models\Gtk;
use App\Models\OutgoingLetter;
use App\Models\SchoolSetting;
use App\Models\Student;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Validation\ValidationException;

class LetterTemplateContentService
{
    public const SYSTEM_FIELDS = [
        'nomor_surat' => 'Nomor surat', 'tanggal_surat' => 'Tanggal surat', 'tujuan' => 'Tujuan surat',
        'perihal' => 'Perihal', 'keterangan' => 'Keterangan', 'nama_madrasah' => 'Nama madrasah',
        'alamat_madrasah' => 'Alamat madrasah', 'nama_kepala_madrasah' => 'Nama kepala madrasah',
        'nip_kepala_madrasah' => 'NIP kepala madrasah',
    ];

    public const FIELD_TYPES = ['text' => 'Teks singkat', 'textarea' => 'Paragraf', 'date' => 'Tanggal',
        'student' => 'Pilih siswa', 'gtk' => 'Pilih GTK', 'repeater' => 'Daftar isian'];

    public function choices(array $fields): array
    {
        $choices = self::SYSTEM_FIELDS;
        foreach ($fields as $field) {
            $name = $field['name'];
            $label = $field['label'];
            $choices[$name] = $label;
            $parts = match ($field['type']) {
                'student' => ['nama' => 'Nama', 'nis' => 'NIS', 'nisn' => 'NISN', 'kelas' => 'Kelas',
                    'tahun_pelajaran' => 'Tahun pelajaran', 'tempat_lahir' => 'Tempat lahir', 'tanggal_lahir' => 'Tanggal lahir', 'alamat' => 'Alamat'],
                'gtk' => ['nama' => 'Nama', 'nip' => 'NIP', 'jabatan' => 'Jabatan'],
                default => [],
            };
            foreach ($parts as $key => $part) {
                $choices[$name.'.'.$key] = $label.' · '.$part;
            }
        }

        return $choices;
    }

    private function document(string $html): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'.$html.'</body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        return $document;
    }

    public function sanitize(string $html): string
    {
        $source = $this->document($html);
        $output = new DOMDocument('1.0', 'UTF-8');
        $root = $output->appendChild($output->createElement('div'));
        foreach ($source->getElementsByTagName('body')->item(0)->childNodes as $node) {
            $this->copyNode($node, $root, $output);
        }

        return $this->innerHtml($root);
    }

    private function copyNode(DOMNode $node, DOMNode $parent, DOMDocument $output): void
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $parent->appendChild($output->createTextNode($node->textContent));

            return;
        }
        if (! $node instanceof DOMElement) {
            return;
        }
        $tag = strtolower($node->tagName);
        if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'input', 'button', 'textarea', 'select', 'img', 'video', 'audio', 'link', 'meta'], true)) {
            return;
        }
        $allowed = ['p', 'h2', 'h3', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'br', 'span', 'blockquote'];
        $target = $parent;
        if (in_array($tag, $allowed, true)) {
            $target = $parent->appendChild($output->createElement($tag));
            if ($tag === 'span' && $node->hasAttribute('data-letter-field')) {
                $key = $node->getAttribute('data-letter-field');
                $target->setAttribute('data-letter-field', $key);
                $label = mb_substr($node->getAttribute('data-label') ?: $key, 0, 150);
                $target->setAttribute('data-label', $label);
                $target->appendChild($output->createTextNode('['.$label.']'));

                return;
            }
            if (preg_match('/(?:^|;)\s*text-align\s*:\s*(left|right|center|justify)\s*(?:;|$)/i', $node->getAttribute('style'), $match)) {
                $target->setAttribute('style', 'text-align:'.strtolower($match[1]));
            }
            if ($tag === 'ol' && ctype_digit($node->getAttribute('start')) && (int) $node->getAttribute('start') >= 1 && (int) $node->getAttribute('start') <= 9999) {
                $target->setAttribute('start', $node->getAttribute('start'));
            }
            if (in_array($tag, ['td', 'th'], true)) {
                foreach (['colspan', 'rowspan'] as $attribute) {
                    if (ctype_digit($node->getAttribute($attribute)) && (int) $node->getAttribute($attribute) >= 1 && (int) $node->getAttribute($attribute) <= 20) {
                        $target->setAttribute($attribute, $node->getAttribute($attribute));
                    }
                }
            }
        }
        foreach ($node->childNodes as $child) {
            $this->copyNode($child, $target, $output);
        }
    }

    private function innerHtml(DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }

        return $html;
    }

    public function validateFields(string $html, array $fields): void
    {
        $choices = $this->choices($fields);
        $document = $this->document($html);
        foreach ($document->getElementsByTagName('span') as $span) {
            if ($span->hasAttribute('data-letter-field') && ! array_key_exists($span->getAttribute('data-letter-field'), $choices)) {
                throw ValidationException::withMessages(['body_html' => 'Data sisipan "'.$span->getAttribute('data-letter-field').'" belum memiliki kolom isian. Tambahkan kolomnya atau hapus sisipan tersebut.']);
            }
        }
        if (trim($document->getElementsByTagName('body')->item(0)->textContent) === '') {
            throw ValidationException::withMessages(['body_html' => 'Isi template surat tidak boleh kosong.']);
        }
    }

    public function values(OutgoingLetter $letter, array $fields, bool $sample = false): array
    {
        $school = SchoolSetting::first() ?? new SchoolSetting;
        $values = ['nomor_surat' => $letter->nomor_surat ?? '[Nomor surat]',
            'tanggal_surat' => $letter->tanggal_surat?->locale('id')->translatedFormat('d F Y') ?? today()->locale('id')->translatedFormat('d F Y'),
            'tujuan' => $letter->tujuan ?? '[Tujuan surat]', 'perihal' => $letter->perihal ?? '[Perihal]', 'keterangan' => $letter->keterangan ?? '',
            'nama_madrasah' => $school->school_name ?? '[Nama madrasah]', 'alamat_madrasah' => $school->address ?? '',
            'nama_kepala_madrasah' => $school->headmaster_name ?? '[Nama kepala madrasah]', 'nip_kepala_madrasah' => $school->headmaster_nip ?? ''];
        foreach ($fields as $field) {
            $key = $field['name'];
            $value = ($letter->data ?? [])[$key] ?? null;
            if ($sample) {
                $choices = $this->choices([$field]);
                foreach ($choices as $token => $label) {
                    if (! array_key_exists($token, self::SYSTEM_FIELDS)) {
                        $values[$token] = '['.$label.']';
                    }
                }

                continue;
            }
            $values[$key] = '';
            if ($field['type'] === 'student' && $value) {
                $student = Student::with(['classHistories.schoolClass', 'classHistories.academicYear'])->find($value);
                $date = $letter->tanggal_surat ?? today();
                $history = $student?->classHistories->filter(fn ($history) => $history->is_active && $history->academicYear
                    && $history->academicYear->start_date->lte($date) && $history->academicYear->end_date->gte($date))->first();
                $parts = ['nama' => $student?->full_name, 'nis' => $student?->nis, 'nisn' => $student?->nisn,
                    'kelas' => $history?->schoolClass?->name, 'tahun_pelajaran' => $history?->academicYear?->name,
                    'tempat_lahir' => $student?->birth_place, 'tanggal_lahir' => $student?->birth_date?->locale('id')->translatedFormat('d F Y'), 'alamat' => $student?->address];
                $values[$key] = $student?->full_name ?? '';
                foreach ($parts as $part => $text) {
                    $values[$key.'.'.$part] = $text ?? '';
                }
            } elseif ($field['type'] === 'gtk' && $value) {
                $gtk = Gtk::find($value);
                $values[$key] = $gtk?->full_name ?? '';
                foreach (['nama' => $gtk?->full_name, 'nip' => $gtk?->nip, 'jabatan' => $gtk?->position] as $part => $text) {
                    $values[$key.'.'.$part] = $text ?? '';
                }
            } elseif ($field['type'] === 'repeater') {
                $values[$key] = implode("\n", is_array($value) ? $value : []);
            } elseif ($field['type'] === 'date') {
                $values[$key] = $value ? Carbon::parse($value)->locale('id')->translatedFormat('d F Y') : '';
            } else {
                $values[$key] = is_scalar($value) ? (string) $value : '';
            }
        }

        return $values;
    }

    public function render(string $html, array $values): string
    {
        $document = $this->document($this->sanitize($html));
        foreach (iterator_to_array($document->getElementsByTagName('span')) as $span) {
            if (! $span->hasAttribute('data-letter-field')) {
                continue;
            }
            $key = $span->getAttribute('data-letter-field');
            $fragment = $document->createDocumentFragment();
            foreach (explode("\n", (string) ($values[$key] ?? '')) as $index => $line) {
                if ($index) {
                    $fragment->appendChild($document->createElement('br'));
                }
                $fragment->appendChild($document->createTextNode($line));
            }
            $span->parentNode->replaceChild($fragment, $span);
        }

        return $this->innerHtml($document->getElementsByTagName('body')->item(0));
    }
}
