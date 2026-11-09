<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $template_snapshot
 */
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
        'signature_method',
        'signed_file',
        'signed_uploaded_at',
        'signed_uploaded_by',
        'tujuan',
        'tanggal_surat',
        'perihal',
        'isi',
        'isi_format',
        'data',
        'template_snapshot',
        'file',
        'final_file',
        'final_docx_file',
        'final_pdf_file',
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
            'template_snapshot' => 'array',
            'approved_at' => 'datetime',
            'signed_uploaded_at' => 'datetime',
        ];
    }

    public function attachments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OutgoingLetterAttachment::class);
    }

    public function attachmentDescription(): string
    {
        $description = trim((string) data_get($this->data, 'lampiran', ''));
        if ($description !== '') return $description;
        $count = $this->exists ? $this->attachments()->count() : 0;

        return $count > 0 ? $count.' berkas' : '-';
    }

    public function usesWetSignature(): bool
    {
        return $this->signature_method === 'wet';
    }

    public function signatureLabel(): string
    {
        return $this->usesWetSignature() ? 'Tanda tangan basah' : 'QR Code';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'pending' => 'Menunggu Persetujuan',
            'awaiting_signature' => 'Disetujui — menunggu tanda tangan basah',
            'issued' => $this->usesWetSignature() ? 'Selesai' : 'Terbit',
            'cancelled' => 'Dibatalkan',
            default => ucfirst($this->status),
        };
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    /** @return BelongsTo<LetterType, $this> */
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
