<?php

namespace App\Services;

use App\Models\Gtk;
use App\Models\GtkDailyApproval;
use App\Models\GtkWorkJournal;
use App\Models\Holiday;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** @phpstan-type Day array{date: string, lckh: GtkWorkJournal|null, approval: GtkDailyApproval|null, eligible: bool, reason: string} */
class GtkDailyApprovalService
{
    /** @return Collection<string, Day> */
    public function days(int $gtkId, CarbonInterface $month): Collection
    {
        $range = [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()->min(today()->endOfDay())];
        $lckhs = GtkWorkJournal::where('gtk_id', $gtkId)->where('kind', 'daily')->whereBetween('journal_date', $range)->get()->keyBy(fn ($row) => $row->journal_date->toDateString());
        $approvals = GtkDailyApproval::where('gtk_id', $gtkId)->whereBetween('date', $range)->get()->keyBy(fn ($row) => $row->date->toDateString());
        $holidays = Holiday::where('is_active', true)->whereBetween('date', $range)->get()->map(fn ($row) => Carbon::parse($row->date)->toDateString())->all();
        $days = collect();
        foreach ($range[0]->toPeriod($range[1]) as $date) {
            $key = $date->toDateString();
            $lckh = $lckhs->get($key);
            $approval = $lckh ? $approvals->get($key) : null;
            if (($date->isSunday() || in_array($key, $holidays, true)) && ! $lckh) {
                continue;
            }
            $reason = $this->ineligibleReason($lckh);
            $days->put($key, ['date' => $key, 'lckh' => $lckh, 'approval' => $approval, 'eligible' => $reason === '', 'reason' => $reason]);
        }

        return $days;
    }

    public function ineligibleReason(?GtkWorkJournal $lckh): string
    {
        return $lckh?->isComplete() ? '' : ($lckh ? 'LCKH belum selesai.' : 'LCKH belum diisi.');
    }

    /** @param array<string, mixed> $input */
    public function decide(User $actor, int $gtkId, CarbonInterface $date, array $input): void
    {
        abort_unless($actor->isHeadmaster() && $date->lte(today()->endOfDay()), 403);
        $data = Validator::make($input, [
            'status' => ['required', 'in:approved,revision_requested'],
            'notes' => ['required_if:status,revision_requested', 'nullable', 'string', 'max:10000'],
        ])->validate();
        DB::transaction(function () use ($actor, $gtkId, $date, $data) {
            Gtk::whereKey($gtkId)->lockForUpdate()->firstOrFail();
            $lckh = GtkWorkJournal::where('gtk_id', $gtkId)->whereDate('journal_date', $date)->where('kind', 'daily')->first();
            if (! $lckh) {
                throw ValidationException::withMessages(['approval' => 'LCKH belum diisi untuk tanggal ini.']);
            }
            if ($data['status'] === 'approved' && ($reason = $this->ineligibleReason($lckh)) !== '') {
                throw ValidationException::withMessages(['approval' => $reason]);
            }
            $approval = GtkDailyApproval::where('gtk_id', $gtkId)->whereDate('date', $date)->first()
                ?? new GtkDailyApproval(['gtk_id' => $gtkId, 'date' => $date->toDateString()]);
            $before = $approval->exists ? $approval->toArray() : null;
            $approval->fill($data + ['reviewed_by' => $actor->id, 'reviewed_at' => now()])->save();
            app(GtkJournalService::class)->audit($actor, $gtkId, $date, 'daily_approval', $before,
                $approval->toArray() + ['lckh' => $lckh->toArray()]);
        });
    }

    public function approveSelected(User $actor, int $gtkId, CarbonInterface $month, array $input): int
    {
        abort_unless($actor->isHeadmaster() && $month->lte(today()->endOfDay()), 403);
        $data = Validator::make($input, [
            'dates' => ['required', 'array', 'min:1', 'max:31'],
            'dates.*' => ['required', 'date_format:Y-m-d', 'distinct',
                'after_or_equal:'.$month->copy()->startOfMonth()->toDateString(),
                'before_or_equal:'.$month->copy()->endOfMonth()->min(today())->toDateString()],
        ], ['dates.required' => 'Pilih minimal satu tanggal untuk disetujui.'])->validate();

        return DB::transaction(function () use ($actor, $gtkId, $month, $data) {
            Gtk::whereKey($gtkId)->lockForUpdate()->firstOrFail();
            $days = $this->days($gtkId, $month);
            $approved = 0;
            foreach (collect($data['dates'])->sort() as $date) {
                $day = $days->get($date);
                if ($day && $day['approval']?->status === 'approved') {
                    continue;
                }
                if (! $day || ! $day['eligible'] || $day['approval']?->status === 'revision_requested') {
                    throw ValidationException::withMessages(['dates' => 'Tanggal '.Carbon::parse($date)->format('d/m/Y').' belum dapat disetujui. Periksa kelengkapan data dan catatan perbaikannya.']);
                }
                $this->decide($actor, $gtkId, Carbon::parse($date), ['status' => 'approved']);
                $approved++;
            }

            return $approved;
        }, 3);
    }

    public function invalidate(int $gtkId, CarbonInterface $date): void
    {
        if (Schema::hasTable('gtk_daily_approvals')) {
            GtkDailyApproval::where('gtk_id', $gtkId)->whereDate('date', $date)->update(['status' => 'pending', 'reviewed_at' => null]);
        }
    }
}
