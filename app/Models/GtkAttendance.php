<?php

namespace App\Models;

use App\Services\GtkJournalService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** @property Carbon $attendance_date */
class GtkAttendance extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    protected static function booted(): void
    {
        // Approved absence records are immutable through manual/GPS attendance actions.
        $protectApprovedAbsence = function (self $attendance): void {
            if ($attendance->getRawOriginal('gtk_absence_request_id') !== null) {
                throw ValidationException::withMessages([
                    'attendance' => 'Presensi berasal dari pengajuan absensi yang disetujui dan tidak dapat diubah atau dihapus secara manual.',
                ]);
            }
        };
        static::updating($protectApprovedAbsence);
        static::deleting($protectApprovedAbsence);
        $recordChange = function (self $attendance): void {
            if (! Schema::hasTable('gtk_work_audits')) {
                return;
            }
            $service = app(GtkJournalService::class);
            if ($actor = auth()->user()) {
                $service->audit($actor, $attendance->gtk_id, $attendance->attendance_date,
                    $attendance->exists ? 'attendance_saved' : 'attendance_deleted',
                    $attendance->getRawOriginal() ?: null, $attendance->toArray());
            }
        };
        static::saved($recordChange);
        static::deleted($recordChange);
    }

    protected $fillable = [
        'gtk_id',
        'gtk_absence_request_id',
        'attendance_date',
        'check_in_at',
        'check_in_latitude',
        'check_in_longitude',
        'check_in_accuracy',
        'check_out_at',
        'check_out_latitude',
        'check_out_longitude',
        'check_out_accuracy',
        'scheduled_check_in_at',
        'scheduled_check_out_at',
        'forgotten_checkout_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',

            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'scheduled_check_in_at' => 'datetime',
            'scheduled_check_out_at' => 'datetime',
            'forgotten_checkout_at' => 'datetime',

            'check_in_latitude' => 'decimal:7',
            'check_in_longitude' => 'decimal:7',
            'check_in_accuracy' => 'decimal:2',

            'check_out_latitude' => 'decimal:7',
            'check_out_longitude' => 'decimal:7',
            'check_out_accuracy' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Gtk, $this> */
    public function gtk(): BelongsTo
    {
        return $this->belongsTo(Gtk::class);
    }
}
