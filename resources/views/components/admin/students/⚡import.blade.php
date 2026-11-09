<?php

use App\Exports\StudentsTemplateExport;
use App\Services\StudentImportService;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

new class extends Component {
    use WithFileUploads;

    public $file;
    #[Locked]
    public array $preview = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    public function updatedFile(): void
    {
        $this->preview = [];
        $this->resetValidation();
    }

    public function previewData(StudentImportService $service): void
    {
        $this->preview = [];
        $this->resetValidation();
        $this->validate(['file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120']], [
            'file.required' => 'Pilih file Excel terlebih dahulu.',
            'file.mimes' => 'Gunakan file Excel .xlsx atau .xls.',
            'file.max' => 'Ukuran file maksimal 5 MB.',
        ]);
        $this->preview = $service->read($this->file->getRealPath());
    }

    public function import(StudentImportService $service): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        if (empty($this->preview)) {
            $this->addError('file', 'Unggah dan tinjau file terlebih dahulu.');
            return;
        }
        $count = $service->store($this->preview);
        $this->reset('file', 'preview');
        $this->resetValidation();
        session()->flash('success', $count.' siswa berhasil diimpor. Lanjutkan penempatan melalui Kelas & Rombel.');
    }

    public function cancelPreview(): void
    {
        $this->preview = [];
        $this->resetValidation();
    }

    public function downloadTemplate()
    {
        return Excel::download(new StudentsTemplateExport(), 'template-data-siswa.xlsx');
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Impor Data Siswa</flux:heading>
            <flux:text class="mt-1">Tambahkan biodata siswa secara massal dari Excel.</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button icon="arrow-left" :href="route('admin.students.index')" wire:navigate>Daftar Siswa</flux:button>
            <flux:button icon="arrow-down-tray" wire:click="downloadTemplate" wire:loading.attr="disabled">Unduh Template Excel</flux:button>
        </div>
    </div>

    @if(session('success'))
        <flux:callout variant="success">{{ session('success') }}</flux:callout>
        <flux:button :href="route('admin.classes.index')" wire:navigate>Tempatkan Siswa ke Rombel</flux:button>
    @endif

    @if($errors->any())
        <flux:callout variant="warning" role="alert">
            <flux:heading>Data belum dapat diimpor</flux:heading>
            <ul class="mt-2 max-h-64 space-y-1 overflow-y-auto">
                @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
            <flux:text class="mt-2">Perbaiki file lalu unggah kembali. Tidak ada siswa dari impor ini yang disimpan.</flux:text>
        </flux:callout>
    @endif

    <flux:card class="space-y-4">
        <flux:heading size="lg">1. Isi template, lalu unggah</flux:heading>
        <ol class="list-decimal space-y-2 pl-5 text-sm">
            <li>Unduh template dan isi sheet <strong>Data Siswa</strong> mulai baris 2. Petunjuk serta contoh tersedia pada sheet <strong>Petunjuk</strong>.</li>
            <li>Nama Lengkap wajib diisi. NIS, NISN, dan NIK tidak boleh duplikat. Pertahankan format teks pada nomor identitas dan telepon.</li>
            <li>Jenis kelamin: L/P. PIP dan KIP: Ya/Tidak. Tanggal lahir: YYYY-MM-DD atau tanggal Excel.</li>
        </ol>
        <flux:text>Format .xlsx atau .xls, maksimal 5 MB dan 1.000 siswa per file. Penempatan kelas dilakukan setelah impor melalui Kelas & Rombel.</flux:text>
        <flux:input type="file" wire:model="file" label="File Excel" accept=".xlsx,.xls" />
        <div class="flex justify-end">
            <flux:button variant="primary" icon="eye" wire:click="previewData" wire:loading.attr="disabled" wire:target="file,previewData,import">Tinjau Data</flux:button>
        </div>
        <flux:text wire:loading wire:target="file,previewData,import">Sedang memproses data…</flux:text>
    </flux:card>

    @if(count($preview))
        <flux:card class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <flux:heading size="lg">2. Periksa {{ count($preview) }} siswa</flux:heading>
                    <flux:text>Data belum disimpan. Periksa identitas sebelum melanjutkan.</flux:text>
                </div>
                <div class="flex flex-wrap gap-2">
                    <flux:button wire:click="cancelPreview" wire:loading.attr="disabled">Batal</flux:button>
                    <flux:button variant="primary" wire:click="import" wire:loading.attr="disabled" wire:target="import,file,previewData">Impor {{ count($preview) }} Siswa</flux:button>
                </div>
            </div>
            <div class="max-h-96 overflow-auto">
                <table class="w-full whitespace-nowrap text-left text-sm">
                    <thead><tr class="border-b"><th class="p-3">Baris Excel</th>@foreach(\App\Services\StudentImportService::COLUMNS as $label)<th class="p-3">{{ $label }}</th>@endforeach</tr></thead>
                    <tbody>
                    @foreach($preview as $row)
                        <tr class="border-b border-zinc-100 dark:border-zinc-800">
                            <td class="p-3">{{ $row['_row'] }}</td>
                            @foreach(\App\Services\StudentImportService::COLUMNS as $key => $label)
                                <td class="p-3">{{ in_array($key, ['pip_status','kip_status'], true) ? ($row[$key] ? 'Ya' : 'Tidak') : ($key === 'gender' ? match($row[$key]) {'male' => 'Laki-laki', 'female' => 'Perempuan', default => '-'} : ($row[$key] ?? '-')) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </flux:card>
    @endif
</div>
