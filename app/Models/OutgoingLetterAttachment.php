<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutgoingLetterAttachment extends Model
{
    protected $fillable = ['original_name', 'path', 'mime_type', 'size', 'uploaded_by'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function outgoingLetter(): BelongsTo
    {
        return $this->belongsTo(OutgoingLetter::class);
    }

    public function formattedSize(): string
    {
        return $this->size >= 1048576
            ? number_format($this->size / 1048576, 1, ',', '.').' MB'
            : number_format(max(1, (int) ceil($this->size / 1024)), 0, ',', '.').' KB';
    }
}
