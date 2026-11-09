<?php

namespace App\Http\Controllers;

use App\Models\GtkDailyApproval;
use App\Models\GtkWorkJournal;
use App\Services\GtkDailyApprovalService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GtkJournalHistoryController extends Controller
{
    private function month(Request $request): Carbon
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m', 'before_or_equal:'.today()->format('Y-m')]]);

        return Carbon::createFromFormat('!Y-m', $data['month'] ?? today()->format('Y-m'));
    }

    public function lckh(Request $request, GtkDailyApprovalService $service): View
    {
        abort_unless($request->user()->gtk_id !== null, 403, 'Akun belum terhubung dengan data GTK.');
        $month = $this->month($request);
        $filters = $request->validate(['status' => ['nullable', 'in:all,completed,draft,missing,pending,approved,revision_requested']]);
        $status = $filters['status'] ?? 'all';
        $days = $service->days($request->user()->gtk_id, $month);
        $summary = [
            'completed' => $days->filter(fn ($day) => $day['lckh']?->isComplete() ?? false)->count(),
            'draft' => $days->filter(fn ($day) => $day['lckh'] && ! $day['lckh']->isComplete())->count(),
            'missing' => $days->filter(fn ($day) => ! $day['lckh'])->count(),
            'revision_requested' => $days->filter(fn ($day) => $day['approval']?->status === 'revision_requested')->count(),
        ];
        $days = $days->filter(fn ($day) => match ($status) {
            'completed' => $day['lckh']?->isComplete() ?? false,
            'draft' => $day['lckh'] && ! $day['lckh']->isComplete(),
            'missing' => ! $day['lckh'],
            'pending' => ! $day['approval'] || $day['approval']->status === 'pending',
            'approved', 'revision_requested' => $day['approval']?->status === $status,
            default => true,
        })->sortKeysDesc();

        return view('gtk.lckh.history', compact('month', 'status', 'days', 'summary'));
    }

    public function teaching(Request $request): View
    {
        abort_unless($request->user()->canTeach(), 403);
        abort_unless($request->user()->gtk_id !== null, 403, 'Akun belum terhubung dengan data GTK.');
        $month = $this->month($request);
        $filters = $request->validate(['status' => ['nullable', 'in:all,draft,completed']]);
        $status = $filters['status'] ?? 'all';
        $query = GtkWorkJournal::where('gtk_id', $request->user()->gtk_id)->where('kind', 'teaching')
            ->whereBetween('journal_date', [$month, $month->copy()->endOfMonth()]);
        $all = (clone $query)->get();
        $summary = ['sessions' => $all->count(), 'completed' => $all->filter(fn ($journal) => $journal->isComplete())->count(),
            'periods' => $all->sum(fn ($journal) => count($journal->schedule_ids ?? []))];
        $journals = $query->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('journal_date')->orderByDesc('id')->paginate(15)->withQueryString();
        $approvedDates = GtkDailyApproval::where('gtk_id', $request->user()->gtk_id)->where('status', 'approved')
            ->whereBetween('date', [$month, $month->copy()->endOfMonth()])->get()
            ->map(fn ($approval) => $approval->date->toDateString())->all();

        return view('gtk.journals.history', compact('month', 'status', 'journals', 'summary', 'approvedDates'));
    }
}
