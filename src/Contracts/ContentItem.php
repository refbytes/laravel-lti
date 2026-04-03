<?php

namespace RefBytes\Lti\Contracts;

interface ContentItem
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
