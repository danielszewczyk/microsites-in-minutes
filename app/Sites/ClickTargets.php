<?php

namespace App\Sites;

use Illuminate\Support\Str;

/**
 * The fixed vocabulary of trackable links and buttons on a published microsite,
 * and the publish-time assignment of a stable id to each. The id — not the
 * label, the text or a CSS selector — is what a click is counted against, so a
 * count follows an element even when its wording changes. Ids are minted once
 * at publish time (see SiteComposer) and carried forward unchanged on every
 * regenerate.
 */
class ClickTargets
{
    /**
     * Each tracked slot and how to describe it in the owner's dashboard. The
     * set is small and fixed, which is what keeps the roll-up table bounded.
     *
     * @var array<string, array{kind: string, name: string}>
     */
    public const array SLOTS = [
        'hero_cta' => ['kind' => 'link', 'name' => 'Hero call-to-action'],
        'entry_submit' => ['kind' => 'button', 'name' => 'Entry form button'],
        'rules' => ['kind' => 'link', 'name' => 'Official rules link'],
        'rules_doc' => ['kind' => 'link', 'name' => 'Official rules document'],
        'branding' => ['kind' => 'link', 'name' => 'Microsites in Minutes credit'],
    ];

    /**
     * Build the slot → id map for a site's content, reusing any id already
     * assigned so an element keeps its identity across regenerates and only a
     * newly introduced slot is minted.
     *
     * @param  array<string, string>  $existing  The site's current content['track'] map.
     * @return array<string, string>
     */
    public static function assign(array $existing = []): array
    {
        $map = [];

        foreach (array_keys(self::SLOTS) as $slot) {
            $current = $existing[$slot] ?? null;
            $map[$slot] = is_string($current) && $current !== '' ? $current : self::mint();
        }

        return $map;
    }

    /**
     * The slot a given element id belongs to within a track map, or null when
     * the id is unknown (e.g. a stale id from a since-removed slot).
     *
     * @param  array<string, string>  $map
     */
    public static function slotForId(array $map, string $elementId): ?string
    {
        $slot = array_search($elementId, $map, true);

        return is_string($slot) ? $slot : null;
    }

    /**
     * A short, lowercase, unguessable element id. Ten hex-ish characters is
     * plenty for the handful of elements on one page and keeps the markup tidy.
     */
    protected static function mint(): string
    {
        return Str::lower(Str::random(10));
    }
}
