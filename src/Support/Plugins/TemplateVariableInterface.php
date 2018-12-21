<?php

namespace Kilvin\Support\Plugins;

interface TemplateVariableInterface
{
    // --------------------------------------------------------------------

    /**
    * Name of the variable - one word, lowercased, underscores allowed
    *
    * @return string
    */
    public function name();

    // --------------------------------------------------------------------

    /**
    * Output the Variable
    *
    * @return string|object|array
    */
    public function run();
}
