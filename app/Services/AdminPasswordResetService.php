<?php

namespace App\Services;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminPasswordResetService
{
    use PasswordValidationRules;

    public function reset(User $actor, User $target, array $input): void
    {
        abort_unless($actor->isAdmin() && $actor->id !== $target->id, 403);
        $data = Validator::make($input, [
            'newPassword' => [...$this->passwordRules(), 'max:255'],
        ], [
            'newPassword.required' => 'Isi password baru.',
            'newPassword.confirmed' => 'Konfirmasi password tidak sama.',
        ], ['newPassword' => 'password baru'])->validate();

        DB::transaction(function () use ($target, $data) {
            $user = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $user->forceFill([
                'password' => $data['newPassword'],
                'remember_token' => Str::random(60),
            ])->save();

            $broker = config('auth.defaults.passwords');
            $tokenTable = config('auth.passwords.'.$broker.'.table', 'password_reset_tokens');
            DB::table($tokenTable)->where('email', $user->email)->delete();

            if (config('session.driver') === 'database') {
                $connection = config('session.connection');
                $table = config('session.table', 'sessions');
                if (Schema::connection($connection)->hasTable($table)) {
                    DB::connection($connection)->table($table)->where('user_id', $user->id)->delete();
                }
            }
        });

        event(new PasswordReset($target->fresh()));
        Log::info('Password akun direset oleh admin.', ['actor_id' => $actor->id, 'user_id' => $target->id]);
    }
}
