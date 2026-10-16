<?php

namespace App\Http\Controllers\Concerns;

trait BuildsLikePatterns
{
    /** Patrón LIKE «contiene» con los comodines del término escapados. */
    protected function containsPattern(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
    }
}
