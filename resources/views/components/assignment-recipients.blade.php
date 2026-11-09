@props(['field', 'rows' => []])
@php
    $rows = is_array($rows) ? $rows : [];
    $selected = collect($rows)->filter(fn ($row) => is_array($row))->pluck('gtk_id')->filter(fn ($id) => is_numeric($id))->take(20);
    $gtks = \App\Models\Gtk::where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $selected))->orderBy('full_name')->get()->keyBy('id');
@endphp
<div class="space-y-3">
    <flux:description>Pilih satu atau beberapa GTK. Nama, NIP, dan jabatan mengikuti data GTK. Pangkat/golongan diisi untuk surat ini; jika kosong akan tercetak “-”.</flux:description>
    @foreach ($rows as $index => $row)
        @php($person = $gtks->get(is_array($row) ? ($row['gtk_id'] ?? '') : ''))
        <div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" wire:key="assignment-recipient-{{ $field }}-{{ $index }}">
            <div class="flex items-center justify-between gap-3">
                <span class="text-sm font-semibold">Penerima tugas {{ $index + 1 }}</span>
                <flux:button type="button" size="xs" variant="ghost" wire:click="removeAssignmentRecipient('{{ $field }}', {{ $index }})" aria-label="Hapus penerima tugas {{ $index + 1 }}">Hapus</flux:button>
            </div>
            <flux:select wire:model.live="formData.{{ $field }}.{{ $index }}.gtk_id" label="GTK yang ditugaskan {{ $index + 1 }}">
                <option value="">Pilih GTK</option>
                @foreach ($gtks as $gtk)
                    <option value="{{ $gtk->id }}" @disabled(!$gtk->is_active)>{{ $gtk->full_name }}{{ $gtk->is_active ? '' : ' (nonaktif)' }}</option>
                @endforeach
            </flux:select>
            <flux:error name="formData.{{ $field }}.{{ $index }}.gtk_id" />
            <div class="grid gap-2 text-sm text-zinc-600 sm:grid-cols-2 dark:text-zinc-300">
                <div>NIP: <span class="font-medium">{{ $person?->nip ?: '-' }}</span></div>
                <div>Jabatan: <span class="font-medium">{{ $person?->position ?: '-' }}</span></div>
            </div>
            <flux:input wire:model="formData.{{ $field }}.{{ $index }}.pangkat" label="Pangkat/Gol. penerima {{ $index + 1 }} (opsional)" placeholder="Contoh: Penata / III c" />
            <flux:error name="formData.{{ $field }}.{{ $index }}.pangkat" />
            <flux:error name="formData.{{ $field }}.{{ $index }}" />
        </div>
    @endforeach
    <flux:button type="button" size="sm" icon="plus" wire:click="addAssignmentRecipient('{{ $field }}')">Tambah Penerima Tugas</flux:button>
    <flux:error name="formData.{{ $field }}" />
    <flux:description>Identitas kepala madrasah mengikuti Profil Madrasah saat surat disimpan. Tujuan surat terisi otomatis dari penerima tugas.</flux:description>
</div>
