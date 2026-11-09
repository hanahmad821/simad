<x-layouts::app title="Jenis Surat">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">Jenis Surat</flux:heading>
                <flux:text class="mt-1">Kelola jenis surat, kode klasifikasi, dan template baku untuk surat keluar.</flux:text>
            </div>
            <flux:button variant="primary" :href="route('admin.letter-types.create')" icon="plus">Tambah Jenis Surat</flux:button>
        </div>
        @if (session('status'))<flux:callout variant="success">{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="warning">{{ $errors->first() }}</flux:callout>@endif
        <form method="GET" class="grid gap-3 sm:grid-cols-3 sm:items-end">
            <flux:input name="q" label="Cari jenis surat" :value="$search" placeholder="Nama atau kode klasifikasi" maxlength="100" />
            <flux:select name="status" label="Status">
                @foreach (['all' => 'Semua status', 'active' => 'Aktif', 'inactive' => 'Nonaktif'] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </flux:select>
            <div class="flex gap-2">
                <flux:button type="submit">Tampilkan</flux:button>
                <flux:button :href="route('admin.letter-types.index')">Reset</flux:button>
            </div>
        </form>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-3">Jenis surat</th><th class="p-3">Kode</th><th class="p-3">Template</th><th class="p-3">Status</th><th class="p-3">Tindakan</th></tr></thead>
                <tbody>
                    @forelse ($letterTypes as $type)
                        <tr class="border-t border-zinc-200 align-top dark:border-zinc-700">
                            <td class="p-3"><div class="font-medium">{{ $type->name }}</div><div class="mt-1 text-xs text-zinc-500">{{ $type->outgoing_letters_count }} surat</div></td>
                            <td class="p-3">{{ $type->code }}</td>
                            <td class="p-3">
                                @if ($type->body_html !== null)
                                    Pilih template baku melalui Edit
                                @elseif (isset($templates[$type->template_view ?? '']))
                                    {{ $templates[$type->template_view]['name'] }}
                                @elseif ($type->template_view)
                                    Template tidak tersedia. Pilih ulang melalui Edit.
                                @else
                                    Tanpa template (surat manual)
                                @endif
                            </td>
                            <td class="p-3"><flux:badge :color="$type->is_active ? 'green' : 'zinc'">{{ $type->is_active ? 'Aktif' : 'Nonaktif' }}</flux:badge></td>
                            <td class="p-3"><div class="flex flex-wrap gap-2">
                                <flux:button size="sm" :href="route('admin.letter-types.edit', $type)">Edit</flux:button>
                                @if ($type->outgoing_letters_count === 0)
                                    <form method="POST" action="{{ route('admin.letter-types.destroy', $type) }}" onsubmit="return confirm('Hapus jenis surat ini?')">
                                        @csrf @method('DELETE')<flux:button type="submit" size="sm" variant="danger">Hapus</flux:button>
                                    </form>
                                @else
                                    <flux:text size="sm">Sudah digunakan. Nonaktifkan melalui Edit.</flux:text>
                                @endif
                            </div></td>
                        </tr>
                    @empty <tr><td colspan="5" class="p-8 text-center">Belum ada jenis surat yang sesuai.</td></tr> @endforelse
                </tbody>
            </table>
        </div>
        {{ $letterTypes->links() }}
        <flux:button :href="route('admin.outgoing-letters.index')">Buka Surat Keluar</flux:button>
    </div>
</x-layouts::app>
