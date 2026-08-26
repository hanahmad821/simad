<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'grade',
        'major',
        'capacity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'grade' => 'integer',
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
