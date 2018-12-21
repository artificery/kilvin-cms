<?php

namespace Kilvin\Support\Plugins;

interface TemplateFunctionInterface
{
    // --------------------------------------------------------------------

    /**
    * Name of the function - one word, lowercased
    *
    * @return string
    */
    public function name();

    // --------------------------------------------------------------------

    /**
    * Run the Function Request
    *
    * @param string
    * @return string
    */
    public function run($str);

    // --------------------------------------------------------------------

    /**
    * Options for Function
    *
    * @return array
    */
    public function options();
}
