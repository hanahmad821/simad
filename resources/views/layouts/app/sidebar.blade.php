<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">

    <flux:sidebar sticky collapsible="mobile"
        class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">

        {{-- Logo --}}
        <div class="space-y-1">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />

                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <div class="text-xs font-normal leading-snug text-zinc-500 dark:text-zinc-400">
                {{ \App\Models\SchoolSetting::query()->first()?->school_name ?: 'MA Ma’arif NU 1 Kemranjen' }}
            </div>


        </div>

        {{-- Navigation --}}
        <flux:sidebar.nav>

            {{-- Dashboard --}}
            <flux:sidebar.item icon="home" :href="route('dashboard')"
                :current="request()->routeIs('dashboard', '*.dashboard')" wire:navigate>
                Dashboard
            </flux:sidebar.item>


            {{-- ========================= --}}
            {{-- ALL GTK --}}
            {{-- ========================= --}}
            @if(auth()->user()->canTeach() || auth()->user()->isHeadmaster() || auth()->user()->isStaff() || auth()->user()->isAdmin())
                <flux:sidebar.group heading="Presensi" class="grid">
                    <flux:sidebar.item icon="map-pin" :href="route('gtk.attendance.index')"
                        :current="request()->routeIs('gtk.attendance.index.*')" wire:navigate>
                        Presensi Saya
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="clock" :href="route('gtk.attendance.history')"
                        :current="request()->routeIs('gtk.attendance.history.*')" wire:navigate>
                        Riwayat Presensi
                    </flux:sidebar.item>
                    @if(auth()->user()->gtk_id)
                        <flux:sidebar.item icon="document-plus" :href="route('gtk.absences.index')"
                            :current="request()->routeIs('gtk.absences.*')" wire:navigate>
                            Pengajuan Absensi
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
                <flux:sidebar.item icon="clipboard-document-list" :href="route('gtk.lckh.index')"
                    :current="request()->routeIs('gtk.lckh.index')" wire:navigate>
                    LCKH
                </flux:sidebar.item>
                <flux:sidebar.item icon="clock" :href="route('gtk.lckh.history')"
                    :current="request()->routeIs('gtk.lckh.history')" wire:navigate>
                    Riwayat LCKH
                </flux:sidebar.item>
                <flux:navlist.item icon="clipboard-document-check" :href="route('gtk.dispositions.index')"
                    :current="request()->routeIs('gtk.dispositions.*')">
                    Disposisi Saya
                </flux:navlist.item>
            @endif

            {{-- ========================= --}}
            {{-- ADMIN --}}
            {{-- ========================= --}}
            @if(auth()->user()->isAdmin())

                <flux:sidebar.group heading="Master Data" class="grid">
                    <flux:navlist.item icon="users" :href="route('admin.gtks.index')"
                        :current="request()->routeIs('admin.gtks.*')" wire:navigate>
                        GTK
                    </flux:navlist.item>

                    <flux:navlist.item icon="academic-cap" :href="route('admin.students.index')"
                        :current="request()->routeIs('admin.students.*')" wire:navigate>
                        Siswa
                    </flux:navlist.item>


                    <flux:sidebar.item icon="building-office-2" :href="route('admin.classes.index')"
                        :current="request()->routeIs('admin.classes.*')" wire:navigate>
                        Kelas & Rombel
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="book-open" :href="route('admin.subjects.index')"
                        :current="request()->routeIs('admin.subjects.*')" wire:navigate>
                        Mata Pelajaran
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="building-office" :href="route('admin.rooms.index')"
                        :current="request()->routeIs('admin.rooms.*')" wire:navigate>
                        Ruangan
                    </flux:sidebar.item>

                    <flux:navlist.item icon="calendar-days" :href="route('admin.schedules.index')"
                        :current="request()->routeIs('admin.schedules.*')" wire:navigate>
                        Jadwal Pelajaran
                    </flux:navlist.item>
                    <flux:sidebar.item icon="arrow-trending-up" :href="route('admin.student-progressions.index')"
                        :current="request()->routeIs('admin.student-progressions.*')" wire:navigate>
                        Proses Akhir Tahun
                    </flux:sidebar.item>

                </flux:sidebar.group>


                <flux:sidebar.group heading="Presensi" class="grid">
                    <flux:sidebar.item icon="qr-code" :href="route('admin.student-attendances.index')"
                        :current="request()->routeIs('admin.student-attendances.index')" wire:navigate>
                        Presensi Siswa
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="map-pin" :href="route('admin.gtk-attendances.index')"
                        :current="request()->routeIs('admin.gtk-attendances.*')">
                        Presensi GTK
                    </flux:sidebar.item>
                </flux:sidebar.group>


                <flux:sidebar.group heading="Persuratan" class="grid">
                    <flux:navlist.item icon="document-duplicate" :href="route('admin.letter-types.index')"
                        :current="request()->routeIs('admin.letter-types.*')" wire:navigate>
                        Jenis Surat
                    </flux:navlist.item>
                    <flux:navlist.item icon="inbox-arrow-down" :href="route('admin.incoming-letters.index')"
                        :current="request()->routeIs('admin.incoming-letters.*')">
                        Surat Masuk
                    </flux:navlist.item>

                    <flux:navlist.item icon="paper-airplane" :href="route('admin.outgoing-letters.index')"
                        :current="request()->routeIs('admin.outgoing-letters.*')">
                        Surat Keluar
                    </flux:navlist.item>
                </flux:sidebar.group>


                <flux:sidebar.group heading="Pengaturan" class="grid">
                    <flux:sidebar.item icon="calendar" :href="route('admin.academic-years.index')"
                        :current="request()->routeIs('admin.academic-years.*')" wire:navigate>Tahun Pelajaran
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="cog-6-tooth" :href="route('admin.school-profile.edit')"
                        :current="request()->routeIs('admin.school-profile.*')" wire:navigate>
                        Profil Madrasah
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="clock" :href="route('admin.kbm-times.index')"
                        :current="request()->routeIs('admin.kbm-times.*')" wire:navigate>
                        Waktu KBM
                    </flux:sidebar.item>
                    <flux:navlist.item icon="calendar-days" :href="route('admin.holidays.index')"
                        :current="request()->routeIs('admin.holidays.*')">
                        Hari Libur
                    </flux:navlist.item>
                </flux:sidebar.group>

            @endif


            {{-- ========================= --}}
            {{-- KEPALA MADRASAH --}}
            {{-- ========================= --}}
            @if(auth()->user()->isHeadmaster())

                <flux:sidebar.group heading="Akademik" class="grid">
                    <flux:sidebar.item icon="clipboard-document-check" :href="route('headmaster.absences.index')"
                        :current="request()->routeIs('headmaster.absences.*')" wire:navigate>
                        Approval Absensi GTK
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="clipboard-document-check" :href="route('headmaster.work-reviews.index')"
                        :current="request()->routeIs('headmaster.work-reviews.*')" wire:navigate>
                        Approval LCKH GTK
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="book-open" :href="route('headmaster.teaching-journals.index')"
                        :current="request()->routeIs('headmaster.teaching-journals.*')" wire:navigate>
                        Rekap Jurnal Mengajar
                    </flux:sidebar.item>
                </flux:sidebar.group>


                <flux:sidebar.group heading="Persuratan" class="grid">
                    <flux:sidebar.item icon="inbox-arrow-down" :href="route('headmaster.incoming-letters.index')"
                        :current="request()->routeIs('headmaster.incoming-letters.*')" wire:navigate>
                        Surat Masuk & Disposisi
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="document-text" :href="route('headmaster.outgoing-letters.index')"
                        :current="request()->routeIs('headmaster.outgoing-letters.*')" wire:navigate>
                        Surat Keluar
                    </flux:sidebar.item>
                </flux:sidebar.group>

            @endif


            {{-- ========================= --}}
            {{-- GURU --}}
            {{-- ========================= --}}
            @if(auth()->user()->canTeach())

                <flux:sidebar.group heading="Pembelajaran" class="grid">
                    <flux:sidebar.item icon="book-open" :href="route('gtk.journals.index')"
                        :current="request()->routeIs('gtk.journals.index')" wire:navigate>
                        Jurnal Mengajar
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="clock" :href="route('gtk.journals.history')"
                        :current="request()->routeIs('gtk.journals.history')" wire:navigate>
                        Riwayat Jurnal Mengajar
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="calendar-days" :href="route('teacher.schedules.index')"
                        :current="request()->routeIs('teacher.schedules.*')" wire:navigate>
                        Jadwal Mengajar
                    </flux:sidebar.item>

                    @if(auth()->user()->canTeach())
                        <flux:sidebar.item icon="qr-code" :href="route('teacher.student-attendances.index')"
                            :current="request()->routeIs('teacher.student-attendances.index')" wire:navigate>
                            Presensi Siswa
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            @endif


            {{-- ========================= --}}
            {{-- STAFF --}}
            {{-- ========================= --}}
            @if(auth()->user()->isStaff())

                <flux:sidebar.group heading="Administrasi" class="grid">
                    <flux:sidebar.item icon="inbox-arrow-down" :href="route('staff.incoming-letters.index')"
                        :current="request()->routeIs('staff.incoming-letters.*')" wire:navigate>
                        Surat Masuk
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="document-text" :href="route('staff.outgoing-letters.index')"
                        :current="request()->routeIs('staff.outgoing-letters.*')" wire:navigate>
                        Surat Keluar
                    </flux:sidebar.item>
                </flux:sidebar.group>


                <flux:sidebar.group heading="Presensi" class="grid">
                    <flux:sidebar.item icon="users" :href="route('staff.gtk-attendances.index')"
                        :current="request()->routeIs('staff.gtk-attendances.*')" wire:navigate>
                        Presensi GTK
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="qr-code" :href="route('staff.student-attendances.index')"
                        :current="request()->routeIs('staff.student-attendances.index')" wire:navigate>
                        Presensi Siswa
                    </flux:sidebar.item>
                </flux:sidebar.group>

            @endif

            @if(auth()->user()->hasActiveRole(['admin', 'staff', 'teacher', 'headmaster']))
                <flux:sidebar.group heading="Riwayat" class="grid">
                    <flux:sidebar.item icon="clipboard-document-list"
                        :href="route(auth()->user()->activeRole().'.student-attendances.history')"
                        :current="request()->routeIs('*.student-attendances.history')" wire:navigate>
                        Riwayat Presensi Siswa
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


                <x-role-switcher />

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
