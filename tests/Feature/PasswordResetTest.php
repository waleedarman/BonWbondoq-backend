<?php

namespace Tests\Feature;

use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_creates_a_reset_code_for_existing_email(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'employee@example.com',
        ]);

        $this->postJson('/api/forgot-password', [
            'email' => 'employee@example.com',
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'تم إرسال رمز التحقق إلى بريدك الإلكتروني.',
            ]);

        $this->assertDatabaseHas('password_reset_codes', [
            'user_id' => $user->id,
            'email' => 'employee@example.com',
            'used_at' => null,
        ]);
    }

    public function test_forgot_password_returns_arabic_error_when_email_does_not_exist(): void
    {
        Mail::fake();

        $this->postJson('/api/forgot-password', [
            'email' => 'missing@example.com',
        ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'البريد الإلكتروني غير موجود أو غير مسجل.',
            ]);

        $this->assertDatabaseCount('password_reset_codes', 0);
    }

    public function test_reset_password_updates_password_when_code_is_valid(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => Hash::make('old-password'),
        ]);

        PasswordResetCode::create([
            'user_id' => $user->id,
            'email' => 'employee@example.com',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
            'verified_at' => now(),
        ]);

        $this->postJson('/api/reset-password', [
            'email' => 'employee@example.com',
            'code' => '123456',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertNotNull(PasswordResetCode::first()->used_at);
    }

    public function test_verify_reset_code_marks_code_as_verified(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@example.com',
        ]);

        PasswordResetCode::create([
            'user_id' => $user->id,
            'email' => 'employee@example.com',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/verify-reset-code', [
            'email' => 'employee@example.com',
            'code' => '123456',
        ])->assertOk();

        $this->assertNotNull(PasswordResetCode::first()->verified_at);
    }
}
