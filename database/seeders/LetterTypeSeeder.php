<?php

namespace Database\Seeders;

use App\Models\LetterType;
use Illuminate\Database\Seeder;

class LetterTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Surat Keterangan Siswa',
                'code' => 'A',
                'fields' => [
                    [
                        'name' => 'student_id',
                        'label' => 'Siswa',
                        'type' => 'student',
                        'required' => true,
                    ],
                    [
                        'name' => 'tahun_pelajaran',
                        'label' => 'Tahun Pelajaran',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'name' => 'keperluan',
                        'label' => 'Keperluan',
                        'type' => 'textarea',
                        'required' => true,
                    ],
                ],
            ],

            [
                'name' => 'Surat Pemberitahuan',
                'code' => 'E',
                'fields' => [
                    [
                        'name' => 'penerima',
                        'label' => 'Penerima',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'name' => 'poin',
                        'label' => 'Isi Pemberitahuan',
                        'type' => 'repeater',
                        'required' => true,
                    ],
                ],
            ],

            [
                'name' => 'Surat Dispensasi',
                'code' => 'C',
                'fields' => [
                    [
                        'name' => 'nama',
                        'label' => 'Nama yang Diberi Dispensasi',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'name' => 'hari_tanggal',
                        'label' => 'Hari/Tanggal',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'name' => 'waktu',
                        'label' => 'Waktu',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'name' => 'tempat',
                        'label' => 'Tempat',
                        'type' => 'text',
                        'required' => true,
                    ],
                    [
                        'name' => 'acara',
                        'label' => 'Acara',
                        'type' => 'text',
                        'required' => true,
                    ],
                ],
            ],

            [
                'name' => 'Surat Tugas',
                'code' => 'E',
                'fields' => [],
            ],

            [
                'name' => 'Surat Perjanjian',
                'code' => 'K',
                'fields' => [],
            ],

            [
                'name' => 'Surat Undangan',
                'code' => 'A',
                'fields' => [],
            ],
        ];

        foreach ($types as $type) {
            $templateView = match ($type['name']) {
                'Surat Keterangan Siswa' => 'pdf.letters.siswa-aktif',
                'Surat Dispensasi' => 'pdf.letters.dispensasi',
                'Surat Pemberitahuan' => 'pdf.letters.pemberitahuan',
                'Surat Undangan' => 'pdf.letters.undangan',
                'Surat Tugas' => 'pdf.letters.tugas',
                default => null,
            };
            LetterType::firstOrCreate(
                ['name' => $type['name']],
                [
                    'code' => $type['code'],
                    'fields' => $templateView ? config('letter_templates')[$templateView]['fields'] : $type['fields'],
                    'template_view' => $templateView,
                    'is_active' => true,
                ]
            );
        }
    }
}
