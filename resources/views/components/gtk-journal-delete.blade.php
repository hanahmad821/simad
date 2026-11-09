@props(['journal', 'approved' => false, 'history' => false])

@if ($journal && ! $approved)
    <form method="POST" action="{{ route($journal->kind === 'daily' ? 'gtk.lckh.destroy' : 'gtk.journals.destroy', $journal) }}"
        onsubmit="return confirm('Hapus {{ $journal->kind === 'daily' ? 'LCKH' : 'jurnal mengajar' }} tanggal {{ $journal->journal_date->format('d/m/Y') }} ini? Data yang dihapus harus diisi kembali jika masih wajib dilengkapi.')">
        @csrf
        @method('DELETE')
        <input type="hidden" name="return_to" value="{{ $history ? 'history' : 'index' }}">
        <flux:button type="submit" variant="danger" icon="trash">{{ $journal->kind === 'daily' ? 'Hapus LCKH' : 'Hapus jurnal' }}</flux:button>
    </form>
@endif
