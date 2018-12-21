<?php

namespace Kilvin\Cp;

use Kilvin\Facades\Cp;
use Illuminate\Support\Facades\DB;
use Kilvin\Facades\Site;
use Kilvin\Facades\Stats;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Cookie;
use Carbon\Carbon;
use Kilvin\Core\Session;

class Sites
{

   /**
    * Request Handler
    *
    * @return mixed
    */
    public function run()
    {
        if (Cp::segment(2) != null) {
            if (method_exists($this, camel_case(Cp::segment(2)))) {
                return $this->{camel_case(Cp::segment(2))}();
            }
        }

        return $this->listSites();
    }

   /**
    * Request Handler
    *
    * @return mixed
    */
    public function loadSite()
    {
        $site_id = Cp::pathVar('site_id');

        if (empty($site_id)) {
            session()->flash(
                'cp-message',
                __('kilvin::sites.unable_to_load_site')
            );

            return redirect(kilvin_cp_url('sites/list-sites'));
        }

        // -----------------------------------------
        //  Are you authorized to view this site?
        // -----------------------------------------

        if (Session::userdata('member_group_id') != 1) {
            $assigned_sites = Session::userdata('assigned_sites');

            if (!isset($assigned_sites[$site_id])) {
                return Cp::unauthorizedAccess();
            }
        }

        // -----------------------------------------
        //  Load Prefs, Save Cookie, Redirect
        // -----------------------------------------

        Site::loadSitePrefs($site_id);

        // @stop/@todo - This does not seem to be working.
        Cookie::queue('cp_last_site_id', $site_id, 365*24*60);

        if (Request::input('location') == 'preferences' || Cp::pathVar('location') == 'preferences') {
            return redirect(kilvin_cp_url('administration').'/site-preferences');
        }

        return redirect('/'.trim(config('cms.cp_path'), '/'));
    }

   /**
    * List Available Sites
    *
    * @return void
    */
    public function listSites()
    {
        if (sizeof(Session::userdata('assigned_sites')) == 0) {
            return Cp::unauthorizedAccess();
        }

        // -----------------------------------------
        //  Header
        // -----------------------------------------

        Cp::$title  = __('kilvin::admin.site_management');
        Cp::$crumb  = __('kilvin::admin.site_management');

        $right_links[] = [
            kilvin_cp_url('sites-administration/new-site'),
            __('kilvin::sites.create_new_site')
        ];

        $r = Cp::header(__('kilvin::sites.choose_a_site'), $right_links);

        // -----------------------------------------
        //  CP Message?
        // -----------------------------------------

        $cp_message = session()->pull('cp-message');

        if (!empty($cp_message)) {
            $r .= Cp::quickDiv('success-message', $cp_message);
        }

        // -----------------------------------------
        //  Choose a Site or Domain!
        // -----------------------------------------

        $r .= Cp::table('tableBorder', '0', '', '100%');

        $query = Site::sitesData();

        $site_urls = DB::table('site_urls')
            ->orderBy('site_url')
            ->pluck('site_id', 'site_url')
            ->all();

        foreach($query as $row) {

            $urls = array_filter(
                $site_urls,
                function($v, $k) use ($row) { return ($row->site_id == $v); },
                ARRAY_FILTER_USE_BOTH
            );

            $r .= '<tr>'.PHP_EOL;
            $r .= Cp::tableCell('', '<strong>'.Cp::anchor('sites/load-site/site_id='.$row->site_id, $row->site_name).'</strong>');

            $r .= Cp::tableCell('', implode(', ', array_keys($urls)));

            $r .= '</tr>'.PHP_EOL;
        }

        $r .= '</table>'.PHP_EOL;

        $r .= Cp::quickDiv(
            'littlePadding',
            "<a href='".kilvin_cp_url('sites-administration/list-sites')."'><em>&#187;&nbsp;<strong>".__('kilvin::cp.edit_sites')."</strong></em></a>");

        Cp::$body = $r;
    }
}
