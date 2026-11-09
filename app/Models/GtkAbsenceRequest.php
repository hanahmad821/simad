<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GtkAbsenceRequest extends Model
{
    public const TYPES = [
        'S' => 'Sakit',
        'C' => 'Cuti',
        'I' => 'Izin',
        'TB' => 'Tugas Belajar',
        'TK' => 'Tidak Ada Keterangan / Alpa',
        'DL' => 'Dinas Luar',
        'L' => 'Lainnya',
    ];

    public const STATUSES = ['pending' => 'Diajukan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'reviewed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function gtk(): BelongsTo
    {
        return $this->belongsTo(Gtk::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function attendanceStatus(): string
    {
        return match ($this->type) {
            'S' => 'sick',
            'TK' => 'absent',
            default => 'leave',
        };
    }
}
