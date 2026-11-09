<?php

namespace App\Http\Controllers;

use App\Models\Gtk;
use App\Models\GtkDailyApproval;
use App\Models\GtkKbmException;
use App\Models\GtkWorkJournal;
use App\Models\User;
use App\Services\GtkJournalService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GtkJournalController extends Controller
{
    private function date(Request $request): Carbon
    {
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today']]);

        return Carbon::parse($data['date'] ?? today()->toDateString())->startOfDay();
    }

    public function index(Request $request, GtkJournalService $service): View
    {
        abort_unless($request->user()->canTeach(), 403);
        abort_unless($request->user()->gtk_id !== null, 403, 'Akun belum terhubung dengan GTK.');
        $date = $this->date($request);
        $requirements = $service->requirements($request->user(), $date);
        $studentAttendance = $requirements['sessions']->mapWithKeys(fn ($session) => [
            $session['key'] => $service->studentAttendanceProgress($session['schedule_ids'], $date),
        ]);
        $exceptions = GtkKbmException::whereDate('date', $date)->where('gtk_id', $request->user()->gtk_id)->get();
        $approved = GtkDailyApproval::where('gtk_id', $request->user()->gtk_id)->whereDate('date', $date)->where('status', 'approved')->exists();

        return view('gtk.journals.index', compact('date', 'requirements', 'exceptions', 'studentAttendance', 'approved'));
    }

    public function store(Request $request, GtkJournalService $service): RedirectResponse
    {
        abort_unless($request->user()->canTeach(), 403);
        $data = $request->validate(['session_key' => ['required', 'string', 'regex:/^kbm-[0-9]+$/', 'max:60']]);
        $date = $this->date($request);
        $service->save($request->user(), $date, $data['session_key'], $request->only(['status', 'material', 'notes', 'reflection']));

        return redirect()->route('gtk.journals.index', ['date' => $date->toDateString()])->with('status', 'Jurnal berhasil disimpan.');
    }

    public function lckh(Request $request): View
    {
        abort_unless($request->user()->gtk_id !== null, 403, 'Akun belum terhubung dengan GTK.');
        $date = $this->date($request);
        $daily = GtkWorkJournal::where('gtk_id', $request->user()->gtk_id)->whereDate('journal_date', $date)->where('kind', 'daily')->where('session_key', 'lckh')->first();
        $review = GtkDailyApproval::where('gtk_id', $request->user()->gtk_id)->whereDate('date', $date)->first();

        return view('gtk.lckh.index', compact('date', 'daily', 'review'));
    }

    public function storeLckh(Request $request, GtkJournalService $service): RedirectResponse
    {
        $date = $this->date($request);
        $service->save($request->user(), $date, 'lckh', $request->only(['status', 'activities']));

        return redirect()->route('gtk.lckh.index', ['date' => $date->toDateString()])->with('status', 'LCKH berhasil disimpan.');
    }

    public function destroy(Request $request, GtkWorkJournal $journal, GtkJournalService $service): RedirectResponse
    {
        abort_unless($journal->kind === 'teaching', 404);
        $service->delete($request->user(), $journal);

        return $request->input('return_to') === 'history'
            ? redirect()->route('gtk.journals.history', ['month' => $journal->journal_date->format('Y-m')])->with('status', 'Jurnal berhasil dihapus.')
            : redirect()->route('gtk.journals.index', ['date' => $journal->journal_date->toDateString()])->with('status', 'Jurnal berhasil dihapus.');
    }

    public function destroyLckh(Request $request, GtkWorkJournal $journal, GtkJournalService $service): RedirectResponse
    {
        abort_unless($journal->kind === 'daily', 404);
        $service->delete($request->user(), $journal);

        return $request->input('return_to') === 'history'
            ? redirect()->route('gtk.lckh.history', ['month' => $journal->journal_date->format('Y-m')])->with('status', 'LCKH berhasil dihapus.')
            : redirect()->route('gtk.lckh.index', ['date' => $journal->journal_date->toDateString()])->with('status', 'LCKH berhasil dihapus.');
    }

    public function exception(Request $request, Gtk $gtk, GtkJournalService $service): RedirectResponse
    {
        $data = $request->validate([
            'session_key' => ['required', 'string', 'max:60'],
            'substitute_gtk_id' => ['nullable', 'integer', 'exists:gtks,id'],
            'reason' => ['required', 'string', 'max:5000'],
        ]);
        $date = $this->date($request);
        $teacher = User::where('gtk_id', $gtk->id)->firstOrFail();
        $service->exception($request->user(), $teacher, $date, $data['session_key'], isset($data['substitute_gtk_id']) ? (int) $data['substitute_gtk_id'] : null, $data['reason']);

        return redirect()->route('headmaster.teaching-journals.show', [$gtk, 'month' => $date->format('Y-m')])->with('status', 'Perubahan pelaksanaan KBM berhasil dicatat.');
    }
}
