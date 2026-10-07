<?php

declare(strict_types=1);

use App\Agovena\Auth\ConfirmsRecentPassword;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

it('does not reuse recent password confirmation for another authenticated user', function (): void {
    $first = User::factory()->create(['password' => 'password']);
    $second = User::factory()->create(['password' => 'password']);
    $confirmation = app(ConfirmsRecentPassword::class);

    $this->actingAs($first);
    expect($confirmation->confirm($first, 'password'))->toBeTrue()
        ->and($confirmation->confirmed())->toBeTrue();

    Auth::logout();
    $this->actingAs($second);

    expect($confirmation->confirmed())->toBeFalse();
});

it('does not confirm a password for a different authenticated user', function (): void {
    $first = User::factory()->create(['password' => 'password']);
    $second = User::factory()->create(['password' => 'password']);
    $confirmation = app(ConfirmsRecentPassword::class);

    $this->actingAs($first);

    expect($confirmation->confirm($second, 'password'))->toBeFalse()
        ->and($confirmation->confirmed())->toBeFalse();
});
