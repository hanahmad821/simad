<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SwitchRoleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', 'string', Rule::in(array_keys(User::ROLE_LABELS))]]);
        $user = $request->user();
        abort_unless($user->hasRole($data['role']), 403);
        $request->session()->put('active_role', ['user_id' => $user->id, 'role' => $data['role']]);

        return redirect()->route($data['role'].'.dashboard', [], 303);
    }
}
