<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file uploaded for a site, one per slot. Banners are public (shown on the
 * page); the rules doc is public (linked from the rules page); the copy deck is
 * owner-only reference material.
 *
 * @property string $slot
 * @property string $disk_path
 * @property string $original_name
 * @property string $mime
 * @property int $size
 */
class SiteAsset extends Model
{
    public const string DISK = 'assets';

    public const string BANNER_DESKTOP = 'banner_desktop';

    public const string BANNER_MOBILE = 'banner_mobile';

    public const string FAVICON = 'favicon';

    public const string COPY_DECK = 'copy_deck';

    public const string RULES_DOC = 'rules_doc';

    /**
     * The upload slots and what each accepts. "kind" is image or doc; favicon is
     * an image kept in its original format (so a transparent PNG mark stays
     * transparent) rather than redrawn to a JPEG like the banners.
     *
     * @var array<string, array{label: string, kind: string, public: bool}>
     */
    public const array SLOTS = [
        self::BANNER_DESKTOP => ['label' => 'Desktop banner', 'kind' => 'image', 'public' => true],
        self::BANNER_MOBILE => ['label' => 'Mobile banner', 'kind' => 'image', 'public' => true],
        self::FAVICON => ['label' => 'Favicon / mark', 'kind' => 'favicon', 'public' => true],
        self::COPY_DECK => ['label' => 'Copy deck', 'kind' => 'doc', 'public' => false],
        self::RULES_DOC => ['label' => 'Rules document', 'kind' => 'doc', 'public' => true],
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function isPublic(): bool
    {
        return (bool) (self::SLOTS[$this->slot]['public'] ?? false);
    }

    public static function isValidSlot(string $slot): bool
    {
        return array_key_exists($slot, self::SLOTS);
    }
}
