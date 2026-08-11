<?php

declare(strict_types=1);

namespace App\Console\Commands\Agovena;

use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Models\StaffUser;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

#[Signature('agovena:create-owner {email?} {--name=} {--password=}')]
#[Description('Create the first staff Owner with all registered permissions')]
final class CreateOwnerCommand extends Command
{
    public function handle(SyncRegisteredPermissions $sync): int
    {
        $sync();

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

        $user = StaffUser::query()->updateOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                'password' => Hash::make($data['password']),
            ],
        );

        $user->syncRoles(['owner']);

        $this->info("Owner ready: {$user->email}");

        return self::SUCCESS;
    }
}
