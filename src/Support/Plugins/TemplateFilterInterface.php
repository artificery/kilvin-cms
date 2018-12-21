<?php

namespace Kilvin\Support\Plugins;

interface TemplateFilterInterface
{
    // --------------------------------------------------------------------

    /**
    * Name of the filter - one word, lowercased
    *
    * @return string
    */
    public function name();

    // --------------------------------------------------------------------

    /**
    * Run the Filter Request
    *
    * @param string
    * @return string
    */
    public function run($str);

    // --------------------------------------------------------------------

    /**
    * Options for Filter
    *
    * @return array
    */
    public function options();
}
