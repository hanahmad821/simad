<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_year_id', 'historical_metadata_unknown',
        'homeroom_teacher_id',
        'room_id',
        'name',
        'grade',
        'major',
        'capacity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'academic_year_id' => 'integer', 'historical_metadata_unknown' => 'boolean',
            'grade' => 'integer',
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(Gtk::class, 'homeroom_teacher_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function studentClassHistories(): HasMany
    {
        return $this->hasMany(StudentClassHistory::class);
    }
}
