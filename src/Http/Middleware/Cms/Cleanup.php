<?php

namespace Kilvin\Http\Middleware\Cms;

use Illuminate\Support\Facades\DB;
use Closure;
use Carbon\Carbon;
use Kilvin\Facades\Stats;

class Cleanup
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if (REQUEST != 'SITE' or defined('ACTION')) {
            return $response;
        }

        // Should be cached
        $stats = Stats::fetchSiteStats();

        // ----------------------------------
        //  Garbage Collection
        //  - Every 7 days we'll run our garbage collection
        // -----------------------------------

        if (isset($stats))
        {
            if (isset($stats['last_cache_clear']) AND $stats['last_cache_clear'] > 1)
            {
                $last_clear = $stats['last_cache_clear'];
            }
        }

        if (isset($last_clear) && Carbon::now()->timestamp > $last_clear)
        {
            $expire = Carbon::now()->addDays(7)->timestamp;

            DB::table('stats')
                ->where('site_id', Site::config('site_id'))
                ->whereNull('weblog_id')
                ->update(['last_cache_clear' => $expire]);

            if (Site::config('enable_throttling') == 'y') {
                $expire = time() - 180;

                DB::table('throttle')->where('last_activity', '<', $expire)->delete();
            }

            cms_clear_caching('all');
        }

        return $response;
    }
}
