<?php

namespace App\Sites;

use Illuminate\Support\Arr;

/**
 * Inline copy editing on the draft preview. The owner clicks a piece of
 * generated text and retypes it; the new text is stored as an override, keyed
 * by its path in the content document, and laid over the generated content at
 * render time. Regenerating replaces only the generated layer, so the owner's
 * words always win until they reset a field.
 *
 * The paths here are the whole allowlist: a path is editable only when it
 * matches one of these patterns AND already holds a string in the generated
 * content, so an edit can never add a node the template does not render.
 */
class CopyEdits
{
    /**
     * Editable paths (a `*` stands for a numeric index), the longest text each
     * accepts, and the name the editor shows for it. Every one is rendered
     * through `{{ }}` in microsite/show, which is what `data-edit` marks.
     *
     * @var array<string, array{label: string, max: int}>
     */
    public const array FIELDS = [
        'hero.kicker' => ['label' => 'Kicker', 'max' => 60],
        'hero.headline' => ['label' => 'Headline', 'max' => 120],
        'hero.subhead' => ['label' => 'Subheading', 'max' => 200],
        'cta.label' => ['label' => 'Button label', 'max' => 40],
        'cta.note' => ['label' => 'Note under the button', 'max' => 160],
        'offer.heading' => ['label' => 'Section heading', 'max' => 80],
        'offer.body' => ['label' => 'Section text', 'max' => 1500],
        'offer.bullets.*' => ['label' => 'Bullet point', 'max' => 160],
        'features.*.title' => ['label' => 'Card title', 'max' => 80],
        'features.*.text' => ['label' => 'Card text', 'max' => 300],
        'about' => ['label' => 'About text', 'max' => 1500],
        'form.heading' => ['label' => 'Form heading', 'max' => 80],
        'form.note' => ['label' => 'Form note', 'max' => 200],
    ];

    /**
     * The character limit for a path, or null when the path is not on the
     * allowlist (the pattern match alone — existence is checked separately).
     */
    public static function limitFor(string $path): ?int
    {
        foreach (self::FIELDS as $pattern => $field) {
            $regex = '/^'.str_replace('\*', '(0|[1-9][0-9]?)', preg_quote($pattern, '/')).'$/';

            if (preg_match($regex, $path) === 1) {
                return $field['max'];
            }
        }

        return null;
    }

    /**
     * Whether the owner may edit this path on this generated content: it is on
     * the allowlist and the generated document already holds a string there.
     *
     * @param  array<string, mixed>  $content
     */
    public static function editable(array $content, string $path): bool
    {
        return self::limitFor($path) !== null && is_string(Arr::get($content, $path));
    }

    /**
     * Normalise typed text to the plain, single-line form the page renders:
     * control characters out, runs of whitespace collapsed, ends trimmed.
     */
    public static function clean(string $text): string
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /**
     * The generated content with the owner's overrides laid on top. An override
     * whose path the current generation no longer renders (a bullet that went
     * away, a type change) is skipped rather than invented into the page.
     *
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function apply(array $content, array $overrides): array
    {
        foreach ($overrides as $path => $text) {
            if (is_string($path) && is_string($text) && self::editable($content, $path)) {
                Arr::set($content, $path, $text);
            }
        }

        return $content;
    }
}
