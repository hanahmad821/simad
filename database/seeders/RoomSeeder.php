<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rooms = [
            [
                'code' => 'R01',
                'name' => 'Ruang Kelas 1',
                'building' => 'Gedung Utama',
                'capacity' => 36,
            ],
            [
                'code' => 'R02',
                'name' => 'Ruang Kelas 2',
                'building' => 'Gedung Utama',
                'capacity' => 36,
            ],
            [
                'code' => 'R03',
                'name' => 'Ruang Kelas 3',
                'building' => 'Gedung Utama',
                'capacity' => 36,
            ],
            [
                'code' => 'R04',
                'name' => 'Ruang Kelas 4',
                'building' => 'Gedung Utama',
                'capacity' => 36,
            ],
            [
                'code' => 'LAB-IPA',
                'name' => 'Laboratorium IPA',
                'building' => 'Gedung Laboratorium',
                'capacity' => 40,
            ],
            [
                'code' => 'LAB-KOM',
                'name' => 'Laboratorium Komputer',
                'building' => 'Gedung Laboratorium',
                'capacity' => 32,
            ],
        ];

        foreach ($rooms as $room) {
            Room::create([
                ...$room,
                'is_active' => true,
            ]);
        }
    }
}
