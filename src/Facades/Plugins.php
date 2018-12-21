<?php

namespace Kilvin\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Stats Functionality
 */
class Plugins extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'cms.plugins';
    }
}
