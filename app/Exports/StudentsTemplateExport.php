<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StudentsTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'nis',
            'nisn',
            'nik',
            'full_name',
            'gender',
            'birth_place',
            'birth_date',
            'father_name',
            'mother_name',
            'guardian_name',
            'guardian_phone',
            'address',
            'pip_status',
            'pip_number',
            'kip_status',
            'kip_number',
            'is_active',
        ];
    }

    public function array(): array
    {
        return [
            [
                '12345',
                '0012345678',
                '3302xxxxxxxxxxxx',
                'Nama Siswa',
                'male',
                'Purwokerto',
                '2010-01-15',
                'Nama Ayah',
                'Nama Ibu',
                '',
                '',
                'Alamat siswa',
                '0',
                '',
                '0',
                '',
                '1',
            ],
        ];
    }
}
