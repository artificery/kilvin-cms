<?php

namespace Kilvin\Support\Plugins;

abstract class TemplateFunction implements TemplateFunctionInterface
{
    // --------------------------------------------------------------------

    /**
    * Run the Function Request
    *
    * @param string
    * @return string
    */
    public function run($str)
    {
        return $str;
    }

    // --------------------------------------------------------------------

    /**
    * Options for Function
    *
    * @return array
    */
    public function options()
    {
        return [];
    }
}
