<form method="POST" action="{{ route('admin.classes.destroy', $class) }}"
    onsubmit="return confirm('Hapus kelas ini? Kelas yang memiliki riwayat penempatan siswa atau pengampu tidak dapat dihapus.')">
    @csrf
    @method('DELETE')
    <flux:button type="submit" size="sm" variant="danger" icon="trash" aria-label="Hapus kelas {{ $class->name }}">Hapus</flux:button>
</form>
