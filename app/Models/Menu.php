<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    protected $fillable = ['handle', 'name'];

    /** @return HasMany<MenuItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->whereNull('parent_id')->orderBy('sort');
    }

    /** @return HasMany<MenuItem, $this> */
    public function allItems(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort');
    }
}
