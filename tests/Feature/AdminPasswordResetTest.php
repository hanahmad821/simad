<?php

use App\Models\Gtk;
use App\Models\User;
use App\Services\AdminPasswordResetService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->gtk = Gtk::create(['full_name' => 'GTK Pemilik Akun']);
    $this->target = User::factory()->create(['name' => 'GTK Pemilik Akun', 'role' => 'teacher', 'gtk_id' => $this->gtk->id, 'password' => 'Password-Lama-123!', 'remember_token' => 'remember-lama']);
    $this->actingAs($this->admin);
    $this->input = ['newPassword' => 'Password-Baru-123!', 'newPassword_confirmation' => 'Password-Baru-123!'];
});

it('lets an admin reset an existing GTK account and clears the form', function (string $role) {
    $this->target->update(['role' => $role]);
    $component = Livewire::test('admin.gtks.index')->assertSee('Reset Password')
        ->call('openPasswordReset', $this->gtk->id)->assertSet('passwordResetUserId', $this->target->id)->assertSee($this->target->email)
        ->set('newPassword', $this->input['newPassword'])->set('newPassword_confirmation', $this->input['newPassword_confirmation'])
        ->call('resetUserPassword')->assertHasNoErrors()->assertSet('showPasswordReset', false)->assertSet('passwordResetUserId', null)
        ->assertSet('newPassword', '')->assertSet('newPassword_confirmation', '');
    $target = $this->target->fresh();
    expect(Hash::check($this->input['newPassword'], $target->password))->toBeTrue()
        ->and(Hash::check('Password-Lama-123!', $target->password))->toBeFalse()
        ->and($target->role)->toBe($role)->and($target->email)->toBe($this->target->email)
        ->and($target->remember_token)->not->toBe('remember-lama');
    $component->assertDontSee($this->input['newPassword']);
})->with(['teacher', 'staff', 'headmaster', 'admin']);

it('validates password strength and confirmation without changing the stored password', function (string $password, string $confirmation) {
    $before = $this->target->password;
    Livewire::test('admin.gtks.index')->call('openPasswordReset', $this->gtk->id)
        ->set('newPassword', $password)->set('newPassword_confirmation', $confirmation)
        ->call('resetUserPassword')->assertHasErrors('newPassword')->assertSet('showPasswordReset', true);
    expect($this->target->fresh()->password)->toBe($before);
})->with([['short', 'short'], ['Password-Baru-123!', 'Tidak-sama'], ['', '']]);

it('discards password values when the admin cancels', function () {
    $before = $this->target->password;
    Livewire::test('admin.gtks.index')->call('openPasswordReset', $this->gtk->id)
        ->set('newPassword', 'Password-Baru-123!')->set('newPassword_confirmation', 'Password-Baru-123!')
        ->call('closePasswordReset')->assertSet('showPasswordReset', false)->assertSet('newPassword', '')
        ->assertSet('newPassword_confirmation', '')->assertSet('passwordResetUserId', null);
    expect($this->target->fresh()->password)->toBe($before);
});

it('rejects non-admin access to both the route and reset service', function (string $role) {
    $user = User::factory()->create(['role' => $role]);
    $before = $this->target->password;
    $this->actingAs($user)->get(route('admin.gtks.index'))->assertForbidden();
    expect(fn () => app(AdminPasswordResetService::class)->reset($user, $this->target, $this->input))->toThrow(HttpException::class);
    expect($this->target->fresh()->password)->toBe($before);
})->with(['teacher', 'staff', 'headmaster']);

it('requires selection of an existing account and rejects resetting the admins own account', function () {
    Livewire::test('admin.gtks.index')->call('resetUserPassword')->assertForbidden();
    $noAccount = Gtk::create(['full_name' => 'Belum Ada Akun']);
    Livewire::test('admin.gtks.index')->call('openPasswordReset', $noAccount->id)->assertForbidden();
    $ownGtk = Gtk::create(['full_name' => 'Admin Sendiri']);
    $this->admin->update(['gtk_id' => $ownGtk->id]);
    Livewire::test('admin.gtks.index')->call('openPasswordReset', $ownGtk->id)->assertForbidden();
});

it('prevents changing the target account from the browser', function () {
    Livewire::test('admin.gtks.index')->call('openPasswordReset', $this->gtk->id)
        ->set('passwordResetUserId', $this->admin->id);
})->throws(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

it('invalidates old reset tokens and database sessions only for the target user', function () {
    config(['session.driver' => 'database']);
    foreach ([$this->admin, $this->target] as $user) {
        DB::table('sessions')->insert(['id' => 'session-'.$user->id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp]);
        DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => Str::random(60), 'created_at' => now()]);
    }
    app(AdminPasswordResetService::class)->reset($this->admin, $this->target, $this->input);
    expect(DB::table('sessions')->where('user_id', $this->target->id)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $this->admin->id)->exists())->toBeTrue()
        ->and(DB::table('password_reset_tokens')->where('email', $this->target->email)->exists())->toBeFalse()
        ->and(DB::table('password_reset_tokens')->where('email', $this->admin->email)->exists())->toBeTrue();
});
