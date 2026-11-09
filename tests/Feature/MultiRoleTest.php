<?php

use App\Models\Gtk;
use App\Models\User;
use App\Services\UserRoleService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->gtk = Gtk::create(['full_name' => 'Guru TIK', 'position' => 'Guru Mata Pelajaran']);
    $this->target = User::factory()->create(['name' => 'Guru TIK', 'role' => 'teacher', 'gtk_id' => $this->gtk->id]);
    $this->actingAs($this->admin);
});

dataset('assigned role pairs', function () {
    $pairs = [];
    foreach (array_keys(User::ROLE_LABELS) as $primary) {
        foreach (array_keys(User::ROLE_LABELS) as $secondary) {
            if ($primary !== $secondary) {
                $pairs[$primary.' + '.$secondary] = [$primary, $secondary];
            }
        }
    }

    return $pairs;
});

it('adds an optional second role without changing existing identities or credentials', function () {
    $before = $this->target->getAttributes();
    $migration = require database_path('migrations/2026_11_05_000001_add_secondary_role_to_users.php');
    $migration->down();
    expect(Schema::hasColumn('users', 'secondary_role'))->toBeFalse();
    $migration->up();
    $target = $this->target->fresh();
    expect($target->secondary_role)->toBeNull();
    foreach (['id', 'name', 'email', 'password', 'role', 'gtk_id'] as $field) {
        expect($target->getRawOriginal($field))->toBe($before[$field]);
    }
});

it('switches between either assigned role while retaining the same login and GTK', function (string $primary, string $secondary) {
    $this->target->update(['role' => $primary, 'secondary_role' => $secondary]);
    $this->actingAs($this->target)->get(route('dashboard'))->assertRedirect(route($primary.'.dashboard'));
    expect($this->target->roleNames())->toBe([$primary, $secondary]);
    $this->get(route($primary.'.dashboard'))->assertOk();
    $this->get(route($secondary.'.dashboard'))->assertForbidden();
    $this->post(route('role.switch'), ['role' => $secondary])->assertRedirect(route($secondary.'.dashboard'))
        ->assertStatus(303)->assertSessionHas('active_role', ['user_id' => $this->target->id, 'role' => $secondary]);
    $this->assertAuthenticatedAs($this->target);
    expect($this->target->activeRole())->toBe($secondary)
        ->and($this->target->hasRole($primary))->toBeTrue()
        ->and($this->target->hasActiveRole($primary))->toBeFalse()
        ->and($this->target->fresh()->gtk_id)->toBe($this->gtk->id)
        ->and($this->target->fresh()->role)->toBe($primary);
    $this->get(route('dashboard'))->assertRedirect(route($secondary.'.dashboard'));
    $this->get(route($secondary.'.dashboard'))->assertOk()->assertViewHas('role', $secondary);
    $this->get(route($primary.'.dashboard'))->assertForbidden();
    $this->post(route('role.switch'), ['role' => $primary])->assertRedirect(route($primary.'.dashboard'));
    $this->get(route($primary.'.dashboard'))->assertOk();
})->with('assigned role pairs');

it('shows role choices in both user menus and changes sidebar links with the active role', function () {
    $this->target->update(['secondary_role' => 'admin']);
    $this->actingAs($this->target);
    $page = $this->get(route('teacher.dashboard'))->assertOk()->assertSee('Gunakan sebagai Guru')
        ->assertSee('Gunakan sebagai Admin')->assertSee(route('teacher.schedules.index'), false)
        ->assertDontSee(route('admin.classes.index'), false);
    expect(substr_count($page->getContent(), 'action="'.route('role.switch').'"'))->toBe(4);
    $this->post(route('role.switch'), ['role' => 'admin'])->assertRedirect();
    $this->get(route('admin.dashboard'))->assertOk()->assertSee(route('admin.classes.index'), false)
        ->assertDontSee(route('teacher.schedules.index'), false)->assertSee('Gunakan sebagai Admin (aktif)');
    $this->get(route('gtk.journals.index'))->assertForbidden();
    $this->post(route('role.switch'), ['role' => 'teacher'])->assertRedirect();
    $this->get(route('gtk.journals.index'))->assertOk();
    $this->get(route('admin.gtks.index'))->assertForbidden();
    expect($this->target->isAdmin())->toBeFalse()->and($this->target->canTeach())->toBeTrue();
});

it('keeps a single-role login working without showing the switcher', function () {
    $this->actingAs($this->target)->get(route('teacher.dashboard'))->assertOk()->assertDontSee('Gunakan akun sebagai');
    $this->post(route('role.switch'), ['role' => 'admin'])->assertForbidden();
    expect($this->target->activeRole())->toBe('teacher');
});

it('rejects unassigned roles and ignores another user or a forged active role', function () {
    $this->actingAs($this->target);
    foreach (['admin', 'staff', 'headmaster'] as $role) {
        $this->post(route('role.switch'), ['role' => $role, 'user_id' => $this->admin->id])->assertForbidden();
    }
    $this->withSession(['active_role' => ['user_id' => $this->target->id, 'role' => 'admin']])
        ->get(route('admin.dashboard'))->assertForbidden();
    expect($this->target->activeRole())->toBe('teacher');
    $this->target->update(['secondary_role' => 'admin']);
    $this->withSession(['active_role' => ['user_id' => $this->admin->id, 'role' => 'admin']])
        ->get(route('admin.dashboard'))->assertForbidden();
    expect($this->target->activeRole())->toBe('teacher');
});

it('drops access when an active secondary role has been revoked', function () {
    $this->target->update(['secondary_role' => 'admin']);
    $this->actingAs($this->target)->post(route('role.switch'), ['role' => 'admin'])->assertRedirect();
    $this->target->update(['secondary_role' => null]);
    $this->get(route('admin.dashboard'))->assertForbidden();
    $this->get(route('dashboard'))->assertRedirect(route('teacher.dashboard'));
});

it('validates role switching and requires an authenticated user', function () {
    $this->post(route('role.switch'), ['role' => 'unknown'])->assertSessionHasErrors('role');
    $this->post(route('role.switch'), [])->assertSessionHasErrors('role');
    $this->post(route('role.switch'), ['role' => ['admin', 'teacher']])->assertSessionHasErrors('role');
    auth()->logout();
    $this->post(route('role.switch'), ['role' => 'admin'])->assertRedirect(route('login'));
});

it('lets an admin configure and remove a second role through GTK account actions', function () {
    $password = $this->target->password;
    $page = Livewire::test('admin.gtks.index')->assertSee('Atur Role')
        ->call('openRoles', $this->gtk->id)->assertSet('accountRole', 'teacher')->assertSet('accountSecondaryRole', '')
        ->set('accountSecondaryRole', 'admin')->call('saveRoles')->assertHasNoErrors()->assertSet('showRoleForm', false)
        ->assertSet('roleUserId', null)->assertSet('accountRole', '')->assertSee('Role akun berhasil diperbarui.');
    expect($this->target->fresh()->roleNames())->toBe(['teacher', 'admin'])
        ->and($this->target->fresh()->password)->toBe($password)
        ->and($this->target->fresh()->gtk_id)->toBe($this->gtk->id)
        ->and($this->gtk->fresh()->position)->toBe('Guru Mata Pelajaran');
    $page->call('openRoles', $this->gtk->id)->set('accountSecondaryRole', '')->call('saveRoles')->assertHasNoErrors();
    expect($this->target->fresh()->secondary_role)->toBeNull();
});

it('rejects duplicate or invalid account roles without granting access', function (string $primary, string $secondary, string $field) {
    expect(fn () => app(UserRoleService::class)->update($this->admin, $this->target,
        ['role' => $primary, 'secondary_role' => $secondary]))->toThrow(ValidationException::class);
    Livewire::test('admin.gtks.index')->call('openRoles', $this->gtk->id)
        ->set('accountRole', $primary)->set('accountSecondaryRole', $secondary)->call('saveRoles')
        ->assertHasErrors($field)->assertSet('showRoleForm', true);
    expect($this->target->fresh()->role)->toBe('teacher')
        ->and($this->target->fresh()->secondary_role)->toBeNull();
})->with([
    ['teacher', 'teacher', 'accountSecondaryRole'],
    ['teacher', 'unknown', 'accountSecondaryRole'],
    ['unknown', 'admin', 'accountRole'],
    ['', 'admin', 'accountRole'],
]);

it('protects account selection and clears canceled role forms', function () {
    $page = Livewire::test('admin.gtks.index')->call('openRoles', $this->gtk->id)
        ->set('accountSecondaryRole', 'admin')->call('closeRoles')->assertSet('roleUserId', null)
        ->assertSet('showRoleForm', false)->assertSet('accountSecondaryRole', '');
    expect($this->target->fresh()->secondary_role)->toBeNull();
    $page->call('saveRoles')->assertForbidden();
    Livewire::test('admin.gtks.index')->call('openRoles', $this->gtk->id)->set('roleUserId', $this->admin->id);
})->throws(CannotUpdateLockedPropertyException::class);

it('prevents self removal of admin while allowing the admin role to move to the second slot', function () {
    expect(fn () => app(UserRoleService::class)->update($this->admin, $this->admin,
        ['role' => 'teacher', 'secondary_role' => null]))->toThrow(ValidationException::class);
    app(UserRoleService::class)->update($this->admin, $this->admin, ['role' => 'teacher', 'secondary_role' => 'admin']);
    expect($this->admin->fresh()->roleNames())->toBe(['teacher', 'admin'])
        ->and($this->admin->activeRole())->toBe('admin');
});

it('clears the selected role on logout so the next login starts with the primary role', function () {
    $this->target->update(['secondary_role' => 'admin']);
    $this->actingAs($this->target)->post(route('role.switch'), ['role' => 'admin'])->assertRedirect();
    $this->post(route('logout'))->assertRedirect(route('home'));
    $this->post(route('login.store'), ['email' => $this->target->email, 'password' => 'password'])
        ->assertSessionHasNoErrors()->assertRedirect();
    $this->get(route('dashboard'))->assertRedirect(route('teacher.dashboard'));
    $this->get(route('admin.dashboard'))->assertForbidden();
});

it('requires the active admin role to edit account permissions', function () {
    $this->target->update(['secondary_role' => 'admin']);
    $this->actingAs($this->target);
    expect(fn () => app(UserRoleService::class)->update($this->target, $this->admin,
        ['role' => 'teacher']))->toThrow(HttpException::class);
    Livewire::test('admin.gtks.index')->assertForbidden();
    $this->post(route('role.switch'), ['role' => 'admin'])->assertRedirect();
    Livewire::test('admin.gtks.index')->call('openRoles', $this->gtk->id)->assertSet('showRoleForm', true);
});

it('finds both assigned roles without leaking users outside another query condition', function () {
    $this->target->update(['role' => 'admin', 'secondary_role' => 'teacher']);
    $other = User::factory()->create(['role' => 'admin', 'secondary_role' => 'teacher']);
    expect(User::where('gtk_id', $this->gtk->id)->withRoles(['teacher'])->pluck('id')->all())->toBe([$this->target->id])
        ->and(User::withRoles(['teacher'])->whereKey($other->id)->exists())->toBeTrue()
        ->and(User::whereKey($this->admin->id)->withRoles(['teacher'])->exists())->toBeFalse();
});
