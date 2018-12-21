<?php

namespace Kilvin\Support\Plugins;

abstract class TemplateVariable implements TemplateVariableInterface
{
    // --------------------------------------------------------------------

    /**
    * Output the Variable
    *
    * @return string|object|array
    */
    public function run()
    {
        return null;
    }
}
