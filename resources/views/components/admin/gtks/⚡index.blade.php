<?php

use App\Models\Gtk;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public bool $showRoleForm = false;
    #[Locked]
    public ?int $roleUserId = null;
    #[Locked]
    public string $roleUserName = '';
    #[Locked]
    public string $roleUserEmail = '';
    public string $accountRole = '';
    public string $accountSecondaryRole = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function openRoles(int $gtkId): void
    {
        $this->closeRoles();
        $user = Gtk::findOrFail($gtkId)->user;
        abort_unless($user, 403, 'Buat akun GTK terlebih dahulu.');
        $this->roleUserId = $user->id;
        $this->roleUserName = $user->name;
        $this->roleUserEmail = $user->email;
        $this->accountRole = $user->role;
        $this->accountSecondaryRole = $user->secondary_role ?? '';
        $this->showRoleForm = true;
    }

    public function saveRoles(\App\Services\UserRoleService $service): void
    {
        abort_unless($this->roleUserId, 403);
        try {
            $service->update(auth()->user(), User::findOrFail($this->roleUserId), [
                'role' => $this->accountRole, 'secondary_role' => $this->accountSecondaryRole,
            ]);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field === 'secondary_role' ? 'accountSecondaryRole' : 'accountRole', $message);
                }
            }
            return;
        }
        $this->closeRoles();
        session()->flash('success', 'Role akun berhasil diperbarui.');
    }

    public function closeRoles(): void
    {
        $this->reset('showRoleForm', 'roleUserId', 'roleUserName', 'roleUserEmail', 'accountRole', 'accountSecondaryRole');
        $this->resetValidation(['accountRole', 'accountSecondaryRole']);
    }

    public function updatedShowRoleForm(bool $value): void
    {
        if (! $value) $this->closeRoles();
    }

    public function updatedAccountRole(string $value): void
    {
        if ($value === $this->accountSecondaryRole) $this->accountSecondaryRole = '';
    }

    public bool $showPasswordReset = false;
    #[Locked]
    public ?int $passwordResetUserId = null;
    #[Locked]
    public string $passwordResetName = '';
    #[Locked]
    public string $passwordResetEmail = '';
    public string $newPassword = '';
    public string $newPassword_confirmation = '';

    public function openPasswordReset(int $gtkId): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $this->closePasswordReset();
        $gtk = Gtk::with('user')->findOrFail($gtkId);
        abort_unless($gtk->user && $gtk->user->id !== auth()->id(), 403);
        $this->passwordResetUserId = $gtk->user->id;
        $this->passwordResetName = $gtk->user->name;
        $this->passwordResetEmail = $gtk->user->email;
        $this->showPasswordReset = true;
    }

    public function resetUserPassword(\App\Services\AdminPasswordResetService $service): void
    {
        abort_unless(auth()->user()->isAdmin() && $this->passwordResetUserId, 403);
        $target = User::findOrFail($this->passwordResetUserId);
        $service->reset(auth()->user(), $target, $this->only(['newPassword', 'newPassword_confirmation']));
        $this->closePasswordReset();
        session()->flash('success', 'Password akun '.$target->name.' berhasil direset. Sampaikan password baru kepada pengguna untuk login kembali.');
    }

    public function closePasswordReset(): void
    {
        $this->reset('showPasswordReset', 'passwordResetUserId', 'passwordResetName', 'passwordResetEmail', 'newPassword', 'newPassword_confirmation');
        $this->resetValidation(['newPassword', 'newPassword_confirmation']);
    }

    public function updatedShowPasswordReset(bool $value): void
    {
        if (! $value) $this->closePasswordReset();
    }

    public ?int $editingId = null;

    public string $full_name = '';
    public ?string $nip = null;
    public ?string $nuptk = null;
    public string $gender = '';
    public string $birth_place = '';
    public string $birth_date = '';
    public string $employment_status = '';
    public ?string $employee_number = null;
    public string $position = '';
    public string $phone = '';
    public string $email = '';
    public ?string $address = null;
    public bool $is_active = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();

        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $gtk = Gtk::findOrFail($id);

        $this->editingId = $gtk->id;

        $this->full_name = $gtk->full_name ?? '';
        $this->nip = $gtk->nip ?? null;
        $this->nuptk = $gtk->nuptk ?? null;
        $this->gender = $gtk->gender ?? '';
        $this->birth_place = $gtk->birth_place ?? '';
        $this->birth_date = $gtk->birth_date?->format('Y-m-d') ?? '';
        $this->employment_status = $gtk->employment_status ?? '';
        $this->employee_number = $gtk->employee_number ?? null;
        $this->position = in_array($gtk->position, ['Staf Tata Usaha', 'Tenaga Kependidikan'], true) ? 'Staff' : ($gtk->position ?? '');
        $this->phone = $gtk->phone ?? '';
        $this->email = $gtk->email ?? '';
        $this->address = $gtk->address ?? null;
        $this->is_active = (bool) $gtk->is_active;

        $this->resetValidation();

        $this->showForm = true;
    }

    public function positionOptions(): array
    {
        $options = ['Kepala Madrasah', 'Guru Mata Pelajaran', 'Staff'];
        $existing = $this->editingId ? Gtk::findOrFail($this->editingId)->position : null;

        if ($existing && ! in_array($existing, [...$options, 'Staf Tata Usaha', 'Tenaga Kependidikan'], true)) {
            $options[] = $existing;
        }

        return $options;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'full_name' => ['required', 'string', 'max:150'],

            'nip' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('gtks', 'nip')->ignore($this->editingId),
            ],

            'nuptk' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('gtks', 'nuptk')->ignore($this->editingId),
            ],

            'gender' => [
                'required',
                Rule::in(['male', 'female']),
            ],

            'birth_place' => ['nullable', 'string', 'max:100'],

            'birth_date' => ['nullable', 'date'],

            'employment_status' => [
                'required',
                Rule::in(['pns', 'non_pns', 'yayasan']),
            ],

            'employee_number' => ['nullable', 'string', 'max:50'],

            'position' => ['required', Rule::in($this->positionOptions())],

            'phone' => ['nullable', 'string', 'max:30'],

            'email' => ['nullable', 'email', 'max:150'],

            'address' => ['nullable', 'string'],

            'is_active' => ['boolean'],
        ]);

        if ($this->editingId) {
            $gtk = Gtk::findOrFail($this->editingId);

            $gtk->update($validated);

            session()->flash('success', 'Data GTK berhasil diperbarui.');
        } else {
            Gtk::create($validated);

            session()->flash('success', 'Data GTK berhasil ditambahkan.');
        }

        $this->closeForm();
    }

    public function delete(int $id): void
    {
        $gtk = Gtk::findOrFail($id);

        $gtk->delete();

        session()->flash('success', 'Data GTK berhasil dihapus.');
    }

    public function closeForm(): void
    {
        $this->showForm = false;

        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId',
            'full_name',
            'nip',
            'nuptk',
            'gender',
            'birth_place',
            'birth_date',
            'employment_status',
            'employee_number',
            'position',
            'phone',
            'email',
            'address',
        ]);

        $this->is_active = true;

        $this->resetValidation();
    }

    public function generateAccount(int $gtkId): void
    {
        $gtk = Gtk::with('user')->findOrFail($gtkId);

        if ($gtk->user) {
            session()->flash('success', "GTK {$gtk->full_name} sudah memiliki akun.");
            return;
        }

        User::create([
            'name' => $gtk->full_name,
            'email' => $this->generateEmail($gtk),
            'password' => '12345678',
            'role' => $this->determineRole($gtk),
            'gtk_id' => $gtk->id,
        ]);

        session()->flash(
            'success',
            "Akun {$gtk->full_name} berhasil dibuat. Password awal: 12345678"
        );
    }

    public function generateAllAccounts(): void
    {
        $gtks = Gtk::query()
            ->where('is_active', true)
            ->whereDoesntHave('user')
            ->get();

        if ($gtks->isEmpty()) {
            session()->flash(
                'success',
                'Semua GTK aktif sudah memiliki akun.'
            );

            return;
        }

        $count = 0;

        foreach ($gtks as $gtk) {
            User::create([
                'name' => $gtk->full_name,
                'email' => $this->generateEmail($gtk),
                'password' => '12345678',
                'role' => $this->determineRole($gtk),
                'gtk_id' => $gtk->id,
            ]);

            $count++;
        }

        session()->flash(
            'success',
            "{$count} akun GTK berhasil dibuat. Password awal: 12345678"
        );
    }

    private function generateEmail(Gtk $gtk): string
    {
        if ($gtk->email) {
            $email = strtolower(trim($gtk->email));

            if (!User::where('email', $email)->exists()) {
                return $email;
            }
        }

        $base = Str::slug($gtk->full_name, '.');
        $email = $base . '@simad.local';

        $counter = 1;

        while (User::where('email', $email)->exists()) {
            $email = $base . $counter . '@simad.local';
            $counter++;
        }

        return $email;
    }

    private function determineRole(Gtk $gtk): string
    {
        $position = Str::lower(trim($gtk->position ?? ''));

        if (
            Str::contains($position, [
                'kepala madrasah',
                'kepala sekolah',
                'kamad',
            ])
        ) {
            return 'headmaster';
        }

        if (
            Str::contains($position, [
                'guru',
                'pengajar',
            ])
        ) {
            return 'teacher';
        }

        return 'staff';
    }

    public function with(): array
    {
        return [
            'gtks' => Gtk::query()
                ->with('user')
                ->when($this->search, function ($query) {
                    $query->where(function ($query) {
                        $query
                            ->where('full_name', 'like', '%' . $this->search . '%')
                            ->orWhere('nip', 'like', '%' . $this->search . '%')
                            ->orWhere('nuptk', 'like', '%' . $this->search . '%')
                            ->orWhere('email', 'like', '%' . $this->search . '%')
                            ->orWhereHas('user', fn ($user) => $user->where('email', 'like', '%' . $this->search . '%'));
                    });
                })
                ->orderBy('full_name')
                ->paginate(10),
        ];
    }
};
?>

<div class="flex flex-col gap-6">

    <flux:modal name="edit-account-roles" wire:model="showRoleForm" class="md:w-xl">
        <form wire:submit="saveRoles" class="space-y-5">
            <div>
                <flux:heading size="lg">Atur Role Akun</flux:heading>
                <flux:text class="mt-1">{{ $roleUserName }} · {{ $roleUserEmail }}</flux:text>
            </div>
            <flux:text>Satu akun dapat memiliki maksimal dua role. Role utama digunakan saat login. Pengguna dapat berganti role melalui menu nama pada sidebar.</flux:text>
            <flux:select label="Role utama" wire:model.live="accountRole" required>
                @foreach (User::ROLE_LABELS as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </flux:select>
            <flux:select label="Role kedua (opsional)" wire:model="accountSecondaryRole">
                <option value="">Tanpa role kedua</option>
                @foreach (User::ROLE_LABELS as $value => $label)
                    <option value="{{ $value }}" @disabled($value === $accountRole)>{{ $label }}</option>
                @endforeach
            </flux:select>
            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="closeRoles">Batal</flux:button>
                <flux:button type="submit" variant="primary">Simpan Role</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="reset-user-password" wire:model="showPasswordReset" class="md:w-xl">
        <form wire:submit="resetUserPassword" class="space-y-5">
            <div>
                <flux:heading size="lg">Reset Password Pengguna</flux:heading>
                <flux:text class="mt-1">{{ $passwordResetName }} · {{ $passwordResetEmail }}</flux:text>
            </div>
            <flux:text>Password lama akan diganti. Pengguna perlu login kembali menggunakan password baru.</flux:text>
            <flux:input type="password" label="Password Baru" wire:model="newPassword" autocomplete="new-password" required viewable />
            <flux:input type="password" label="Konfirmasi Password Baru" wire:model="newPassword_confirmation" autocomplete="new-password" required viewable />
            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="closePasswordReset">Batal</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="resetUserPassword">Simpan Password Baru</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Header --}}
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">
                Data GTK
            </flux:heading>

            <flux:text class="mt-1">
                Kelola data Guru dan Tenaga Kependidikan.
            </flux:text>
        </div>

        <div class="flex gap-2">
            <flux:button variant="primary" icon="plus" wire:click="create">
                Tambah GTK
            </flux:button>

            <flux:button variant="filled" icon="key" wire:click="generateAllAccounts"
                wire:confirm="Generate akun untuk semua GTK aktif yang belum memiliki akun?">
                Generate Semua Akun
            </flux:button>
        </div>
    </div>


    {{-- Flash Message --}}
    @if (session()->has('success'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('success') }}
        </flux:callout>
    @endif


    {{-- Table Card --}}
    <flux:card>

        {{-- Search --}}
        <div class="mb-5 max-w-md">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari nama, NIP, NUPTK, atau email..."
                icon="magnifying-glass" />
        </div>


        {{-- Table --}}
        <div class="overflow-x-auto">

            <table class="w-full text-sm">

                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">

                        <th class="px-4 py-3 text-left">
                            No
                        </th>

                        <th class="px-4 py-3 text-left">
                            Nama
                        </th>

                        <th class="px-4 py-3 text-left">
                            NIP / NUPTK
                        </th>

                        <th class="px-4 py-3 text-left">
                            Email
                        </th>

                        <th class="px-4 py-3 text-left">
                            Jabatan
                        </th>

                        <th class="px-4 py-3 text-center">
                            Status / Akun
                        </th>

                        <th class="px-4 py-3 text-right">
                            Aksi
                        </th>

                    </tr>
                </thead>

                <tbody>

                    @forelse ($gtks as $gtk)

                        <tr wire:key="gtk-{{ $gtk->id }}" class="border-b border-zinc-100 dark:border-zinc-800">

                            {{-- No --}}
                            <td class="px-4 py-3">
                                {{ $gtks->firstItem() + $loop->index }}
                            </td>


                            {{-- Nama --}}
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{ $gtk->full_name }}
                                </div>

                                @if ($gtk->gender)
                                    <div class="text-xs text-zinc-500">
                                        {{ $gtk->gender === 'male' ? 'Laki-laki' : 'Perempuan' }}
                                    </div>
                                @endif
                            </td>


                            {{-- NIP / NUPTK --}}
                            <td class="px-4 py-3">

                                @if ($gtk->nip)
                                    <div>
                                        {{ $gtk->nip }}
                                    </div>
                                @endif

                                @if ($gtk->nuptk)
                                    <div class="text-xs text-zinc-500">
                                        NUPTK: {{ $gtk->nuptk }}
                                    </div>
                                @endif

                                @if (!$gtk->nip && !$gtk->nuptk)
                                    -
                                @endif

                            </td>


                            {{-- Email akun login, atau email GTK jika belum memiliki akun --}}
                            <td class="px-4 py-3">
                                {{ $gtk->user?->email ?: ($gtk->email ?: '-') }}
                            </td>


                            {{-- Jabatan --}}
                            <td class="px-4 py-3">
                                {{ in_array($gtk->position, ['Staf Tata Usaha', 'Tenaga Kependidikan'], true) ? 'Staff' : ($gtk->position ?: '-') }}
                            </td>


                            {{-- Status GTK dan ketersediaan akun --}}
                            <td class="px-4 py-3 text-center">
                                <div class="flex flex-col items-center gap-1">
                                    <flux:badge size="sm" :color="$gtk->is_active ? 'green' : 'zinc'">
                                        {{ $gtk->is_active ? 'Aktif' : 'Tidak Aktif' }}
                                    </flux:badge>
                                    <span class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $gtk->user ? 'Akun tersedia' : 'Belum ada akun' }}
                                    </span>
                                    @if ($gtk->user)
                                        <div class="flex flex-wrap justify-center gap-1">
                                            @foreach ($gtk->user->roleNames() as $assignedRole)
                                                <flux:badge size="sm" color="blue">{{ User::ROLE_LABELS[$assignedRole] }}</flux:badge>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </td>

                            {{-- Menu aksi --}}
                            <td class="px-4 py-3 text-right">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-vertical"
                                        aria-label="Aksi untuk {{ $gtk->full_name }}" />
                                    <flux:menu>
                                        <flux:menu.item icon="eye" :href="route('admin.gtks.show', $gtk)" wire:navigate>
                                            Detail
                                        </flux:menu.item>
                                        <flux:menu.item icon="pencil-square" wire:click="edit({{ $gtk->id }})">
                                            Edit
                                        </flux:menu.item>
                                        @if (!$gtk->user)
                                            <flux:menu.item icon="key" wire:click="generateAccount({{ $gtk->id }})"
                                                wire:confirm="Buat akun untuk {{ $gtk->full_name }}?">
                                                Buat Akun
                                            </flux:menu.item>
                                        @elseif ($gtk->user->id !== auth()->id())
                                            <flux:menu.item icon="key" wire:click="openPasswordReset({{ $gtk->id }})">
                                                Reset Password
                                            </flux:menu.item>
                                        @endif
                                        @if ($gtk->user)
                                            <flux:menu.item icon="user-group" wire:click="openRoles({{ $gtk->id }})">
                                                Atur Role
                                            </flux:menu.item>
                                        @endif
                                        <flux:menu.separator />
                                        <flux:menu.item variant="danger" icon="trash" wire:click="delete({{ $gtk->id }})"
                                            wire:confirm="Yakin ingin menghapus data {{ $gtk->full_name }}?">
                                            Hapus
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="7" class="px-4 py-10 text-center">
                                <flux:text>
                                    @if ($search)
                                        Data GTK tidak ditemukan.
                                    @else
                                        Belum ada data GTK.
                                    @endif
                                </flux:text>
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>


        {{-- Pagination --}}
        @if ($gtks->hasPages())
            <div class="mt-5">
                {{ $gtks->links() }}
            </div>
        @endif

    </flux:card>


    {{-- Form --}}
    <flux:modal wire:model="showForm" class="md:w-[700px]">

        <div class="space-y-6">

            <div>
                <flux:heading size="lg">
                    {{ $editingId ? 'Edit Data GTK' : 'Tambah GTK' }}
                </flux:heading>

                <flux:text class="mt-1">
                    Lengkapi data Guru atau Tenaga Kependidikan.
                </flux:text>
            </div>


            {{-- Data Utama --}}
            <div class="space-y-4">

                <flux:heading size="sm">
                    Data Utama
                </flux:heading>


                <flux:input label="Nama Lengkap" wire:model="full_name" placeholder="Nama lengkap" required />


                <div class="grid gap-4 md:grid-cols-2">

                    <flux:input label="NIP" wire:model="nip" placeholder="NIP" />

                    <flux:input label="NUPTK" wire:model="nuptk" placeholder="NUPTK" />

                </div>


                <div class="grid gap-4 md:grid-cols-2">

                    <flux:select label="Jenis Kelamin" wire:model="gender">
                        <flux:select.option value="">
                            Pilih jenis kelamin
                        </flux:select.option>

                        <flux:select.option value="male">
                            Laki-laki
                        </flux:select.option>

                        <flux:select.option value="female">
                            Perempuan
                        </flux:select.option>
                    </flux:select>


                    <flux:input type="date" label="Tanggal Lahir" wire:model="birth_date" />

                </div>


                <flux:input label="Tempat Lahir" wire:model="birth_place" placeholder="Tempat lahir" />

            </div>


            {{-- Kepegawaian --}}
            <div class="space-y-4">

                <flux:heading size="sm">
                    Data Kepegawaian
                </flux:heading>


                <div class="grid gap-4 md:grid-cols-2">

                    <flux:select label="Status Kepegawaian" wire:model="employment_status">
                        <flux:select.option value="">
                            Pilih status
                        </flux:select.option>

                        <flux:select.option value="pns">
                            PNS
                        </flux:select.option>

                        <flux:select.option value="non_pns">
                            Non-PNS
                        </flux:select.option>

                        <flux:select.option value="yayasan">
                            Yayasan
                        </flux:select.option>
                    </flux:select>


                    <flux:input label="Nomor Pegawai" wire:model="employee_number" placeholder="Nomor pegawai" />

                </div>


                <flux:select label="Jabatan" wire:model="position" required>
                    <flux:select.option value="">Pilih jabatan</flux:select.option>
                    @foreach ($this->positionOptions() as $option)
                        <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:description>Jabatan menentukan role saat akun dibuat: Kepala Madrasah, Guru, atau Staf.</flux:description>

            </div>


            {{-- Kontak --}}
            <div class="space-y-4">

                <flux:heading size="sm">
                    Kontak
                </flux:heading>


                <div class="grid gap-4 md:grid-cols-2">

                    <flux:input label="Nomor HP" wire:model="phone" placeholder="08xxxxxxxxxx" />

                    <flux:input type="email" label="Email" wire:model="email" placeholder="email@example.com" />

                </div>


                <flux:textarea label="Alamat" wire:model="address" placeholder="Alamat lengkap" rows="3" />

            </div>


            {{-- Status --}}
            <flux:checkbox wire:model="is_active" label="GTK masih aktif" />


            {{-- Footer --}}
            <div class="flex justify-end gap-2">

                <flux:button wire:click="closeForm" variant="ghost">
                    Batal
                </flux:button>

                <flux:button wire:click="save" variant="primary">
                    {{ $editingId ? 'Simpan Perubahan' : 'Simpan' }}
                </flux:button>

            </div>

        </div>

    </flux:modal>

</div>
