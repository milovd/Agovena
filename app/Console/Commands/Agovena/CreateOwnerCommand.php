<?php

declare(strict_types=1);

namespace App\Console\Commands\Agovena;

use App\Agovena\Staff\CreateOwnerStaff;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('agovena:create-owner {email?} {--name=} {--password=}')]
#[Description('Create the first Owner with all registered permissions')]
final class CreateOwnerCommand extends Command
{
    public function handle(CreateOwnerStaff $createOwner): int
    {
        $email = $this->argument('email') ?? $this->ask('Owner email');
        $name = $this->option('name') ?: $this->ask('Owner name', 'Owner');
        $password = $this->option('password') ?: $this->secret('Owner password');

        $validator = Validator::make([
            'email' => $email,
            'name' => $name,
            'password' => $password,
        ], [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        /** @var array{email: string, name: string, password: string} $data */
        $data = $validator->validated();

        $user = $createOwner($data['name'], $data['email'], $data['password']);

        $this->info("Owner ready: {$user->email}");

        return self::SUCCESS;
    }
}
