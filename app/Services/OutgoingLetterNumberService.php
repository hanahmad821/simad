<?php

namespace App\Services;

use App\Models\LetterSequence;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OutgoingLetterNumberService
{
    public function generate(string $code, Carbon $date): array
    {
        $year = $date->year;

        $number = DB::transaction(function () use ($year) {
            LetterSequence::firstOrCreate(
                ['year' => $year],
                ['last_number' => 0]
            );

            $sequence = LetterSequence::query()
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->increment('last_number');

            return $sequence->last_number;
        }, attempts: 5);

        $romanMonth = $this->romanMonth($date->month);

        $nomorUrut = str_pad($number, 3, '0', STR_PAD_LEFT);

        $nomorSurat = "{$code}.{$nomorUrut}/LPM/33-23/MA-03/{$romanMonth}/{$year}";

        return [
            'nomor_urut' => $number,
            'nomor_tahun' => $year,
            'nomor_surat' => $nomorSurat,
        ];
    }

    private function romanMonth(int $month): string
    {
        return [
            1 => 'I',
            2 => 'II',
            3 => 'III',
            4 => 'IV',
            5 => 'V',
            6 => 'VI',
            7 => 'VII',
            8 => 'VIII',
            9 => 'IX',
            10 => 'X',
            11 => 'XI',
            12 => 'XII',
        ][$month];
    }
}
