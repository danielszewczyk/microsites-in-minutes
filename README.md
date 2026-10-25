# Microsites in Minutes

Self-serve generator for polished, mobile-first one-page promo and sweepstakes
microsites. Describe a promotion (the prompt) plus a few fields and a site is
generated and published to a shareable URL in seconds. An alternative to
template-microsite builders.

Built on The Localhost Company's own IP — evolved from the danielszewczyk.com
`make-demo-site` generator. No DJA code or resources.

## What's here (MVP)
- **Builder** (`/`): prompt box + fields → generate & publish. Owner identified
  by email; control proven by a per-site manage token (no login).
- **Published microsite** (`/s/{slug}`): self-contained HTML, everything
  escaped, no external assets (except the owner's own GA4 tag). Rendered HTML is
  cached by version; view counting runs on a queue after the response.
- **Regenerate in place** (`/s/{slug}/manage`): edit the prompt/fields, the same
  URL updates and open pages self-refresh.
- **SEO**: per-site title/meta/OG, canonical, per-site favicon, `/sitemap.xml`,
  `/robots.txt`, semantic HTML.
- **Analytics**: built-in view/entry counters + 30-day chart; optional per-site
  GA4 measurement ID (Pro plan).
- **Lead/entry capture** (`/s/{slug}/enter`): stored per site, CSV export,
  honeypot + per-IP rate limit, CSRF-exempt + stateless so pages stay cacheable.
- **Sweepstakes**: official rules page (`/s/{slug}/rules`) with eligibility,
  dates, sponsor, prize value, winner selection and a free alternate method of
  entry (AMOE).
- **Monetization data model**: `owners` carry a plan + Stripe columns;
  `config/plans.php` holds tiers and limits; billing modes (subscription,
  per-campaign, metered-entries). **Billing is stubbed — nothing charges.**

## Generation
`App\Sites\Copywriter\Copywriter` is the seam. The MVP ships the deterministic
`TemplateCopywriter` (no external calls, full control over legal wording). Swap
the binding in `AppServiceProvider` for an LLM-backed writer later.

## Dr. Herman drafts
`php artisan db:seed --class=HermanPromosSeeder` publishes two sweepstakes drafts
(Submit Your Smile, Submit Your Snore) to staging slugs for review. Prizes are
products/swag only (no treatment inducement), NY-only, value under $5,000, and
the snore promo makes no diagnosis claim. Placeholders `[...]` need Daniel to
confirm (mailing address, dates, phone) before anything goes live.

## Stack
Laravel 13, SQLite locally / Postgres on Laravel Cloud, database queue + cache.
No Node build step — pages are self-contained inline HTML/CSS/JS.
