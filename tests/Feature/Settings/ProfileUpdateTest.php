<?php

use App\Models\User;
use App\Models\Gtk;
use Livewire\Livewire;

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get(route('profile.edit'))->assertOk()
        ->assertDontSee('Delete account')
        ->assertDontSee('delete-user-button', false)
        ->assertDontSee('confirm-user-deletion', false);
});

test('users can update their own gtk profile and synchronize account details', function () {
    $gtk = Gtk::create([
        'full_name' => 'Nama GTK', 'gender' => 'female',
        'employment_status' => 'pns', 'nip' => '123', 'is_active' => true,
    ]);
    $other = Gtk::create(['full_name' => 'GTK Lain', 'nip' => '456']);
    $user = User::factory()->create(['gtk_id' => $gtk->id, 'role' => 'teacher']);
    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->assertSet('name', 'Nama GTK')
        ->assertSet('nip', '123')
        ->set('name', 'Nama Baru')
        ->set('email', 'gtk@example.com')
        ->set('phone', '08123456789')
        ->set('birth_place', 'Bandung')
        ->set('birth_date', '1990-01-02')
        ->set('address', 'Alamat baru')
        ->set('position', 'Guru')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($gtk->refresh()->full_name)->toBe('Nama Baru');
    expect($gtk->email)->toBe('gtk@example.com');
    expect($gtk->phone)->toBe('08123456789');
    expect($gtk->birth_date->format('Y-m-d'))->toBe('1990-01-02');
    expect($gtk->address)->toBe('Alamat baru');
    expect($gtk->is_active)->toBeTrue();
    expect($user->refresh()->name)->toBe('Nama Baru');
    expect($user->email)->toBe('gtk@example.com');
    expect($user->role)->toBe('teacher');
    expect($user->gtk_id)->toBe($gtk->id);
    expect($other->refresh()->full_name)->toBe('GTK Lain');
});

test('gtk profile rejects duplicate identifiers and invalid values without updating account', function () {
    $gtk = Gtk::create(['full_name' => 'Nama GTK', 'gender' => 'male', 'employment_status' => 'pns']);
    Gtk::create(['full_name' => 'GTK Lain', 'nip' => '456', 'nuptk' => '789']);
    $user = User::factory()->create(['gtk_id' => $gtk->id]);
    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('name', 'Tidak tersimpan')
        ->set('nip', '456')
        ->set('nuptk', '789')
        ->set('gender', 'invalid')
        ->set('employment_status', 'invalid')
        ->set('birth_date', 'invalid')
        ->call('updateProfileInformation')
        ->assertHasErrors(['nip', 'nuptk', 'gender', 'employment_status', 'birth_date']);

    expect($gtk->refresh()->full_name)->toBe('Nama GTK');
    expect($user->refresh()->name)->not->toBe('Tidak tersimpan');
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('users cannot delete their own account through the retired component', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.delete-user-modal')
        ->call('deleteUser');

    $response->assertForbidden();

    expect($user->fresh())->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});

test('admins also manage accounts outside the self deletion component', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.delete-user-modal')
        ->call('deleteUser');

    $response->assertForbidden();

    expect($user->fresh())->not->toBeNull();
});
