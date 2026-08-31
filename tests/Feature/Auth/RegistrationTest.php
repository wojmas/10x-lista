<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The PRD forbids public sign-up (§Access Control): only the owner creates
 * accounts, via the "app:user:create" command. Breeze's registration code is
 * still in the repo behind the "app.registration_enabled" flag, so these tests
 * guard the default: the routes must not exist unless someone deliberately
 * turns the flag on.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_not_available_by_default(): void
    {
        $this->assertFalse(config('app.registration_enabled'));

        $this->get('/register')->assertNotFound();
    }

    public function test_new_users_can_not_register_by_default(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }
}
