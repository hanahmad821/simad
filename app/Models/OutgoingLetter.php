<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutgoingLetter extends Model
{
    use HasFactory;

    protected $fillable = [
        'letter_type_id',
        'nomor_urut',
        'nomor_tahun',
        'nomor_surat',
        'jenis_surat',
        'use_template',
        'tujuan',
        'tanggal_surat',
        'perihal',
        'isi',
        'data',
        'file',
        'keterangan',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'verification_token',
    ];

    protected function casts(): array
    {
        return [
            'use_template' => 'boolean',
            'tanggal_surat' => 'date',
            'nomor_urut' => 'integer',
            'nomor_tahun' => 'integer',
            'data' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }
    public function letterType(): BelongsTo
    {
        return $this->belongsTo(LetterType::class);
    }
    public function approver(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approved_by'
        );
    }

}
