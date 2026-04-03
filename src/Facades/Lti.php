<?php

namespace RefBytes\Lti\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \RefBytes\Lti\Lti
 */
class Lti extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \RefBytes\Lti\Lti::class;
    }
}
