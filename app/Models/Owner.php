<?php

namespace App\Models;

use App\Sites\Plans;
use Database\Factories\OwnerFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * The authenticated account a microsite belongs to. An owner registers, verifies
 * their email and logs in; publishing requires a verified account, so a site's
 * owner is proven rather than just an email someone typed. The MCP OAuth flow
 * also logs in as an Owner. Day-to-day control of a single site is still proven
 * by its manage token (the shareable link).
 *
 * @property string $email
 * @property ?string $name
 * @property ?string $password
 * @property string $plan
 * @property string $plan_status
 * @property ?string $billing_mode
 * @property ?Carbon $email_verified_at
 */
class Owner extends Authenticatable implements MustVerifyEmail, OAuthenticatable
{
    /** @use HasFactory<OwnerFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $guarded = [];

    /**
     * @var list<string>
     */
    protected $hidden = ['password', 'remember_token'];

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

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
        ];
    }

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

    /**
     * Whether the owner's plan includes a feature. (Named `allows` rather than
     * `can` to avoid colliding with the framework's authorization `can()`.)
     */
    public function allows(string $feature): bool
    {
        return (bool) ($this->plan()[$feature] ?? false);
    }

    /**
     * Whether this account may reach the /admin dashboard and see every site on
     * the service. Membership is an email allowlist in config/webmaster.php, so
     * granting access is an environment change, never a self-serve flag.
     */
    public function isWebmaster(): bool
    {
        // A verified address is required: registration signs an owner in before
        // verification, so without this check someone could register an
        // allowlisted address they don't control and reach the admin area.
        if (! $this->hasVerifiedEmail()) {
            return false;
        }

        /** @var list<string> $emails */
        $emails = config('webmaster.emails', []);

        return in_array(strtolower(trim($this->email)), $emails, true);
    }
}
