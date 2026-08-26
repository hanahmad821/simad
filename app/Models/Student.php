<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_code',
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

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'pip_status' => 'boolean',
            'kip_status' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
    public function classHistories(): HasMany
    {
        return $this->hasMany(StudentClassHistory::class);
    }
    public function attendances(): HasMany
    {
        return $this->hasMany(StudentAttendance::class);
    }
}
