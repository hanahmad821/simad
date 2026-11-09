<?php

use App\Models\User;

test('confirm password screen can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('password.confirm'));

    $response->assertOk()
        ->assertSee('confirm-password-button', false)
        ->assertDontSee('Confirm with passkey');
});

test('security settings can be opened after confirming password without passkeys', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertRedirect(route('password.confirm'));

    $this->get(route('password.confirm'))
        ->assertOk()
        ->assertDontSee('Confirm with passkey');

    $this->post(route('password.confirm.store'), ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    $this->get(route('security.edit'))
        ->assertOk()
        ->assertSee('Update password')
        ->assertDontSee('Two-factor authentication')
        ->assertDontSee('Enable 2FA')
        ->assertDontSee('Manage your passkeys for passwordless sign-in');
});
