<x-layouts::app title="Profil Madrasah">
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <div>
            <flux:heading size="xl">Profil Madrasah</flux:heading>
            <flux:text class="mt-2">Kelola identitas madrasah untuk kartu pelajar, persuratan, dan pengaturan lokasi presensi.</flux:text>
        </div>
        @if (session('status'))
            <div role="status" class="rounded-lg border border-green-300 bg-green-50 p-4 text-green-900">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-lg border border-red-300 bg-red-50 p-4 text-red-900">Profil belum disimpan. Periksa kolom yang ditandai di bawah.</div>
        @endif
        <form method="POST" action="{{ route('admin.school-profile.update') }}" class="space-y-6"
            x-data="schoolLocationPicker">
            @csrf
            @method('PUT')
            @foreach ([
                'Identitas dan kontak' => [
                    'school_name' => ['Nama madrasah', 'text', 150],
                    'nsm' => ['NSM', 'text', 30],
                    'npsn' => ['NPSN', 'text', 30],
                    'phone' => ['Telepon', 'tel', 30],
                    'email' => ['Email madrasah', 'email', 150],
                ],
                'Kepala madrasah' => [
                    'headmaster_name' => ['Nama kepala madrasah', 'text', 255],
                    'headmaster_nip' => ['NIP', 'text', 255],
                    'headmaster_rank' => ['Pangkat / golongan', 'text', 255],
                    'headmaster_position' => ['Jabatan', 'text', 255],
                ],
                'Lokasi presensi' => [
                    'latitude' => ['Latitude', 'number', null],
                    'longitude' => ['Longitude', 'number', null],
                    'attendance_radius' => ['Radius presensi (meter)', 'number', null],
                ],
            ] as $heading => $fields)
                <section class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:heading size="lg">{{ $heading }}</flux:heading>
                    @if ($heading === 'Lokasi presensi')
                        <flux:text>Klik peta atau geser pin ke lokasi sekolah. Lingkaran hijau menunjukkan radius presensi. Koordinat juga dapat diisi manual.</flux:text>
                        <div class="flex flex-wrap gap-2">
                            <flux:button type="button" icon="map-pin" x-on:click="locate()" x-bind:disabled="locating" x-bind:aria-busy="locating">
                                <span x-text="locating ? 'Mencari lokasi…' : 'Lokasi saya'">Lokasi saya</span>
                            </flux:button>
                            <flux:button type="button" x-show="locating" x-cloak x-on:click="cancelLocate()">Batalkan pencarian</flux:button>
                            <flux:button type="button" x-on:click="fitRadius()" x-bind:disabled="!hasPoint">Lihat area presensi</flux:button>
                            <flux:button type="button" x-on:click="resetPoint()">Kembalikan titik awal</flux:button>
                        </div>
                        <p role="status" aria-live="polite" aria-atomic="true" class="text-sm" x-bind:class="locationError ? 'text-red-600 dark:text-red-400' : 'text-zinc-600 dark:text-zinc-300'" x-text="locationMessage"></p>
                        <div x-show="mapError" x-cloak role="alert" class="space-y-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                            <p x-text="mapError"></p>
                            <flux:button type="button" x-on:click="retryTiles()">Muat ulang peta</flux:button>
                        </div>
                        <div x-ref="locationMap" wire:ignore class="school-location-map" aria-label="Peta lokasi sekolah. Klik untuk memilih titik atau geser pin. Koordinat dapat diisi melalui kolom di bawah."></div>
                        <flux:text size="sm">Pin merah: lokasi sekolah. Lingkaran hijau: radius presensi. Lingkaran biru putus-putus: perkiraan akurasi lokasi perangkat.</flux:text>
                        <p x-show="!hasPoint" class="text-sm text-zinc-500">Lokasi sekolah belum dipilih. Klik peta atau isi latitude dan longitude.</p>
                        <a x-show="hasPoint" x-cloak x-bind:href="mapUrl" class="inline-flex text-sm underline" target="_blank" rel="noopener noreferrer">Buka titik di OpenStreetMap ↗</a>
                        <flux:text size="sm">Perubahan lokasi dan radius berlaku setelah menekan Simpan Profil Madrasah.</flux:text>
                    @endif
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ($fields as $field => [$label, $type, $max])
                            <flux:field>
                                <flux:label for="{{ $field }}">{{ $label }}{{ in_array($field, ['school_name', 'attendance_radius']) ? ' *' : '' }}</flux:label>
                                <flux:input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" :value="old($field, $school->$field)" :maxlength="$max" :required="in_array($field, ['school_name', 'attendance_radius'])" :step="$type === 'number' ? ($field === 'attendance_radius' ? '1' : 'any') : null" :min="match($field) { 'latitude' => -90, 'longitude' => -180, 'attendance_radius' => 1, default => null }" :max="match($field) { 'latitude' => 90, 'longitude' => 180, 'attendance_radius' => 100000, default => null }" />
                                <flux:error name="{{ $field }}" />
                            </flux:field>
                        @endforeach
                    </div>
                    @if ($heading === 'Identitas dan kontak')
                        <flux:field>
                            <flux:label for="address">Alamat lengkap</flux:label>
                            <flux:textarea id="address" name="address" rows="3" maxlength="5000">{{ old('address', $school->address) }}</flux:textarea>
                            <flux:error name="address" />
                        </flux:field>
                    @endif
                </section>
            @endforeach
            <flux:button type="submit" variant="primary">Simpan Profil Madrasah</flux:button>
        </form>
    </div>
</x-layouts::app>
