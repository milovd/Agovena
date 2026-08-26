<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * Commerce profile for a User. Not an authentication identity.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $email
 * @property Carbon|null $anonymized_at
 * @property Carbon|null $deletion_requested_at
 */
#[Fillable(['user_id', 'name', 'email'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use Notifiable;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function routeNotificationForMail(): string
    {
        return $this->user->email;
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->user->hasVerifiedEmail();
    }

    protected function anonymizedAt(): Attribute
    {
        return Attribute::get(fn (): ?Carbon => $this->user->anonymized_at);
    }

    protected function deletionRequestedAt(): Attribute
    {
        return Attribute::get(fn (): ?Carbon => $this->user->deletion_requested_at);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<CustomerAddress, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function referralCodes(): HasMany
    {
        return $this->hasMany(ReferralCode::class);
    }

    public function referralAttributions(): HasMany
    {
        return $this->hasMany(ReferralAttribution::class, 'referrer_customer_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<CreditNote, $this> */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function creditEntries(): HasMany
    {
        return $this->hasMany(CustomerCreditEntry::class);
    }

    public function creditAccount(): HasOne
    {
        return $this->hasOne(CustomerCreditAccount::class);
    }

    /** @return HasMany<CustomerPropertyValue, $this> */
    public function propertyValues(): HasMany
    {
        return $this->hasMany(CustomerPropertyValue::class);
    }
}
