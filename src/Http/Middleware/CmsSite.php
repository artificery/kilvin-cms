<?php

namespace Kilvin\Http\Middleware;

use Closure;
use Illuminate\Foundation\Application;
use Kilvin\Facades\Site;
use Illuminate\Http\Response;
use Kilvin\Exceptions\CmsFailureException;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Http\Request;

class CmsSite
{
    /**
     * The application instance.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Handle an incoming request.
     * - Loading up the Kilvin CMS site data
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $guard = null)
    {
        if (defined('KILVIN_REQUEST') && in_array(KILVIN_REQUEST, ['INSTALL','CONSOLE'])) {
            return $next($request);
        }

        // ----------------------------------------------
        //  Set Site Preferences class
        //  - @todo: Consider moving this into a piece of middleware that is indicated via route group
        // ----------------------------------------------

        try {
            if (defined('SITE_URL_ID')) {
                Site::loadSiteUrlPrefs(SITE_URL_ID);
            } elseif (KILVIN_REQUEST == 'CP' && request()->hasCookie('cp_last_site_id')) {
                $site_id = null;

                try {
                    $site_id = request()->cookie('cp_last_site_id');
                } catch (\Exception $e) { }

                if (is_numeric($site_id)) {
                    Site::loadSitePrefs($site_id);
                } else {
                    Site::loadSiteMagically();
                }
            } else {
                Site::loadSiteMagically();
            }

        } catch(\Illuminate\Database\QueryException $e) {
            // @todo - Maybe make this an exception that is caught by the Handler and made pretty?
            // @todo - Refine this to see if we are even installed?
            exit('Unable to load the CMS. Please check your database settings and ensure you ran the <a href="/installer">installer</a>.');
        }
        
        if (KILVIN_REQUEST === 'SITE') {
            $this->app['view']->getFinder()->prependLocation(base_path('templates/'.Site::config('site_handle')));
        }

        $this->app['config']->set('filesystems.disks.templates', ['driver' => 'local', 'root' => base_path('templates')]);

        if (!empty(Site::config('cookie_path'))) {
            $sconfig = config()->get('session');
            Cookie::setDefaultPathAndDomain(Site::config('cookie_path'), $sconfig['domain'], $sconfig['secure']);
        }

        if (Site::config('site_debug') == 2) {
            error_reporting(E_ALL);
        }

        if (KILVIN_REQUEST === 'CP') {
            define('KILVIN_CP_THEMES', KILVIN_THEMES.'cp/');
        }

        return $next($request);
    }
}
