<?php

declare(strict_types=1);

namespace App\Models;

use App\Notifications\ResetCustomerPassword;
use App\Notifications\VerifyCustomerEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * Single authenticated identity. Admin access is additive via roles/permissions.
 * Commerce FKs stay on the Customer profile (1:1).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $anonymized_at
 * @property Carbon|null $deletion_requested_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;

    public const GUARD = 'web';

    protected string $guard_name = self::GUARD;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'anonymized_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
        ];
    }

    protected static function booted(): void
    {
        static::created(static function (User $user): void {
            $user->ensureCustomer();
        });

        static::updated(static function (User $user): void {
            $profile = $user->customer;
            if ($profile === null) {
                $user->ensureCustomer();

                return;
            }

            $profile->fill([
                'name' => $user->name,
                'email' => $user->email,
            ])->save();
        });
    }

    /** @return HasOne<Customer, $this> */
    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function ensureCustomer(): Customer
    {
        if ($this->relationLoaded('customer')) {
            $loaded = $this->getRelation('customer');
            if ($loaded instanceof Customer) {
                return $loaded;
            }
        }

        $existing = $this->customer()->first();
        if ($existing !== null) {
            $this->setRelation('customer', $existing);

            return $existing;
        }

        $created = $this->customer()->create([
            'name' => $this->name,
            'email' => $this->email,
        ]);
        $this->setRelation('customer', $created);

        return $created;
    }

    public function canAccessAdmin(): bool
    {
        return $this->getAllPermissions()->isNotEmpty();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyCustomerEmail);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetCustomerPassword($token));
    }
}
