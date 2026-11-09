<?php

namespace App\Http\Controllers;

use App\Models\Gtk;
use App\Models\GtkDailyApproval;
use App\Models\GtkKbmException;
use App\Models\GtkWorkJournal;
use App\Models\User;
use App\Services\GtkDailyApprovalService;
use App\Services\GtkJournalService;
use App\Services\GtkReportService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class GtkWorkReviewController extends Controller
{
    private function month(Request $request): Carbon
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m', 'before_or_equal:'.today()->format('Y-m')]]);

        return Carbon::createFromFormat('!Y-m', $data['month'] ?? today()->format('Y-m'));
    }

    public function index(Request $request): View
    {
        $month = $this->month($request);
        $range = [$month, $month->copy()->endOfMonth()];
        $gtks = Gtk::where(fn ($q) => $q->where('is_active', true)
            ->orWhereIn('id', GtkWorkJournal::select('gtk_id')->where('kind', 'daily')->whereBetween('journal_date', $range)))
            ->orderBy('full_name')->paginate(20)->withQueryString();
        $ids = $gtks->pluck('id');
        $lckhs = GtkWorkJournal::whereIn('gtk_id', $ids)->where('kind', 'daily')->whereBetween('journal_date', $range)->get()->groupBy('gtk_id');
        $approvals = GtkDailyApproval::whereIn('gtk_id', $ids)->whereBetween('date', $range)
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('gtk_work_journals')->where('kind', 'daily')
                ->whereColumn('gtk_work_journals.gtk_id', 'gtk_daily_approvals.gtk_id')
                ->whereColumn('gtk_work_journals.journal_date', 'gtk_daily_approvals.date'))
            ->get()->groupBy('gtk_id');

        return view('headmaster.work-reviews.index', compact('month', 'gtks', 'lckhs', 'approvals'));
    }

    public function show(Request $request, Gtk $gtk, GtkDailyApprovalService $service): View
    {
        $month = $this->month($request);
        $days = $service->days($gtk->id, $month);
        $selectableDates = $days->filter(fn ($day) => $day['eligible']
            && ! in_array($day['approval']?->status, ['approved', 'revision_requested'], true))->keys()->values()->all();

        return view('headmaster.work-reviews.show', compact('month', 'gtk', 'days', 'selectableDates'));
    }

    public function decide(Request $request, Gtk $gtk, GtkDailyApprovalService $service): RedirectResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']]);
        $date = Carbon::parse($data['date']);
        $service->decide($request->user(), $gtk->id, $date, $request->only(['status', 'notes']));

        return to_route('headmaster.work-reviews.show', [$gtk, 'month' => $date->format('Y-m')])->with('status', 'Approval tanggal '.$date->format('d/m/Y').' berhasil disimpan.');
    }

    public function approveSelected(Request $request, Gtk $gtk, GtkDailyApprovalService $service): RedirectResponse
    {
        $month = $this->month($request);
        $approved = $service->approveSelected($request->user(), $gtk->id, $month, $request->only('dates'));

        return to_route('headmaster.work-reviews.show', [$gtk, 'month' => $month->format('Y-m')])
            ->with('status', $approved > 0 ? $approved.' tanggal terpilih berhasil disetujui.' : 'Tanggal terpilih sudah disetujui sebelumnya.');
    }

    public function teachers(Request $request): View
    {
        $month = $this->month($request);
        $teachers = Gtk::where(fn ($q) => $q->whereIn('id', User::select('gtk_id')->withRoles(['teacher', 'headmaster']))
            ->orWhereIn('id', GtkWorkJournal::select('gtk_id')->where('kind', 'teaching')
                ->whereBetween('journal_date', [$month, $month->copy()->endOfMonth()])))
            ->orderBy('full_name')->paginate(20)->withQueryString();
        $journals = GtkWorkJournal::whereIn('gtk_id', $teachers->pluck('id'))->where('kind', 'teaching')
            ->whereBetween('journal_date', [$month, $month->copy()->endOfMonth()])->get()->groupBy('gtk_id');

        return view('headmaster.teaching-journals.index', compact('month', 'teachers', 'journals'));
    }

    public function teaching(Request $request, Gtk $gtk, GtkReportService $service): View
    {
        $month = $this->month($request);
        $rows = $service->teachingRows($gtk->id, $month);
        $exceptions = GtkKbmException::whereBetween('date', [$month, $month->copy()->endOfMonth()])
            ->where(fn ($q) => $q->where('gtk_id', $gtk->id)->orWhere('substitute_gtk_id', $gtk->id))->get();
        $substitutes = User::with('gtk')->withRoles(['teacher', 'headmaster'])->whereNotNull('gtk_id')->where('gtk_id', '!=', $gtk->id)->get();
        $missing = collect();
        foreach ($month->toPeriod($month->copy()->endOfMonth()->min(today())) as $date) {
            foreach (app(GtkJournalService::class)->requirementsForGtk($gtk->id, $date)['missing'] as $session) {
                $missing->push(['date' => $date->toDateString()] + $session);
            }
        }

        return view('headmaster.teaching-journals.show', compact('month', 'gtk', 'rows', 'exceptions', 'substitutes', 'missing'));
    }

    public function downloadOwn(Request $request, string $kind, GtkReportService $service): Response
    {
        $user = $request->user();
        abort_unless($user->gtk !== null, 403, 'Akun belum terhubung dengan data GTK.');
        abort_if($kind === 'teaching' && ! $user->canTeach(), 403);

        return $this->download($request, $user->gtk, $kind, $service);
    }

    public function download(Request $request, Gtk $gtk, string $kind, GtkReportService $service): Response
    {
        $month = $this->month($request);
        abort_unless(in_array($kind, ['attendance', 'lckh', 'teaching'], true), 404);
        $name = ['attendance' => 'presensi', 'lckh' => 'lckh', 'teaching' => 'jurnal-mengajar'][$kind];

        return response($service->pdf($gtk, $month, $kind), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'-gtk-'.$gtk->id.'-'.$month->format('Y-m').'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
