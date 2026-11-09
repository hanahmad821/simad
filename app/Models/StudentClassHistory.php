<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentClassHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'school_class_id',
        'academic_year_id',
        'semester_id', 'starts_on', 'ends_on', 'voided_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean', 'starts_on' => 'date', 'ends_on' => 'date', 'voided_at' => 'datetime',
        ];
    }

    public function scopeOnDate($query, $date)
    {
        return $query->whereNull('voided_at')->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
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
        // Legacy metadata only. Class membership is scoped by academic_year_id.
        return $this->belongsTo(Semester::class);
    }
}
