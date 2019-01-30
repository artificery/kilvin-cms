<?php

namespace Kilvin\Cp;

use Illuminate\Support\Facades\DB;
use Kilvin\Facades\Cp;
use Kilvin\Facades\Site;
use Illuminate\Support\Facades\Request;
use Carbon\Carbon;
use Kilvin\Core\Session;
use Kilvin\Core\Localize;

class Logging
{
   /**
    * Clear the Control Panel Logs action
    *
    * @return string
    */
    public function clearCpLogs()
    {
        if ( ! Session::access('can_admin_utilities')) {
            return Cp::unauthorizedAccess();
        }

        DB::table('cp_log')->delete();

        Cp::log(__('kilvin::cp.cleared_logs'));

        return $this->viewLogs();
    }

   /**
    * View CP Logs
    *
    * @return string
    */
    public function viewLogs()
    {
        if ( ! Session::access('can_admin_utilities')) {
            return Cp::unauthorizedAccess();
        }

        // Number of results per page
         $perpage = 100;

        // Fetch the total number of logs for our paginate links
        $total = DB::table('cp_log')->count();

        if ( ! $rownum = Request::input('rownum')) {
            $rownum = 0;
        }

        // Run the query
        $sites_query = DB::table('sites')->select('sites.id AS site_id', 'site_name')->get();

        $sites = [];

        foreach($sites_query as $row) {
        	$sites[$row->site_id] = $row->site_name;
        }

        $query = DB::table('cp_log')
        	->orderBy('act_date', 'desc')
        	->limit($perpage)
        	->offset($rownum)
        	->get();

        // Build the output
        Cp::$title  = __('kilvin::admin.view-log-files');
        Cp::$crumb  = Cp::anchor('administration/utilities', __('kilvin::admin.utilities')).
                       Cp::breadcrumbItem(__('kilvin::admin.view-log-files'));

        $right_links[] = ['administration/utilities/clear-cplogs', __('kilvin::admin.clear_logs')];

        $r  = Cp::header(__('kilvin::admin.view-log-files'), $right_links);

        $r .= Cp::table('tableBorder', '0', '0', '100%');

        $r .= Cp::tableQuickRow('tableHeadingAlt',
			[
				__('kilvin::cp.member_id'),
				__('kilvin::admin.screen_name'),
				__('kilvin::cp.ip_address'),
				__('kilvin::cp.date'),
				__('kilvin::cp.site'),
				__('kilvin::cp.action')
			]
		);

        $i = 0;

        foreach ($query as $row)
        {
            $r .= Cp::tableQuickRow('',
                                    array(
                                            $row->member_id,
                                            $row->screen_name,
                                            $row->ip_address,
                                            Localize::createHumanReadableDateTime($row->act_date),
                                            $sites[$row->site_id],
                                            nl2br($row->action)
                                          )
                                   );
        }

        $r .= '</table>'.PHP_EOL;

        $r .= Cp::quickDiv('littlePadding',
              Cp::quickDiv('crumblinks',
              Cp::pager(
					'administration/utilities/view-logs',
					$total,
					$perpage,
					$rownum,
					'rownum'
			  )
			)
		);


        Cp::$body   = $r;
    }
}
