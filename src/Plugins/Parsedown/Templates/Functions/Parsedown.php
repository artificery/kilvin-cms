<?php

namespace Kilvin\Plugins\Parsedown\Templates\Functions;

use Kilvin\Support\Plugins\TemplateFunction;
use Illuminate\Http\Request;

/**
 * Parsedown function
 *
 * @category   Plugin
 * @package    Parsedown
 * @author     Paul Burdick <paul@reedmaniac.com>
 */

class Parsedown extends TemplateFunction
{
    private $request;

    /**
     * Constructor
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Name of the Function
     *
     * @return string
     */
    public function name()
    {
        return 'parsedown';
    }

    /**
     * Perform the Function
     *
     * @return string
     */
    public function run($str)
    {
        return parsedown($str);
    }
}
