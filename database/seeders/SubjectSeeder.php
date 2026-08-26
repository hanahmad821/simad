<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subjects = [
            [
                'code' => 'AQD',
                'name' => 'Akidah Akhlak',
                'category' => 'PAI',
            ],
            [
                'code' => 'FIK',
                'name' => 'Fikih',
                'category' => 'PAI',
            ],
            [
                'code' => 'QH',
                'name' => 'Al-Qur\'an Hadis',
                'category' => 'PAI',
            ],
            [
                'code' => 'SKI',
                'name' => 'Sejarah Kebudayaan Islam',
                'category' => 'PAI',
            ],
            [
                'code' => 'BING',
                'name' => 'Bahasa Inggris',
                'category' => 'Umum',
            ],
            [
                'code' => 'BIN',
                'name' => 'Bahasa Indonesia',
                'category' => 'Umum',
            ],
            [
                'code' => 'MTK',
                'name' => 'Matematika',
                'category' => 'Umum',
            ],
            [
                'code' => 'IPA',
                'name' => 'Ilmu Pengetahuan Alam',
                'category' => 'Umum',
            ],
            [
                'code' => 'IPS',
                'name' => 'Ilmu Pengetahuan Sosial',
                'category' => 'Umum',
            ],
            [
                'code' => 'PJOK',
                'name' => 'Pendidikan Jasmani, Olahraga, dan Kesehatan',
                'category' => 'Umum',
            ],
        ];

        foreach ($subjects as $subject) {
            Subject::create([
                ...$subject,
                'is_active' => true,
            ]);
        }
    }
}
