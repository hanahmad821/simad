<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** @property Carbon $date */
class GtkDailyApproval extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date', 'reviewed_at' => 'datetime'];
    }
}
