<?php

namespace Kilvin\Cp;

use Kilvin\Facades\Cp;
use Illuminate\Support\Facades\DB;
use Kilvin\Facades\Site;
use Illuminate\Support\Facades\Request;
use Carbon\Carbon;
use Kilvin\Core\Session;
use Kilvin\Core\Localize;

class Home
{
	public $methods 	= [];

   /**
    * Constructor
    *
    * @return  void
    */
    public function __construct()
    {
        $this->methods = [
			'recentEntries',
			'siteStatistics',
			'notepad'
		];

		if (Session::access('can_access_admin') === true) {
			$this->methods[] = 'recentMembers';
			$this->methods[] = 'memberSearchForm';
		}
	}

   /**
    * Process Home section request
    *
    * @return  string
    */
    public function run()
    {
        switch(Cp::segment(2)) {
            case 'updateNotepad'		: return $this->updateNotepad();
				break;
            default	 					: return $this->homepage();
				break;
		}
    }

   /**
    * Build CP Homepage
    *
    * @return  string
    */
    public function homepage()
    {
		Cp::$title = __('kilvin::cp.homepage');

		// ------------------------------------
		//  Fetch the user display prefs
		// ------------------------------------

		// We'll fill two arrays.  One containing the left side options, the other containing the right side

		$left 	= [];
		$right 	= [];

		$query = DB::table('homepage_widgets')
			->where('member_id', Session::userdata('member_id'))
			->orderBy('order')
			->orderBy('name')
			->get();

		foreach ($query as $row) {
			if ($row->column === 'l') {
				$left[] = $row->name;
			}

			if ($row->column === 'r') {
				$right[] = $row->name;
			}
		}

		Cp::$body	.=	Cp::table('', '0', '0', '100%');

		// ------------------------------------
		//  Build the left page display
		// ------------------------------------

        if (count($left) > 0) {
			Cp::$body	.=	'<tr>'.PHP_EOL;
			Cp::$body	.=	Cp::td('leftColumn', '50%', '', '', 'top');

        	foreach ($left as $method) {
        		if (in_array($method, $this->methods)) {
        			Cp::$body .= $this->$method();
					Cp::$body .= Cp::quickDiv('defaultSmall', '');
        		}
        	}

			Cp::$body	.=	'</td>'.PHP_EOL;
        }

		// ------------------------------------
		//  Build the right page display
		// ------------------------------------

        if (count($right) > 0)
        {
			Cp::$body	.=	Cp::td('rightColumn', '50%', '', '', 'top');

        	foreach ($right as $method) {
        		if (in_array($method, $this->methods)) {
        			Cp::$body .= $this->$method();
					Cp::$body .= Cp::quickDiv('defaultSmall', '');
        		}
        	}

			Cp::$body	.=	'</td>'.PHP_EOL;
        }

		Cp::$body	.=	'</tr>'.PHP_EOL;
		Cp::$body	.=	'</table>'.PHP_EOL;
    }


   /**
    * Display Recent Entries
    *
    * @return  string
    */
   	private function recentEntries()
    {
		$query = DB::table('weblog_entries')
			->join('weblogs', 'weblogs.id', '=', 'weblog_entries.weblog_id')
			->join('weblog_entry_data', 'weblog_entry_data.weblog_entry_id', '=', 'weblog_entries.id')
			->select(
				'weblog_entries.weblog_id',
				'weblog_entries.author_id',
				'weblog_entries.id AS entry_id',
				'weblog_entry_data.title');

		// Probably make this a method
		if (Session::userdata('member_group_id') != 1) {
			if ( ! Session::access('can_view_other_entries') AND
				 ! Session::access('can_edit_other_entries') AND
				 ! Session::access('can_delete_all_entries')) {
				$query->where('weblog_entries.author_id', Session::userdata('member_id'));
			}

			$allowed_blogs = array_keys(Session::userdata('assigned_weblogs'));

			// If the user is not assigned a weblog we want the
			// query to return false, so we'll use a dummy ID number

			if (count($allowed_blogs) == 0) {
				$query->where('weblog_entries.weblog_id', 0);
			} else {
				$query->whereIn('weblog_entries.weblog_id', $allowed_blogs);
			}
		}

        $query = $query->orderBy('entry_date')
        	->limit(10)
        	->get();

		// ------------------------------------
		//  Table Header
		// ------------------------------------

        $r  = Cp::table('tableBorder', '0', '0', '100%').
              '<tr>'.PHP_EOL.
              Cp::tableCell('tableHeading', __('kilvin::home.most_recent_entries')).
              '</tr>'.PHP_EOL;

		// ------------------------------------
		//  Table Rows
		// ------------------------------------

        if ($query->count() == 0) {
			$r .= Cp::tableQuickRow('',
				[
					__('kilvin::no_entries')
				]
			);
        } else {
			foreach ($query as $row)
			{
				$r .= Cp::tableQuickRow(
					'',
					Cp::quickSpan(
						'defaultBold',
						Cp::anchor(
							'publish/entry-form/entry_id='.$row->entry_id,
							$row->title)
					)
				);
			}
        }

        $r .= '</table>'.PHP_EOL;

    	return $r;
	}

   /**
    * Display Recent Members
    *
    * @return  string
    */
    private function recentMembers()
    {
    	$query = DB::table('members')
    		->select('members.id AS member_id', 'screen_name', 'member_group_id', 'join_date')
    		->orderBy('join_date', 'desc')
    		->limit(10)
    		->get();

		// ------------------------------------
		//  Table Header
		// ------------------------------------

        $r  = Cp::table('tableBorder', '0', '0', '100%').
			'<tr>'.PHP_EOL.
				Cp::tableCell(
					'tableHeading',
					[
						__('kilvin::home.recent_members'),
						__('kilvin::home.join_date')
					]
				).
			'</tr>'.PHP_EOL;

		// ------------------------------------
		//  Table Rows
		// ------------------------------------

		foreach ($query as $row) {
			$r .= Cp::tableQuickRow(
				'',
				[
					Cp::quickSpan(
						'defaultBold',
						Cp::anchor(
							'account/id='.$row->member_id,
							$row->screen_name
						)
					),
					Localize::createHumanReadableDateTime($row->join_date)
				]
			);
		}

        $r .= '</table>'.PHP_EOL;

		return $r;
	}

   /**
    * Display Current Site's Statistics
    *
    * @return  string
    */
    private function siteStatistics()
    {
		// ------------------------------------
		//  Fetch stats
		// ------------------------------------

        $stats = DB::table('stats')
        	->where('site_id', Site::config('site_id'))
        	->select('total_entries')
        	->first();

		// ------------------------------------
		//  Table Header
		// ------------------------------------

        $r  = Cp::table('tableBorder', '0', '0', '100%').
              '<tr>'.PHP_EOL.
              Cp::tableCell('tableHeading',
              		[
              			__('kilvin::home.site_statistics'),
              			__('kilvin::home.value')
              		]
				).
				'</tr>'.PHP_EOL;


		if (Session::userdata('member_group_id') == 1) {
			$r .= $this->systemStatusRow();
			$r .= $this->systemVersionRow();
		}

		$r .= $this->totalEntriesRow($stats);

		if (Session::userdata('member_group_id') == 1) {
			$r .= $this->totalMembersRow();
		}

        $r .= '</table>'.PHP_EOL;

		return $r;
	}

   /**
    * Display CMS System Version
    *
    * @return  string
    */
	private function systemVersionRow()
	{
  		return Cp::tableQuickRow(
  			'',
			[
				Cp::quickSpan('defaultBold', __('kilvin::cp.cms_version')),
				KILVIN_VERSION
			]
		);
	}

   /**
    * Total Members in System
    *
    * @return  string
    */
	private function totalMembersRow()
	{
    	$count = DB::table('members')->count();

		return Cp::tableQuickRow(
			'',
			[
				Cp::quickSpan('defaultBold', __('kilvin::home.total_members')),
				$count
			]
		);
	}

   /**
    * Display Total Weblog Entries
    *
    * @return  string
    */
	private function totalEntriesRow($stats)
	{
  		return Cp::tableQuickRow('',
  			[
				Cp::quickSpan('defaultBold', __('kilvin::home.total_entries')),
				$stats->total_entries
			]
		);
	}

   /**
    * System Status Details
    *
    * @return  string
    */
	private function systemStatusRow()
	{
  		$r = Cp::tableQuickRow(
  			'',
  			[
				Cp::quickSpan('defaultBold', __('kilvin::home.system_status')),
				(Site::config('is_system_on') === true) ?
					Cp::quickDiv('highlight_alt_bold', __('kilvin::home.online')) :
					Cp::quickDiv('highlight_bold', __('kilvin::home.offline'))
			]
		);

		$r .= Cp::tableQuickRow(
			'',
			[
				Cp::quickSpan('defaultBold', __('kilvin::home.site_status')),
				(Site::config('is_site_on') == 'y' && Site::config('is_system_on') == true) ?
					Cp::quickDiv('highlight_alt_bold', __('kilvin::home.online')) :
					Cp::quickDiv('highlight_bold', __('kilvin::home.offline'))
			]
		);

		return $r;
	}

   /**
    * Display a Simple Member Search Form
    *
    * @return  string
    */
    private function memberSearchForm()
    {
        $r = Cp::formOpen(['action' => 'members/do-member-search']);

        $r .= Cp::div('box');
		$r .= Cp::heading(__('kilvin::home.member_search') ,5);
		$r .= Cp::quickDiv('littlePadding', __('kilvin::home.search_instructions'));
		$r .= Cp::quickDiv('littlePadding', Cp::input_text('keywords', '', '35', '100', 'input', '100%'));

        $r .= Cp::input_select_header('criteria');
        $r .= Cp::input_select_option('screen_name', 	__('kilvin::home.search_by'));
		$r .= Cp::input_select_option('screen_name', 	__('kilvin::account.screen_name'));
		$r .= Cp::input_select_option('email',			__('kilvin::cp.email_address'));
		$r .= Cp::input_select_option('url', 			__('kilvin::account.url'));
		$r .= Cp::input_select_option('ip_address', 	__('kilvin::cp.ip_address'));

		$query = DB::table('member_fields')
			->orderBy('field_name')
			->select('field_name', 'field_handle')
			->get();

		if ($query->count() > 0) {
			$r .= Cp::input_select_option('screen_name', '---');

			foreach($query as $row) {
				$r .= Cp::input_select_option('m_field_'.$row->field_handle, $row->field_name);
			}
		}

        $r .= Cp::input_select_footer();

        // Member group select list
        $query = DB::table('member_groups')
        	->select('member_groups.id AS member_group_id', 'group_name')
        	->orderBy('group_name');

		if (Session::userdata('member_group_id') != '1') {
			$query = $query->where('member_group_id', '!=', 1);
        }

        $query = $query->get();

        $r.= Cp::input_select_header('member_group_id');

        $r.= Cp::input_select_option('any', __('kilvin::members.member_group'));
        $r.= Cp::input_select_option('any', __('kilvin::cp.any'));

        foreach ($query as $row) {
            $r .= Cp::input_select_option($row->member_group_id, $row->group_name);
        }

        $r .= Cp::input_select_footer();

        $r .= '&nbsp;'.__('kilvin::home.exact_match').'&nbsp;'.Cp::input_checkbox('exact_match', 'y').'&nbsp;';

        $r.= Cp::input_submit(__('kilvin::cp.submit'));

        $r.= '</div>'.PHP_EOL;

        $r.= '</form>'.PHP_EOL;

        return $r;
	}

   /**
    * Display Notepad form
    *
    * @return  string
    */
    private function notepad()
    {
        $query = DB::table('members')
        	->where('id', Session::userdata('member_id'))
        	->select('notepad', 'notepad_size')
        	->first();

		return
			 Cp::formOpen(['action' => 'home/updateNotepad'])
			.Cp::quickDiv('tableHeading', __('kilvin::home.notepad'))
			.Cp::input_textarea('notepad', $query->notepad, 10, 'textarea', '100%')
			.Cp::quickDiv('littlePadding', Cp::input_submit(__('kilvin::cp.update')))
			.'</form>'.PHP_EOL;
	}

   /**
    * Update Notepad Data
    *
    * @return \Illuminate\Http\RedirectResponse
    */
    private function updateNotepad()
    {
        DB::table('members')
        	->where('id', Session::userdata('member_id'))
        	->update(['notepad' => Request::input('notepad')]);

        return redirect('?');
    }
}
