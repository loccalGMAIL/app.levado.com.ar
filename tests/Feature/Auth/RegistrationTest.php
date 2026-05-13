<?php

test('registration screen is not publicly accessible', function () {
    $this->get('/register')->assertStatus(404);
});

test('registration via post is not publicly accessible', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertStatus(404);

    $this->assertGuest();
});
