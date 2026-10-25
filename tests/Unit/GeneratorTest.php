<?php

namespace Tests\Unit;

use App\Sites\Copywriter\TemplateCopywriter;
use App\Sites\HeroArt;
use PHPUnit\Framework\TestCase;

class GeneratorTest extends TestCase
{
    public function test_sweepstakes_content_includes_full_official_rules(): void
    {
        $content = (new TemplateCopywriter)->generate([
            'business_name' => 'Beyond Dentistry',
            'prompt' => 'Win a whitening kit.',
            'type' => 'sweepstakes',
            'prize' => 'a teeth-whitening kit',
            'prize_value' => '$250',
            'start_date' => '2026-11-03',
            'end_date' => '2026-11-28',
        ]);

        $rules = $content['rules'];
        foreach (['sponsor', 'eligibility', 'start_date', 'end_date', 'prize_description', 'prize_value', 'winner_selection', 'amoe', 'no_purchase', 'disclaimer'] as $key) {
            $this->assertArrayHasKey($key, $rules);
            $this->assertNotEmpty($rules[$key], "rules.$key should not be empty");
        }

        $this->assertStringContainsString('NO PURCHASE NECESSARY', $rules['no_purchase']);
        $this->assertStringContainsString('November 28, 2026', $rules['end_date']);
    }

    public function test_standard_content_has_three_features_and_no_rules(): void
    {
        $content = (new TemplateCopywriter)->generate([
            'business_name' => 'Paul\'s Parlor',
            'prompt' => 'A hair salon.',
            'type' => 'standard',
        ]);

        $this->assertCount(3, $content['features']);
        $this->assertNull($content['rules']);
        $this->assertNotEmpty($content['cta']['label']);
        $this->assertNotEmpty($content['meta']['title']);
    }

    public function test_hero_art_is_suggested_from_the_prompt(): void
    {
        $this->assertSame('smile', HeroArt::suggestFor('a dental practice offering cosmetic dentistry'));
        $this->assertSame('tooth', HeroArt::suggestFor('professional teeth whitening giveaway'));
        $this->assertSame('paw', HeroArt::suggestFor('a mobile dog grooming service'));
        $this->assertSame('star', HeroArt::suggestFor('something with no obvious category at all'));
        $this->assertContains(HeroArt::suggestFor('anything'), HeroArt::ILLUSTRATIONS);
    }
}
