<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncomingLetter extends Model
{
    use HasFactory;

    protected $fillable = [
        'nomor_surat',
        'pengirim',
        'tanggal_surat',
        'tanggal_masuk',
        'perihal',
        'file',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_surat' => 'date',
            'tanggal_masuk' => 'date',
        ];
    }
    public function dispositions(): HasMany
    {
        return $this->hasMany(
            LetterDisposition::class
        );
    }
}
