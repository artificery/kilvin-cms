<?php

namespace Kilvin\Http\ViewComposers;

use Kilvin\Facades\Cp as CpFacade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Kilvin\Facades\Site;
use Illuminate\Support\Facades\Cache;
use Kilvin\Core\Session;
use Illuminate\View\View;

class Cp
{
   /**
     * Contructor
     *
     * @return void
     */
    public function __construct()
    {

    }

    /**
     * Bind data to the view.
     *
     * @param  View  $view
     * @return void
     */
    public function compose(View $view)
    {
        // Exception was thrown somewhere and CMS is not loaded
        // So, we disable this ViewComposer's loading as it requires
        // that the CMS be loaded and functioning
        if ( ! defined('CMS_NAME')) {
            return;
        }

        $view->with('cms', [
                'name' => CMS_NAME,
                'version' => KILVIN_VERSION,
            ]
        );

        $elapsed_time = number_format(microtime(true) - LARAVEL_START, 4);
        $query_log = DB::getQueryLog();

        $view->with('total_queries', sizeof($query_log));
        $view->with('elapsed_time', $elapsed_time);
        $view->with('page_creation_time', $elapsed_time);

        // Not logged in? No more work to be done.
        if (!Auth::check() || Auth::user()->is_banned == true) {
            $view->with(
                'cp',
                [
                    'header_elements' => CpFacade::jsAndCssHeaderElements()
                ]
            );

            return;
        }

        $view->with('site_url', Site::config('site_url'));
        $view->with('site_handle', Site::config('site_handle'));
        $view->with('site_name', Site::config('site_name'));
        $view->with(
            'cp',
            [
                'breadcrumbs' => CpFacade::breadcrumb(true),
                'sites_pulldown' => CpFacade::buildSitesPulldown(),
                'quick_links' => CpFacade::buildQuickLinks(),
                'tabs' => CpFacade::pageNavigation(),
                'quick_tab' => CpFacade::buildQuickTab(),
                'js_css_header_elements' => CpFacade::jsAndCssHeaderElements()
            ]
        );

        if (Session::userdata('member_group_id') == 1 && Site::config('show_queries') == 'y' && !empty($query_log)) {
            $query_log = $this->prettifyQueries($query_log);
            $view->with('query_log', $query_log);
        }

        $user = ['screen_name' => Session::userdata('screen_name')];
        $view->with('user', $user);
    }

    /**
     * Make the Queries in the Query Log Prettier
     *
     * @param  array $query_log
     * @return array
     */
    private function prettifyQueries($query_log)
    {
        $highlight =
        [
            'SELECT',
            'FROM',
            'WHERE',
            'AND',
            'LEFT JOIN',
            'ORDER BY',
            'LIMIT',
            'INSERT',
            'INTO',
            'VALUES',
            'UPDATE'
        ];

        foreach ($query_log as $val)
        {
            // bindings/time
            $query = htmlspecialchars($val['query'], ENT_QUOTES);

            foreach ($highlight as $bold)
            {
                $query = preg_replace("/(\s|^)".$bold."\s/i", ' <b>'.$bold.'</b> ', $query);
            }

            $buffer .= "<div style=\"text-align: left; font-family: Sans-serif; font-size: 11px; margin: 12px; padding: 6px\"><hr size='1'>";
            $buffer .= "<h5>".$i.' ('.$val['time'].'ms)</h5>';
            $buffer .= str_replace("\t", " ", $query);
            $buffer .= "</div>";
        }
    }
}
