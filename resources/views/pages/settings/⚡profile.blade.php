<?php

use App\Concerns\ProfileValidationRules;
/* @chisel-email-verification */
use Illuminate\Contracts\Auth\MustVerifyEmail;
/* @end-chisel-email-verification */
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';
    public string $nip = '';
    public string $nuptk = '';
    public string $gender = '';
    public string $birth_place = '';
    public string $birth_date = '';
    public string $employment_status = '';
    public string $employee_number = '';
    public string $position = '';
    public string $phone = '';
    public string $address = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;

        if ($gtk = Auth::user()->gtk) {
            $this->name = $gtk->full_name;
            foreach (['nip', 'nuptk', 'gender', 'birth_place', 'employment_status', 'employee_number', 'position', 'phone', 'address'] as $field) {
                $this->{$field} = $gtk->{$field} ?? '';
            }
            $this->birth_date = $gtk->birth_date?->format('Y-m-d') ?? '';
        }
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $gtk = $user->gtk;
        $rules = $this->profileRules($user->id);

        if ($gtk) {
            $rules['name'] = ['required', 'string', 'max:150'];
            $rules['email'][] = 'max:150';
            $rules += [
                'nip' => ['nullable', 'string', 'max:30', Rule::unique('gtks', 'nip')->ignore($gtk->id)],
                'nuptk' => ['nullable', 'string', 'max:30', Rule::unique('gtks', 'nuptk')->ignore($gtk->id)],
                'gender' => ['required', Rule::in(['male', 'female'])],
                'birth_place' => ['nullable', 'string', 'max:100'],
                'birth_date' => ['nullable', 'date'],
                'employment_status' => ['required', Rule::in(['pns', 'non_pns', 'yayasan'])],
                'employee_number' => ['nullable', 'string', 'max:50'],
                'position' => ['nullable', 'string', 'max:100'],
                'phone' => ['nullable', 'string', 'max:30'],
                'address' => ['nullable', 'string'],
            ];
        }

        $validated = $this->validate($rules);

        $user->fill(['name' => $validated['name'], 'email' => $validated['email']]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        DB::transaction(function () use ($user, $gtk, $validated) {
            $user->save();

            if ($gtk) {
                $gtkData = collect($validated)->except('name')->map(fn ($value) => $value === '' ? null : $value)->all();
                $gtk->update(['full_name' => $validated['name']] + $gtkData);
            }
        });

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /* @chisel-email-verification */
    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    /* @end-chisel-email-verification */
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profil')" :subheading="__('Perbarui profil dan data pribadi GTK Anda. Email ini juga digunakan untuk login.')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" label="Nama lengkap" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                {{-- @chisel-email-verification --}}
                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
                {{-- @end-chisel-email-verification --}}
            </div>

            @if (auth()->user()->gtk)
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:input wire:model="nip" label="NIP" />
                    <flux:input wire:model="nuptk" label="NUPTK" />
                    <flux:select wire:model="gender" label="Jenis kelamin" required>
                        <option value="">Pilih jenis kelamin</option>
                        <option value="male">Laki-laki</option>
                        <option value="female">Perempuan</option>
                    </flux:select>
                    <flux:input wire:model="birth_place" label="Tempat lahir" />
                    <flux:input wire:model="birth_date" label="Tanggal lahir" type="date" />
                    <flux:select wire:model="employment_status" label="Status kepegawaian" required>
                        <option value="">Pilih status kepegawaian</option>
                        <option value="pns">PNS</option>
                        <option value="non_pns">Non PNS</option>
                        <option value="yayasan">Yayasan</option>
                    </flux:select>
                    <flux:input wire:model="employee_number" label="Nomor pegawai" />
                    <flux:input wire:model="position" label="Jabatan" />
                    <flux:input wire:model="phone" label="Nomor telepon" type="tel" autocomplete="tel" />
                </div>
                <flux:textarea wire:model="address" label="Alamat" autocomplete="street-address" />
            @endif

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>

            </div>
        </form>

        @if (auth()->user()->gtk)
            <livewire:pages::settings.education />
        @endif

    </x-pages::settings.layout>
</section>
