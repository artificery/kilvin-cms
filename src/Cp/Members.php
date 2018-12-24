<?php

namespace Kilvin\Cp;

use Kilvin\Facades\Cp;
use Illuminate\Support\Facades\DB;
use Kilvin\Facades\Site;
use Illuminate\Support\Facades\Hash;
use Kilvin\Facades\Stats;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Request;
use Kilvin\Facades\Plugins;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;
use Kilvin\Core\Regex;
use Kilvin\Core\Session;
use Kilvin\Core\Localize;
use Kilvin\Core\ValidateAccount;

class Members
{
    public $default_groups = ['Members', 'Admins'];

    public $perpage = 50;  // Number of results on the "View all member" page

    private $no_delete = [1]; // Member groups that can not be deleted

    public static $group_preferences = [

        'cp_site_cp_access_privs'       => null,  // Site specific

        'cp_site_offline_privs'         =>  null, // Site specific

        'mbr_account_privs' => [
            'include_in_authorlist'     => 'n',
            'can_delete_self'           => 'n',
            'mbr_delete_notify_emails'  => '',
        ],

        'cp_section_access' => [
            'can_access_content'        => 'n',
            'can_access_templates'      => 'n',
            'can_access_plugins'        => 'n',
            'can_access_admin'          => 'n'
        ],

        'cp_admin_privs' => [
            'can_admin_weblogs'         => 'n',
            'can_edit_categories'       => 'n',
            'can_admin_templates'       => 'n',
            'can_admin_members'         => 'n',
            'can_admin_utilities'       => 'n',
            'can_admin_preferences'     => 'n',
            'can_admin_plugins'         => 'n',
            'can_admin_asset_containers' => 'n',
        ],

        'cp_weblog_privs' =>
        [
            'can_view_other_entries'   => 'n',
            'can_delete_self_entries'  => 'n',
            'can_edit_other_entries'   => 'n',
            'can_delete_all_entries'   => 'n',
            'can_assign_post_authors'  => 'n',
        ],

        'cp_weblog_post_privs' =>  null,

        'cp_plugin_access_privs'   =>  null,
    ];


   /**
    * Constructor
    *
    * @return void
    */
    public function __construct()
    {
    }

   /**
    * Request Handler
    *
    * @return mixed
    */
    public function run()
    {
        if ( ! Session::access('can_admin_members')) {
            return Cp::unauthorizedAccess();
        }

        if (Cp::segment(2)) {
            if (method_exists($this, camel_case(Cp::segment(2)))) {
                return $this->{camel_case(Cp::segment(2))}();
            }
        }


        return Cp::unauthorizedAccess();
    }

   /**
    * View All Members page
    *
    * @param string $message Did something happen?!
    * @return string
    */
    public function listMembers($message = '')
    {
        // These variables are only set when one of the pull-down menus is used
        // We use it to construct the SQL query with
        $member_group_id = Request::input('member_group_id') ?? Cp::pathVar('member_group_id');
        $order      = Request::input('order');

        $total_members = DB::table('members')->count();

        // Begin building the page output
        $right_links[] = [
            'members/member-search',
            __('kilvin::members.new_member_search')
        ];

        $r  = Cp::header(__('kilvin::admin.view-members'), $right_links);

        if ($message != '') {
            $r .= Cp::quickDiv('success-message', $message);
        }

        // Declare the "filtering" form
        $r .= Cp::formOpen(['action' => 'members/list-members']);

        // Table start
        $r .= Cp::div('box');
        $r .= Cp::table('', '0', '', '100%').
              '<tr>'.PHP_EOL.
              Cp::td('littlePadding', '', '5').PHP_EOL;

        // Member group selection pull-down menu
        $r .= Cp::input_select_header('member_group_id').
              Cp::input_select_option('', __('kilvin::admin.member-groups')).
              Cp::input_select_option('', __('kilvin::cp.all'));

        // Fetch the names of all member groups and write each one in an <option> field
        $query = DB::table('member_groups')
            ->select('group_name', 'id AS member_group_id')
            ->orderBy('group_name')
            ->get();

        foreach ($query as $row)
        {
            $r .= Cp::input_select_option($row->member_group_id, $row->group_name, ($member_group_id == $row->member_group_id) ? 1 : '');
        }

        $r .= Cp::input_select_footer().'&nbsp;';

        // "display order" pull-down menu
        $sel_1  = ($order == 'desc')              ? 1 : '';
        $sel_2  = ($order == 'asc')               ? 1 : '';
        $sel_5  = ($order == 'screen_name')       ? 1 : '';
        $sel_6  = ($order == 'screen_name_desc')  ? 1 : '';
        $sel_7  = ($order == 'email')             ? 1 : '';
        $sel_8  = ($order == 'email_desc')        ? 1 : '';

        $r .= Cp::input_select_header('order').
              Cp::input_select_option('desc',  __('kilvin::admin.sort_order'), $sel_1).
              Cp::input_select_option('asc',   __('kilvin::publish.ascending'), $sel_2).
              Cp::input_select_option('desc',  __('kilvin::publish.descending'), $sel_1).
              Cp::input_select_option('screen_name_asc', __('kilvin::members.screen_name_asc'), $sel_5).
              Cp::input_select_option('screen_name_desc', __('kilvin::members.screen_name_desc'), $sel_6).
              Cp::input_select_option('email_asc', __('kilvin::members.email_asc'), $sel_7).
              Cp::input_select_option('email_desc', __('kilvin::members.email_desc'), $sel_8).
              Cp::input_select_footer().
              '&nbsp;';


        // Submit button and close filtering form

        $r .= Cp::input_submit(__('kilvin::cp.submit'), 'submit');

        $r .= '</td>'.PHP_EOL.
              Cp::td('defaultRight', '', 2).
              Cp::heading(__('kilvin::members.total_members').NBS.$total_members.NBS, 5).
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL.
              '</table>'.PHP_EOL;

        $r .= '</div>'.PHP_EOL;
        $r .= '</form>'.PHP_EOL;

        // Build the SQL query as well as the query string for the paginate links
        $pageurl = 'members/list-members';

        if ($member_group_id) {
            $total_count = DB::table('members')
                ->where('member_group_id', $member_group_id)
                ->count();
        } else {
            $total_count = $total_members;
        }

        // No result?  Show the "no results" message
        if ($total_count == 0) {
            $r .= Cp::quickDiv('', __('kilvin::members.no_members_matching_that_criteria'));

			Cp::$title = __('kilvin::admin.view-members');
			Cp::$body  = $r;
			Cp::$crumb = __('kilvin::admin.view-members');

			return;
        }

        // Get the current row number and add the LIMIT clause to the SQL query
        if ( ! $rownum = Request::input('rownum')) {
            $rownum = 0;
        }

        $base_query = DB::table('members')
            ->join('member_groups', 'members.member_group_id', '=', 'member_groups.id')
            ->offset($rownum)
            ->limit($this->perpage)
            ->select(
                'members.id AS member_id',
                'members.screen_name',
                'members.email',
                'members.join_date',
                'members.last_activity',
                'member_groups.group_name'
            );

        if ($member_group_id) {
            $base_query->where('members.member_group_id', $member_group_id);

            $pageurl .= '/member_group_id='.$member_group_id;
        }

        if ($order) {
            $pageurl .= '/order='.$order;

            switch ($order) {
                case 'asc'              : $base_query->orderBy('join_date', 'sac');
                    break;
                case 'desc'             : $base_query->orderBy('join_date', 'desc');
                    break;
                case 'screen_name_asc'  : $base_query->orderBy('screen_name', 'asc');
                    break;
                case 'screen_name_desc' : $base_query->orderBy('screen_name', 'desc');
                    break;
                case 'email_asc'        : $base_query->orderBy('email', 'asc');
                    break;
                case 'email_desc'       : $base_query->orderBy('email', 'desc');
                    break;
                default                 : $base_query->orderBy('join_date', 'desc');
            }
        } else {
            $base_query->orderBy('join_date', 'desc');
        }

        $query = $base_query->get();

        // Magic Checkboxes JS
        $r .= Cp::magicCheckboxesJavascript();

        // Declare the "delete" form
        $r .= Cp::formOpen(
            [
                'action' => 'members/delete-member-confirm',
                'name'   => 'target',
                'id'     => 'target'
            ]
        );

        // Build the table heading
        $r .= Cp::table('tableBorder row-hover', '0', '', '100%').
            '<tr>'.PHP_EOL.
                Cp::tableCell('tableHeadingAlt', __('kilvin::account.screen_name')).
                Cp::tableCell('tableHeadingAlt', __('kilvin::account.email')).
                Cp::tableCell('tableHeadingAlt', __('kilvin::account.join_date')).
                Cp::tableCell('tableHeadingAlt', __('kilvin::account.last_activity')).
                Cp::tableCell('tableHeadingAlt', __('kilvin::admin.member_group')).
                Cp::tableCell('tableHeadingAlt', Cp::input_checkbox('toggle_all')).
            '</tr>'.
            PHP_EOL;

        // Loop through the query result and write each table row

        $i = 0;

        foreach($query as $row) {
            $r .= '<tr>'.PHP_EOL;

            // Screen name
            $r .= Cp::tableCell(
                '',
                Cp::anchor(
                    '/account/id='.$row->member_id,
                    '<b>'.$row->screen_name.'</b>'
                )
            );

            // Email
            $r .= Cp::tableCell(
                '',
                Cp::mailto($row->email, $row->email)
            );

            // Join date

            $r .= Cp::td('').
                  Localize::format('%Y', $row->join_date).'-'.
                  Localize::format('%m', $row->join_date).'-'.
                  Localize::format('%d', $row->join_date).
                  '</td>'.PHP_EOL;

            // Last visit date

            $r .= Cp::td('');

            if (!empty($row->last_activity)) {
                $r .= Localize::createHumanReadableDateTime($row->last_activity);
            } else {
                $r .= "--";
            }

            $r .= '</td>'.PHP_EOL;

            // Member group
            $r .= Cp::td('');
            $r .= $row->group_name;
            $r .= '</td>'.PHP_EOL;

            // Delete checkbox
            $r .= Cp::tableCell('', Cp::input_checkbox('toggle[]', $row->member_id, '', ' id="delete_box_'.$row->member_id.'"'));

            $r .= '</tr>'.PHP_EOL;

        } // End foreach


        $r .= '</table>'.PHP_EOL;

        $r .= Cp::table('', '0', '', '98%');
        $r .= '<tr>'.PHP_EOL.
              Cp::td();

        // Pass the relevant data to the paginate class so it can display the "next page" links

        $r .=  Cp::div('crumblinks').
               Cp::pager(
                            $pageurl,
                            $total_count,
                            $this->perpage,
                            $rownum,
                            'rownum'
                          ).
              '</div>'.PHP_EOL.
              '</td>'.PHP_EOL.
              Cp::td('defaultRight');

        $r .= Cp::input_submit(__('kilvin::cp.submit'));
        $r .= NBS.Cp::input_select_header('action');

        $r .= Cp::input_select_option('delete', __('kilvin::cp.delete_selected')).
              Cp::input_select_footer().
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        $r .= '</table>'.PHP_EOL.
              '</form>'.PHP_EOL;

        // Set output data
        Cp::$title = __('kilvin::admin.view-members');
        Cp::$crumb = Cp::anchor('administration/members-and-groups', __('kilvin::admin.members-and-groups')).
                      Cp::breadcrumbItem(__('kilvin::admin.view-members'));
        Cp::$body  = $r;
    }

   /**
    * Delete Member Confirmation page
    *
    * @return string
    */
    public function deleteMemberConfirm()
    {
        if ( ! Session::access('can_admin_members')) {
            return Cp::unauthorizedAccess();
        }

        $from_myaccount = false;
        $entries_exit = false;

        $data = Request::all();

        if (Cp::pathVar('mid') !== null) {
            $from_myaccount = true;
            $data['toggle'][] = Cp::pathVar('mid');
        }

        if (empty($data['toggle'])) {
            return $this->listMembers();
        }

        $r = Cp::formOpen(['action' => 'members/delete-members']);

        $damned = [];

        foreach ($data['toggle'] as $key => $val)
        {
            $r .= Cp::input_hidden('delete[]', $val);

            // Is the user trying to delete himself?
            if (Session::userdata('member_id') == $val) {
                return Cp::errorMessage(__('kilvin::members.can_not_delete_self'));
            }

            $damned[] = $val;
        }

        $r .= Cp::quickDiv('alertHeading', __('kilvin::members.delete_member'));
        $r .= Cp::div('box');

        if (sizeof($damned) == 1) {
            $r .= Cp::quickDiv('littlePadding', '<b>'.__('kilvin::members.delete_member_confirm').'</b>');

            $screen_name = DB::table('members')->where('id', $damned[0])->value('screen_name');

            $r .= Cp::quickDiv('littlePadding', Cp::quickDiv('highlight', $screen_name));
        } else {
            $r .= '<b>'.__('kilvin::members.delete_members_confirm').'</b>';
        }

        $r .= Cp::quickDiv('littlePadding', Cp::quickDiv('alert', __('kilvin::cp.action_can_not_be_undone')));

        // ------------------------------------
        //  Do the users being deleted have entries assigned to them?
        // ------------------------------------

        $count = DB::table('weblog_entries')
            ->whereIn('author_id', $damned)
            ->count();

        if ($count > 0) {
            $entries_exit = true;
            $r .= Cp::input_hidden('entries_exit', 'yes');
        }

       // ------------------------------------
        //  If so, fetch the member names for reassigment
        // ------------------------------------

        if ($entries_exit == TRUE)
        {
            $member_group_ids = DB::table('members')
                ->whereIn('id', $damned)
                ->pluck('member_group_id')
                ->all();

            $member_group_ids = array_unique($member_group_ids);

            // Find Valid Member Replacements
            $query = DB::table('members')
                ->select('members.id AS member_id', 'screen_name')
                ->leftJoin('member_groups', 'member_groups.member_group_id', '=', 'members.member_group_id')
                ->whereIn('member_groups.member_group_id', $member_group_ids)
                ->whereNotIn('members.id', $damned)
                ->where(function($q) {
                    $q->where('members.in_authorlist', 'y')->orWhere('member_groups.include_in_authorlist', 'y');
                })
                ->orderBy('screen_name', 'asc')
                ->get();

            if ($query->count() == 0)
            {
                $query = DB::table('members')
                    ->select('members.id AS member_id', 'screen_name')
                    ->where('member_group_id', 1)
                    ->whereNotIn('id', $damned)
                    ->orderBy('screen_name', 'asc')
                    ->get();
            }

            $r .= Cp::div('littlePadding');
            $r .= Cp::div('defaultBold');
            $r .= ($i == 1) ? __('kilvin::members.heir_to_member_entries') : __('kilvin::members.heir_to_members_entries');
            $r .= '</div>'.PHP_EOL;

            $r .= Cp::div('littlePadding');
            $r .= Cp::input_select_header('heir');

            foreach($query as $row)
            {
                $r .= Cp::input_select_option($row->member_id, $row->screen_name);
            }

            $r .= Cp::input_select_footer();
            $r .= '</div>'.PHP_EOL;
            $r .= '</div>'.PHP_EOL;
        }

        $r .= Cp::quickDiv('littlePadding', Cp::input_submit(__('kilvin::cp.delete'))).
              '</div>'.PHP_EOL.
              '</form>'.PHP_EOL;


        Cp::$title = __('kilvin::members.delete_member');
        Cp::$crumb = Cp::anchor('administration/members-and-groups', __('kilvin::admin.members-and-groups')).
                      Cp::breadcrumbItem(__('kilvin::members.delete_member'));
        Cp::$body  = $r;
    }

   /**
    * Login as Member [page] - SuperAdmins only
    *
    * @return string
    */
    public function loginAsMember()
    {
        if (Session::userdata('member_group_id') != 1) {
            return Cp::unauthorizedAccess();
        }

        if (($id = Cp::pathVar('mid')) === false) {
            return Cp::unauthorizedAccess();
        }

        if (Session::userdata('member_id') == $id) {
            return Cp::unauthorizedAccess();
        }

        // ------------------------------------
        //  Fetch member data
        // ------------------------------------

        // @todo - Can Access CP? That is now a member_group_preferences value
        $query = DB::table('members')
            ->select('account.screen_name', 'member_groups.can_access_cp')
            ->join('member_groups', 'member_groups.member_group_id', '=', 'members.member_group_id')
            ->where('id', $id)
            ->first();

        if (!$query){
            return Cp::unauthorizedAccess();
        }

        Cp::$title = __('kilvin::members.login_as_member');
        Cp::$crumb = Cp::anchor('administration/members-and-groups', __('kilvin::admin.members-and-groups')).
                      Cp::breadcrumbItem(__('kilvin::members.login_as_member'));


        // ------------------------------------
        //  Create Our Little Redirect Form
        // ------------------------------------

        $r  = Cp::formOpen(
            array('action' => 'members/do-login-as-member'),
            array('mid' => $id)
        );

        $r .= Cp::quickDiv('default', '', 'menu_contents');

        $r .= Cp::table('tableBorder', '0', '', '100%');

        $r .= '<tr>'.PHP_EOL.
              Cp::td('tableHeadingAlt', '', '2').__('kilvin::members.login_as_member').
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        $r .= '<tr>'.PHP_EOL.
              Cp::td('').
              Cp::quickDiv('alert', __('kilvin::cp.action_can_not_be_undone')).
              Cp::quickDiv('littlePadding', str_replace('%screen_name%', $query->screen_name, __('kilvin::members.login_as_member_description'))).
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        $r .= '<tr>'.PHP_EOL.
              Cp::td('');

        $r .= Cp::quickDiv('',
                        Cp::input_radio('return_destination', 'site', 1).'&nbsp;'.
                        __('kilvin::members.site_homepage')
                        );

        if ($query->can_access_cp == 'y')
        {
            $r .= Cp::quickDiv('',
                            Cp::input_radio('return_destination', 'cp').'&nbsp;'.
                            __('kilvin::members.control_panel')
                  );
        }

        $r .= Cp::quickDiv('',
                        Cp::input_radio('return_destination', 'other', '').'&nbsp;'.
                        __('kilvin::members.other').NBS.':'.NBS.Cp::input_text('other_url', Site::config('site_url'), '30', '80', 'input', '500px')
                        );

        $r .= '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL.
              '<tr>'.PHP_EOL.
              Cp::td('').
              Cp::quickDiv('littlePadding', Cp::input_submit(__('kilvin::cp.submit'), 'submit')).
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL.
              '</table>'.PHP_EOL.
              '</div>'.PHP_EOL;

        Cp::$body = $r;
    }

   /**
    * Login as Member [action] - SuperAdmins only
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
    public function doLoginAsMember()
    {
        // You lack the power, mortal!
        if (Session::userdata('member_group_id') != 1) {
            return Cp::unauthorizedAccess();
        }

        if (($id = Cp::pathVar('mid')) === null) {
            return Cp::unauthorizedAccess();
        }

        // ------------------------------------
        //  Determine Return Path
        // ------------------------------------

        $return_path = Site::config('site_url');

        if (Request::input('return_destination'))
        {
            if (Request::input('return_destination') == 'cp')
            {
                $return_path = Site::config('cp_url', FALSE);
            }

            if (Request::input('return_destination') == 'other')
            {
                if (stristr(Request::input('other_url'), 'http')) {
                    $return_path = strip_tags(Request::input('other_url'));
                }
            }
        }

        // ------------------------------------
        //  Log Them In and Boot up new Session Data
        // ------------------------------------

        // Already logged in as that member
        if (Session::userdata('member_id') == $id) {
            return redirect($return_path);
        }

        Auth::loginUsingId($id);

        Session::boot();

        // ------------------------------------
        //  Determine Redirect Path
        // ------------------------------------

        return redirect($return_path);
    }

   /**
    * Delete Members
    *
    * @return string
    */
    public function deleteMembers()
    {
        if ( ! Session::access('can_admin_members')) {
            return Cp::unauthorizedAccess();
        }

        if ( ! Request::input('delete')) {
            return $this->listMembers();
        }

        // ------------------------------------
        //  Fetch member ID numbers and build the query
        // ------------------------------------

        $mids = [];

        if (Request::input('delete') && is_array(Request::input('delete'))) {
            $mids = Request::input('delete');
        }

        if (empty($mids)) {
            return $this->listMembers();
        }

        // SAFETY CHECK
        // SuperAdmin deletion requires logged in member be a SuperAdmin

        $admins = 0;

        $query = DB::table('members')
            ->select('member_group_id')
            ->whereIn('id', $mids)
            ->get();

        foreach ($query as $row) {
            if ($row->member_group_id == 1) {
                $admins++;
            }
        }

        if ($admins > 0) {
            if (Session::userdata('member_group_id') != 1) {
                return Cp::errorMessage(__('kilvin::members.must_be_admin_to_delete_one'));
            }

            // You can't detete the only Admin
            $total_count = DB::table('members')
                ->where('member_group_id', 1)
                ->count();

            if ($admins >= $total_count) {
                return Cp::errorMessage(__('kilvin::members.can_not_delete_admin'));
            }
        }

        // If we got this far we're clear to delete the members
        $deletes = [
            'members' => 'id',
            'member_data' => 'member_id',
            'homepage_widgets' => 'member_id'
        ];

        foreach($deletes as $table => $field) {
            DB::table($table)->whereIn($field, $mids)->delete();
        }

        // ------------------------------------
        //  Reassign Entires to Heir
        // ------------------------------------

        $heir_id      = Request::input('heir');
        $entries_exit = Request::input('entries_exit');

        if ($heir_id !== FALSE && is_numeric($heir_id))
        {
            if ($entries_exit == 'yes')
            {
                DB::table('weblog_entries')
                    ->whereIn('author_id', $mids)
                    ->update(['author_id' => $heir_id]);

                $query = DB::table('weblog_entries')
                    ->where('author_id', $heir_id)
                    ->select(DB::raw('COUNT(entry_id) AS count, MAX(entry_date) AS entry_date'))
                    ->first();

                DB::table('members')
                    ->where('id', $heir_id)
                    ->update(['total_entries' => $query->count, 'last_entry_date' => $query->entry_date]);
            }
        }

        // Update global stats

        Stats::update_member_stats();

        $message = (count($mids) == 1) ?
            __('kilvin::members.member_deleted') :
            __('kilvin::members.members_deleted');

        return $this->listMembers($message);
    }

   /**
    * List Member Groups
    *
    * @return string
    */
    public function memberGroupManager()
    {
        $row_limit = 20;
        $paginate = '';

        $message = session()->pull('cp-message');

        if ( ! Session::access('can_admin_members')) {
            return Cp::unauthorizedAccess();
        }

        $query = DB::table('member_groups')
            ->select('id AS member_group_id', 'member_groups.*')
            ->orderBy('group_name');

        $count_query = clone $query;
        $count = $count_query->count();

        if ($count > $row_limit)
        {
            $row_count = ( ! Request::input('row')) ? 0 : Request::input('row');

            $paginate = Cp::pager(
            	'members/member-group-manager',
				  $count,
				  $row_limit,
				  $row_count,
				  'row'
			);

            $query->offset($row_count)->limit($row_limit);
        }

        $query = $query->get();

        Cp::$title  = __('kilvin::admin.member-groups');
        Cp::$crumb  = Cp::anchor('administration/members-and-groups', __('kilvin::admin.members-and-groups')).
                       Cp::breadcrumbItem(__('kilvin::admin.member-groups'));

        $right_links[] = [
            'members/edit-member-group',
            __('kilvin::members.create_new_member_group')
        ];

        Cp::$body = Cp::header(__('kilvin::admin.member-groups'), $right_links);

        if ($message != '') {
            Cp::$body .= Cp::quickDiv('success-message', $message);
        }

        Cp::$body .= Cp::table('tableBorder', '0', '', '100%').
                      '<tr>'.PHP_EOL.
                      Cp::tableCell(
                        'tableHeadingAlt',
                        [
                            __('kilvin::members.member_group_id'),
                            __('kilvin::members.group_name'),
                            __('kilvin::cp.edit'),
                            __('kilvin::members.member_count'),
                            __('kilvin::cp.delete')
                        ]).
                      '</tr>'.PHP_EOL;


        $i = 0;

        foreach($query as $row)
        {
            Cp::$body .= '<tr>'.PHP_EOL;
            Cp::$body .= Cp::tableCell('', $row->member_group_id, '5%');

            $title = $row->group_name;

            Cp::$body .= Cp::tableCell('', Cp::quickSpan('defaultBold', $title), '35%');

            Cp::$body .= Cp::tableCell(
            	'',
            	Cp::anchor('members/edit-member-group/member_group_id='.$row->member_group_id, __('kilvin::cp.edit')),
            	'20%'
            );

            $member_group_id = $row->member_group_id;
            $total_count = DB::table('members')
                ->where('member_group_id', $member_group_id)
                ->count();

            Cp::$body .= Cp::tableCell(
                '',
                '('.$total_count.')'.
                NBS.
                Cp::anchor(
                    'members/list-members'.
                        '/member_group_id='.$row->member_group_id,
                    __('kilvin::cp.view')
                ),
                '15%');

            $delete = ( ! in_array($row->member_group_id, $this->no_delete)) ?
                Cp::anchor(
                    'members/delete-member-group-confirm'.
                        '/member_group_id='.$row->member_group_id, __('kilvin::cp.delete')) :
                '--';

            Cp::$body .= Cp::tableCell('',  $delete, '10%');

            Cp::$body .= '</tr>'.PHP_EOL;
        }

        Cp::$body .= '</table>'.PHP_EOL;

        if ($paginate != '')
        {
            Cp::$body .= Cp::quickDiv('littlePadding', Cp::quickDiv('defaultBold', $paginate));
        }

        Cp::$body .= Cp::formOpen(['action' => 'members/edit-member-group']);

        Cp::$body .= Cp::div('box');
        Cp::$body .= NBS.__('kilvin::members.create_group_based_on_existing');
        Cp::$body .= Cp::input_select_header('clone_id');

        foreach($query as $row)
        {
            Cp::$body .= Cp::input_select_option($row->member_group_id, $row->group_name);
        }

        Cp::$body .= Cp::input_select_footer();
        Cp::$body .= '&nbsp;'.Cp::input_submit();
        Cp::$body .= '</div>'.PHP_EOL;
        Cp::$body .= '</form>'.PHP_EOL;
    }

   /**
    * Edit or Create Member Group Form
    *
    * @return string
    */
    public function editMemberGroup()
    {
        // ------------------------------------
        //  Only super admins can administrate member groups
        // ------------------------------------

        if (Session::userdata('member_group_id') != 1) {
            return Cp::unauthorizedAccess(__('kilvin::members.only_admins_can_admin_groups'));
        }

        $msg = session()->pull('cp-message');

        $clone_id = Request::input('clone_id');

        $member_group_id = Request::input('member_group_id') ?? Cp::pathVar('member_group_id');

        $id = (!empty($clone_id)) ? $clone_id : $member_group_id;

        // ------------------------------------
        //  Fetch the Group's Data
        // ------------------------------------

        if (!empty($id)) {
            $group_data = (array) DB::table('member_groups')->where('id', $id)->first();
            $preferences = DB::table('member_group_preferences')->where('member_group_id', $id)->get();

            foreach($preferences as $row) {
                $group_data[$row->handle] = $row->value;
            }
        }

        if(empty($group_data['is_locked'])) {
            $group_data['is_locked'] = 'y';
        }

        // ------------------------------------
        //  Group title
        // ------------------------------------

        $group_name       = ($member_group_id == '') ? '' : $group_data['group_name'];
        $group_description = ($member_group_id == '') ? '' : $group_data['group_description'];

        if ($msg != '') {
            Cp::$body .= Cp::quickDiv('success-message', $msg);
        }

        // ------------------------------------
        //  Declare form and page heading
        // ------------------------------------

        $r  = Cp::formOpen(
            [
                'action' => 'members/update-member-group'
            ]
        );

        if ($clone_id != '')
        {
            $group_name = '';
            $group_description = '';
            $r .= Cp::input_hidden('clone_id', $clone_id);
        }

        $r .= Cp::input_hidden('member_group_id', $member_group_id);

        // ------------------------------------
        //  Group name form field
        // ------------------------------------

        $r .= '<div id="group_name_on">'.
              Cp::table('tableBorder', '0', '', '100%').
              '<tr>'.PHP_EOL.
              "<td class='tableHeadingAlt' colspan='2'>".
              NBS.__('kilvin::members.group_name').
              '</tr>'.PHP_EOL.
              '<tr>'.PHP_EOL.
              Cp::td('', '40%').
              Cp::quickDiv('defaultBold', __('kilvin::members.group_name')).
              '</td>'.PHP_EOL.
              Cp::td('', '60%').
              Cp::input_text('group_name', $group_name, '50', '70', 'input', '100%').
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL.
              '</tr>'.PHP_EOL.
              '<tr>'.PHP_EOL.
              Cp::td('', '40%', '', '', 'top').
              Cp::quickDiv('defaultBold', __('kilvin::members.group_description')).
              '</td>'.PHP_EOL.
              Cp::td('', '60%').
              Cp::input_textarea('group_description', $group_description, 10).
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL.
              '</table>'.PHP_EOL.
              Cp::quickDiv('defaultSmall', '');

        // ------------------------------------
        //  Top section of page
        // ------------------------------------

        if ($member_group_id == 1)
        {
            $r .= Cp::quickDiv('box', __('kilvin::members.admin_edit_note'));
        }

        $r .= Cp::quickDiv('defaultSmall', '');

        $r .= '</div>'.PHP_EOL;

        // ------------------------------------
        //  Group lock
        // ------------------------------------

        $r .= '<div id="group_lock_on">';

        $r .= Cp::table('tableBorder', '0', '', '100%');

        $r .= '<tr>'.PHP_EOL.
              Cp::td('tableHeadingAlt', '', '2').__('kilvin::members.group_lock').
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        $r .= '<tr>'.PHP_EOL.
              Cp::td('', '60%').
              Cp::quickDiv('alert', __('kilvin::members.enable_lock')).
              Cp::quickDiv('littlePadding', __('kilvin::members.lock_description')).
              '</td>'.PHP_EOL.
              Cp::td('', '40%');

        $selected = ($group_data['is_locked'] == 'y') ? true : false;

        $r .= __('kilvin::members.locked').NBS.
              Cp::input_radio('is_locked', 'y', $selected).'&nbsp;';

        $selected = ($group_data['is_locked'] == 'n') ? true : false;

        $r .= __('kilvin::members.unlocked').NBS.
              Cp::input_radio('is_locked', 'n', $selected).'&nbsp;';

        $r .= '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL.
              '</table>'.PHP_EOL.
              '</div>'.PHP_EOL;

        // ------------------------------------
        //  Fetch the names and IDs of all weblogs
        // ------------------------------------

        $weblog_names = [];
        $weblog_ids   = [];

        $query = DB::table('weblogs')
            ->orderBy('weblog_name')
            ->select('id AS weblog_id', 'weblog_name')
            ->get();

        if ($id != 1)
        {
            foreach($query as $row)
            {
                $field = 'weblog_id_'.$row->weblog_id;

                $status = (isset($group_data[$field]) && $group_data[$field] == 'y') ? 'y' : 'n';

                $weblog_names[$field] = $row->weblog_name;
                $group_data[$field] = $status;
            }
        }

        // ------------------------------------
        //  Fetch the names and IDs of all plugins
        // ------------------------------------

        $plugins      = Plugins::installedPlugins();
        $plugin_names = [];
        $plugin_ids   = [];

        if ($id != 1)
        {
            foreach(Plugins::installedPlugins() as $plugin_name => $details)
            {
                $field = 'plugin_name_'.$plugin_name;

                $status = (isset($group_data[$field]) && $group_data[$field] == 'y') ? 'y' : 'n';

                $plugin_names[$field] = $plugin_name;
                $group_data[$field] = $status;
            }
        }

        // ------------------------------------
        //  Fetch the names and IDs of all Sites
        // ------------------------------------

        $site_cp_names = [];
        $site_offline_names = [];
        $site_ids   = []; // Figure out where I am storing these

        if ($id != 1) {
            foreach(Site::sitesList() as $site) {

                $field = 'can_access_offline_site_id_'.$site->site_id;
                $site_offline_names[$field] = $site->site_name;
                $status = (isset($group_data[$field]) && $group_data[$field] == 'y') ? 'y' : 'n';
                $group_data[$field] = $status;

                $field = 'can_access_cp_site_id_'.$site->site_id;
                $site_cp_names[$field] = $site->site_name;
                $status = (isset($group_data[$field]) && $group_data[$field] == 'y') ? 'y' : 'n';
                $group_data[$field] = $status;
            }
        }

        // ------------------------------------
        //  Assign clusters of member groups
        //  - The associative value (y/n) is the default setting
        // ------------------------------------

        $G = static::$group_preferences;

        $G['cp_site_cp_access_privs']  = $site_cp_names;
        $G['cp_site_offline_privs']    = $site_offline_names;
        $G['cp_weblog_post_privs']     = $weblog_names;
        $G['cp_plugin_access_privs']   = $plugin_names;

        // ------------------------------------
        //  Admin Group cannot be edited
        // ------------------------------------

        if ($member_group_id == 1) {
            $G = ['mbr_account_privs' => ['include_in_authorlist' => 'n']];
        }

        // ------------------------------------
        //  Assign items we want to highlight
        // ------------------------------------

        $alert = [
            'can_view_offline_system',
            'can_access_cp',
            'can_admin_weblogs',
            'can_admin_templates',
            'can_admin_members',
            'can_admin_preferences',
            'can_admin_plugins',
            'can_admin_utilities',
            'can_edit_categories',
            'can_delete_self'
        ];

        // ------------------------------------
        //  Items that should be shown in an input box
        // ------------------------------------

        $tbox = [
            'mbr_delete_notify_emails'
        ];

        // ------------------------------------
        //  Render the group matrix
        // ------------------------------------

        $special = ['cp_plugin_access_privs', 'cp_site_offline_privs', 'cp_site_cp_access_privs'];

        foreach ($G as $g_key => $g_val)
        {
            // ------------------------------------
            //  Start the Table
            // ------------------------------------

            $r .= '<div id="'.$g_key.'_on">';
            $r .= Cp::table('tableBorder', '0', '', '100%');
            $r .= '<tr>'.PHP_EOL;

            $r .= "<td class='tableHeadingAlt' id='".$g_key."2' colspan='2'>";
            $r .= NBS.__('kilvin::members.'.$g_key);
            $r .= '</tr>'.PHP_EOL;

            $i = 0;

            foreach($g_val as $key => $val)
            {
                if ( !in_array($g_key, $special) && ! isset($group_data[$key])) {
                    $group_data[$key] = $val;
                }

                $line = __('kilvin::members.'.$key);

                if (substr($key, 0, strlen('weblog_id_')) == 'weblog_id_')
                {
                    $line = __('kilvin::members.can_post_in').Cp::quickSpan('alert', $weblog_names[$key]);
                }

                if (substr($key, 0, strlen('plugin_name_')) == 'plugin_name_')
                {

                    $line = __('kilvin::members.can_access_plugin').Cp::quickSpan('alert', $plugin_names[$key]);
                }

                if (substr($key, 0, strlen('can_access_offline_site_id_')) == 'can_access_offline_site_id_')
                {
                    $line = __('kilvin::members.can_access_offline_site').Cp::quickSpan('alert', $site_offline_names[$key]);
                }

                if (substr($key, 0, strlen('can_access_cp_site_id_')) == 'can_access_cp_site_id_')
                {
                    $line = __('kilvin::members.can_access_cp').Cp::quickSpan('alert', $site_cp_names[$key]);
                }

                $mark = (in_array($key, $alert)) ?  Cp::quickSpan('alert', $line) : Cp::quickSpan('defaultBold', $line);

                $r .= '<tr>'.PHP_EOL.
                      Cp::td('', '60%').
                      $mark;

                $r .= '</td>'.PHP_EOL.
                      Cp::td('', '40%');

                if (in_array($key, $tbox))
                {
                    $width = ($key == 'mbr_delete_notify_emails') ? '100%' : '100px';
                    $length = ($key == 'mbr_delete_notify_emails') ? '255' : '5';
                    $r .= Cp::input_text($key, $group_data[$key], '15', $length, 'input', $width);
                }
                else
                {
                    $r .= __('kilvin::cp.yes').NBS.
                          Cp::input_radio($key, 'y', ($group_data[$key] == 'y') ? 1 : '').'&nbsp;';

                    $r .= __('kilvin::cp.no').NBS.
                          Cp::input_radio($key, 'n', ($group_data[$key] == 'n') ? 1 : '').'&nbsp;';
                }

                $r .= '</td>'.PHP_EOL;
                $r .= '</tr>'.PHP_EOL;
            }

            $r .= '</table>'.PHP_EOL;
            $r .= '</div>'.PHP_EOL;
        }

        // ------------------------------------
        //  Submit button
        // ------------------------------------

        if (empty($member_group_id))
        {
            $r .= Cp::quickDiv(
                'paddingTop',
                Cp::input_submit(__('kilvin::cp.submit'))
                .NBS.
                Cp::input_submit(__('kilvin::cp.submit_and_return'),'return')
            );
        }
        else
        {
            $r .= Cp::quickDiv(
                'paddingTop',
                Cp::input_submit(__('kilvin::cp.update')).
                NBS.
                Cp::input_submit(__('kilvin::cp.update_and_return'), 'return')
            );
        }

        $r .= '</form>'.PHP_EOL;

        // ------------------------------------
        //  Compile all of it into output
        // ------------------------------------

        $title = (!empty($id)) ? __('kilvin::members.edit_member_group') : __('kilvin::members.create_member_group');

        // Create the Table
        $table_row = [
            'third'     => ['valign' => "top", 'text' => $r]
        ];

        Cp::$body .= Cp::tableRow($table_row).
                      '</table>'.PHP_EOL;

        Cp::$title = $title;

        if ($member_group_id != '')
        {
            Cp::$crumb =
                Cp::anchor(
                    'administration/members-and-groups',
                    __('kilvin::admin.members-and-groups')
                ).
                Cp::breadcrumbItem(
                    Cp::anchor(
                        'members/member-group-manager',
                        __('kilvin::admin.member-groups')
                    )
                ).
                Cp::breadcrumbItem($group_data['group_name']);
        }
        else
        {
            Cp::$crumb =
                Cp::anchor(
                    'administration/members-and-groups',
                    __('kilvin::admin.members-and-groups')
                ).
                Cp::breadcrumbItem(
                    Cp::anchor(
                        'members/member-group-manager',
                        __('kilvin::admin.member-groups')
                    )
                ).
                Cp::breadcrumbItem($title);
        }
    }

   /**
    * Create or Update Member Group Processing
    *
    * @return string
    */
    public function updateMemberGroup()
    {
        // ------------------------------------
        //  Only super admins can administrate member groups
        // ------------------------------------

        if (Session::userdata('member_group_id') != 1) {
            return Cp::unauthorizedAccess(__('kilvin::members.only_admins_can_admin_groups'));
        }

        $edit = (bool) Request::filled('member_group_id');

        $member_group_id = Request::input('member_group_id');
        $clone_id = Request::input('clone_id');

        // No group name
        if ( ! Request::input('group_name')) {
            return Cp::errorMessage(__('kilvin::members.missing_group_name'));
        }

        $return = (Request::filled('return'));

        $site_ids     = [];
        $plugin_ids   = [];
        $weblog_ids   = [];

        // ------------------------------------
        //  Remove and Store Weblog and Template Permissions
        // ------------------------------------

        $data = [
            'group_name'        => Request::input('group_name'),
            'group_description' => (string) Request::input('group_description'),
        ];

        $duplicate = DB::table('member_groups')
            ->where('group_name', $data['group_name']);

        if (!empty($member_group_id)) {
            $duplicate->where('id', '!=', $member_group_id);
        }

        if($duplicate->count() > 0) {
            return Cp::errorMessage(__('kilvin::members.duplicate_group_name'));
        }

        // ------------------------------------
        //  Preferences
        // ------------------------------------

        $preferences['member_group_id']  = $member_group_id;
        $preferences['is_locked'] = Request::input('is_locked');

        foreach(static::$group_preferences as $group => $prefs) {
            foreach((array) $prefs as $key => $default) {
                if (Request::filled($key)) {
                    $preferences[$key] = Request::get($key);
                }
            }
        }

        foreach (Request::all() as $key => $val)
        {
            if (substr($key, 0, strlen('weblog_id_')) == 'weblog_id_') {
                $preferences[$key] = ($val == 'y') ? 'y' : 'n';
            } elseif (substr($key, 0, strlen('plugin_name_')) == 'plugin_name_') {
                $preferences[$key] = ($val == 'y') ? 'y' : 'n';
            } elseif (substr($key, 0, strlen('can_access_offline_site_id_')) == 'can_access_offline_site_id_') {
                $preferences[$key] = ($val == 'y') ? 'y' : 'n';
            } elseif (substr($key, 0, strlen('can_access_cp_site_id_')) == 'can_access_cp_site_id_') {
                $preferences[$key] = ($val == 'y') ? 'y' : 'n';
            } else {
                continue;
            }
        }

        if ($edit === false) {
            $member_group_id = DB::table('member_groups')->insertGetId($data);

            foreach($preferences as $handle => $value) {
                $prefs =
                [
                    'member_group_id' => $member_group_id,
                    'handle'    => $handle,
                    'value'     => $value
                ];

                DB::table('member_group_preferences')->insert($prefs);
            }

            $message = __('kilvin::members.member_group_created').' '.Request::input('group_name');
        } else {
            DB::table('member_groups')
                ->where('id', $member_group_id)
                ->update($data);

            DB::table('member_group_preferences')
                ->where('member_group_id', $member_group_id)
                ->delete();

            foreach($preferences as $handle => $value) {
                $prefs =
                [
                    'member_group_id'  => $member_group_id,
                    'handle'    => $handle,
                    'value'     => $value
                ];

                DB::table('member_group_preferences')->insert($prefs);
            }

            $message = __('kilvin::members.member_group_updated').'&nbsp;'.Request::input('group_name');
        }

        // Update CP log
        Cp::log($message);

        $this->clearMemberGroupCache($member_group_id);

        if ($return == true) {
            return redirect(kilvinCpUrl('members/member-group-manager'))->with('cp-message', $message);
        }

        return redirect(
            kilvinCpUrl(
                'members/edit-member-group'.
                '/member_group_id='.$member_group_id
            )
        )->with('cp-message', $message);
    }

   /**
    * Delete Member Group confirmation form
    *
    * @return string
    */
    public function deleteMemberGroupConfirm()
    {
        // ------------------------------------
        //  Only super admins can delete member groups
        // ------------------------------------

        if (Session::userdata('member_group_id') != 1) {
            return Cp::unauthorizedAccess(__('kilvin::members.only_admins_can_admin_groups'));
        }


        if ( ! $member_group_id = Cp::pathVar('member_group_id')) {
            return false;
        }

        // You can't delete these groups
        if (in_array($member_group_id, $this->no_delete)) {
            return Cp::unauthorizedAccess();
        }

        // Are there any members that are assigned to this group?
        $count = DB::table('members')
                ->where('member_group_id', $member_group_id)
                ->count();

        $members_exist = (!empty($count)) ? true : false;

        $group_name = DB::table('member_groups')
            ->where('id', $member_group_id)
            ->value('group_name');

        Cp::$title = __('kilvin::members.delete_member_group');

        Cp::$crumb = Cp::anchor('administration/members-and-groups', __('kilvin::admin.members-and-groups')).
			Cp::breadcrumbItem(Cp::anchor('members/group-manager', __('kilvin::admin.member-groups'))).
			Cp::breadcrumbItem(__('kilvin::members.delete_member_group'));

        Cp::$body = Cp::formOpen(['action' => 'members/delete-member-group/member_group_id='.$member_group_id]).
            Cp::input_hidden('member_group_id', $member_group_id);

        Cp::$body .= ($members_exist === TRUE) ? Cp::input_hidden('reassign', 'y') : Cp::input_hidden('reassign', 'n');

        Cp::$body .= Cp::heading(Cp::quickSpan('alert', __('kilvin::members.delete_member_group')))
                     .Cp::div('box')
                     .Cp::quickDiv('littlePadding', '<b>'.__('kilvin::members.delete_member_group_confirm').'</b>')
                     .Cp::quickDiv('littlePadding', '<i>'.$group_name.'</i>')
                     .Cp::quickDiv('alert', BR.__('kilvin::cp.action_can_not_be_undone').BR.BR);

        if ($members_exist === true) {
            Cp::$body .= Cp::quickDiv('defaultBold', str_replace('%x', $count, __('kilvin::members.member_assignment_warning')));

            Cp::$body .= Cp::div('littlePadding');
            Cp::$body .= Cp::input_select_header('new_group_id');

            $query = DB::table('member_groups')
                ->select('group_name', 'id AS member_group_id')
                ->orderBy('group_name')
                ->get();

            foreach ($query as $row) {
                Cp::$body .= Cp::input_select_option($row->member_group_id, $row->group_name, '');
            }

            Cp::$body .= Cp::input_select_footer();
            Cp::$body .= '</div>'.PHP_EOL;
        }

        Cp::$body .= Cp::quickDiv('littlePadding', Cp::input_submit(__('kilvin::cp.delete')))
                    .'</div>'.PHP_EOL
                    .'</form>'.PHP_EOL;
    }

   /**
    * Delete a Member Group
    *
    * @return string
    */
    public function deleteMemberGroup()
    {
        //  Only super admins can delete member groups
        if (Session::userdata('member_group_id') != 1) {
            return Cp::unauthorizedAccess(__('kilvin::members.only_admins_can_admin_groups'));
        }

        if (!$member_group_id = Request::input('member_group_id')) {
            return false;
        }

        if (in_array($member_group_id, $this->no_delete)) {
            return Cp::unauthorizedAccess();
        }

        if (Request::input('reassign') == 'y' AND Request::input('new_group_id') !== null) {
            DB::table('members')
                ->where('member_group_id', $member_group_id)
                ->update(['member_group_id' => Request::input('new_group_id')]);
        }

        DB::table('member_groups')
            ->where('id', $member_group_id)
            ->delete();

        DB::table('member_group_preferences')
            ->where('member_group_id', $member_group_id)
            ->delete();

        $this->clearMemberGroupCache($member_group_id);

        return redirect(kilvinCpUrl('members/member-group-manager'))
            ->with('cp-message', __('kilvin::members.member_group_deleted'));
    }

   /**
    * New Member Form
    *
    * @return string
    */
    public function registerMember()
    {
        if ( ! Session::access('can_admin_members')) {
            return Cp::unauthorizedAccess();
        }

        Cp::$body_props = " onload=\"document.forms[0].email.focus();\"";

        $title = __('kilvin::members.register_member');

        // Build the output
        $r  = Cp::formOpen(['action' => 'members/create-member']);

        $r .= Cp::quickDiv('tableHeading', $title);
        $r .= Cp::div('box');
        $r .= Cp::itemgroup(
            Cp::required().NBS.__('kilvin::account.email'),
            Cp::input_text('email', '', '35', '32', 'input', '300px')
        );

        $r .= Cp::itemgroup(
            Cp::required().NBS.__('kilvin::account.password'),
            Cp::input_pass('password', '', '35', '32', 'input', '300px')
        );

        $r .= Cp::itemgroup(
            Cp::required().NBS.__('kilvin::account.password_confirm'),
            Cp::input_pass('password_confirm', '', '35', '32', 'input', '300px')
        );

        $r .= Cp::itemgroup(
            Cp::required().NBS.__('kilvin::account.screen_name'),
            Cp::input_text('screen_name', '', '40', '50', 'input', '300px')
        );

        // Member groups assignment
        if (Session::access('can_admin_members')) {
            $query = DB::table('member_groups')
                ->select('id AS member_group_id', 'group_name')
                ->orderBy('group_name');

            if (Session::userdata('member_group_id') != 1) {
                $query->where('is_locked', 'n');
            }

            $query = $query->get();

            if ($query->count() > 0)
            {
                $r .=
                    Cp::quickDiv(
                        'paddingTop',
                        Cp::quickDiv('defaultBold', __('kilvin::account.member_group_assignment'))
                );

                $r .= Cp::input_select_header('member_group_id');

                foreach ($query as $row)
                {
                    $selected = ($row->member_group_id == 5) ? 1 : '';

                    // Only SuperAdmins can assigned SuperAdmins
                    if ($row->member_group_id == 1 AND Session::userdata('member_group_id') != 1) {
                        continue;
                    }

                    $r .= Cp::input_select_option($row->member_group_id, $row->group_name, $selected);
                }

                $r .= Cp::input_select_footer();
            }
        }

        $r .= '</td></div>'.PHP_EOL;

        // Submit button

        $r .= Cp::itemgroup(
            '',
            Cp::required(1).'<br><br>'.Cp::input_submit(__('kilvin::cp.submit'))
        );
        $r .= '</form>'.PHP_EOL;


        Cp::$title = $title;
        Cp::$crumb = Cp::anchor('administration/members-and-groups', __('kilvin::admin.members-and-groups')).
                      Cp::breadcrumbItem($title);
        Cp::$body  = $r;
    }

   /**
    * New Member Creation
    *
    * @return string
    */
    public function createMember()
    {
        if ( ! Session::access('can_admin_members')) {
            return Cp::unauthorizedAccess();
        }

        $data = [];

        if (Request::filled('member_group_id')) {
            if ( ! Session::access('can_admin_members')) {
                return Cp::unauthorizedAccess();
            }

            $data['member_group_id'] = Request::input('member_group_id');
        }

        // ------------------------------------
        //  Instantiate validation class
        // ------------------------------------

        $VAL = new ValidateAccount(
            [
                'request_type'          => 'new', // new or update
                'require_password'      => false,
                'screen_name'           => Request::input('screen_name'),
                'password'              => Request::input('password'),
                'password_confirm'      => Request::input('password_confirm'),
                'email'                 => Request::input('email'),
            ]
        );

        $VAL->validateScreenName();
        $VAL->validateEmail();
        $VAL->validatePassword();

        // ------------------------------------
        //  Display error is there are any
        // ------------------------------------

        if (count($VAL->errors()) > 0) {
            return Cp::errorMessage($VAL->errors());
        }

        // Assign the query data
        $data['password']    = Hash::make(Request::input('password'));
        $data['ip_address']  = Request::ip();
        $data['unique_id']   = Uuid::uuid4();
        $data['join_date']   = Carbon::now();
        $data['email']       = Request::input('email');
        $data['screen_name'] = Request::input('screen_name');

        // Was a member group ID submitted?
        $data['member_group_id'] = ( ! Request::input('member_group_id')) ? 2 : Request::input('member_group_id');

        // Create records
        $member_id = DB::table('members')->insertGetId($data);

        $fields_data = ['member_id' => $member_id];

        $custom_fields = DB::table('member_fields')
            ->pluck('field_handle')
            ->all();

        foreach ($custom_fields as $field) {
            $fields_data['m_field_'.$field] = Request::input('m_field_'.$field, '');
        }

        DB::table('member_data')->insert($fields_data);

        $message = __('kilvin::account.new_member_added');

        // Write log file
        Cp::log($message.' '.$data['email']);

        // Update global stat
        Stats::update_member_stats();

        // Build success message
        return $this->listMembers($message.' <b>'.stripslashes($data['screen_name']).'</b>');
    }

   /**
    * Ban Members Form
    *
    * @return string
    */
    public function memberBanning()
    {
        if ( ! Session::access('can_admin_members')) {
            return Cp::unauthorizedAccess();
        }

        $banned_ips          = Site::config('banned_ips');
        $banned_emails       = Site::config('banned_emails');
        $banned_screen_names = Site::config('banned_screen_names');

        $out        = '';
        $ips        = '';
        $email      = '';
        $users      = '';
        $screens    = '';

        if ($banned_ips != '') {
            foreach (explode('|', $banned_ips) as $val) {
                $ips .= $val.PHP_EOL;
            }
        }

        if ($banned_emails != '')
        {
            foreach (explode('|', $banned_emails) as $val)
            {
                $email .= $val.PHP_EOL;
            }
        }

        if ($banned_screen_names != '')
        {
            foreach (explode('|', $banned_screen_names) as $val)
            {
                $screens .= $val.PHP_EOL;
            }
        }

        $r  = Cp::formOpen(['action' => 'members/save-banning-preferences']).
              Cp::quickDiv('tableHeading', __('kilvin::members.user_banning'));

        if (Cp::pathVar('U')) {
            $r .= Cp::quickDiv('success-message', __('kilvin::members.ban_preferences_updated'));
        }

        $r .=   Cp::table('', '', '', '100%', '').
                '<tr>'.PHP_EOL.
                Cp::td('', '48%', '', '', 'top');


        $r .=   Cp::div('box').
                Cp::heading(__('kilvin::members.ip_address_banning'), 5).
                Cp::quickDiv('littlePadding', Cp::quickSpan('highlight', __('kilvin::members.ip_banning_instructions'))).
                Cp::quickDiv('littlePadding', __('kilvin::members.ip_banning_instructions_cont')).
                Cp::input_textarea('banned_ips', stripslashes($ips), '22', 'textarea', '100%').BR.BR;

        $r .=   Cp::heading(BR.__('kilvin::members.ban_options'), 5);

        $selected = (Site::config('ban_action') == 'restrict') ? 1 : '';

        $r .=   Cp::div('littlePadding').
                '<label>'.
                	Cp::input_radio('ban_action', 'restrict', $selected).NBS. __('kilvin::members.restrict_to_viewing').
                '</label>'.
                BR.
                '</div>'.PHP_EOL;

        $selected    = (Site::config('ban_action') == 'message') ? 1 : '';

        $r .=   Cp::div('littlePadding').
        		'<label>'.
					Cp::input_radio('ban_action', 'message', $selected).NBS.__('kilvin::members.show_this_message').BR.
				'</label>'.
                Cp::input_text('ban_message', Site::config('ban_message'), '50', '100', 'input', '100%').
                '</div>'.PHP_EOL;

        $selected    = (Site::config('ban_action') == 'bounce') ? 1 : '';
        $destination = (Site::config('ban_destination') == '') ? 'https://' : Site::config('ban_destination');

        $r .=   Cp::div('littlePadding').
        		'<label>'.
					Cp::input_radio('ban_action', 'bounce', $selected).NBS.__('kilvin::members.send_to_site').BR.
				'</label>'.
                Cp::input_text('ban_destination', $destination, '50', '70', 'input', '100%').
                '</div>'.PHP_EOL;

        $r .=   Cp::div().BR.
                Cp::input_submit(__('kilvin::cp.update')).BR.BR.BR.
                '</div>'.PHP_EOL.
                '</div>'.PHP_EOL;

        $r .=   '</td>'.PHP_EOL.
                Cp::td('', '4%', '', '', 'top').NBS.
                '</td>'.PHP_EOL.
                Cp::td('', '48%', '', '', 'top');

        $r .=   Cp::div('box').
                Cp::heading(__('kilvin::members.email_address_banning'), 5).
                Cp::quickDiv('littlePadding', Cp::quickSpan('highlight', __('kilvin::members.email_banning_instructions'))).
                Cp::quickDiv('littlePadding', __('kilvin::members.email_banning_instructions_cont')).
                Cp::input_textarea('banned_emails', stripslashes($email), '9', 'textarea', '100%').
                '</div>'.PHP_EOL;

        $r .= Cp::quickDiv('defaultSmall', NBS);

        $r .=   Cp::div('box').
                Cp::heading(__('kilvin::members.screen_name_banning'), 5).
                Cp::quickDiv('littlePadding', Cp::quickSpan('highlight', __('kilvin::members.screen_name_banning_instructions'))).
                Cp::input_textarea('banned_screen_names', stripslashes($screens), '9', 'textarea', '100%').
                '</div>'.PHP_EOL;

        $r .=   '</td>'.PHP_EOL.
                '</tr>'.PHP_EOL.
                '</table>'.PHP_EOL;

        $r .= '</form>'.PHP_EOL;

        Cp::$title = __('kilvin::members.user_banning');
        Cp::$crumb = Cp::anchor('administration/members-and-groups', __('kilvin::admin.members-and-groups')).
                      Cp::breadcrumbItem(__('kilvin::members.user_banning'));
        Cp::$body  = $r;
    }

   /**
    * Save Banning Data
    *
    * @return string
    */
    public function saveBanningPreferences()
    {
        if ( ! Session::access('can_admin_members')) {
            return Cp::unauthorizedAccess();
        }

        if (empty(Request::all())) {
            return Cp::unauthorizedAccess();
        }

        $banned_ips             = str_replace(PHP_EOL, '|', trim(Request::input('banned_ips')));
        $banned_emails          = str_replace(PHP_EOL, '|', trim(Request::input('banned_emails')));
        $banned_screen_names    = str_replace(PHP_EOL, '|', trim(Request::input('banned_screen_names')));

        $destination = (Request::input('ban_destination') == 'https://') ? '' : Request::input('ban_destination');

        $data = [
            'banned_ips'            => $banned_ips,
            'banned_emails'         => $banned_emails,
            'banned_emails'         => $banned_emails,
            'banned_screen_names'   => $banned_screen_names,
            'ban_action'            => Request::input('ban_action'),
            'ban_message'           => Request::input('ban_message'),
            'ban_destination'       => $destination
        ];

        // ------------------------------------
        //  Preferences Stored in Database For Site
        // ------------------------------------

        foreach($data AS $handle => $value)
        {
            DB::table('site_preferences')
                ->where('site_id', Site::config('site_id'))
                ->where('handle', $handle)
                ->update(
                    [
                        'value' => $value
                    ]
                );
        }

        return redirect(kilvinCpUrl('members/member-banning/U=1'));
    }

   /**
    * Member Profile Fields
    *
    * @param integer $member_group_id
    * @return string
    */
    public function profileFields($member_group_id = '')
    {
        if ( ! Session::access('can_admin_members')) {
            return Cp::unauthorizedAccess();
        }

        Cp::$title  = __('kilvin::members.member_profile_fields');
        Cp::$crumb  = Cp::anchor('administration/members-and-groups', __('kilvin::admin.members-and-groups')).
            Cp::breadcrumbItem(__('kilvin::members.member_profile_fields'));

        $right_links[] = [
            'members/edit-profile-field',
            __('kilvin::members.create_new_profile_field')
        ];

        $r  = Cp::header(__('kilvin::members.member_profile_fields'), $right_links);

        // Build the output
        if (Cp::pathVar('U')) {
            $r .= Cp::quickDiv('success-message', __('kilvin::members.field_updated'));
        }

        $query = DB::table('member_fields')
            ->select('member_fields.id AS member_field_id', 'field_order', 'field_name')
            ->orderBy('field_order')
            ->get();

        if ($query->count() == 0)
        {
            Cp::$body  = Cp::div('box');
            Cp::$body .= Cp::quickDiv('littlePadding', Cp::heading(__('kilvin::members.no_member_profile_fields'), 5));
			Cp::$body .= Cp::quickDiv('littlePadding', Cp::anchor('members/edit-profile-field', __('kilvin::members.create_new_profile_field')));
            Cp::$body .= '</div>'.PHP_EOL;

            return;
        }

        $r .= Cp::table('tableBorder', '0', '10', '100%').
              '<tr>'.PHP_EOL.
              Cp::td('tableHeadingAlt', '', '3').
              __('kilvin::members.current_fields').
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        $i = 0;

        foreach ($query as $row)
        {
            $r .= '<tr>'.PHP_EOL;
            $r .= Cp::tableCell('', Cp::quickSpan('defaultBold', $row->field_name), '40%');
            $r .= Cp::tableCell('', Cp::anchor('members/edit-profile-field/member_field_id='.$row->member_field_id, __('kilvin::cp.edit')), '30%');
            $r .= Cp::tableCell('', Cp::anchor('members/delete-field-confirm/member_field_id='.$row->member_field_id, __('kilvin::cp.delete')), '30%');
            $r .= '</tr>'.PHP_EOL;
        }

        $r .= '</table>'.PHP_EOL;

        $r .= Cp::quickDiv('paddedWrapper', Cp::anchor('members/edit-field-order', __('kilvin::members.edit_field_order')));

        Cp::$body   = $r;
    }

   /**
    * Edit Member Profile Field
    *
    * @return string
    */
    public function editProfileField()
    {
        if ( ! Session::access('can_admin_members')) {
            return Cp::unauthorizedAccess();
        }

        $type = ($member_field_id = Cp::pathVar('member_field_id')) ? 'edit' : 'new';

        // Fetch language file
        // There are some lines in the publish administration language file
        // that we need.

        $total_fields = '';

        if ($type == 'new')
        {
            $total_fields = DB::table('member_fields')->count() + 1;
        }

        $query = DB::table('member_fields')
            ->where('id', $member_field_id)
            ->first();

        if (!$query) {

            $field_handle='';
            $field_name='';
            $field_description='';
            $field_type='text';
            $field_list_items='';
            $textarea_num_rows=8;
            $field_maxlength='';
            $field_width='';
            $is_field_searchable=true;
            $is_field_required=false;
            $is_field_public=true;
            $field_order='';
        } else {
            foreach ($query as $key => $val) {
                $$key = $val;
            }
        }

        $r = <<<EOT

	<script type="text/javascript">

        function switchFieldTypeDisplay(id)
        {
        	id = $('select[name=field_type]').val();

            $('#text_block').css('display', 'none');
            $('#textarea_block').css('display', 'none');
            $('#select_block').css('display', 'none');

            if (id == 'text') {
            	$('#text_block').css('display', 'block');
            }

            if (id == 'textarea') {
            	$('#textarea_block').css('display', 'block');
            }

			if (id == 'select') {
            	$('#select_block').css('display', 'block');
            }
        }
	</script>
EOT;

        $title = ($type == 'edit') ? 'members.edit_member_field' : 'members.create_member_field';

        $i = 0;

        // Form declaration
        $r .= Cp::formOpen(['action' => 'members/update-profile-field']);
        $r .= Cp::input_hidden('member_field_id', $member_field_id);
        $r .= Cp::input_hidden('cur_field_handle', $field_handle);

        $r .= Cp::table('tableBorder', '0', '10', '100%').
              '<tr>'.PHP_EOL.
              Cp::td('tableHeading', '', '2').__('kilvin::'.$title).'</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;


        // ------------------------------------
        //  Field label
        // ------------------------------------

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell('', Cp::quickSpan('defaultBold', Cp::required().NBS.__('kilvin::members.fieldlabel')).Cp::quickDiv('littlePadding', __('kilvin::members.for_profile_page')), '40%');
        $r .= Cp::tableCell('', Cp::input_text('field_name', $field_name, '50', '60', 'input', '300px'), '60%');
        $r .= '</tr>'.PHP_EOL;

        // ------------------------------------
        //  Field handle
        // ------------------------------------

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell('', Cp::quickSpan('defaultBold', Cp::required().NBS.__('kilvin::members.field_handle')).Cp::quickDiv('littlePadding', __('kilvin::members.fieldname_cont')), '40%');
        $r .= Cp::tableCell('', Cp::input_text('field_handle', $field_handle, '50', '60', 'input', '300px'), '60%');
        $r .= '</tr>'.PHP_EOL;


        // ------------------------------------
        //  Field Description
        // ------------------------------------

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::members.field_description')).Cp::quickDiv('littlePadding', __('kilvin::members.field_description_info')), '40%');
        $r .= Cp::tableCell('', Cp::input_textarea('field_description', $field_description, '4', 'textarea', '100%'), '60%');
        $r .= '</tr>'.PHP_EOL;

        // ------------------------------------
        //  Field order
        // ------------------------------------

        if ($type == 'new')
            $field_order = $total_fields;

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::admin.field_order')), '40%');
        $r .= Cp::tableCell('', Cp::input_text('field_order', $field_order, '4', '3', 'input', '30px'), '60%');
        $r .= '</tr>'.PHP_EOL;

        // ------------------------------------
        //  Field type
        // ------------------------------------

        $sel_1 = ''; $sel_2 = ''; $sel_3 = '';
        $text_js = ($type == 'edit') ? 'none' : 'block';
        $textarea_js = 'none';
        $select_js = 'none';
        $select_opt_js = 'none';

        switch ($field_type)
        {
            case 'text'     : $sel_1 = 1; $text_js = 'block';
                break;
            case 'textarea' : $sel_2 = 1; $textarea_js = 'block';
                break;
            case 'select'   : $sel_3 = 1; $select_js = 'block'; $select_opt_js = 'block';
                break;
        }

        // ------------------------------------
        //  Create the pull-down menu
        // ------------------------------------

        $typemenu = "<select name='field_type' class='select' onchange='switchFieldTypeDisplay();' >".PHP_EOL;
        $typemenu .= Cp::input_select_option('text',      __('kilvin::admin.Text Input'), $sel_1)
                    .Cp::input_select_option('textarea',  __('kilvin::admin.Textarea'),   $sel_2)
                    .Cp::input_select_option('select',    __('kilvin::admin.select_list'), $sel_3)
                    .Cp::input_select_footer();


        // ------------------------------------
        //  Field width
        // ------------------------------------

        if ($field_width == '') {
            $field_width = '100%';
        }

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::members.field_width')).Cp::quickDiv('littlePadding', __('kilvin::members.field_width_cont')), '40%');
        $r .= Cp::tableCell('', Cp::input_text('field_width', $field_width, '8', '6', 'input', '60px'), '60%');
        $r .= '</tr>'.PHP_EOL;

        // ------------------------------------
        //  Max-length Field
        // ------------------------------------

        if ($field_maxlength == '') $field_maxlength = '100';

        $typopts  = '<div id="text_block" style="display: '.$text_js.'; padding:0; margin:5px 0 0 0;">';
        $typopts .= Cp::quickDiv('defaultBold', __('kilvin::members.max_length')).Cp::quickDiv('littlePadding', Cp::input_text('field_maxlength', $field_maxlength, '4', '3', 'input', '30px'));
        $typopts .= '</div>'.PHP_EOL;

        // ------------------------------------
        //  Textarea Row Field
        // ------------------------------------

        if ($textarea_num_rows == '') $textarea_num_rows = '10';

        $typopts .= '<div id="textarea_block" style="display: '.$textarea_js.'; padding:0; margin:5px 0 0 0;">';
        $typopts .= Cp::quickDiv('defaultBold', __('kilvin::members.text_area_rows')).Cp::quickDiv('littlePadding', Cp::input_text('textarea_num_rows', $textarea_num_rows, '4', '3', 'input', '30px'));
        $typopts .= '</div>'.PHP_EOL;

        // ------------------------------------
        //  Select List Field
        // ------------------------------------

        $typopts .= '<div id="select_block" style="display: '.$select_js.'; padding:0; margin:5px 0 0 0;">';
        $typopts .= Cp::quickDiv('defaultBold', __('kilvin::members.pull_down_items')).Cp::quickDiv('default', __('kilvin::admin.field_list_instructions')).Cp::input_textarea('field_list_items', $field_list_items, 10, 'textarea', '400px');
        $typopts .= '</div>'.PHP_EOL;


        // ------------------------------------
        //  Generate the above items
        // ------------------------------------

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell('', Cp::quickDiv('littlePadding', Cp::quickSpan('defaultBold', __('kilvin::admin.field_type'))).$typemenu, '50%', 'top');
        $r .= Cp::tableCell('', $typopts, '50%', 'top');
        $r .= '</tr>'.PHP_EOL;

        // ------------------------------------
        //  Is field required?
        // ------------------------------------

        if ($is_field_required == '') {
            $is_field_required = 0;
        }

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::admin.is_field_required')), '40%');
        $r .= Cp::tableCell('', __('kilvin::cp.yes').'&nbsp;'.Cp::input_radio('is_field_required', 1, ($is_field_required == 1) ? 1 : '').'&nbsp;'.__('kilvin::cp.no').'&nbsp;'.Cp::input_radio('is_field_required', 0, ($is_field_required == 0) ? 1 : ''), '60%');
        $r .= '</tr>'.PHP_EOL;


        // ------------------------------------
        //  Is field public?
        // ------------------------------------

        if ($is_field_public == '') {
            $is_field_public = 1;
        }

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::members.is_field_public')).Cp::quickDiv('littlePadding', __('kilvin::members.is_field_public_cont')), '40%');
        $r .= Cp::tableCell('', __('kilvin::cp.yes').'&nbsp;'.Cp::input_radio('is_field_public', 1, ($is_field_public == 1) ? 1 : '').'&nbsp;'.__('kilvin::cp.no').'&nbsp;'.Cp::input_radio('is_field_public', 0, ($is_field_public == 0) ? 1 : ''), '60%');
        $r .= '</tr>'.PHP_EOL;

        $r .= '</table>'.PHP_EOL;

        $r .= Cp::div('littlePadding');
        $r .= Cp::required(1).BR.BR;

        if ($type == 'edit')
            $r .= Cp::input_submit(__('kilvin::cp.update'));
        else
            $r .= Cp::input_submit(__('kilvin::cp.submit'));

        $r .= '</div>'.PHP_EOL;

        $r .= '</form>'.PHP_EOL;

        Cp::$title = __('kilvin::members.edit_member_field');
        Cp::$crumb = Cp::anchor('administration/members-and-groups', __('kilvin::admin.members-and-groups')).
			Cp::breadcrumbItem(Cp::anchor('members/profile-fields', __('kilvin::members.member_profile_fields'))).
			Cp::breadcrumbItem(__('kilvin::members.edit_member_field'));
        Cp::$body  = $r;
    }

   /**
    * Create/Update Custom Field
    *
    * @return string
    */
    public function updateProfileField()
    {
        if ( ! Session::access('can_admin_members')) {
            return Cp::unauthorizedAccess();
        }

        $fields = [
            "member_field_id",
            "cur_field_handle",
            "field_handle",
            "field_name",
            "field_description",
            "field_order",
            "field_width",
            "field_type",
            "field_maxlength",
            "textarea_num_rows",
            "field_list_items",
            "is_field_required",
            "is_field_public",
        ];

        $input = request()->only($fields);

        $string_default = [
            'field_description',
            'field_list_items',
        ];

        foreach ($string_default as $field) {
            if ($input[$field] == null) {
                $input[$field] = '';
            }
        }

        // If the $member_field_id variable is present we are editing existing
        $edit = (bool) request()->filled('member_field_id');

        // Check for required fields
        if (empty($input['field_handle'])) {
            $errors[] = __('kilvin::admin.no_field_handle');
        }

        if (empty($input['field_name'])) {
            $errors[] = __('kilvin::admin.no_field_name');
        }

        // Is the field one of the reserved words?
        if (in_array($input['field_handle'], Cp::unavailableFieldNames())) {
            $errors[] = __('kilvin::admin.reserved_word');
        }

        // Does field name have invalid characters?
        if ( ! preg_match("#^[a-z0-9\_]+$#i", $input['field_handle'])) {
            $errors[] = __('kilvin::admin.field_illegal_characters');
        }

        // Is the field name taken?
        $field_count = DB::table('member_fields')
            ->where('field_handle', $input['field_handle'])
            ->count();

        if ($field_count > 0) {
            if ($edit === false) {
                $errors[] = __('kilvin::members.duplicate_field_handle');
            }

            if ($edit === true && $input['field_handle'] != $input['cur_field_handle']) {
                $errors[] = __('kilvin::members.duplicate_field_handle');
            }
        }

        // Are there errors to display?
        if (!empty($errors)) {
            return Cp::errorMessage(implode("\n", $errors));
        }

        if (!empty($input['field_list_items'])) {
            $input['field_list_items'] = htmlspecialchars($input['field_list_items']);
        }

        $n = 100;

        $f_type = 'text';

        if ($input['field_type'] == 'text') {
            if ( !empty($input['field_maxlength']) && is_numeric($input['field_maxlength'])) {
                $n = '100';
            }

            $f_type = 'string';
        }

        if ($edit === true) {

            if ($input['cur_field_handle'] !== $input['field_handle']) {
                Schema::table('member_data', function ($table) use ($input) {
                    $table->renameColumn('m_field_'.$input['cur_field_handle'], 'm_field_'.$input['field_handle']);
                });
            }

            // ALTER
            Schema::table('member_data', function($table) use ($input, $f_type, $n) {
                if ($f_type == 'string') {
                    $table->string('m_field_'.$input['field_handle'], $n)->change();
                } else {
                    $table->text('m_field_'.$input['field_handle'])->change();
                }
            });

            unset($input['cur_field_handle']);

            $update = $input;
            unset($update['member_field_id']);

            DB::table('member_fields')
                ->where('id', $input['member_field_id'])
                ->update($update);
        }

        if ($edit === false) {
            if (empty($input['field_order'])) {
                $total = DB::table('member_fields')->count() + 1;

                $input['field_order'] = $total;
            }

            unset($input['member_field_id']); // insure empty
            unset($input['cur_field_handle']);

            $field_id = DB::table('member_fields')->insertGetId($input);

            // Add Field
            Schema::table('member_data', function($table) use ($input, $f_type, $n) {
                if ($f_type == 'string') {
                    $table->string('m_field_'.$input['field_handle'], $n);
                } else {
                    $table->text('m_field_'.$input['field_handle']);
                }
            });
        }

        // Insure every member has member data row?
        $query = DB::table('members')
            ->leftJoin('member_data', 'members.id', '=', 'member_data.member_id')
            ->whereNull('member_data.member_id')
            ->select('members.id AS member_id')
            ->get();

        foreach ($query as $row) {
            DB::table('member_data')->insert(['member_id' => $row->member_id]);
        }

        return redirect(kilvinCpUrl('members/profile-fields'));
    }

   /**
    * Delete Member Field Confirmation
    *
    * @return string
    */
    public function deleteFieldConfirm()
    {
        if ( ! Session::access('can_admin_members')) {
            return Cp::unauthorizedAccess();
        }

        if ( ! $member_field_id = Cp::pathVar('member_field_id')) {
            return Cp::unauthorizedAccess();
        }

        $query = DB::table('member_fields')
            ->where('id', $member_field_id)
            ->select('field_name')
            ->first();

        Cp::$title = __('kilvin::admin.delete_field');
        Cp::$crumb = Cp::anchor('administration/members-and-groups', __('kilvin::admin.members-and-groups')).
			Cp::breadcrumbItem(Cp::anchor('members/profile-fields', __('kilvin::members.member_profile_fields'))).
			Cp::breadcrumbItem(__('kilvin::members.edit_member_field'));

        Cp::$body =
            Cp::formOpen([
                'action' => 'members/delete-profile-field/member_field_id='.$member_field_id
            ]).
                Cp::input_hidden('member_field_id', $member_field_id).
                Cp::quickDiv('alertHeading', __('kilvin::admin.delete_field')).
                Cp::div('box').
                    Cp::quickDiv('littlePadding', '<b>'.__('kilvin::admin.delete_field_confirmation').'</b>').
                    Cp::quickDiv('littlePadding', '<i>'.$query->field_name.'</i>').
                    Cp::quickDiv('alert', BR.__('kilvin::cp.action_can_not_be_undone')).
                    Cp::quickDiv('littlePadding', BR.Cp::input_submit(__('kilvin::cp.delete'))).
                '</div>'.PHP_EOL
            .'</form>'.PHP_EOL;
    }

   /**
    * Delete a Member Field
    *
    * @return string
    */
    public function deleteProfileField()
    {
        if ( ! Session::access('can_admin_members')) {
            return Cp::unauthorizedAccess();
        }

        if ( ! $member_field_id = Request::input('member_field_id')) {
            return false;
        }

        $query = DB::table('member_fields')
            ->where('id', $member_field_id)
            ->select('field_handle', 'field_name', 'member_fields.id AS member_field_id')
            ->first();

        if (!$query) {
            return false;
        }

        // Drop Column
        Schema::table('member_data', function($table) use ($query) {
            $table->dropColumn('m_field_'.$query->field_handle);
        });

        DB::table('member_fields')->where('id', $query->member_field_id)->delete();

        Cp::log(__('kilvin::members.profile_field_deleted').'&nbsp;'.$query->field_name);

        return $this->profileFields();
    }

   /**
    * Edit Member Fields Order
    *
    * @return string
    */
    public function editFieldOrder()
    {
        if ( ! Session::access('can_admin_members')) {
            return Cp::unauthorizedAccess();
        }

        $query = DB::table('member_fields')
            ->orderBy('field_order')
            ->select('field_name', 'field_handle', 'field_order')
            ->get();

        if ($query->count() == 0) {
            return false;
        }

        $r  = Cp::formOpen(['action' => 'members/update-field-order']);
        $r .= Cp::table('tableBorder', '0', '10', '100%');

        $r .=
            '<tr>'.
                Cp::td('tableHeading', '', '3').
                    __('kilvin::members.edit_field_order').
                '</td>'.PHP_EOL.
            '</tr>'.PHP_EOL;

        foreach ($query as $row)
        {
            $r .= '<tr>'.PHP_EOL;
            $r .= Cp::tableCell('', $row->field_name);
            $r .= Cp::tableCell('', Cp::input_text($row->field_handle, $row->field_order, '4', '3', 'input', '30px'));
            $r .= '</tr>'.PHP_EOL;
        }

        $r .= '</table>'.PHP_EOL;

        $r .= Cp::quickDiv('paddingTop', Cp::input_submit(__('kilvin::cp.update')));

        $r .= '</form>'.PHP_EOL;

        Cp::$title = __('kilvin::members.edit_field_order');
        Cp::$crumb = Cp::anchor('administration/members-and-groups', __('kilvin::admin.members-and-groups')).
                      Cp::breadcrumbItem(Cp::anchor('members/profile-fields', __('kilvin::members.member_profile_fields'))).
                      Cp::breadcrumbItem(__('kilvin::members.edit_field_order'));

        Cp::$body  = $r;
    }

   /**
    * Update Member Fields Order
    *
    * @return string
    */
    public function updateFieldOrder()
    {
        if ( ! Session::access('can_admin_members')) {
            return Cp::unauthorizedAccess();
        }

        $query = DB::table('member_fields')
            ->orderBy('field_order')
            ->select('field_name', 'field_handle', 'field_order')
            ->get();

        foreach ($query as $row)
        {
            DB::table('member_fields')
                ->where('field_handle' , $row->field_handle)
                ->update(['field_order' => Request::input($row->field_handle)]);
        }

        return $this->profileFields();
    }

   /**
    * Search Members Form
    *
    * @param string $message
    * @return string
    */
    public function memberSearch($message = '')
    {
        Cp::$body  = Cp::formOpen(['action' => 'members/do-member-search']);

        Cp::$body .= Cp::quickDiv('tableHeading', __('kilvin::members.member_search'));

        if ($message != '') {
            Cp::$body .= Cp::quickDiv('success-message', $message);
        }

        Cp::$body .= Cp::div('box');

        Cp::$body .= Cp::itemgroup(
            __('kilvin::account.email'),
            Cp::input_text('email', '', '35', '100', 'input', '300px')
        );

        Cp::$body .= Cp::itemgroup(
            __('kilvin::account.screen_name'),
            Cp::input_text('screen_name', '', '35', '100', 'input', '300px')
        );

        Cp::$body .= Cp::itemgroup(
            __('kilvin::account.url'),
            Cp::input_text('url', '', '35', '100', 'input', '300px')
        );

        Cp::$body .= Cp::itemgroup(
            __('kilvin::cp.ip_address'),
            Cp::input_text('ip_address', '', '35', '100', 'input', '300px')
        );

        Cp::$body .= Cp::itemgroup(
            Cp::quickDiv('defaultBold', __('kilvin::admin.member_group'))
        );

        // Member group select list
        $query = DB::table('member_groups')
                ->select('id AS member_group_id', 'group_name')
                ->orderBy('group_name')
                ->get();

        Cp::$body .= Cp::input_select_header('member_group_id');

        Cp::$body .= Cp::input_select_option('any', __('kilvin::members.any'));

        foreach ($query as $row) {
            Cp::$body.= Cp::input_select_option($row->member_group_id, $row->group_name);
        }

        Cp::$body .= Cp::input_select_footer();

        // @todo - Add Member Fields

        Cp::$body .= '</div>'.PHP_EOL;
        Cp::$body .= Cp::quickDiv('paddingTop', Cp::input_submit(__('kilvin::cp.submit')));

        Cp::$body .= '</form>'.PHP_EOL;

        Cp::$title = __('kilvin::members.member_search');
        Cp::$crumb =
            Cp::anchor('administration/members-and-groups', __('kilvin::admin.members-and-groups')).
            Cp::breadcrumbItem(__('kilvin::members.member_search'));
    }

   /**
    * Perform a Member Search
    *
    * @return string
    */
    public function doMemberSearch()
    {
        $pageurl = 'members/do-member-search';

        $custom = false;
        $search = [];

        // ------------------------------------
        //  Criteria
        // ------------------------------------

        if (Request::input('criteria')) {
            if (!Request::input('keywords') && ! Request::input('member_group_id')) {
                return redirect(kilvinCpUrl(''));
            }

            if (starts_with(Request::input('criteria'), 'm_field_')) {
                $custom = [Request::input('criteria'), Request::input('keywords')];
            } else {
                $search[Request::input('criteria')] = Request::input('keywords');
            }
        }

        // ------------------------------------
        //  Parse the Request
        // ------------------------------------

        if ($Q = Cp::pathVar('Q')) {
            $Q = base64_decode(urldecode($Q));
        } else {
            foreach (['screen_name', 'email', 'url', 'ip_address'] as $pval) {
                if (Request::input($pval)) {
                    $search[$pval] = Request::input($pval);
                }
            }

            if (empty($search) && $custom === false && !Request::input('member_group_id')) {
                return redirect(kilvinCpUrl('members/member-search'));
            }

            $search_query = DB::table('members')
                ->select('members.id AS member_id', 'screen_name', 'email', 'join_date', 'ip_address', 'group_name')
                ->join('member_groups', 'member_groups.id', '=', 'members.member_group_id');

            if (Request::input('member_group_id') && Request::input('member_group_id') != 'any') {
                $search_query->where('members.member_group_id', Request::input('member_group_id'));
            }

            if (Request::input('exact_match')) {
                foreach($search as $field => $value) {
                    $search_query->where('members.'.$field, '=', $value);
                }
            } else {
                foreach($search as $field => $value) {
                    $search_query->where('members.'.$field, 'LIKE', '%'.$value.'%');
                }
            }
        }

        $pageurl .= '/Q='.urlencode(base64_encode($Q));

        if ($custom !== false) {
            $search_query->join('member_data', 'member_data.member_id', '=', 'members.id');

            if (Request::input('exact_match')) {
                $search_query->where('member_data.'.$custom[0], $custom[1]);
            } else {
                $search_query->where('member_data.'.$custom[0], 'LIKE', '%'.$custom[1].'%');
            }
        }

        // No result?  Show the "no results" message
        $query = clone $search_query;
        $total_count = $query->count();

        if ($total_count == 0)  {
            return $this->memberSearch(Cp::quickDiv('littlePadding', Cp::quickDiv('alert', __('kilvin::members.no_search_results'))));
        }

        // Get the current row number and add the LIMIT clause to the SQL query
        if ( ! $rownum = Request::input('rownum')) {
            $rownum = 0;
        }

        $search_query->offset($rownum)->limit($this->perpage);

        // Run the query
        $query = clone $search_query;
        $query = $query->get();

        // Build the table heading
        $right_links[] = [
            'members/member-search',
            __('kilvin::members.new_member_search')
        ];

        $r  = Cp::header(__('kilvin::admin.view-members'), $right_links);

        $r .= Cp::magicCheckboxesJavascript();

        // Declare the "delete" form
        $r .= Cp::formOpen(
            [
				'action' => 'members/delete-member-confirm',
				'name'  => 'target',
				'id'    => 'target'
            ]
        );

        $r .= Cp::table('tableBorder', '0', '', '100%').
              '<tr>'.PHP_EOL.
              Cp::tableCell('tableHeadingAlt', __('kilvin::account.screen_name')).
              Cp::tableCell('tableHeadingAlt', __('kilvin::account.email')).
              Cp::tableCell('tableHeadingAlt', __('kilvin::cp.ip_address')).
              Cp::tableCell('tableHeadingAlt', __('kilvin::account.join_date')).
              Cp::tableCell('tableHeadingAlt', __('kilvin::admin.member_group')).
              Cp::tableCell('tableHeadingAlt', Cp::input_checkbox('toggle_all')).
              '</tr>'.PHP_EOL;

        $i = 0;

        foreach($query as $row)
        {
            $r .= '<tr>'.PHP_EOL;

            // Screen name
            $r .= Cp::tableCell('',
                Cp::anchor(
                    '/account/id='.$row->member_id,
                    '<b>'.$row->screen_name.'</b>'
                ));

            // Email
            $r .= Cp::tableCell(
                '',
                Cp::mailto($row->email, $row->email)
            );

            // IP Address
            $r .= Cp::td('');
            $r .= $row->ip_address;
            $r .= '</td>'.PHP_EOL;

            // Join date
            $r .= Cp::td('').
                  Localize::format('%Y', $row->join_date).'-'.
                  Localize::format('%m', $row->join_date).'-'.
                  Localize::format('%d', $row->join_date).
                  '</td>'.PHP_EOL;

            // Member group

            $r .= Cp::td('');

            $r .= $row->group_name;

            $r .= '</td>'.PHP_EOL;

            // Delete checkbox

            $r .= Cp::tableCell('', Cp::input_checkbox('toggle[]', $row->member_id, '', " id='delete_box_".$row->member_id."'"));

            $r .= '</tr>'.PHP_EOL;

        } // End foreach


        $r .= '</table>'.PHP_EOL;

        $r .= Cp::table('', '0', '', '98%');
        $r .= '<tr>'.PHP_EOL.
              Cp::td();

        // Pass the relevant data to the paginate class so it can display the "next page" links

        $r .=  Cp::div('crumblinks').
               Cp::pager(
                    $pageurl,
                    $total_count,
                    $this->perpage,
                    $rownum,
                    'rownum'
                  ).
              '</div>'.PHP_EOL.
              '</td>'.PHP_EOL.
              Cp::td('defaultRight');

        // Delete button

        $r .= Cp::input_submit(__('kilvin::cp.delete')).
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        // Table end

        $r .= '</table>'.PHP_EOL.
              '</form>'.PHP_EOL;

        Cp::$title = __('kilvin::members.member_search');
        Cp::$crumb = Cp::anchor('administration/members-and-groups', __('kilvin::admin.members-and-groups')).
                      Cp::breadcrumbItem(__('kilvin::members.member_search'));
        Cp::$body  = $r;
    }

    // ------------------------------------------------------

    /**
     * Clear out caches related to Member Groups
     *
     * @param integer  $member_group_id The group we are loading this for
     * @return void
     */
    private function clearMemberGroupCache($member_group_id = null)
    {
        $tags = (is_null($member_group_id)) ? ['member_groups'] : 'member_group'.$member_group_id;

        if (Cache::getStore() instanceof TaggableStore) {
            return Cache::tags($tags)->flush();
        }

        if (!is_null($member_group_id)) {

            // Session::fetchSpecialPreferencesCache()
            $keys[] = 'cms.member_group:'.$member_group_id.'.specialPreferences';
        }

        foreach($keys as $key) {
            Cache::forget($key);
        }
    }
}
