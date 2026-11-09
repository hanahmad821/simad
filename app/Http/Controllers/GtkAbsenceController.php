<?php

namespace App\Http\Controllers;

use App\Models\GtkAbsenceRequest;
use App\Services\GtkAbsenceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GtkAbsenceController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->gtk_id, 403, 'Akun belum terhubung dengan GTK.');

        return $this->listing($request, false);
    }

    public function reviews(Request $request): View
    {
        abort_unless($request->user()->isHeadmaster(), 403);

        return $this->listing($request, true);
    }

    private function listing(Request $request, bool $reviewing): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(GtkAbsenceRequest::STATUSES))],
            'month' => ['nullable', 'date_format:Y-m'], 'q' => ['nullable', 'string', 'max:100'],
        ]);
        $status = $filters['status'] ?? ($reviewing && ! $request->has('status') ? 'pending' : '');
        $month = $filters['month'] ?? '';
        $search = trim($filters['q'] ?? '');
        $absences = GtkAbsenceRequest::with(['gtk', 'reviewer'])
            ->when(! $reviewing, fn ($q) => $q->where('gtk_id', $request->user()->gtk_id))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($month, function ($q) use ($month) {
                $start = Carbon::createFromFormat('!Y-m', $month);
                $q->whereDate('start_date', '<=', $start->copy()->endOfMonth())->whereDate('end_date', '>=', $start);
            })
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('description', 'like', '%'.$search.'%')
                ->orWhereHas('gtk', fn ($q) => $q->where('full_name', 'like', '%'.$search.'%'))))
            ->latest('id')->paginate(15)->withQueryString();

        return view('gtk.absences.index', compact('absences', 'reviewing', 'status', 'month', 'search'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->gtk_id && $request->user()->gtk?->is_active, 403, 'Akun harus terhubung dengan GTK aktif.');

        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = $data['date'] ?? today()->toDateString();

        return view('gtk.absences.create', compact('date'));
    }

    public function store(Request $request, GtkAbsenceService $service): RedirectResponse
    {
        $service->submit($request->user(), $request->only(['type', 'description', 'start_date', 'end_date']), $request->file('evidence'));

        return to_route('gtk.absences.index')->with('status', 'Pengajuan absensi dikirim. Menunggu persetujuan kepala madrasah.');
    }

    private function authorizeView(Request $request, GtkAbsenceRequest $absence): void
    {
        abort_unless($request->user()->isHeadmaster() || ($request->user()->gtk_id && $request->user()->gtk_id === $absence->gtk_id), 403);
    }

    public function show(Request $request, GtkAbsenceRequest $absence): View
    {
        $this->authorizeView($request, $absence);
        $absence->load(['gtk', 'submitter', 'reviewer']);
        $reviewing = $request->user()->isHeadmaster() && $request->user()->gtk_id !== $absence->gtk_id;

        return view('gtk.absences.show', compact('absence', 'reviewing'));
    }

    public function evidence(Request $request, GtkAbsenceRequest $absence): StreamedResponse
    {
        $this->authorizeView($request, $absence);
        abort_unless(Storage::disk('local')->exists($absence->evidence_path), 404, 'Bukti tidak ditemukan.');
        $extension = pathinfo($absence->evidence_path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download($absence->evidence_path, 'bukti-absensi-'.$absence->id.'.'.$extension,
            ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function cancel(Request $request, GtkAbsenceRequest $absence, GtkAbsenceService $service): RedirectResponse
    {
        $service->cancel($request->user(), $absence);

        return to_route('gtk.absences.index')->with('status', 'Pengajuan dibatalkan. Riwayat tetap tersimpan.');
    }

    public function decide(Request $request, GtkAbsenceRequest $absence, GtkAbsenceService $service): RedirectResponse
    {
        $service->decide($request->user(), $absence, $request->only(['status', 'review_notes']));

        return to_route('gtk.absences.show', $absence)->with('status', 'Keputusan kepala madrasah berhasil disimpan.');
    }
}
