<?php

namespace App\Models;

use App\Jobs\SendAlarm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A threshold alarm: email the owner when a site reaches a number of views,
 * entries or bytes of storage. Fires once, then stays quiet (triggered_at).
 *
 * @property string $metric
 * @property int $threshold
 * @property string $email
 * @property bool $enabled
 * @property ?Carbon $triggered_at
 */
class SiteAlarm extends Model
{
    public const string VIEWS = 'views';

    public const string ENTRIES = 'entries';

    public const string STORAGE = 'storage';

    /** @var list<string> */
    public const array METRICS = [self::VIEWS, self::ENTRIES, self::STORAGE];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'threshold' => 'integer',
            'enabled' => 'boolean',
            'triggered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function label(): string
    {
        return match ($this->metric) {
            self::STORAGE => number_format($this->threshold / 1_048_576).' MB storage',
            default => number_format($this->threshold).' '.$this->metric,
        };
    }

    /**
     * Check a site's alarms for the given metrics and fire any whose threshold
     * has been reached. Current values are read from the site and its assets.
     *
     * @param  list<string>  $metrics
     */
    public static function checkFor(Site $site, array $metrics): void
    {
        $alarms = $site->alarms()
            ->where('enabled', true)
            ->whereNull('triggered_at')
            ->whereIn('metric', $metrics)
            ->get();

        foreach ($alarms as $alarm) {
            $current = match ($alarm->metric) {
                self::VIEWS => (int) $site->view_count,
                self::ENTRIES => (int) $site->entry_count,
                self::STORAGE => (int) $site->assets()->sum('size'),
                default => 0,
            };

            if ($current >= $alarm->threshold) {
                $alarm->update(['triggered_at' => now()]);
                SendAlarm::dispatch($alarm->id, $current);
            }
        }
    }
}
