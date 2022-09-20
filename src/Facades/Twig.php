<?php

namespace Kilvin\Facades;

use Illuminate\Support\Facades\Facade\Twig;

class Twig extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'cms.twig';
    }
}
