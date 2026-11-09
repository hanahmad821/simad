<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KbmDayPattern extends Model
{
    protected $fillable = ['day_of_week', 'morning_start', 'morning_end', 'teaching_periods', 'breaks'];

    protected function casts(): array
    {
        return ['day_of_week' => 'integer', 'teaching_periods' => 'integer', 'breaks' => 'array'];
    }
}
