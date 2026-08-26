<?php

namespace Database\Seeders;

use App\Models\Gtk;
use Illuminate\Database\Seeder;

class GtkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $gtks = [
            [
                'full_name' => 'Nama Guru 1',
                'nip' => null,
                'nuptk' => null,
                'gender' => 'male',
                'birth_place' => null,
                'birth_date' => null,
                'employment_status' => 'non_pns',
                'employee_number' => null,
                'position' => 'Guru Mata Pelajaran',
                'phone' => null,
                'email' => null,
                'address' => null,
            ],

            [
                'full_name' => 'Nama Guru 2',
                'nip' => null,
                'nuptk' => null,
                'gender' => 'female',
                'birth_place' => null,
                'birth_date' => null,
                'employment_status' => 'non_pns',
                'employee_number' => null,
                'position' => 'Guru Mata Pelajaran',
                'phone' => null,
                'email' => null,
                'address' => null,
            ],
        ];

        foreach ($gtks as $gtk) {
            Gtk::create([
                ...$gtk,
                'is_active' => true,
            ]);
        }
    }
}
