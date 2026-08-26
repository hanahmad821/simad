<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gtk extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'nip',
        'nuptk',
        'gender',
        'birth_place',
        'birth_date',
        'employment_status',
        'employee_number',
        'position',
        'phone',
        'email',
        'address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
