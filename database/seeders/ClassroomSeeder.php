<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\SchoolClass;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClassroomSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach (SchoolClass::orderBy('id')->lockForUpdate()->get() as $class) {
                $code = 'KLS-'.$class->id;
                $room = Room::where('code', $code)->first();
                if ($room && $room->schoolClasses()->where('id', '!=', $class->id)->exists()) {
                    throw new \RuntimeException('Kode ruangan '.$code.' sudah digunakan oleh kelas lain.');
                }
                $room ??= Room::where('name', $class->name)
                    ->whereDoesntHave('schoolClasses', fn ($query) => $query->where('id', '!=', $class->id))
                    ->orderBy('id')->first();
                $room ??= Room::create([
                    'code' => $code,
                    'name' => $class->name,
                    'capacity' => $class->capacity,
                    'is_active' => $class->is_active,
                ]);
                if ($room->name !== $class->name) {
                    $room->update(['name' => $class->name]);
                }
                if ($class->room_id !== $room->id) {
                    $class->update(['room_id' => $room->id]);
                }
            }
        }, 3);
    }
}
