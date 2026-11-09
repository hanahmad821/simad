<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class LetterType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'template_file',
        'template_view',
        'fields',
        'is_active',
        'body_html',
        'template_version',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'is_active' => 'boolean',
            'template_version' => 'integer',
        ];
    }
    public function outgoingLetters(): HasMany
    {
        return $this->hasMany(OutgoingLetter::class);
    }
}
