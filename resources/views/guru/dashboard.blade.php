<x-layouts::app :title="__('Dashboard')">

    <div class="flex flex-col gap-6">

        <div>
            <flux:heading size="xl">
                Dashboard Guru
            </flux:heading>

            <flux:text class="mt-1">
                Selamat datang, {{ auth()->user()->name }}.
            </flux:text>
        </div>

        <div class="grid gap-4 md:grid-cols-3">

            <flux:card>
                <flux:text>Jadwal Hari Ini</flux:text>
                <flux:heading size="xl">0</flux:heading>
            </flux:card>

            <flux:card>
                <flux:text>Presensi Siswa</flux:text>
                <flux:heading size="xl">0</flux:heading>
            </flux:card>

            <flux:card>
                <flux:text>Presensi Saya</flux:text>
                <flux:heading size="xl">Belum</flux:heading>
            </flux:card>

        </div>

    </div>

</x-layouts::app>
