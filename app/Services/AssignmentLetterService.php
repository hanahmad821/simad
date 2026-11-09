<?php

namespace App\Services;

use App\Models\Gtk;
use App\Models\OutgoingLetter;
use App\Models\SchoolSetting;
use Illuminate\Support\Facades\Validator;

class AssignmentLetterService
{
    public function resolve(OutgoingLetter $letter, ?SchoolSetting $school = null): array
    {
        $data = $letter->data ?? [];
        $fields = config('letter_templates')['pdf.letters.tugas']['fields'];
        Validator::make(['formData' => $data], app(LetterTemplateService::class)->letterRules($fields))->validate();
        $school ??= SchoolSetting::first() ?? new SchoolSetting;
        $gtks = Gtk::whereIn('id', array_column($data['penerima_tugas'], 'gtk_id'))->get()->keyBy('id');
        $recipients = [];
        foreach ($data['penerima_tugas'] as $row) {
            $gtk = $gtks->get($row['gtk_id']);
            $recipients[] = ['gtk_id' => (int) $gtk->id, 'nama' => $gtk->full_name,
                'nip' => $this->text($gtk->nip), 'pangkat' => $this->text($row['pangkat'] ?? null),
                'jabatan' => $this->text($gtk->position)];
        }

        return ['signer' => ['nama' => $this->text($school->headmaster_name), 'nip' => $this->text($school->headmaster_nip),
            'pangkat' => $this->text($school->headmaster_rank), 'jabatan' => $this->text($school->headmaster_position, 'Kepala Madrasah'),
            'alamat' => $this->text($school->address)], 'recipients' => $recipients];
    }

    public function viewData(OutgoingLetter $letter, ?SchoolSetting $school = null): array
    {
        return $letter->template_snapshot['assignment'] ?? $this->resolve($letter, $school);
    }

    public function recipientSummary(array $data): string
    {
        $rows = is_array($data['penerima_tugas'] ?? null) ? $data['penerima_tugas'] : [];
        $ids = collect($rows)->filter(fn ($row) => is_array($row) && is_numeric($row['gtk_id'] ?? null))->pluck('gtk_id')->take(20);
        $names = Gtk::whereIn('id', $ids)->pluck('full_name', 'id');
        $summary = $ids->map(fn ($id) => $names->get($id))->filter()->implode(', ');

        return $summary !== '' ? mb_strimwidth($summary, 0, 250, '…') : 'GTK yang ditugaskan';
    }

    private function text(?string $value, string $fallback = '-'): string
    {
        return trim($value ?? '') !== '' ? trim($value) : $fallback;
    }
}
