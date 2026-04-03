<?php

namespace RefBytes\Lti\Concerns;

trait ParsesLinkHeader
{
    /**
     * Parse an RFC 8288 Link header for a rel="next" URL.
     */
    protected function parseNextLink(?string $linkHeader): ?string
    {
        if (! $linkHeader) {
            return null;
        }

        foreach (explode(',', $linkHeader) as $part) {
            $part = trim($part);
            if (preg_match('/<([^>]+)>;\s*rel="next"/', $part, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }
}
