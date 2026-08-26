<?php

namespace Database\Seeders;

use App\Models\SchoolSetting;
use Illuminate\Database\Seeder;

class SchoolSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SchoolSetting::create([
            'school_name' => 'MA Maarif NU 1 Sirau',

            'nsm' => null,
            'npsn' => null,

            'address' => null,
            'phone' => null,
            'email' => null,

            // Diisi setelah mendapatkan koordinat madrasah
            'latitude' => null,
            'longitude' => null,

            // Radius awal 100 meter
            'attendance_radius' => 100,
        ]);
    }
}
