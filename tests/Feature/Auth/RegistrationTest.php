<?php

test('public registration screen is unavailable', function () {
    $this->get('/register')->assertNotFound();
});

test('users cannot register publicly', function () {
    $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
});
