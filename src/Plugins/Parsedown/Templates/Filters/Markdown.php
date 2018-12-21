<?php

namespace Kilvin\Plugins\Parsedown\Templates\Filters;

use Kilvin\Support\Plugins\TemplateFilter;
use Illuminate\Http\Request;

/**
 * Markdown filter using Parsedown
 *
 * @category   Plugin
 * @package    Parsedown
 * @author     Paul Burdick <paul@reedmaniac.com>
 */

class Markdown extends TemplateFilter
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
     * Name of the Filter
     *
     * @return string
     */
    public function name()
    {
        return 'markdown';
    }

    /**
     * Perform the Filtering
     *
     * @return string
     */
    public function run($str)
    {
        return parsedown($str);
    }
}
