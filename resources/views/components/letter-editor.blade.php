@props(['model' => 'isi', 'formatModel' => 'isiFormat', 'sample' => ''])
@php
    $tools = [
        ['bold', 'Tebal', 'Ctrl+B'], ['italic', 'Miring', 'Ctrl+I'], ['underline', 'Garis bawah', 'Ctrl+U'],
        ['orderedList', 'Nomor', 'Ctrl+Shift+7'], ['bulletList', 'Poin', 'Ctrl+Shift+8'],
        ['indent', 'Subpoin', 'Tab'], ['outdent', 'Naik tingkat', 'Shift+Tab'],
        ['undo', 'Urungkan', 'Ctrl+Z'], ['redo', 'Ulangi', 'Ctrl+Shift+Z'], ['clear', 'Hapus format', ''],
    ];
@endphp
<div {{ $attributes->class('letter-editor-field') }}>
    <div class="mb-2 flex items-center justify-between gap-3">
        <span class="text-sm font-medium text-zinc-800 dark:text-zinc-100">Isi Surat <span class="text-red-600" aria-hidden="true">*</span></span>
        <span class="text-xs text-zinc-500">Format teks dan daftar</span>
    </div>
    <div wire:ignore x-data="letterEditor($wire.entangle('{{ $model }}'), $wire.entangle('{{ $formatModel }}'), @js($sample))"
        class="letter-editor" :class="{ 'is-expanded': expanded }"
        @keydown.escape="if (expanded) { $event.stopPropagation(); $event.preventDefault(); expanded = false }">
        <div class="letter-editor-toolbar" role="toolbar" aria-label="Format isi surat">
            @foreach ($tools as [$command, $label, $shortcut])
                @if (in_array($command, ['orderedList', 'indent', 'undo']))
                    <span class="letter-editor-separator" aria-hidden="true"></span>
                @endif
                <button type="button" @mousedown.prevent @click="run('{{ $command }}')"
                    title="{{ $label }}{{ $shortcut ? ' ('.$shortcut.')' : '' }}" aria-label="{{ $label }}"
                    @if (in_array($command, ['bold', 'italic', 'underline', 'orderedList', 'bulletList']))
                        :aria-pressed="active('{{ $command }}')" :class="{ 'is-active': active('{{ $command }}') }"
                    @endif
                    @if (in_array($command, ['indent', 'outdent', 'undo', 'redo'])) :disabled="loading || !can('{{ $command }}')" @else :disabled="loading" @endif>
                    @switch($command)
                        @case('bold') <b aria-hidden="true">B</b> @break
                        @case('italic') <i aria-hidden="true">I</i> @break
                        @case('underline') <u aria-hidden="true">U</u> @break
                        @case('orderedList') <span aria-hidden="true">1.</span> Nomor @break
                        @case('bulletList') <span aria-hidden="true">&bull;</span> Poin @break
                        @case('indent') <span aria-hidden="true">↳</span> Subpoin @break
                        @case('outdent') <span aria-hidden="true">↰</span> Naik tingkat @break
                        @case('undo') <span aria-hidden="true">↶</span> @break
                        @case('redo') <span aria-hidden="true">↷</span> @break
                        @default <span>Hapus format</span>
                    @endswitch
                </button>
            @endforeach
            <span class="letter-editor-separator" aria-hidden="true"></span>
            <select aria-label="Perataan teks" x-model="alignment" @change="align($event.target.value)" :disabled="loading">
                <option value="left">Rata kiri</option>
                <option value="center">Rata tengah</option>
                <option value="right">Rata kanan</option>
                <option value="justify">Rata kiri kanan</option>
            </select>
            <button type="button" @click="expanded = !expanded" :aria-expanded="expanded" aria-label="Perluas editor" class="letter-editor-expand" x-text="expanded ? 'Perkecil' : 'Perluas'"></button>
        </div>
        <div class="letter-editor-scroll">
            <p x-show="loading" class="px-6 py-4 text-sm text-zinc-500">Memuat editor…</p>
            <p x-show="loadError" x-cloak class="px-6 py-4 text-sm text-red-600">Editor belum dapat dimuat. Muat ulang halaman untuk melanjutkan.</p>
            <div x-ref="canvas"></div>
        </div>
        <div class="letter-editor-footer">
            <span><span x-text="words">0</span> kata &middot; <span x-text="characters.toLocaleString('id-ID')">0</span> karakter</span>
            <button type="button" x-show="empty && sample" @click="useSample()">Gunakan kerangka awal</button>
        </div>
        <div class="letter-editor-hint">Enter: paragraf baru &middot; Shift+Enter: baris baru &middot; Tab pada daftar: subpoin</div>
    </div>
    <flux:error name="{{ $model }}" />
</div>
