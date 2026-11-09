<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeachingAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'gtk_id',
        'subject_id',
        'school_class_id',
        'academic_year_id',
        'semester_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function gtk(): BelongsTo
    {
        return $this->belongsTo(Gtk::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester(): BelongsTo
    {
        // Legacy metadata only. KBM assignments are scoped by academic_year_id.
        return $this->belongsTo(Semester::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /** @return HasMany<Schedule, $this> */
    public function activeSchedules(): HasMany
    {
        return $this->schedules()->where('is_active', true)
            ->whereHas('lessonPeriod', fn ($query) => $query->where('is_active', true)
                ->where('is_break', false)->where('period_number', '>', 0)
                ->where(fn ($query) => $query->whereNull('day_of_week')->orWhereColumn('lesson_periods.day_of_week', 'schedules.day_of_week')));
    }

    /** @return Attribute<int, never> */
    protected function scheduledHours(): Attribute
    {
        return Attribute::get(fn ($value) => $value !== null ? (int) $value : $this->activeSchedules()->count());
    }
}
