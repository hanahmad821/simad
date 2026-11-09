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
            Room::firstOrCreate(['code' => $room['code']], [
                ...$room,
                'is_active' => true,
            ]);
        }

        $this->call(ClassroomSeeder::class);
    }
}
