<?php

namespace App\Sites;

use Illuminate\Support\Str;

/**
 * The hero pictures a microsite can carry, each a small inline drawing in
 * resources/views/microsite/art. A closed list rather than a generated or
 * fetched image: nothing is loaded from anywhere, nothing costs anything, and a
 * name that is not on the list never reaches a view path.
 */
class HeroArt
{
    /** @var list<string> */
    public const array ILLUSTRATIONS = [
        'smile', 'tooth', 'leaf', 'coffee', 'shoe', 'house', 'car', 'bee', 'cake', 'paw', 'heart', 'star',
        'comb-and-scissors', 'scissors', 'comb', 'barber-pole', 'wrench', 'dumbbell', 'paintbrush', 'camera',
        'music', 'fork-and-knife', 'book', 'flower', 'laptop', 'plane',
    ];

    /**
     * Keyword to illustration. The first picture whose keywords appear in the
     * text wins; order matters, so the more specific entries come first.
     *
     * @var array<string, list<string>>
     */
    protected const array KEYWORDS = [
        'tooth' => ['teeth whitening', 'whiten', 'whitening'],
        'smile' => ['smile', 'dentist', 'dental', 'tooth', 'teeth', 'orthodont', 'cosmetic'],
        'paw' => ['pet', 'vet', 'dog', 'cat', 'groom', 'animal'],
        'comb-and-scissors' => ['barber', 'salon', 'haircut', 'hair ', 'stylist'],
        'coffee' => ['coffee', 'cafe', 'espresso', 'roaster', 'barista'],
        'fork-and-knife' => ['restaurant', 'menu', 'dining', 'kitchen', 'chef', 'food'],
        'cake' => ['bakery', 'cake', 'pastry', 'dessert', 'sweet'],
        'wrench' => ['mechanic', 'repair', 'plumb', 'hvac', 'auto shop', 'garage', 'fix'],
        'car' => ['car wash', 'dealership', 'auto ', 'vehicle', 'detailing'],
        'dumbbell' => ['gym', 'fitness', 'trainer', 'workout', 'crossfit', 'yoga'],
        'house' => ['real estate', 'realtor', 'home', 'property', 'roofing', 'contractor'],
        'leaf' => ['spa', 'wellness', 'holistic', 'organic', 'green', 'nature', 'sleep'],
        'camera' => ['photo', 'photographer', 'studio', 'video'],
        'music' => ['music', 'band', 'dj', 'concert', 'studio'],
        'paintbrush' => ['paint', 'art', 'design', 'decor'],
        'plane' => ['travel', 'flight', 'vacation', 'tour', 'trip'],
        'shoe' => ['shoe', 'sneaker', 'footwear', 'run'],
        'flower' => ['florist', 'flower', 'bouquet', 'garden'],
        'book' => ['book', 'school', 'course', 'tutor', 'learn'],
        'laptop' => ['software', 'app', 'tech', 'web', 'startup', 'saas'],
        'heart' => ['charity', 'nonprofit', 'donate', 'care', 'clinic'],
        'bee' => ['honey', 'farm'],
        'barber-pole' => ['barbershop'],
    ];

    /**
     * The illustration that best fits a prompt, or a neutral default when
     * nothing matches.
     */
    public static function suggestFor(string $text): string
    {
        $haystack = Str::lower($text);

        foreach (self::KEYWORDS as $art => $keywords) {
            foreach ($keywords as $keyword) {
                // Match whole words so "cat" does not fire on "category".
                if (preg_match('/\b'.preg_quote(trim($keyword), '/').'\b/', $haystack) === 1) {
                    return $art;
                }
            }
        }

        return 'star';
    }
}
