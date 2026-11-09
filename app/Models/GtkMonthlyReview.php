<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GtkMonthlyReview extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['month' => 'date', 'reviewed_at' => 'datetime'];
    }
}
