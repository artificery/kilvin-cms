<?php

namespace Kilvin\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Site Related Data Functionality
 */
class Site extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'cms.site';
    }
}
