<x-layouts::app :title="__('Dashboard')">

    <div class="flex flex-col gap-6">

        <div>
            <flux:heading size="xl">
                Dashboard Administrator
            </flux:heading>

            <flux:text class="mt-1">
                Selamat datang, {{ auth()->user()->name }}.
            </flux:text>
        </div>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">

            <flux:card>
                <flux:text>GTK</flux:text>
                <flux:heading size="xl">0</flux:heading>
            </flux:card>

            <flux:card>
                <flux:text>Siswa</flux:text>
                <flux:heading size="xl">0</flux:heading>
            </flux:card>

            <flux:card>
                <flux:text>Kelas</flux:text>
                <flux:heading size="xl">0</flux:heading>
            </flux:card>

            <flux:card>
                <flux:text>Surat Masuk</flux:text>
                <flux:heading size="xl">0</flux:heading>
            </flux:card>

        </div>

    </div>

</x-layouts::app>
