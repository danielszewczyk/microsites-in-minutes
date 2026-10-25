<?php

namespace App\Models;

use App\Sites\Plans;
use Database\Factories\OwnerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The account a microsite belongs to. Passwordless in the MVP: an owner is
 * found or created by email when a site is published, and control of a site is
 * proven by its manage token rather than a login.
 *
 * @property string $email
 * @property ?string $name
 * @property string $plan
 * @property string $plan_status
 * @property ?string $billing_mode
 */
class Owner extends Model
{
    /** @use HasFactory<OwnerFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Defaults held in memory so a freshly made owner reports its plan before
     * the row is reloaded (the column default lives only in the database).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'plan' => 'free',
        'plan_status' => 'active',
    ];

    /**
     * @return HasMany<Site, $this>
     */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /**
     * @return HasMany<BuildEvent, $this>
     */
    public function buildEvents(): HasMany
    {
        return $this->hasMany(BuildEvent::class);
    }

    /**
     * The limits and features of this owner's current plan.
     *
     * @return array<string, mixed>
     */
    public function plan(): array
    {
        return Plans::tier($this->plan);
    }

    public function can(string $feature): bool
    {
        return (bool) ($this->plan()[$feature] ?? false);
    }
}
