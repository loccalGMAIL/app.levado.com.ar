<?php

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

test('reset password link can be requested', function () {
    Mail::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Mail::assertQueued(PasswordResetMail::class, fn ($mail) => $mail->hasTo($user->email));
});

test('reset password screen can be rendered', function () {
    Mail::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Mail::assertQueued(PasswordResetMail::class, function ($mail) {
        $token = basename(parse_url($mail->resetUrl, PHP_URL_PATH));

        $response = $this->get('/reset-password/'.$token);

        $response->assertStatus(200);

        return true;
    });
});

test('password can be reset with valid token', function () {
    Mail::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Mail::assertQueued(PasswordResetMail::class, function ($mail) use ($user) {
        $token = basename(parse_url($mail->resetUrl, PHP_URL_PATH));

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});
