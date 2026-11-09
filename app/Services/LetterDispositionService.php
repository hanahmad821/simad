<?php

namespace App\Services;

use App\Models\IncomingLetter;
use App\Models\LetterDisposition;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LetterDispositionService
{
    /** @return Builder<User> */
    public function recipients(): Builder
    {
        return User::query()->withRoles(['teacher', 'staff'])
            ->whereHas('gtk', fn (Builder $query) => $query->where('is_active', true));
    }

    /** @param array<string, mixed> $input */
    public function send(IncomingLetter $letter, User $sender, array $input): LetterDisposition
    {
        abort_unless($sender->isHeadmaster(), 403);
        $data = Validator::make($input, [
            'toUserId' => ['required', 'integer'],
            'instruction' => ['required', 'string', 'max:5000'],
            'deadline' => ['nullable', 'date', 'after_or_equal:today'],
        ], [
            'toUserId.required' => 'Pilih penerima disposisi.',
            'instruction.required' => 'Instruksi disposisi wajib diisi.',
            'deadline.after_or_equal' => 'Batas waktu tidak boleh sebelum hari ini.',
        ])->validate();
        $recipient = $this->recipients()->whereKey($data['toUserId'])->first();
        if (! $recipient) {
            throw ValidationException::withMessages(['toUserId' => 'Penerima harus guru atau staf dengan data GTK aktif.']);
        }
        $instruction = trim($data['instruction']);
        if ($instruction === '') {
            throw ValidationException::withMessages(['instruction' => 'Instruksi disposisi wajib diisi.']);
        }

        return $letter->dispositions()->create([
            'from_user_id' => $sender->id,
            'to_user_id' => $recipient->id,
            'instruction' => $instruction,
            'deadline' => ($data['deadline'] ?? null) ?: null,
            'status' => 'pending',
        ]);
    }

    public function markRead(LetterDisposition $disposition, User $recipient): void
    {
        $this->authorizeRecipient($disposition, $recipient);
        $disposition->newQuery()->whereKey($disposition->id)->where('to_user_id', $recipient->id)
            ->where('status', 'pending')->update(['status' => 'read', 'read_at' => now()]);
        $disposition->refresh();
    }

    public function start(LetterDisposition $disposition, User $recipient): void
    {
        $this->authorizeRecipient($disposition, $recipient);
        $changed = $disposition->newQuery()->whereKey($disposition->id)->where('to_user_id', $recipient->id)
            ->where('status', 'read')->update(['status' => 'in_progress']);
        if (! $changed) {
            throw ValidationException::withMessages(['status' => 'Hanya disposisi yang sudah dibaca dapat mulai diproses.']);
        }
        $disposition->refresh();
    }

    public function complete(LetterDisposition $disposition, User $recipient, string $notes): void
    {
        $this->authorizeRecipient($disposition, $recipient);
        Validator::make(['notes' => $notes], ['notes' => ['nullable', 'string', 'max:5000']])->validate();
        $changed = $disposition->newQuery()->whereKey($disposition->id)->where('to_user_id', $recipient->id)
            ->where('status', 'in_progress')->update([
                'status' => 'completed', 'completed_at' => now(), 'notes' => trim($notes) ?: null,
            ]);
        if (! $changed) {
            throw ValidationException::withMessages(['status' => 'Disposisi harus sedang diproses sebelum diselesaikan.']);
        }
        $disposition->refresh();
    }

    private function authorizeRecipient(LetterDisposition $disposition, User $recipient): void
    {
        $disposition->refresh();
        abort_unless($disposition->to_user_id === $recipient->id, 403);
    }
}
