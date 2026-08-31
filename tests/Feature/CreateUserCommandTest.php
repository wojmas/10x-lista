<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_account_with_a_hashed_password(): void
    {
        $this->artisan('app:user:create')
            ->expectsQuestion('Imię członka rodziny', 'Wojtek')
            ->expectsQuestion('Adres e-mail', 'wojtek@example.com')
            ->expectsQuestion('Hasło', 'tajne-haslo')
            ->assertSuccessful();

        $user = User::query()->where('email', 'wojtek@example.com')->sole();

        $this->assertSame('Wojtek', $user->name);
        $this->assertNotSame('tajne-haslo', $user->password);
        $this->assertTrue(Hash::check('tajne-haslo', $user->password));
    }

    public function test_it_refuses_a_duplicate_email_and_leaves_the_existing_account_alone(): void
    {
        $existing = User::factory()->create(['email' => 'wojtek@example.com']);

        $this->artisan('app:user:create', ['name' => 'Ktos Inny', 'email' => 'wojtek@example.com'])
            ->expectsQuestion('Hasło', 'inne-haslo')
            ->assertFailed();

        $this->assertSame(1, User::query()->count());
        $this->assertSame($existing->name, $existing->fresh()->name);
        $this->assertTrue(Hash::check('password', $existing->fresh()->password));
    }

    public function test_it_rejects_a_password_shorter_than_the_default_minimum(): void
    {
        $this->artisan('app:user:create', ['name' => 'Wojtek', 'email' => 'wojtek@example.com'])
            ->expectsQuestion('Hasło', 'krotkie')
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
    }
}
