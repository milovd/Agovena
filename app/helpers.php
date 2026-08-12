<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

function current_user(): ?User
{
    $user = Auth::user();

    return $user instanceof User ? $user : null;
}

function current_customer(): ?Customer
{
    $user = current_user();
    if ($user === null) {
        return null;
    }

    return $user->customer ?? $user->ensureCustomer();
}

function authenticated_customer(): Customer
{
    $customer = current_customer();
    abort_unless($customer instanceof Customer, 403);

    return $customer;
}
