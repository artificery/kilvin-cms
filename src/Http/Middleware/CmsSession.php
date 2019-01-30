<?php

namespace Kilvin\Http\Middleware;

use Kilvin\Facades\Cp;
use Kilvin\Facades\Site;
use Request;
use Closure;
use Carbon\Carbon;
use Kilvin\Core\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\MessageBag;
use Illuminate\Http\Response;
use Illuminate\Container\Container;
use Illuminate\Routing\RouteDependencyResolverTrait;
use Kilvin\Exceptions\CmsFailureException;

class CmsSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        // ----------------------------------------------
        //  Instantiate Kilvin Session Data
        // ----------------------------------------------

        if (defined('REQUEST') && in_array(REQUEST, ['INSTALL','CONSOLE'])) {
            return $next($request);
        }

        Session::boot();

        if (Session::userdata('is_banned') == true) {
            if (REQUEST == 'CP') {
                throw new CmsFailureException(__('kilvin::admin.Your account has been banned.'));
            }

            switch (Site::config('ban_action')) {
                case 'restrict' :
                    // View only, do nothing here.
                    break;
                case 'bounce'  : return redirect(Site::config('ban_destination'));
                    break;
                case 'message' :
                    throw new CmsFailureException(Site::config('ban_message'));
                    break;
            }
        }

        // ----------------------------------------------
        //  If Site Debug is 1 and User is SuperAdmin, Debugging On
        //  - App debug value overrides Site debugging value
        // ----------------------------------------------

        if (config('app.debug') == true or (Site::config('site_debug') == 1 and Session::userdata('member_group_id') == 1)) {
            error_reporting(E_ALL);
        }

        // ----------------------------------------------
        //  Is the system turned on?
        //  - If system off, only CP is viewable and only by admins
        // ----------------------------------------------

        if (config('kilvin.is_system_on') != true) {
            if (REQUEST != 'CP' || (Auth::check() && Session::userdata('member_group_id') != 1)) {
                if (REQUEST == 'CP') {
                    abort(403);
                } else {
                    exit(view('offline'));
                }
            }
        }

        // ----------------------------------------------
        //  Is the site turned on?
        //  - Note: super-admins can always view a site
        // ----------------------------------------------

        if (Session::userdata('member_group_id') != 1 and REQUEST == 'SITE') {
            if (Site::config('is_site_on') != 'y') {
                $viewable_sites = Session::userdata('offline_sites');
                if (!in_array(Site::config('site_id'), $viewable_sites)) {
                    exit(view('offline'));
                }
            }
        }

        // ----------------------------------------------
        //  Done for Now
        // ----------------------------------------------

        return $next($request);
    }
}
