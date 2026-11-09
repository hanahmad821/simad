<?php

namespace App\Services;

use App\Models\Gtk;
use App\Models\GtkWorkJournal;
use App\Models\SchoolSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class GtkReportService
{
    /** @return Collection<int, array<string, mixed>> */
    public function teachingRows(int $gtkId, CarbonInterface $month): Collection
    {
        $journals = GtkWorkJournal::where('gtk_id', $gtkId)->where('kind', 'teaching')
            ->whereBetween('journal_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->orderBy('journal_date')->orderBy('id')->get();

        return $journals->map(fn (GtkWorkJournal $journal) => $this->teachingRow($journal));
    }

    /** @return array<string, mixed> */
    private function teachingRow(GtkWorkJournal $journal): array
    {
        // Labels and period IDs were captured when the session journal was saved.
        $parts = explode(' / ', $journal->label, 3);

        return ['date' => $journal->journal_date->toDateString(), 'class' => $parts[1] ?? '-',
            'subject' => $parts[0], 'periods' => count($journal->schedule_ids ?? []),
            'material' => $journal->material, 'notes' => $journal->notes, 'reflection' => $journal->reflection,
            'status' => $journal->isComplete() ? 'Selesai' : 'Draf', 'key' => $journal->session_key];
    }

    /** @return array<string, mixed> */
    public function data(Gtk $gtk, CarbonInterface $month, string $kind): array
    {
        abort_unless(in_array($kind, ['attendance', 'lckh', 'teaching'], true), 404);
        $school = SchoolSetting::first() ?? new SchoolSetting;
        $rows = match ($kind) {
            'attendance' => app(GtkAttendancePolicyService::class)->monthRows($gtk->id, $month),
            'teaching' => $this->teachingRows($gtk->id, $month),
            default => app(GtkDailyApprovalService::class)->days($gtk->id, $month)->values(),
        };
        $title = ['attendance' => 'REKAP PRESENSI GTK', 'lckh' => 'LAPORAN CAPAIAN KINERJA HARIAN (LCKH)', 'teaching' => 'REKAP JURNAL MENGAJAR'][$kind];
        $printRows = collect();
        foreach ($rows as $index => $row) {
            $fields = $kind === 'teaching' ? ['material', 'notes', 'reflection'] : ($kind === 'lckh' ? ['activities'] : ['attendance_notes']);
            $chunks = [];
            foreach ($fields as $field) {
                $text = (string) ($field === 'activities' ? $row['lckh']?->activities : ($field === 'attendance_notes' ? $row['attendance']?->notes : ($row[$field] ?? '')));
                $chunks[$field] = $this->splitText($text, $kind === 'lckh' ? 850 : 420);
            }
            $parts = max(array_map('count', $chunks));
            for ($part = 0; $part < $parts; $part++) {
                $printRow = $row + ['number' => $index + 1, 'continued' => $part > 0];
                foreach ($fields as $field) {
                    $printRow[$field] = $chunks[$field][$part] ?? '';
                }
                $printRows->push($printRow);
            }
        }

        return compact('gtk', 'month', 'kind', 'school', 'rows', 'printRows', 'title');
    }

    public function pdf(Gtk $gtk, CarbonInterface $month, string $kind): string
    {
        return Pdf::loadView('pdf.gtk.report', $this->data($gtk, $month, $kind))
            ->setPaper('a4', $kind === 'teaching' ? 'landscape' : 'portrait')
            ->setOptions(['isRemoteEnabled' => false])->output();
    }

    /** @return list<string> */
    private function splitText(string $text, int $limit): array
    {
        $chunks = [];
        while (mb_strlen($text) > $limit) {
            $prefix = mb_substr($text, 0, $limit);
            $space = mb_strrpos($prefix, ' ');
            $cut = $space !== false && $space > $limit / 2 ? $space + 1 : $limit;
            $chunks[] = mb_substr($text, 0, $cut);
            $text = mb_substr($text, $cut);
        }
        $chunks[] = $text;

        return $chunks;
    }
}
