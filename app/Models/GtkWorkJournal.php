<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property array<int, int>|null $schedule_ids
 * @property Carbon $journal_date
 */
class GtkWorkJournal extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['journal_date' => 'date', 'schedule_ids' => 'array', 'completed_at' => 'datetime'];
    }

    public function isComplete(): bool
    {
        $fields = $this->kind === 'teaching' ? ['material', 'notes', 'reflection'] : ['activities'];

        return $this->status === 'completed' && collect($fields)->every(fn ($field) => trim((string) $this->$field) !== '');
    }
}
