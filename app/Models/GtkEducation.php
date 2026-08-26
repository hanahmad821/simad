<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GtkEducation extends Model
{
    use HasFactory;

    protected $table = 'gtk_educations';
    protected $fillable = [
        'gtk_id',
        'level',
        'institution_name',
        'major',
        'start_year',
        'graduation_year',
        'degree',
    ];

    protected function casts(): array
    {
        return [
            'start_year' => 'integer',
            'graduation_year' => 'integer',
        ];
    }

    public function gtk(): BelongsTo
    {
        return $this->belongsTo(Gtk::class);
    }
}
