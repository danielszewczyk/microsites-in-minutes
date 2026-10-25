<?php

namespace App\Sites\Copywriter;

/**
 * Turns an owner's builder inputs into the render-ready content of a microsite.
 *
 * This is the seam that keeps generation swappable. The MVP ships a
 * deterministic TemplateCopywriter — no external calls, nothing to pay for,
 * fully under our control for the legal scaffolding of a sweepstakes. A future
 * LLM-backed implementation can replace the binding in AppServiceProvider
 * without the rest of the app changing.
 */
interface Copywriter
{
    /**
     * @param  array<string, mixed>  $inputs  Normalised builder inputs.
     * @return array<string, mixed> The content document the template renders.
     */
    public function generate(array $inputs): array;
}
