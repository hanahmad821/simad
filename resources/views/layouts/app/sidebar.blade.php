<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">

    <flux:sidebar sticky collapsible="mobile"
        class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">

        {{-- Logo --}}
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />

            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>


        {{-- Navigation --}}
        <flux:sidebar.nav>

            {{-- Dashboard --}}
            <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')"
                wire:navigate>
                Dashboard
            </flux:sidebar.item>


            {{-- ========================= --}}
            {{-- ADMIN --}}
            {{-- ========================= --}}
            @if(auth()->user()->isAdmin())

                <flux:sidebar.group heading="Master Data" class="grid">
                    <flux:navlist.item icon="users" :href="route('admin.gtks.index')"
                        :current="request()->routeIs('admin.gtks.*')" wire:navigate>
                        GTK
                    </flux:navlist.item>

                    <flux:sidebar.item icon="academic-cap">
                        Siswa
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="building-office-2">
                        Kelas
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="book-open">
                        Mata Pelajaran
                    </flux:sidebar.item>

                    <flux:navlist.item icon="calendar-days" :href="route('admin.schedules.index')"
                        :current="request()->routeIs('admin.schedules.*')" wire:navigate>
                        Jadwal
                    </flux:navlist.item>
                </flux:sidebar.group>


                <flux:sidebar.group heading="Presensi" class="grid">
                    <flux:sidebar.item icon="qr-code">
                        Presensi Siswa
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="map-pin">
                        Presensi GTK
                    </flux:sidebar.item>
                </flux:sidebar.group>


                <flux:sidebar.group heading="Persuratan" class="grid">
                    <flux:sidebar.item icon="inbox-arrow-down">
                        Surat Masuk
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="paper-airplane">
                        Disposisi
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="document-text">
                        Surat Keluar
                    </flux:sidebar.item>
                </flux:sidebar.group>


                <flux:sidebar.group heading="Pengaturan" class="grid">
                    <flux:sidebar.item icon="cog-6-tooth">
                        Profil Madrasah
                    </flux:sidebar.item>
                </flux:sidebar.group>

            @endif


            {{-- ========================= --}}
            {{-- KEPALA MADRASAH --}}
            {{-- ========================= --}}
            @if(auth()->user()->isHeadmaster())

                <flux:sidebar.group heading="Akademik" class="grid">
                    <flux:sidebar.item icon="calendar-days">
                        Jadwal
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="chart-bar">
                        Laporan
                    </flux:sidebar.item>
                </flux:sidebar.group>


                <flux:sidebar.group heading="Persuratan" class="grid">
                    <flux:sidebar.item icon="inbox-arrow-down">
                        Surat Masuk
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="paper-airplane">
                        Disposisi
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="document-text">
                        Surat Keluar
                    </flux:sidebar.item>
                </flux:sidebar.group>

            @endif


            {{-- ========================= --}}
            {{-- GURU --}}
            {{-- ========================= --}}
            @if(auth()->user()->isTeacher())

                <flux:sidebar.group heading="Pembelajaran" class="grid">
                    <flux:sidebar.item icon="calendar-days">
                        Jadwal Mengajar
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="qr-code">
                        Presensi Siswa
                    </flux:sidebar.item>
                </flux:sidebar.group>


                <flux:sidebar.group heading="Presensi" class="grid">
                    <flux:sidebar.item icon="map-pin">
                        Presensi Saya
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="clock">
                        Riwayat Presensi
                    </flux:sidebar.item>
                </flux:sidebar.group>

            @endif


            {{-- ========================= --}}
            {{-- STAFF --}}
            {{-- ========================= --}}
            @if(auth()->user()->isStaff())

                <flux:sidebar.group heading="Administrasi" class="grid">
                    <flux:sidebar.item icon="inbox-arrow-down">
                        Surat Masuk
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="document-text">
                        Surat Keluar
                    </flux:sidebar.item>
                </flux:sidebar.group>


                <flux:sidebar.group heading="Presensi" class="grid">
                    <flux:sidebar.item icon="users">
                        Presensi GTK
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="qr-code">
                        Presensi Siswa
                    </flux:sidebar.item>
                </flux:sidebar.group>

            @endif

        </flux:sidebar.nav>


        {{-- Bottom --}}
        <flux:spacer />


        {{-- User menu --}}
        <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />

    </flux:sidebar>


    {{-- Mobile User Menu --}}
    <flux:header class="lg:hidden">

        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        <flux:dropdown position="top" align="end">

            <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

            <flux:menu>

                <flux:menu.radio.group>

                    <div class="p-0 text-sm font-normal">

                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">

                            <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />

                            <div class="grid flex-1 text-start text-sm leading-tight">

                                <flux:heading class="truncate">
                                    {{ auth()->user()->name }}
                                </flux:heading>

                                <flux:text class="truncate">
                                    {{ auth()->user()->email }}
                                </flux:text>

                            </div>

                        </div>

                    </div>

                </flux:menu.radio.group>


                <flux:menu.separator />


                <flux:menu.radio.group>

                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                        Settings
                    </flux:menu.item>

                </flux:menu.radio.group>


                <flux:menu.separator />


                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf

                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer" data-test="logout-button">
                        Log out
                    </flux:menu.item>

                </form>

            </flux:menu>

        </flux:dropdown>

    </flux:header>


    {{ $slot }}


    @persist('toast')
    <flux:toast.group>
        <flux:toast />
    </flux:toast.group>
    @endpersist


    @fluxScripts

</body>

</html>
