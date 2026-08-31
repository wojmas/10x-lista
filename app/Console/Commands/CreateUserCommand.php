<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Creates a single family account.
 *
 * The PRD forbids public sign-up, and docker/entrypoint.sh deliberately never
 * seeds accounts, so this command is the only way an account reaches
 * production: run it once per family member in the Render service shell.
 *
 * The password is never an argument or an option — arguments end up in the
 * shell history, and the repository is public.
 */
class CreateUserCommand extends Command
{
    protected $signature = 'app:user:create {name? : Imię członka rodziny} {email? : Adres e-mail używany do logowania}';

    protected $description = 'Zakłada konto członka rodziny (hasło podawane w ukrytym promptcie)';

    public function handle(): int
    {
        $data = [
            'name' => $this->argument('name') ?? $this->ask('Imię członka rodziny'),
            'email' => $this->argument('email') ?? $this->ask('Adres e-mail'),
            'password' => $this->secret('Hasło'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', Password::defaults()],
        ], [
            'email.unique' => 'Konto z tym adresem już istnieje — nie nadpisuję go.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $user = User::create($validator->validated());

        $this->components->info("Konto utworzone: {$user->name} <{$user->email}>");

        return self::SUCCESS;
    }
}
