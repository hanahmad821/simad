<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserRoleService
{
    /** @param array<string, mixed> $input */
    public function update(User $actor, User $target, array $input): void
    {
        abort_unless($actor->isAdmin(), 403);
        $data = Validator::make($input, [
            'role' => ['required', 'string', Rule::in(array_keys(User::ROLE_LABELS))],
            'secondary_role' => ['nullable', 'string', Rule::in(array_keys(User::ROLE_LABELS)), 'different:role'],
        ], ['secondary_role.different' => 'Role kedua harus berbeda dari role utama.'])->validate();
        $data['secondary_role'] = ($data['secondary_role'] ?? '') ?: null;

        DB::transaction(function () use ($actor, $target, $data) {
            $users = User::whereIn('id', [$actor->id, $target->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($users->get($actor->id)?->isAdmin(), 403);
            $current = $users->get($target->id);
            abort_unless($current !== null, 404);
            if ($actor->id === $current->id && ! in_array('admin', $data, true)) {
                throw ValidationException::withMessages(['role' => 'Role Admin pada akun yang sedang digunakan tidak dapat dihapus.']);
            }
            $current->update($data);
            if ($actor->id === $current->id) {
                session()->put('active_role', ['user_id' => $actor->id, 'role' => 'admin']);
                $actor->refresh();
            }
        });
    }
}
