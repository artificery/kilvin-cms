<?php

namespace Kilvin\Http\Controllers;

use Site;
use Stats;
use Template;
use Kilvin\Core\Url;
use Kilvin\Core\Actions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SiteController extends Controller
{
    private $request;

	/**
     * Viggo, the Constructor!
     *
     * @param  \Illuminate\Http\Request  $request
     */
	public function __construct(Request $request)
	{
		// ----------------------------------------------
        //  Parse Page Request and Set Variables Used in Plugins
        // ----------------------------------------------

        if (REQUEST === 'SITE') {
            Url::parseUri(request()->path());
            Url::parseQueryString(); // Part beyond template_group/template
        }

        if (defined('ACTION')) {
            // The IN->QSTR variable is not available during
            // action requests so we'll set it.

            if (Url::$QSTR == '' AND (count(request()->segments()) > 0))
            {
                $segs = $request->segments();
                Url::$QSTR = end($segs);
            }
        }

        // ----------------------------------------------
        //  Update system statistics
        // ----------------------------------------------

        if (REQUEST == 'SITE' && ! defined('ACTION')) {
            $stats = Stats::fetchSiteStats();
        }
	}

	// -------------------------------------------------

    /**
     * Loads up Templates for Site
     *
     * This is where the magic happens!
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function all(Request $request)
    {
        // @todo - Check for maintenance/update mode.

        if($this->isSitePage($request)) {
            return $this->sitePage($request);
        }

        // The magic!!
        // Also handles headers!
        return Template::discover(Url::$URI);
    }

    // -------------------------------------------------

    /**
     * Checks for Site Page
     *
     * Determines if request is a Site Page
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    private function isSitePage(Request $request)
    {
        return false;
    }

    // -------------------------------------------------

    /**
     * Loads up Actions for Site
     *
     * This is where the magic happens!
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function action(Request $request)
    {
    	return 'Action!';

    	// $ACT = new Actions;
    }
}
