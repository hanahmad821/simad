<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_name',
        'nsm',
        'npsn',
        'address',
        'phone',
        'email',
        'latitude',
        'longitude',
        'attendance_radius',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'attendance_radius' => 'integer',
        ];
    }
}
