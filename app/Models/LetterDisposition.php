<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetterDisposition extends Model
{
    use HasFactory;

    protected $fillable = [
        'incoming_letter_id',
        'from_user_id',
        'to_user_id',
        'instruction',
        'deadline',
        'status',
        'read_at',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'read_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function incomingLetter(): BelongsTo
    {
        return $this->belongsTo(
            IncomingLetter::class
        );
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'from_user_id'
        );
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'to_user_id'
        );
    }
}
