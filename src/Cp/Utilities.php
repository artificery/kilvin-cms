<?php

namespace Kilvin\Cp;

use Kilvin\Facades\Cp;
use Illuminate\Support\Facades\DB;
use Kilvin\Facades\Site;
use Kilvin\Facades\Stats;
use Illuminate\Support\Facades\Request;
use Carbon\Carbon;
use Kilvin\Core\Session;

class Utilities
{
   /**
    * Delete Cache Form
    *
    * @return  string
    */
    public function clearCacheForm($message = false)
    {
        if ( ! Session::access('can_admin_utilities')) {
            return Cp::unauthorizedAccess();
        }

        Cp::$title = __('kilvin::admin.clear-caching');
        Cp::$crumb = Cp::anchor('administration/utilities', __('kilvin::admin.utilities')).
					  Cp::breadcrumbItem(__('kilvin::admin.clear-caching'));

        Cp::$body = Cp::quickDiv('tableHeading', __('kilvin::admin.clear-caching'));

        if ($message == true) {
            Cp::$body  .= Cp::quickDiv('success-message', __('kilvin::admin.cache_deleted'));
        }

		Cp::$body .= Cp::div('box');
        Cp::$body .= Cp::formOpen(
        	[
        		'action' => 'administration/utilities/clear-caching',
        	]
        );

        Cp::$body .= Cp::div('littleTopPadding');
        Cp::$body .= __('kilvin::admin.clear_cache_details');
		Cp::$body .= '</div>'.PHP_EOL;

        Cp::$body .= Cp::quickDiv('paddingTop', Cp::input_submit(__('kilvin::admin.clear-caching')));
        Cp::$body .= '</form>'.PHP_EOL;
        Cp::$body .= '</div>'.PHP_EOL;
    }

   /**
    * Clear Caching for Site
    *
    * Right now it simply calls a method that empties the Laravel Cache
    *
    * @return  string
    */
    public function clearCaching()
    {
        cmsClearCaching();

        return $this->clearCacheForm(true);
    }

   /**
    * Recount Statistics Form
    *
    * @return  string
    */
    public function recountStatistics()
    {
        if ( ! Session::access('can_admin_utilities')) {
            return Cp::unauthorizedAccess();
        }

        $sources = array('members', 'weblog_entries');

        Cp::$title = __('kilvin::admin.recount-statistics');
        Cp::$crumb =
            Cp::anchor('administration/utilities', __('kilvin::admin.utilities')).
            Cp::breadcrumbItem(__('kilvin::admin.recount-statistics'));

		$right_links[] = [
			'administration/utilities/recount-preferences',
			__('kilvin::admin.set_recount_prefs')
		];

		$r  = Cp::header(Cp::$title, $right_links);
        $r .= Cp::quickDiv('tableHeading', __('kilvin::admin.recalculate'));
        $r .= Cp::quickDiv('box', __('kilvin::admin.recount_info'));
        $r .= Cp::table('tableBorder', '0', '', '100%').
              '<tr>'.PHP_EOL.
              Cp::tableCell('tableHeadingAlt',
                                array(
                                        __('kilvin::admin.source'),
                                        __('kilvin::admin.records'),
                                        __('kilvin::cp.action')
                                     )
                                ).
                '</tr>'.PHP_EOL;

        $i = 0;

        foreach ($sources as $val) {
			$source_count = DB::table($val)->count();

			$r .= '<tr>'.PHP_EOL;

			// Table name
			$r .= Cp::tableCell('', Cp::quickDiv('defaultBold', __('kilvin::admin.stats_'.$val)), '20%');

			// Table rows
			$r .= Cp::tableCell('', $source_count, '20%');

			// Action
			$r .= Cp::tableCell(
                '',
                Cp::anchor(
                    'administration/utilities/perform-recount/TBL='.$val,
                    __('kilvin::admin.do_recount')
                ),
                '20%'
            );
        }


		$r .= '<tr>'.PHP_EOL;

		// Table name
		$r .= Cp::tableCell('', Cp::quickDiv('defaultBold', __('kilvin::admin.site_statistics')), '20%');

		// Table rows
		$r .= Cp::tableCell('', '4', '20%');

		// Action
		$r .= Cp::tableCell(
            '',
            Cp::anchor(
                'administration/utilities/perform-stats-recount',
                __('kilvin::admin.do_recount')
            ),
            '20%'
        );

        $r .= '</table>'.PHP_EOL;

        Cp::$body = $r;
    }

   /**
    * Recount Preferences Form (pretty much batch total)
    *
    * @return string
    */
    public function recountPreferences()
    {
        if ( ! Session::access('can_admin_utilities')) {
            return Cp::unauthorizedAccess();
        }

        $recount_batch_total = Site::config('recount_batch_total');

        Cp::$title = __('kilvin::admin.utilities');
        Cp::$crumb =
            Cp::anchor('administration/utilities', __('kilvin::admin.utilities')).
            Cp::breadcrumbItem(
                Cp::anchor(
                    'administration/utilities/recount-statistics',
                    __('kilvin::admin.recount-statistics')
                )
            ).
            Cp::breadcrumbItem(__('kilvin::admin.set_recount_prefs'));

        $r = Cp::quickDiv('tableHeading', __('kilvin::admin.set_recount_prefs'));

        if (Cp::pathVar('U')) {
            $r .= Cp::quickDiv('success-message', __('kilvin::admin.preference_updated'));
        }

        $r .= Cp::formOpen(
			['action' => 'administration/utilities/update-recount-preferences'],
			['return_location' => 'administration/utilities/recount-preferences/U=1']
		);

        $r .= Cp::div('box');

        $r .= Cp::quickDiv('littlePadding', Cp::quickDiv('defaultBold', __('kilvin::admin.recount_instructions')));

        $r .= Cp::quickDiv('littlePadding', __('kilvin::admin.recount_instructions_cont'));

        $r .= Cp::input_text('recount_batch_total', $recount_batch_total, '7', '5', 'input', '60px');

        $r .= Cp::quickDiv('paddingTop', Cp::input_submit(__('kilvin::cp.update')));

        $r .= '</div>'.PHP_EOL;
        $r .= '</form>'.PHP_EOL;

        Cp::$body = $r;
    }

   /**
    * Save Recount Preferences
    *
    * @return string
    */
    public function updateRecountPreferences()
    {
        if ( ! Session::access('can_admin_utilities')) {
            return Cp::unauthorizedAccess();
        }

        $total = Request::input('recount_batch_total');

        if (empty($total) or ! is_numeric($total)) {
            return Utilities::recount_preferences_form();
        }

        DB::table('site_preferences')
            ->where('site_id', Site::config('site_id'))
            ->where('handle', 'recount_batch_total')
            ->update([
                    'value' => $total
                ]
            );

        return redirect(kilvinCpUrl('administration/utilities/recount-preferences/U=updated'));
    }

   /**
    * Recount Statistics
    *
    * @return string
    */
    public function performStatsRecount()
    {
        if ( ! Session::access('can_admin_utilities')) {
            return Cp::unauthorizedAccess();
        }

        $original_site_id = Site::config('site_id');

        $query = DB::table('sites')
        	->select('sites.id AS site_id')
        	->get();

        foreach($query as $row)
		{
			Site::setConfig('site_id', $row->site_id);

			Stats::update_member_stats();
			Stats::update_weblog_stats();
		}

		Site::setConfig('site_id', $original_site_id);

        Cp::$title = __('kilvin::admin.utilities');
        Cp::$crumb = Cp::anchor('administration/utilities', __('kilvin::admin.utilities')).
            Cp::breadcrumbItem(
            	Cp::anchor('administration/utilities/recount-statistics',
            	__('kilvin::admin.recalculate')
            )
        ).
		Cp::breadcrumbItem(__('kilvin::admin.recounting'));

		Cp::$body  = Cp::quickDiv('tableHeading', __('kilvin::admin.site_statistics'));
		Cp::$body .= Cp::div('success-message');
		Cp::$body .= __('kilvin::admin.recount_completed');
		Cp::$body .= Cp::quickDiv('littlePadding', Cp::anchor('administration/utilities/recount-statistics', __('kilvin::admin.return_to_recount_overview')));
		Cp::$body .= '</div>'.PHP_EOL;
	}

   /**
    * Weblog or Members recount
    *
    * @return string
    */
    public function performRecount()
    {
        if ( ! Session::access('can_admin_utilities')) {
            return Cp::unauthorizedAccess();
        }

        if ( ! $table = CP::pathVar('TBL')) {
            return false;
        }

        $sources = ['members', 'weblog_entries'];

        if ( ! in_array($table, $sources)) {
            return false;
        }

   		if ( ! CP::pathVar('T')) {
        	$num_rows = false;
        } else {
        	$num_rows = CP::pathVar('T');
			settype($num_rows, 'integer');
        }

        $batch = Site::config('recount_batch_total');

		if ($table == 'members') {
			$total_rows = DB::table('members')->count();

			if ($num_rows !== false) {
				$query = DB::table('members')
					->select('members.id AS member_id')
					->orderBy('id')
					->offset($num_rows)
					->limit($batch)
					->get();

				foreach ($query as $row) {
					$total_entries = DB::table('weblog_entries')
						->where('author_id', $row->member_id)
						->count();

					DB::table('members')
						->where('id', $row->member_id)
						->update(
						[
							'total_entries' => $total_entries
						]
					);
				}
			}
		}

        if ($table == 'weblog_entries') {
			$total_rows = DB::table('weblog_entries')->count();
		}

        Cp::$title = __('kilvin::admin.utilities');
        Cp::$crumb = Cp::anchor('administration/utilities', __('kilvin::admin.utilities')).
            Cp::breadcrumbItem(Cp::anchor('administration/utilities/recount-statistics', __('kilvin::admin.recalculate'))).
            Cp::breadcrumbItem(__('kilvin::admin.recounting'));


        $r = <<<EOT

	<script type="text/javascript">

        function standby()
        {
			if ($('#batchlink').css('display') == 'block') {
				$('#batchlink').css('display', 'none');
				$('#wait').css('display', 'block');
        	}
        }

	</script>
EOT;

		$r .= PHP_EOL.PHP_EOL;

        $r .= Cp::quickDiv('tableHeading', __('kilvin::admin.recalculate'));
        $r .= Cp::div('success-message');

		if ($num_rows === FALSE) {
			$total_done = 0;
		} else {
			$total_done = $num_rows + $batch;
		}

        if ($total_done >= $total_rows) {
            $r .= __('kilvin::admin.recount_completed');
            $r .= Cp::quickDiv(
                'littlePadding',
                Cp::anchor(
                    'administration/utilities/recount-statistics',
                    __('kilvin::admin.return_to_recount_overview')
                )
            );
        } else {
			$r .= Cp::quickDiv('littlePadding', __('kilvin::admin.total_records').NBS.$total_rows);
			$r .= Cp::quickDiv('itemWrapper', __('kilvin::admin.items_remaining').NBS.($total_rows - $total_done));

            $line = __('kilvin::admin.click_to_recount');

        	$to = (($total_done + $batch) >= $total_rows) ? $total_rows : ($total_done + $batch);

            $line = str_replace("%x", $total_done, $line);
            $line = str_replace("%y", $to, $line);

            $link = "<a href='".
                'administration/utilities/perform-recount'.
                    '/TBL='.$table.
                    '/T='.$total_done.
                "'  onclick='standby();'><b>".$line."</b></a>";

			$r .= '<div id="batchlink" style="display: block; padding:0; margin:0;">';
            $r .= Cp::quickDiv('littlePadding', BR.$link);
			$r .= '</div>'.PHP_EOL;


			$r .= '<div id="wait" style="display: none; padding:0; margin:0;">';
			$r .= BR.__('kilvin::admin.standby_recount');
			$r .= '</div>'.PHP_EOL;

        }

		$r .= '</div>'.PHP_EOL;

        Cp::$body = $r;
   }

   /**
    * PHP Info display!
    *
    * @return string
    */
    public function php_info()
    {
        phpinfo();
        exit;
    }
}
