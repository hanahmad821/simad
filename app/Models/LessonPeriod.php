<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LessonPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_number',
        'day_of_week',
        'sort_order',
        'name',
        'start_time',
        'end_time',
        'is_break',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'period_number' => 'integer',
            'day_of_week' => 'integer',
            'sort_order' => 'integer',
            'is_break' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}
