<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_arabic_message_when_email_does_not_exist(): void
    {
        $this->postJson('/api/login', [
            'email' => 'missing@example.com',
            'password' => 'password',
        ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'البريد الإلكتروني غير موجود أو غير مسجل.',
            ]);
    }
}
