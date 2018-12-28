<?php

namespace Kilvin\Cp;

use Kilvin\Facades\Cp;
use Illuminate\Support\Facades\DB;
use Kilvin\Facades\Site;
use Kilvin\Facades\Stats;
use Illuminate\Support\Facades\Schema;
use Kilvin\Facades\Plugins;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Kilvin\Core\Regex;
use Kilvin\Core\Session;

class WeblogAdministration
{
    // Category arrays
    public $categories = [];
    public $cat_update = [];

    public $temp;

   /**
    * Constructor
    *
    * @return void
    */
    public function __construct()
    {
        $publish_access = [
            'category-editor',
            'edit-category',
            'update-category',
            'del-category-conf',
            'del-category',
            'category-order'
        ];

        // This flag determines if a user can edit categories from the publish page.
        $category_exception =
            (
                in_array(Cp::segment(2), $publish_access)
                &&
                Cp::pathVar('Z') == 1
            ) ?
            true :
            false;

        if (
            $category_exception === false
            and
            (
                ! Session::access('can_admin_weblogs')
                or
                ! Session::access('can_access_admin')
            )
        ) {
            return Cp::unauthorizedAccess();
        }
    }

   /**
    * Request Handler
    *
    * @return mixed
    */
    public function run()
    {
        if (Cp::segment(2)) {
            if (method_exists($this, camel_case(Cp::segment(2)))) {
                return $this->{camel_case(Cp::segment(2))}();
            }
        }

        return Cp::unauthorizedAccess();
    }

   /**
    * Weblogs Management
    *
    * @return string
    */
    public function weblogsOverview()
    {
        Cp::$title  = __('kilvin::admin.weblog-management');
        Cp::$crumb .= __('kilvin::admin.weblog-management');

        $right_links[] = [
            'weblogs-administration/new-weblog-form',
            __('kilvin::admin.create_new_weblog')
        ];

        $r = Cp::header(__('kilvin::admin.weblog-management'), $right_links);

        // Fetch weblogs
        $query = DB::table('weblogs')
            ->select('id AS weblog_id', 'weblog_handle', 'weblog_name')
            ->orderBy('weblog_name')
            ->get();

        $cp_message = session()->pull('cp-message');

        if (!empty($cp_message)) {
            $r .= Cp::quickDiv('success-message', $cp_message);
        }

        if ($query->count() == 0) {
            $r .= Cp::div('box');
            $r .= Cp::quickDiv('littlePadding', Cp::heading(__('kilvin::admin.no_weblogs_exist'), 6));
            $r .= Cp::quickDiv(
                'littlePadding',
                Cp::anchor(
                    'weblogs-administration/new-weblog-form',
                    __('kilvin::admin.create_new_weblog')
                )
            );
            $r .= '</div>'.PHP_EOL;

            return Cp::$body = $r;
        }

        $r .= Cp::table('tableBorder', '0', '', '100%');

        $r .= '<tr>'.PHP_EOL.
              Cp::td('tableHeadingAlt').__('kilvin::admin.weblog_handle').'</td>'.PHP_EOL.
              Cp::td('tableHeadingAlt', '', 6).__('kilvin::admin.weblog_handle').'</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        $i = 0;

        foreach($query as $row)
        {

            $r .= '<tr>'.PHP_EOL;

            $r .= Cp::tableCell('',
                  Cp::anchor(
                    'weblogs-administration/edit-weblog/weblog_id='.$row->weblog_id,
                    $row->weblog_name,
                    'class="defaultBold"'
                )
            );

            $r .= Cp::tableCell('', Cp::quickSpan('default', $row->weblog_handle).' &nbsp; ');


            $r .= Cp::tableCell('',
                  Cp::anchor(
					'weblogs-administration/edit-weblog-fields/weblog_id='.$row->weblog_id,
					__('kilvin::admin.edit_fields')
				  ));

            $r .= Cp::tableCell('',
			  Cp::anchor(
				'weblogs-administration/edit-weblog-layout/weblog_id='.$row->weblog_id,
				__('kilvin::admin.edit_publish_layout')
				)
			);

            $r .= Cp::tableCell('',
                  Cp::anchor(
                    'weblogs-administration/delete-weblog-confirm'.
                        '/weblog_id='.$row->weblog_id,
                    __('kilvin::cp.delete'),
                    'class="delete-link"'
                )
            );

            $r .= '</tr>'.PHP_EOL;
        }

        $r .= '</table>'.PHP_EOL;

        // Assign output data

        Cp::$body = $r;

    }

   /**
     * New Weblog Form
     *
     * @return string
     */
    public function newWeblogForm()
    {
        $r = <<<EOT
<script type="text/javascript">

$(function() {
    $('input[name=edit_group_prefs]').click(function(e){
        $('#group_preferences').toggle();
    });
});

</script>
EOT;

        $r .= Cp::formOpen(['action' => 'weblogs-administration/update-weblog']);

        $r .= Cp::table('tableBorder', '0', '', '100%');
        $r .= '<tr>'.PHP_EOL
            .Cp::td('tableHeading', '', '2').__('kilvin::admin.create_new_weblog').'</td>'.PHP_EOL
            .'</tr>'.PHP_EOL;

        // Weblog name field
        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::required().NBS.Cp::quickSpan('defaultBold', __('kilvin::admin.full_weblog_name'))).
              Cp::tableCell('', Cp::input_text('weblog_name', '', '20', '100', 'input', '260px')).
              '</tr>'.PHP_EOL;

        // Weblog handle
        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell(
              	'',
              	Cp::required().
              		NBS.
              		Cp::quickSpan(
              			'defaultBold',
              			__('kilvin::admin.weblog_handle')
              		).
              		Cp::quickDiv('', __('kilvin::admin.field_handle_explanation')),
              	'40%'
              ).
              Cp::tableCell('', Cp::input_text('weblog_handle', '', '20', '40', 'input', '260px'), '60%').
              '</tr>'.PHP_EOL;

        // Duplicate Preferences Select List
        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::admin.duplicate_weblog_prefs')));

        $w  = Cp::input_select_header('duplicate_weblog_prefs');
        $w .= Cp::input_select_option('', __('kilvin::admin.do_not_duplicate'));

        $wquery = DB::table('weblogs')
            ->select('id AS weblog_id', 'weblog_handle', 'weblog_name')
            ->orderBy('weblog_name')
            ->get();

        foreach($wquery as $row) {
            $w .= Cp::input_select_option($row->weblog_id, $row->weblog_name);
        }

        $w .= Cp::input_select_footer();

        $r .= Cp::tableCell('', $w).
              '</tr>'.PHP_EOL;

        // Edit Group Preferences option

        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::admin.edit_group_prefs')), '40%').
              Cp::tableCell('', Cp::input_radio('edit_group_prefs', 'y').
                                                NBS.__('kilvin::admin.yes').
                                                NBS.
                                                Cp::input_radio('edit_group_prefs', 'n', 1).
                                                NBS.__('kilvin::admin.no'), '60%').
              '</tr>'.PHP_EOL;

        $r .= '</table>'.PHP_EOL.BR;

        // GROUP FIELDS
        $g = '';
        $i = 0;
        $category_group_id = '';
        $status_group_id = '';
        $weblog_field_group_id = '';

        $r .= Cp::div('', '', 'group_preferences', '', 'style="display:none;"');
        $r .= Cp::table('tableBorder', '0', '', '100%');
        $r .= '<tr>'.PHP_EOL.
              Cp::td('tableHeadingAlt', '100%', 2).__('kilvin::admin.edit_group_prefs').'</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        // Category group select list
        $query = DB::table('category_groups')
            ->orderBy('group_name')
            ->select('category_groups.id AS category_group_id', 'group_name')
            ->get();

        $g .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::admin.category_group')), '40%', 'top');

        $g .= Cp::td().
              Cp::input_select_header('category_group_id[]', ($query->count() > 0) ? 'y' : '');

        $selected = '';

        $g .= Cp::input_select_option('', __('kilvin::admin.none'), $selected);

        if ($query->count() > 0)
        {
            foreach ($query as $row)
            {
                $g .= Cp::input_select_option($row->category_group_id, $row->group_name);
            }
        }

        $g .= Cp::input_select_footer().
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        // Status group select list
        $query = DB::table('status_groups')
            ->orderBy('group_name')
            ->select('status_groups.id AS status_group_id', 'group_name')
            ->get();

        $g .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::admin.status_group')));

        $g .= Cp::td().
              Cp::input_select_header('status_group_id');

        $selected = '';

        $g .= Cp::input_select_option('', __('kilvin::admin.none'), $selected);

        if ($query->count() > 0) {
            foreach ($query as $row) {
                $selected = ($status_group_id == $row->status_group_id) ? 1 : '';

                $g .= Cp::input_select_option($row->status_group_id, $row->group_name, $selected);
            }
        }

        $g .= Cp::input_select_footer().
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        // Field group select list
        $query = DB::table('weblog_field_groups')
            ->orderBy('group_name')
            ->select('id AS weblog_field_group_id', 'group_name')
            ->get();

        $g .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::admin.field_group')));

        $g .= Cp::td().
              Cp::input_select_header('weblog_field_group_id');

        $selected = '';

        $g .= Cp::input_select_option('', __('kilvin::admin.none'), $selected);

		foreach ($query as $row) {
			$selected = ($weblog_field_group_id == $row->weblog_field_group_id) ? 1 : '';

			$g .= Cp::input_select_option($row->weblog_field_group_id, $row->group_name, $selected);
		}

        $g .= Cp::input_select_footer().
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL.
              '</table>'.PHP_EOL.BR.
              '</div>'.PHP_EOL;

        $r .= $g;
        // Table end

        // Submit button
        $r .= Cp::quickDiv('littlePadding', Cp::required(1));
        $r .= Cp::quickDiv('', Cp::input_submit(__('kilvin::cp.submit')));

        $r .= '</form>'.PHP_EOL;

        // Assign output data
        Cp::$title = __('kilvin::admin.create_new_weblog');
        Cp::$crumb =
            Cp::anchor('weblogs-administration/weblogs-overview', __('kilvin::admin.weblog-management')).
            Cp::breadcrumbItem(__('kilvin::admin.new_weblog'));

        Cp::$body  = $r;
    }

   /**
    * Update or Create Weblog Preferences
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
    public function updateWeblog()
    {
        $edit    = (bool) Request::filled('weblog_id');
        $return  = (bool) Request::filled('return');
        $dupe_id = Request::input('duplicate_weblog_prefs');

        // ------------------------------------
        //  Validation
        // ------------------------------------

        $validator = Validator::make(request()->all(), [
            'weblog_handle' => 'required|regex:#^[a-zA-Z0-9\_]+$#i',
            'weblog_name'   => 'required',
        ]);

        if ($validator->fails()) {
            return Cp::errorMessage(implode(BR, $validator->errors()->all()));
        }

        // Is the weblog name taken?
        $query = DB::table('weblogs')
            ->where('site_id', Site::config('site_id'))
            ->where('weblog_handle', Request::input('weblog_handle'));

        if ($edit === true) {
            $query->where('id', '!=', Request::input('weblog_id'));
        }

        if ($query->count() > 0) {
            return Cp::errorMessage(__('kilvin::admin.taken_weblog_handle'));
        }

        $data = [];
        if ($edit === true) {
            $data = (array) DB::table('weblogs')
            	->select('weblogs.id AS weblog_id', 'weblogs.*')
                ->where('id', Request::input('weblog_id'))
                ->first();
        }

        $fields = [
            'weblog_id',
            'weblog_name',
            'weblog_handle',
            'weblog_description',
            'weblog_url',
            'live_look_template',
            'enable_versioning',
            'enable_qucksave_versioning',
            'max_revisions',
            'weblog_notify',
            'weblog_notify_emails',
        ];

        foreach($fields as $field) {
            if (Request::filled($field)) {
                $data[$field] = Request::input($field);
            }
        }

        $strings = [
            'weblog_description',
            'weblog_notify_emails',
            'weblog_url'
        ];

        foreach($strings as $field) {
            if(empty($data[$field])) {
                $data[$field] = '';
            }
        }

        // Let DB defaults handle these if empty
        $unsettable = [
            'enable_versioning',
            'enable_qucksave_versioning',
            'max_revisions',
            'weblog_notify',
        ];

        foreach($unsettable as $field) {
            if(empty($data[$field])) {
                unset($data[$field]);
            }
        }

        // ------------------------------------
        //  Template Error Trapping
        // ------------------------------------

        if ($edit === false) {
            $old_group_id   = Request::input('old_group_id');
            $group_name     = strtolower(Request::input('group_name'));
            $template_theme = filenameSecurity(Request::input('template_theme'));
        }

        // ------------------------------------
        //  Conversion
        // ------------------------------------

        if (Request::filled('weblog_notify_emails') && is_array(Request::input('weblog_notify_emails'))) {
            $data['weblog_notify_emails'] = implode(',', Request::input('weblog_notify_emails'));
        }

        // ------------------------------------
        //  Create Weblog
        // ------------------------------------

        if ($edit === false) {
            // Assign field group if there is only one
            if ( ! isset($data['weblog_field_group_id']) or ! is_numeric($data['weblog_field_group_id'])) {
                $query = DB::table('weblog_field_groups')
                        ->select('id AS weblog_field_group_id')
                        ->get();

                if ($query->count() == 1) {
                    $data['weblog_field_group_id'] = $query->first()->weblog_field_group_id;
                }
            }

            // --------------------------------------
            //  Duplicate Preferences
            // --------------------------------------

            if ($dupe_id !== false AND is_numeric($dupe_id))
            {
                $wquery = DB::table('weblogs')
                    ->where('id', $dupe_id)
                    ->first();

                if ($wquery) {
                    $exceptions = [
                        'id',
                        'weblog_handle',
                        'weblog_name',
                        'total_entries',
                        'last_entry_date',
                    ];

                    foreach($wquery as $key => $val) {
                        // don't duplicate fields that are unique to each weblog
                        if (in_array($key, $exceptions)) {
                            continue;
                        }

                        if (empty($data[$key])) {
                            $data[$key] = $val;
                        }
                    }
                }
            }

            $data['site_id'] = Site::config('site_id');
            $weblog_id = DB::table('weblogs')->insertGetId($data);

            // ------------------------------------
            //  Create First Tab
            // ------------------------------------

            DB::table('weblog_layout_tabs')
                ->insert([
                    'weblog_id' => $weblog_id,
                    'tab_name' => 'Publish',
                    'tab_order' => 1
                ]);

            $success_msg = __('kilvin::admin.weblog_created');
        }

        // ------------------------------------
        //  Updating Weblog
        // ------------------------------------

        if ($edit === true) {
            if (isset($data['clear_versioning_data'])) {
                DB::table('entry_versioning')
                    ->where('weblog_id', $data['weblog_id'])
                    ->delete();
            }

            unset($data['weblog_id']);

            DB::table('weblogs')
                ->where('id', Request::input('weblog_id'))
                ->update($data);

            $weblog_id = Request::input('weblog_id');

            $success_msg = __('kilvin::admin.weblog_updated');
        }

        // ------------------------------------
        //  Messages and Return
        // ------------------------------------

        Cp::log($success_msg.$data['weblog_name']);

        $message = $success_msg.'<strong>'.$data['weblog_name'].'</strong>';

        if ($edit === false || $return === true) {
            return redirect(kilvinCpUrl('weblogs-administration/weblogs-overview'))
                ->with('cp-message', $message);
        } else {
            return redirect(kilvinCpUrl('weblogs-administration/edit-weblog/weblog_id='.$weblog_id))
                ->with('cp-message', $message);
        }
    }

   /**
    * Update Weblog Layout Preferences
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
    public function updateWeblogFields()
    {
        $return = (bool) Request::filled('return');

        // ------------------------------------
        //  Validation
        // ------------------------------------

        $validator = Validator::make(request()->all(), [
            'url_title_prefix'   => 'nullable|alpha_dash',
        ]);

        if ($validator->fails()) {
            return Cp::errorMessage(implode(BR, $validator->errors()->all()));
        }

        $data = (array) DB::table('weblogs')
        	->select('weblogs.id AS weblog_id', 'weblogs.*')
            ->where('id', Request::input('weblog_id'))
            ->first();

        if (empty($data)) {
            return Cp::unauthorizedAccess();
        }

        $fields = [
            'category_group_id',
            'status_group_id',
            'weblog_field_group_id',
            'live_look_template',
            'default_status',
            'default_category',
            'show_url_title',
            'show_categories_tab',
            'url_title_prefix'
        ];

        foreach($fields as $field) {
            if (Request::input($field) !== null) {
                $data[$field] = Request::input($field);
            }
        }

        if (isset($data['category_group_id']) && is_array($data['category_group_id'])) {
            $data['category_group_id'] = implode('|', $data['category_group_id']);
        }

        $nullable = [
            'category_group_id',
            'status_group_id',
            'weblog_field_group_id'
        ];

        foreach($nullable as $field) {
            if(empty($data[$field])) {
                $data[$field] = null;
            }
        }

        $strings = [
            'default_status',
            'url_title_prefix'
        ];

        foreach($strings as $field) {
            if(empty($data[$field])) {
                $data[$field] = '';
            }
        }

        // Let DB defaults handle these if empty
        $unsettable = [
            'show_categories_tab',
        ];

        foreach($unsettable as $field) {
            if(empty($data[$field])) {
                unset($data[$field]);
            }
        }

        // ------------------------------------
        //  Updating Weblog
        // ------------------------------------

        $update = $data;
        unset($update['weblog_id']);

        DB::table('weblogs')
            ->where('id', $data['weblog_id'])
            ->update($update);

        $weblog_id = $data['weblog_id'];

        $success_msg = __('kilvin::admin.weblog_updated');

        // ------------------------------------
        //  Messages and Return
        // ------------------------------------

        Cp::log($success_msg.$data['weblog_name']);

        $message = $success_msg.'<strong>'.$data['weblog_name'].'</strong>';

        if ($return === true) {
            return redirect(kilvinCpUrl('weblogs-administration/weblogs-overview'))
                ->with('cp-message', $message);
        } else {
            return redirect(kilvinCpUrl('weblogs-administration/edit-weblog-fields/weblog_id='.$weblog_id))
                ->with('cp-message', $message);
        }
    }

   /**
     * Edit Weblog Preferences
     *
     * @return void
     */
    public function editWeblog()
    {
        // Default values
        $i            = 0;
        $weblog_handle  = '';
        $weblog_name = '';
        $category_group_id    = '';
        $status_group_id = '';

        if (empty($weblog_id)) {
            if ( ! $weblog_id = Cp::pathVar('weblog_id')) {
                return false;
            }
        }

        $query = DB::table('weblogs')->where('id', $weblog_id)->first();

        if (!$query) {
            return redirect(kilvinCpUrl('weblogs-administration/weblogs-overview'));
        }

        foreach ($query as $key => $val) {
            $$key = $val;
        }

        $cp_message = session()->pull('cp-message');

        if ($cp_message) {
            $r .= Cp::quickDiv('box', $cp_message);
        }

        // New blog so set default
        if (empty($weblog_url)) {
           $weblog_url = Site::config('site_url');
        }

        //------------------------------------
        // Build the output
        //------------------------------------

        $r  = Cp::formOpen(['action' => 'weblogs-administration/update-weblog']);
        $r .= Cp::input_hidden('weblog_id', $weblog_id);

        // ------------------------------------
        //  General settings
        // ------------------------------------

        $r .= Cp::table('tableBorder', '0', '', '100%');
        $r .= '<tr>'.PHP_EOL;

        $r .= "<td class='tableHeadingAlt' id='weblog2' colspan='2'>";
        $r .= __('kilvin::admin.weblog_base_setup').'</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;

        // Weblog name field
        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::required().Cp::quickSpan('defaultBold', __('kilvin::admin.full_weblog_name')), '50%').
              Cp::tableCell('', Cp::input_text('weblog_name', $weblog_name, '20', '100', 'input', '260px'), '50%').
              '</tr>'.PHP_EOL;

        // Weblog handle field
        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell(
              	'',
              	Cp::required().
              		Cp::quickSpan(
              			'defaultBold',
              			__('kilvin::admin.weblog_handle')).
							'&nbsp;'.
							'-'.
							'&nbsp;'.
							__('kilvin::admin.field_handle_explanation'),
				'50%'
				).
              Cp::tableCell('', Cp::input_text('weblog_handle', $weblog_handle, '20', '40', 'input', '260px'), '50%').
              '</tr>'.PHP_EOL;

        // Weblog descriptions field
        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::admin.blog_description')), '50%').
              Cp::tableCell('', Cp::input_text('weblog_description', $weblog_description, '50', '225', 'input', '100%'), '50%').
              '</tr>'.PHP_EOL;

         // Weblog URL field
        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell(
                '',
                Cp::quickSpan(
                    'defaultBold',
                    __('kilvin::admin.weblog_url')
                ).
                Cp::quickDiv('default', __('kilvin::admin.weblog_url_explanation')),
                '50%').
              Cp::tableCell('', Cp::input_text('weblog_url', $weblog_url, '50', '80', 'input', '100%'), '50%').
              '</tr>'.PHP_EOL;

        $r .= '</table>'.PHP_EOL;

        // Text: * Indicates required fields
        $r .= Cp::quickDiv('littlePadding', Cp::required(1)).'<br>';

        // ------------------------------------
        //  Versioning settings
        // ------------------------------------

        $r .= Cp::table('tableBorder', '0', '', '100%');
        $r .= '<tr>'.PHP_EOL;

        $r .= "<td class='tableHeadingAlt' id='versioning2' colspan='2'>";
        $r .= __('kilvin::admin.versioning').'</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;

        // Enable Versioning?
        $r .= '<tr>'.PHP_EOL
             .Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::admin.enable_versioning')), '50%')
             .Cp::td('', '50%');

              $selected = ($enable_versioning == 'y') ? 1 : '';

        $r .= Cp::qlabel(__('kilvin::admin.yes'))
             .Cp::input_radio('enable_versioning', 'y', $selected).'&nbsp;';

              $selected = ($enable_versioning == 'n') ? 1 : '';

        $r .= Cp::qlabel(__('kilvin::admin.no'))
             .Cp::input_radio('enable_versioning', 'n', $selected)
             .'</td>'.PHP_EOL
             .'</tr>'.PHP_EOL;


        // Enable Quicksave versioning
        $r .= '<tr>'.PHP_EOL
             .Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::admin.enable_qucksave_versioning')).BR.__('kilvin::admin.quicksave_note'), '50%')
             .Cp::td('', '50%');

              $selected = ($enable_qucksave_versioning == 'y') ? 1 : '';

        $r .= Cp::qlabel(__('kilvin::admin.yes'))
             .Cp::input_radio('enable_qucksave_versioning', 'y', $selected).'&nbsp;';

              $selected = ($enable_qucksave_versioning == 'n') ? 1 : '';

        $r .= Cp::qlabel(__('kilvin::admin.no'))
             .Cp::input_radio('enable_qucksave_versioning', 'n', $selected)
             .'</td>'.PHP_EOL
             .'</tr>'.PHP_EOL;

        // Max Revisions
        $x = Cp::quickDiv('littlePadding', Cp::input_checkbox('clear_versioning_data', 'y', 0).' '.Cp::quickSpan('highlight', __('kilvin::admin.clear_versioning_data')));

        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::admin.max_revisions')).BR.__('kilvin::admin.max_revisions_note'), '50%').
              Cp::tableCell('', Cp::input_text('max_revisions', $max_revisions, '30', '4', 'input', '100%').$x, '50%').
              '</tr>'.PHP_EOL;

        $r .= '</table><br>'.PHP_EOL;

        // ------------------------------------
        //  Notifications
        // ------------------------------------

        $r .= Cp::table('tableBorder', '0', '', '100%');
        $r .= '<tr>'.PHP_EOL;

        $r .= "<td class='tableHeadingAlt' id='not2' colspan='2'>";
        $r .= __('kilvin::admin.notification_settings').'</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;

        $r .= '<tr>'.PHP_EOL
             .Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::admin.weblog_notify')), '50%')
             .Cp::td('', '50%');

        $selected = ($weblog_notify == 'y') ? 1 : '';

        $r .= Cp::qlabel(__('kilvin::admin.yes'))
             .Cp::input_radio('weblog_notify', 'y', $selected).'&nbsp;';

        $selected = ($weblog_notify == 'n') ? 1 : '';

        $r .= Cp::qlabel(__('kilvin::admin.no'))
             .Cp::input_radio('weblog_notify', 'n', $selected)
             .'</td>'.PHP_EOL
             .'</tr>'.PHP_EOL;

        $users = DB::table('members')
            ->distinct()
            ->select('members.screen_name', 'members.id AS member_id', 'members.email')
            ->leftJoin('member_group_preferences', function ($join) use ($weblog_id) {
                $join->on('member_group_preferences.member_group_id', '=', 'members.member_group_id')
                     ->where('member_group_preferences.handle', 'weblog_id_'.$weblog_id);
            })
            ->where('members.member_group_id', 1)
            ->orWhere('member_group_preferences.value', 'y')
            ->get();

        $weblog_notify_emails = explode(',', $weblog_notify_emails);

        $s = '<select name="weblog_notify_emails[]" multiple="multiple" size="8" style="width:100%">'.PHP_EOL;

        foreach($users as $row) {
            $selected = (in_array($row->member_id, $weblog_notify_emails)) ? 'selected="selected"' : '';
            $s .= '<option value="'.$row->member_id.'" '.$selected.'>'.$row->screen_name.' &lt;'.$row->email.'&gt;</option>'.PHP_EOL;
        }

        $s .= '</select>'.PHP_EOL;

        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell(
                '',
                Cp::quickSpan('defaultBold', __('kilvin::admin.emails_of_notification_recipients')), '50%', 'top').
              Cp::tableCell('', $s, '50%').
              '</tr>'.PHP_EOL;

        $r .= '</table>'.PHP_EOL;

        // Update button and form close
        $r .= Cp::div('littlePadding');
        $r .= Cp::quickDiv('littlePadding', Cp::input_submit(__('kilvin::cp.update'),'return'));
        $r .= '</div>'.PHP_EOL.'</form>'.PHP_EOL;

        // ------------------------------------
        //  Create Table
        // ------------------------------------

        Cp::$body .=
            Cp::table('', '0', '', '100%').
                Cp::tableRow(['valign' => "top", 'text' => $r]).
            '</table>'.
            PHP_EOL;

        Cp::$title = __('kilvin::admin.edit_weblog_prefs');

        Cp::$crumb =
            Cp::anchor('weblogs-administration/weblogs-overview', __('kilvin::admin.weblog-management')).
            Cp::breadcrumbItem(__('kilvin::admin.edit_weblog_prefs'));
    }

   /**
     * Edit Layout for Weblog
     *
     * @return string
     */
    public function editWeblogLayout()
    {
        if ( ! $weblog_id = Cp::pathVar('weblog_id')) {
            return Cp::unauthorizedAccess();
        }

        // ------------------------------------
        //  Fetch Weblog
        // ------------------------------------

        $weblog_query = DB::table('weblogs')
                ->where('id', $weblog_id)
                ->first();

        if (!$weblog_query) {
            return Cp::unauthorizedAccess();
        }

        // ------------------------------------
        //  Fetch Layout
        // ------------------------------------

        $layout_query = DB::table('weblog_layout_tabs')
            ->leftJoin('weblog_layout_fields', 'weblog_layout_tabs.id', '=', 'weblog_layout_fields.weblog_layout_tab_id')
            ->where('weblog_layout_tabs.weblog_id', $weblog_id)
            ->orderBy('weblog_layout_tabs.tab_order')
            ->orderBy('weblog_layout_fields.field_order')
            ->orderBy('weblog_layout_fields.field_handle')
            ->get();

        // ------------------------------------
        //  Fetch Custom Fields
        // ------------------------------------

        $available_fields = DB::table('weblog_fields')
            ->select('field_handle', 'field_name', 'field_type')
            ->where('weblog_field_group_id', $weblog_query->weblog_field_group_id)
            ->orderBy('field_name')
            ->get()
            ->keyBy('field_handle')
            ->map(function ($item, $key) {
                $item->used = false;
                return $item;
            })
            ->toArray();

        // ------------------------------------
        //  Layout Array
        // ------------------------------------

        foreach($layout_query as $row) {

            $handle = $row->weblog_layout_tab_id;

            if (!isset($layout[$handle])) {
                $layout[$handle] = [];
                $publish_tabs[$handle] = $row->tab_name;
            }

            if (isset($available_fields[$row->field_handle])) {
                $layout[$row->weblog_layout_tab_id][$row->field_handle] = $available_fields[$row->field_handle];
                $available_fields[$row->field_handle]->used = true;
            }
        }

        // ------------------------------------
        //  Build Vars
        // ------------------------------------

        $vars = [
            'calendar_image'       => Cp::calendarImage(),
            'weblog_id'            => $weblog_id,
            'publish_tabs'         => $publish_tabs,
            'layout'               => $layout,
            'fields'               => $available_fields,
            'url_title_javascript' => urlTitleJavascript('_'),
            'svg_icon_gear'        => svgIconGear()
        ];

        // -----------------------------------------
        //  CP Message?
        // -----------------------------------------

        $cp_message = session()->pull('cp_message');

        if (!empty($cp_message)) {
            $vars['cp_message'] = $cp_message;
        }

        // ------------------------------------
        //  Build Page
        // ------------------------------------

        Cp::$title = $vars['page_name'] = __('kilvin::admin.edit_weblog_layout');

        Cp::$crumb =
            Cp::anchor('weblogs-administration/weblogs-overview', $weblog_query->weblog_name).
            Cp::breadcrumbItem(__('kilvin::admin.edit_weblog_layout'));


        return view('kilvin::cp.administration.weblogs.layout', $vars);
    }

   /**
     * Update Layout for Weblog
     *
     * @return string|\Illuminate\Http\RedirectResponse
     */
    public function updateWeblogLayout()
    {
        if ( ! $weblog_id = Request::input('weblog_id')) {
            return Cp::unauthorizedAccess();
        }

        if ( ! $tabs = Request::input('tabs')) {
            return Cp::unauthorizedAccess();
        }

        if (!is_array($tabs)) {
            return Cp::unauthorizedAccess();
        }

        if ( ! $fields = Request::input('fields')) {
            return Cp::unauthorizedAccess();
        }

        if (!is_array($fields)) {
            return Cp::unauthorizedAccess();
        }

        // ------------------------------------
        //  Fetch Weblog
        // ------------------------------------

        $weblog_query = DB::table('weblogs')
                ->where('id', $weblog_id)
                ->first();

        if (!$weblog_query) {
            return Cp::unauthorizedAccess();
        }

        // ------------------------------------
        //  Fetch Custom Fields
        // ------------------------------------

        $field_query = DB::table('weblog_fields')
            ->select('field_name', 'field_handle', 'field_type')
            ->where('weblog_field_group_id', $weblog_query->weblog_field_group_id)
            ->orderBy('field_name')
            ->get();

        $available_fields = $field_query
            ->keyBy('field_handle')
            ->toArray();

        // ------------------------------------
        // Clear Out Existing (Ruthless!)
        // - Foreign keys will take care of weblog_layout_fields
        // ------------------------------------

        $tab_ids = DB::table('weblog_layout_tabs')
            ->where('weblog_id', $weblog_id)
            ->delete();

        // ------------------------------------
        //  Add in Tabs
        // ------------------------------------

        $order = 0;

        $db_tabs = [];

        foreach($tabs as $handle => $tab) {

            $id = DB::table('weblog_layout_tabs')
                ->insertGetId(
                    [
                        'weblog_id' => $weblog_id,
                        'tab_name'  => $tab,
                        'tab_order' => $order++
                    ]
                );

            $db_tabs[$handle] = $id;
        }

        // ------------------------------------
        //  Add in Fields
        // ------------------------------------

        foreach($fields as $handle => $tab_fields) {

            if (!isset($db_tabs[$handle])) {
                continue;
            }

            $order = 0;

            foreach($tab_fields as $field_handle) {

                if (!isset($available_fields[$field_handle])) {
                    continue;
                }

                $id = DB::table('weblog_layout_fields')
                    ->insertGetId(
                        [
                            'weblog_layout_tab_id' => $db_tabs[$handle],
                            'field_handle' => $field_handle,
                            'field_order'  => $order++
                        ]
                    );
            }
        }

        //  Redirect with Message
        return redirect(kilvinCpUrl('weblogs-administration/edit-weblog-layout/weblog_id='.$weblog_id))
            ->with('cp-message', __('kilvin::admin.Layout Updated'));
    }

   /**
     * Edit Weblog Fields
     *
     * @return string
     */
    public function editWeblogFields()
    {
        // Default values
        $i = 0;
        $category_group_id = '';
        $status_group_id = '';

        if (empty($weblog_id)) {
            if ( ! $weblog_id = Cp::pathVar('weblog_id')) {
                return false;
            }
        }

        $query = DB::table('weblogs')->where('id', $weblog_id)->first();

        if (!$query) {
            return redirect(kilvinCpUrl('weblogs-administration/weblogs-overview'));
        }

        foreach ($query as $key => $val) {
            $$key = $val;
        }

        $cp_message = session()->pull('cp-message');

        if ($cp_message) {
            Cp::$body .= Cp::quickDiv('success-message', $cp_message);
        }

        // New blog so set default
        if (empty($weblog_url)) {
           $weblog_url = Site::config('site_url');
        }

        //------------------------------------
        // Build the output
        //------------------------------------

        $js = <<<EOT
<script type="text/javascript">

var lastShownObj = '';
var lastShownColor = '';
function showHideMenu(objValue)
{
    if (lastShownObj) {
        $('#' + lastShownObj+'_pointer a').first().css('color', lastShownColor);
        $('#' + lastShownObj+'_on').css('display', 'none');
    }

    lastShownObj = objValue;
    lastShownColor = $('#' + objValue+'_pointer a').first().css('color');

    $('#' + objValue + '_on').css('display', 'block');
    $('#' + objValue+'_pointer a').first().css('color', '#000');

    return false;
}

$(function() {
    showHideMenu('fields');
});

</script>

EOT;
        Cp::$body .= $js;

        $r  = Cp::formOpen(['action' => 'weblogs-administration/update-weblog-fields']);
        $r .= Cp::input_hidden('weblog_id', $weblog_id);

        $r .= Cp::quickDiv('none', '', 'menu_contents');

        // ------------------------------------
        //  Fields
        // ------------------------------------

        $category_group_ids = explode('|', $category_group_id);

        $r .= '<div id="fields_on" style="display: none; padding:0; margin: 0;">';
        $r .= Cp::table('tableBorder', '0', '', '100%');
        $r .= '<tr>'.PHP_EOL;

        $r .= "<td class='tableHeadingAlt' colspan='2'>";
        $r .= NBS.__('kilvin::admin.fields').'</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;

        // Category group select list
        $query = DB::table('category_groups')
            ->orderBy('group_name')
            ->select('category_groups.id AS category_group_id', 'group_name')
            ->get();

        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::admin.category_group')), '40%', 'top');

        $r .= Cp::td().
              Cp::input_select_header('category_group_id[]', ($query->count() > 0) ? 'y' : '');

        $selected = (empty($category_group_ids)) ? 1 : '';

        $r .= Cp::input_select_option('', __('kilvin::admin.none'), $selected);

        foreach ($query as $row) {
            $selected = (in_array($row->category_group_id, $category_group_ids)) ? 1 : '';

            $r .= Cp::input_select_option($row->category_group_id, $row->group_name, $selected);
        }

        $r .= Cp::input_select_footer().
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        // Status group select list
        $query = DB::table('status_groups')
            ->orderBy('group_name')
            ->select('status_groups.id AS status_group_id', 'group_name')
            ->get();

        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::admin.status_group')));

        $r .= Cp::td().
              Cp::input_select_header('status_group_id');

        $selected = '';

        $r .= Cp::input_select_option('', __('kilvin::admin.none'), $selected);

        foreach ($query as $row) {
            $selected = ($status_group_id == $row->status_group_id) ? 1 : '';

            $r .= Cp::input_select_option($row->status_group_id, $row->group_name, $selected);
        }

        $r .= Cp::input_select_footer().
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        // Field Groups
        $query = DB::table('weblog_field_groups')
            ->orderBy('group_name')
            ->select('id AS weblog_field_group_id', 'group_name')
            ->get();

        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::admin.custom_field_group')));

        $r .= Cp::td().
              Cp::input_select_header('weblog_field_group_id');

        $selected = '';

        $r .= Cp::input_select_option('', __('kilvin::admin.none'), $selected);

        foreach ($query as $row) {
            $selected = ($weblog_field_group_id == $row->weblog_field_group_id) ? 1 : '';

            $r .= Cp::input_select_option($row->weblog_field_group_id, $row->group_name, $selected);
        }

        $r .= Cp::input_select_footer().
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;


        $r .= '</table>'.PHP_EOL;
        $r .= '</div>'.PHP_EOL;

        // ------------------------------------
        //  Default Values
        // ------------------------------------

        $r .= '<div id="defaults_on" style="display: none; padding:0; margin: 0;">';
        $r .= Cp::table('tableBorder', '0', '', '100%');
        $r .= '<tr>'.PHP_EOL;

        $r .= "<td class='tableHeadingAlt' colspan='2'>";
        $r .= NBS.__('kilvin::admin.default_settings').'</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;

        // Default status menu
        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::admin.default_status')), '50%').
              Cp::td('', '50%').
              Cp::input_select_header('default_status');

        $query = DB::table('statuses')
            ->where('status_group_id', $status_group_id)
            ->orderBy('status')
            ->get();

        foreach ($query as $row) {
            $selected = ($default_status == $row->status) ? 1 : '';

            $status_name =
                ($row->status == 'open' OR $row->status == 'closed') ?
                __('kilvin::admin.'.$row->status) :
                $row->status;

            $r .= Cp::input_select_option($row->status, $status_name, $selected);
        }

        $r .= Cp::input_select_footer().
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        // Default category menu
        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::admin.default_category')), '50%');

        $r .= Cp::td('', '50%').
              Cp::input_select_header('default_category');

        $selected = '';

        $r .= Cp::input_select_option('', __('kilvin::admin.none'), $selected);

        $query = DB::table('categories')
            ->join('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->whereIn('categories.category_group_id', $category_group_ids)
            ->select(
                'categories.id AS category_id',
                'categories.category_name',
                'category_groups.group_name'
            )
            ->orderBy('category_groups.group_name')
            ->orderBy('categories.category_name')
            ->get();

        foreach ($query as $row) {
            $row->display_name = $row->group_name.': '.$row->category_name;

            $selected = ($default_category == $row->category_id) ? 1 : '';

            $r .= Cp::input_select_option($row->category_id, $row->display_name, $selected);
        }

        $r .= Cp::input_select_footer().
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        $r .= '</table>'.PHP_EOL;
        $r .= '</div>'.PHP_EOL;

        // ------------------------------------
        //  Layout Options
        // ------------------------------------

        $r .= '<div id="options_on" style="display: none; padding:0; margin: 0;">';
        $r .= Cp::table('tableBorder', '0', '', '100%');
        $r .= '<tr>'.PHP_EOL;

        $r .= "<td class='tableHeadingAlt' colspan='2'>";
        $r .= NBS.__('kilvin::admin.field_display_options').'</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;

        // Live Look Template
        $r .= '<tr>'.PHP_EOL
             .Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::admin.live_look_template')))
             .Cp::td('', '50%')
             .Cp::input_select_header('live_look_template')
             .Cp::input_select_option('0', __('kilvin::admin.no_live_look_template'), ($live_look_template == 0) ? '1' : 0);

        $tquery = DB::table('templates AS t')
            ->join('sites', 'sites.id', '=', 't.site_id')
            ->orderBy('t.template_name')
            ->select('t.folder', 't.id AS template_id', 't.template_name', 'sites.site_name')
            ->get();

        foreach ($tquery as $template) {
            $r .= Cp::input_select_option(
                $template->template_id,
                $template->site_name.': '.removeDoubleSlashes($template->folder.'/'.$template->template_name),
                (($template->template_id == $live_look_template) ? 1 : ''));
        }

        $r .= Cp::input_select_footer()
             .'</td>'.PHP_EOL
             .'</tr>'.PHP_EOL;


        // url_title_prefix
        $r .= '<tr>'.PHP_EOL
             .Cp::tableCell(
             	'',
             	Cp::quickSpan(
             		'defaultBold', __('kilvin::admin.url_title_prefix')
             	).
             	'&nbsp;'.'-'.'&nbsp;'.
             	__('kilvin::admin.single_word_no_spaces_with_underscores_hyphens')
              )
             .Cp::td('', '50%')
             .Cp::input_text('url_title_prefix', $url_title_prefix, '50', '255', 'input', '100%')
             .'</td>'.PHP_EOL
             .'</tr>'.PHP_EOL;

        // show_url_title
        $r .= '<tr>'.PHP_EOL
             .Cp::tableCell(
                '',
                Cp::quickSpan(
                    'defaultBold',
                    __('kilvin::admin.show_url_title').
                    '<div class="subtext">'.__('kilvin::admin.show_url_title_blurb').'</div>'
                ),
                '50%')
             .Cp::td('', '50%');
        $r .= Cp::qlabel(__('kilvin::admin.yes'))
             .Cp::input_radio('show_url_title', 'y', ($show_url_title == 'y') ? 1 : '').'&nbsp;';
        $r .= Cp::qlabel(__('kilvin::admin.no'))
             .Cp::input_radio('show_url_title', 'n', ($show_url_title == 'n') ? 1 : '')
             .'</td>'.PHP_EOL
             .'</tr>'.PHP_EOL;

        // show_categories_tab
        $r .= '<tr>'.PHP_EOL
             .Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::admin.show_categories_tab')), '50%')
             .Cp::td('', '50%');
        $r .= Cp::qlabel(__('kilvin::admin.yes'))
             .Cp::input_radio('show_categories_tab', 'y', ($show_categories_tab == 'y') ? 1 : '').'&nbsp;';
        $r .= Cp::qlabel(__('kilvin::admin.no'))
             .Cp::input_radio('show_categories_tab', 'n', ($show_categories_tab == 'n') ? 1 : '')
             .'</td>'.PHP_EOL
             .'</tr>'.PHP_EOL;

        $r .= '</table>'.PHP_EOL;
        $r .= '</div>'.PHP_EOL;

        // ------------------------------------
        // Update Buttons
        // ------------------------------------

        $r .= Cp::div('littlePadding');
        $r .= Cp::quickDiv(
            'littlePadding',
            Cp::input_submit(__('kilvin::cp.update')).
                NBS.
                Cp::input_submit(__('kilvin::cp.update_and_return'),'return')
        );
        $r .= '</div>'.PHP_EOL.'</form>'.PHP_EOL;

        // ------------------------------------
        //  Finish up the Layout
        // ------------------------------------

        Cp::$body .= Cp::table('', '0', '', '100%');

        // Menu areas
        $areas = [
            "fields"   => "admin.fields",
            "defaults" => "admin.default_settings",
            "options"  => "admin.field_display_options",
        ];

        $menu = '';

        foreach($areas as $area => $area_lang) {
            $menu .= Cp::quickDiv(
                'navPad',
                '<span id="'.$area.'_pointer">&#8226; '.
                    Cp::anchor("#", __('kilvin::'.$area_lang), 'onclick="showHideMenu(\''.$area.'\'); return false;"').
                '</span>');
        }

        $first_text =   Cp::div('tableHeadingAlt')
                        .   $weblog_name
                        .'</div>'.PHP_EOL
                        .Cp::div('profileMenuInner')
                        .   $menu
                        .'</div>'.PHP_EOL;

        // Create the Table
        $table_row = [
            'first'     => ['valign' => "top", 'width' => "220px", 'text' => $first_text],
            'second'    => ['class' => "default", 'width'  => "8px"],
            'third'     => ['valign' => "top", 'text' => $r]
        ];

        Cp::$body .= Cp::tableRow($table_row).'</table>'.PHP_EOL;

        Cp::$title = __('kilvin::admin.edit_fields');

        Cp::$crumb =
            Cp::anchor('weblogs-administration/weblogs-overview', $weblog_name).
            Cp::breadcrumbItem(__('kilvin::admin.edit_fields'));
    }

   /**
    * Delete Weblog Confirm form
    *
    * @return string
    */
    public function deleteWeblogConfirm()
    {
        if ( ! $weblog_id = Cp::pathVar('weblog_id')) {
            return false;
        }

        $query = DB::table('weblogs')
            ->select('weblog_name')
            ->where('id', $weblog_id)
            ->first();

        Cp::$title = __('kilvin::admin.delete_weblog');

        Cp::$crumb =
            Cp::anchor(
            	'weblogs-administration/weblogs-overview',
            	$query->weblog_name
            ).
            Cp::breadcrumbItem(__('kilvin::admin.delete_weblog'));

        Cp::$body = Cp::deleteConfirmation(
            [
                'path'      => 'weblogs-administration/delete-weblog/weblog_id='.$weblog_id,
                'heading'   => 'admin.delete_weblog',
                'message'   => 'admin.delete_weblog_confirmation',
                'item'      => $query->weblog_name,
                'extra'     => '',
                'hidden'    => ['weblog_id' => $weblog_id]
            ]
        );
    }

   /**
    * Delete Weblog
    *
    * @return string
    */
    public function deleteWeblog()
    {
        if ( ! $weblog_id = Request::input('weblog_id')) {
            return false;
        }

        $weblog_name = DB::table('weblogs')
            ->where('id', $weblog_id)
            ->value('weblog_name');

        if (empty($weblog_name)) {
            return false;
        }

        Cp::log(__('kilvin::admin.weblog_deleted').NBS.$weblog_name);

        $query = DB::table('weblog_entries')
            ->where('weblog_id', $weblog_id)
            ->select('id AS entry_id', 'author_id')
            ->get();

        $entries = [];
        $authors = [];

        if ($query->count() > 0)
        {
            foreach ($query as $row)
            {
                $entries[] = $row->entry_id;
                $authors[] = $row->author_id;
            }
        }

        $authors = array_unique($authors);

        DB::table('weblog_layout_tabs')->where('weblog_id', $weblog_id)->delete();
        DB::table('weblog_entry_data')->where('weblog_id', $weblog_id)->delete();
        DB::table('weblog_entries')->where('weblog_id', $weblog_id)->delete();
        DB::table('weblogs')->where('id', $weblog_id)->delete();


        // ------------------------------------
        //  Clear catagories
        // ------------------------------------

        if (!empty($entries)) {
            DB::table('weblog_entry_categories')->whereIn('weblog_entry_id', $entries)->delete();
        }

        // ------------------------------------
        //  Update author stats
        // ------------------------------------

        foreach ($authors as $author_id)
        {
            $total_entries = DB::table('weblog_entries')->where('author_id', $author_id)->count();

            DB::table('members')
                ->where('id', $author_id)
                ->update(['total_entries' => $total_entries]);
        }

        // ------------------------------------
        //  McFly, update the stats!
        // ------------------------------------

        Stats::update_weblog_stats();

        return redirect(kilvinCpUrl('weblogs-administration/weblogs-overview'))
            ->with('cp-message', __('kilvin::admin.weblog_deleted').NBS.'<b>'.$weblog_name.'</b>');
    }

//=====================================================================
//  Category Administration
//=====================================================================

   /**
    * Category Overview page
    *
    * @return string
    */
    public function categoryOverview()
    {
        if ( ! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        $message = session()->pull('cp-message');

        Cp::$title  = __('kilvin::admin.categories');
        Cp::$crumb  = __('kilvin::admin.categories');

        $right_links[] = [
            'weblogs-administration/edit-category-group-form',
            __('kilvin::admin.create_new_category_group')
        ];

        $r = Cp::header(__('kilvin::admin.categories'), $right_links);

        // Fetch category groups
        $query = DB::table('category_groups')
            ->orderBy('group_name')
            ->select('category_groups.id AS category_group_id', 'group_name')
            ->get();

        if ($query->count() == 0) {
            $r .= stripslashes($message);
            $r .= Cp::div('box');
            $r .= Cp::quickDiv('littlePadding', Cp::heading(__('kilvin::admin.no_category_group_message'), 5));
            $r .= Cp::quickDiv('littlePadding',
                Cp::anchor(
                    'weblogs-administration/edit-category-group-form',
                    __('kilvin::admin.create_new_category_group')));
            $r .= '</div>'.PHP_EOL;

            return Cp::$body = $r;
        }

        if ($message != '') {
            $r .= $message;
        }

        $i = 0;

        $r .= Cp::table('tableBorder', '0', '', '100%').
              '<tr>'.PHP_EOL.
              Cp::td('tableHeading').'</td>'.PHP_EOL.
              Cp::td('tableHeading', '', '4').
              __('kilvin::admin.category_groups').
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        foreach($query as $row) {
            $count = DB::table('categories')
                ->where('category_group_id', $row->category_group_id)
                ->count();

            $r .= '<tr>'.PHP_EOL.
                  Cp::td('', '5%').
                  Cp::quickSpan('defaultBold', $row->category_group_id).
                  '</td>'.PHP_EOL;

            $r .= Cp::tableCell('',
                  Cp::anchor(
					'weblogs-administration/edit-category-group-form/category_group_id='.$row->category_group_id,
					$row->group_name,
					'class="defaultBold"'
					));

            $r .= Cp::tableCell('',
                  Cp::anchor(
                        'weblogs-administration/category-manager/category_group_id='.$row->category_group_id,
                        __('kilvin::admin.add_edit_categories')
                    ).
                   ' ('.$count.')'
                );


            $r .= Cp::tableCell('',
                  Cp::anchor(
                        'weblogs-administration/delete-category-group-conf/category_group_id='.$row->category_group_id,
                        __('kilvin::cp.delete'),
                        'class="delete-link"'
                    )).
                  '</tr>'.PHP_EOL;
        }

        $r .= '</table>'.PHP_EOL;
        Cp::$body = $r;
    }

   /**
    * Edit Category Group form
    *
    * @return string
    */
    public function editCategoryGroupForm()
    {
        if ( ! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        // default values
        $edit = false;
        $category_group_id = '';
        $group_name = '';
        $can_edit   = [];
        $can_delete = [];

        // If we have the category_group_id variable, it's an edit request, so fetch the category data
        if ($category_group_id = Cp::pathVar('category_group_id')) {
            $edit = true;

            if ( ! is_numeric($category_group_id)) {
                return false;
            }

            $query = DB::table('category_groups')
            	->select('category_groups.id AS category_group_id', 'category_groups.*')
                ->where('id', $category_group_id)
                ->first();

            if (empty($query)) {
                return $this->category_overview();
            }

            foreach ($query as $key => $val) {
                $$key = $val;
            }
        }

        // ------------------------------------
        //  Opening Outpu
        // ------------------------------------

        $title = ($edit == false)
        	? __('kilvin::admin.create_new_category_group')
        	: __('kilvin::admin.edit_category_group');

        // Build our output
        $r = Cp::formOpen([
            'action' => 'weblogs-administration/update-category-group'
        ]);

        if ($edit == true) {
            $r .= Cp::input_hidden('category_group_id', $category_group_id);
        }

        $r .= Cp::quickDiv('tableHeading', $title);

        $r .= Cp::div('box').
              Cp::quickDiv(
              	'littlePadding',
              	Cp::quickDiv(
              		'defaultBold',
              		__('kilvin::admin.name_of_category_group')
              	)
              ).
              Cp::quickDiv(
              	'littlePadding',
              	Cp::input_text('group_name', $group_name, '20', '50', 'input', '300px')
              );

        $r .= '</div>'.PHP_EOL; // main box

        $r .= Cp::div('paddingTop');

        if ($edit == false) {
            $r .= Cp::input_submit(__('kilvin::cp.submit'));
        }
        else {
            $r .= Cp::input_submit(__('kilvin::cp.update'));
        }

        $r .= '</div>'.PHP_EOL;

        $r .= '</form>'.PHP_EOL;

        Cp::$title = $title;
        Cp::$crumb =
            Cp::anchor('weblogs-administration/category-overview', __('kilvin::admin.category_groups')).
            Cp::breadcrumbItem($title);

        Cp::$body  = $r;
    }

   /**
    * Create/Update Category Group
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
    public function updateCategoryGroup()
    {
        if ( ! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        $edit = (bool) request()->filled('category_group_id');

        if (!request()->filled('group_name')) {
            // @todo = Make redirect
            return $this->editCategoryGroupForm();
        }

        $category_group_id = request()->input('category_group_id');
        $group_name = request()->input('group_name');

        // check for bad characters in group name
        if ( ! preg_match("#^[a-zA-Z0-9_\-/\s]+$#i", $group_name)) {
            return Cp::errorMessage(__('kilvin::admin.group_illegal_characters'));
        }

        // Is the group name taken for site
        $query = DB::table('category_groups')
            ->where('site_id', Site::config('site_id'))
            ->where('group_name', $group_name);

        if ($edit === true) {
            $query->where('id', '!=', $category_group_id);
        }

        if ($query->count() > 0) {
            return Cp::errorMessage(__('kilvin::admin.taken_category_group_name'));
        }

        // Construct the query based on whether we are updating or inserting
        if ($edit === false) {
            $insert['group_name'] = $group_name;
            $insert['site_id'] = Site::config('site_id');

            DB::table('category_groups')->insert($insert);

            $success_msg = __('kilvin::admin.category_group_created');

            Cp::log(__('kilvin::admin.category_group_created').$group_name);
        } else {
            DB::table('category_groups')
                ->where('id', $category_group_id)
                ->update(['group_name' => $group_name]);

            $success_msg = __('kilvin::admin.category_group_updated');

            Cp::log(__('kilvin::admin.category_group_updated').$group_name);
        }

        $message  = Cp::div('success-message');
        $message .= $success_msg.$group_name;

        if ($edit === false) {
            $query = DB::table('weblogs')
                ->select('weblogs.id AS weblog_id')
                ->get();

            if ($query->count() > 0) {
                $message .= Cp::quickDiv(
                	'littlePadding',
                	Cp::quickDiv('alert', __('kilvin::admin.assign_group_to_weblog'))
                );

                if ($query->count() == 1) {
                    $link = 'weblogs-administration/edit-weblog-fields/weblog_id='.$query->first()->weblog_id;
                } else {
                    $link = 'weblogs-administration/weblogs-overview';
                }

                $message .= Cp::quickDiv(
                	'littlePadding',
                	Cp::anchor(
                		$link,
                		__('kilvin::admin.click_to_assign_group')
                	)
                );
            }
        }

        $message .= '</div>'.PHP_EOL;

        return redirect(kilvinCpUrl('weblogs-administration/category-overview'))
            ->with('cp-message', $message);
    }

   /**
    * Delete Category Group confirmation form
    *
    * @return string
    */
    public function deleteCategoryGroupConf()
    {
        if ( ! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        if (!($category_group_id = Cp::pathVar('category_group_id')) || ! is_numeric($category_group_id)) {
            return false;
        }

        $group_name = DB::table('category_groups')->where('id', $category_group_id)->value('group_name');

        if(empty($group_name)) {
            return false;
        }

        Cp::$title = __('kilvin::admin.delete_group');
        Cp::$crumb =
            Cp::anchor('weblogs-administration/category_overview', __('kilvin::admin.category_groups')).
            Cp::breadcrumbItem(__('kilvin::admin.delete_group'));

        Cp::$body = Cp::deleteConfirmation(
            [
                'path'      => 'weblogs-administration/delete-category-group/category_group_id='.$category_group_id,
                'heading'   => 'admin.delete_group',
                'message'   => 'admin.delete_category_group_confirmation',
                'item'      => $group_name,
                'extra'     => '',
                'hidden'    => ['category_group_id' => $category_group_id]
            ]
        );
    }

   /**
    * Delete Category Group (and all categories)
    *
    * @return string
    */
    public function deleteCategoryGroup()
    {
        if ( ! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        if (($category_group_id = Request::input('category_group_id')) === false or ! is_numeric($category_group_id)) {
            return false;
        }

        $query = DB::table('category_groups')
            ->where('id', $category_group_id)
            ->select('category_groups.id AS category_group_id', 'group_name')
            ->first();

        if (!$query) {
            return false;
        }

        $name = $query->group_name;
        $category_group_id = $query->category_group_id;

        // ------------------------------------
        //  Delete from weblog_entry_categories
        // ------------------------------------

        $cat_ids = DB::table('categories')
            ->where('category_group_id', $category_group_id)
            ->pluck('id AS category_id')
            ->all();

        if (!empty($cat_ids)) {
            DB::table('weblog_entry_categories')
                ->whereIn('category_id', $cat_ids)
                ->delete();
        }

        DB::table('category_groups')
            ->where('id', $category_group_id)
            ->delete();

        DB::table('categories')
            ->where('category_group_id', $category_group_id)
            ->delete();

        $message = Cp::quickDiv('success-message', __('kilvin::admin.category_group_deleted').NBS.'<b>'.$name.'</b>');

        Cp::log(__('kilvin::admin.category_group_deleted').'&nbsp;'.$name);

        cmsClearCaching('all');

        return redirect(kilvinCpUrl('weblogs-administration/category-overview'))
            ->with('cp-message', $message);
    }

   /**
    * Build Category Tree for Display in Edit Categories page
    *
    * @param string $type
    * @param integer $category_group_id
    * @param integer $p_id
    * @param string $sort_order
    * @return string
    */
    public function categoryTree($type = 'text', $category_group_id = '', $p_id = '', $sort_order = 'a')
    {
        // Fetch category group ID number
        if ($category_group_id == '') {
            $category_group_id = Cp::pathVar('category_group_id');
        }

        if ( ! is_numeric($category_group_id)) {
            return false;
        }

        // Fetch category groups
        $query = DB::table('categories')
            ->where('category_group_id', $category_group_id)
            ->select('id AS category_id', 'category_name', 'parent_id', 'category_url_title')
            ->orderBy('parent_id')
            ->orderBy(($sort_order == 'a') ? 'category_name' : 'category_order')
            ->get();

        if ($query->count() == 0) {
            return false;
        }

        // Assign the query result to a multi-dimensional array
        foreach($query as $row) {
            $cat_array[$row->category_id]  = [$row->parent_id, $row->category_name, $row->category_url_title];
        }

        if ($type == 'data')  {
            return $cat_array;
        }

        $up     = '<img src="'.arrowUp().'" border="0"  width="16" height="16" alt="" title="" />';
        $down   = '<img src="'.arrowDown().'" border="0"  width="16" height="16" alt="" title="" />';

        $can_delete = (Session::access('can_edit_categories')) ? true : false;

        $zurl  = (Cp::pathVar('Z') == 1) ? '/Z=1' : '';
        $zurl .= (Cp::pathVar('category_group_id') !== null) ? '/category_group_id='.Cp::pathVar('category_group_id') : '';
        $zurl .= (Cp::pathVar('integrated') !== null) ? '/integrated='.Cp::pathVar('integrated') : '';

        foreach($cat_array as $key => $val) {
            if (0 == $val[0]) {
                if ($type == 'table')
                {
                    if ($can_delete == true)
                        $delete = Cp::anchor(
                            'weblogs-administration/delete-category-confirm'.
                                '/category_id='.$key.
                                $zurl,
                            __('kilvin::cp.delete'),
                            'class="delete-link"');
                    else {
                        $delete = __('kilvin::cp.delete');
                    }

                    $this->categories[] =
                        Cp::tableQuickRow(
                            '',
                            [
                                $key,
                                Cp::anchor(
                                    'weblogs-administration/change-category-order'.
                                        '/category_id='.$key.
                                        '/category_group_id='.$category_group_id.
                                        '/order=up'.$zurl,
                                    $up).
                                NBS.
                                Cp::anchor(
                                    'weblogs-administration/change-category-order'.
                                        '/category_id='.$key.
                                        '/category_group_id='.$category_group_id.
                                        '/order=down'.$zurl,
                                    $down),
                                Cp::quickDiv('defaultBold', NBS.$val[1]),
                                Cp::quickDiv('defaultBold', NBS.$val[2]),
                                Cp::anchor(
                                    'weblogs-administration/edit-category-form'.
                                        '/category_id='.$key.
                                        '/category_group_id='.$category_group_id.$zurl,
                                    __('kilvin::cp.edit')),
                                $delete
                            ]
                        );
                } else {
                    $this->categories[] = Cp::input_select_option($key, $val[1], ($key == $p_id) ? '1' : '');
                }

                $this->categorySubTree($key, $cat_array, $category_group_id, $depth=0, $type, $p_id);
            }
        }
    }

   /**
    * Helps build Category Tree for Display in Edit Categories page
    *
    * @param integer $cat_id
    * @param array $cat_array
    * @param integer $category_group_id
    * @param integer $depth
    * @param string $type
    * @param integer $p_id
    * @return string
    */
    public function categorySubTree($cat_id, $cat_array, $category_group_id, $depth, $type, $p_id)
    {
        if ($type == 'table') {
            $spcr = '<span style="display:inline-block; margin-left:10px;"></span>';
            $indent = $spcr.'<img src="'.categoryIndent().'" border="0" width="12" height="12" title="indent" style="vertical-align:top; display:inline-block;"  />';
        } else {
            $spcr = '&nbsp;';
            $indent = $spcr.$spcr.$spcr.$spcr;
        }

        $up   = '<img src="'.arrowUp().'" border="0"  width="16" height="16" alt="" title="" />';
        $down = '<img src="'.arrowDown().'" border="0"  width="16" height="16" alt="" title="" />';


        if ($depth == 0) {
            $depth = 1;
        } else {
            $indent = str_repeat($spcr, $depth+1).$indent;
            $depth = ($type == 'table') ? $depth + 1 : $depth + 4;
        }

        $can_delete = (Session::access('can_edit_categories')) ? true : false;

        $zurl = (Cp::pathVar('Z') == 1) ? '/Z=1' : '';
        $zurl .= (Cp::pathVar('category_group_id') !== null) ? '/category_group_id='.Cp::pathVar('category_group_id') : '';
        $zurl .= (Cp::pathVar('integrated') !== null) ? '/integrated='.Cp::pathVar('integrated') : '';

        foreach ($cat_array as $key => $val) {
            if ($cat_id == $val[0]) {
                $pre = ($depth > 2) ? "&nbsp;" : '';

                if ($type == 'table') {
                    if ($can_delete == true)
                        $delete = Cp::anchor(
                            'weblogs-administration/delete-category-confirm'.
                                '/category_id='.$key.$zurl,
                            __('kilvin::cp.delete'),
                            'class="delete-link"');
                    else {
                        $delete = __('kilvin::cp.delete');
                    }

                    $this->categories[] =

                    Cp::tableQuickRow(
                        '',
                        [
                            $key,
                            Cp::anchor(
                                'weblogs-administration/change-category-order'.
                                    '/category_id='.$key.
                                    '/category_group_id='.$category_group_id.
                                    '/order=up'.$zurl,
                                $up).
                        NBS.
                            Cp::anchor(
                                'weblogs-administration/change-category-order'.
                                    '/category_id='.$key.
                                    '/category_group_id='.$category_group_id.
                                    '/order=down'.$zurl,
                                $down),
                            Cp::quickDiv('defaultBold', $pre.$indent.NBS.$val[1]),
                            Cp::quickDiv('defaultBold', $pre.$indent.NBS.$val[2]),
                            Cp::anchor(
                                'weblogs-administration/edit-category-form'.
                                    '/category_id='.$key.
                                    '/category_group_id='.$category_group_id.$zurl,
                                __('kilvin::cp.edit')),
                            $delete
                        ]
                    );
                } else {
                    $this->categories[] = Cp::input_select_option($key, $pre.$indent.NBS.$val[1], ($key == $p_id) ? '1' : '');
                }

                $this->categorySubTree($key, $cat_array, $category_group_id, $depth, $type, $p_id);
            }
        }
    }

   /**
    * Change order of categories form
    *
    * @return string
    */
    public function changeCategoryOrder()
    {
        if (! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        // Fetch required globals
        foreach (['category_id', 'category_group_id', 'order'] as $var)
        {
            if (!Cp::pathVar($var)) {
                return false;
            }

            $$var = Cp::pathVar($var);
        }

        $zurl  = (Cp::pathVar('Z') == 1) ? '/Z=1' : '';
        $zurl .= (Cp::pathVar('category_group_id') !== null) ? '/category_group_id='.Cp::pathVar('category_group_id') : '';
        $zurl .= (Cp::pathVar('integrated') !== null) ? '/integrated='.Cp::pathVar('integrated') : '';

        // Return Location
        $return = kilvinCpUrl('weblogs-administration/category-manager/category_group_id='.$category_group_id.$zurl);

        // Fetch the parent ID
        $parent_id = DB::table('categories')
            ->where('id', $category_id)
            ->value('parent_id');

        // Is the requested category already at the beginning/end of the list?

        $dir = ($order == 'up') ? 'asc' : 'desc';

        $query = DB::table('categories')
            ->select('id AS category_id')
            ->where('category_group_id', $category_group_id)
            ->where('parent_id', $parent_id)
            ->orderBy('category_order', $dir)
            ->first();

        if ($query->category_id == $category_id) {
            return redirect($return);
        }

        // Fetch all the categories in the parent
        $query = DB::table('categories')
            ->select('id AS category_id', 'category_order')
            ->where('category_group_id', $category_group_id)
            ->where('parent_id', $parent_id)
            ->orderBy('category_order', 'asc')
            ->get();

        // If there is only one category, there is nothing to re-order
        if ($query->count() <= 1) {
            return redirect($return);
        }

        // Assign category ID numbers in an array except the category being shifted.
        // We will also set the position number of the category being shifted, which
        // we'll use in array_shift()

        $flag   = '';
        $i      = 1;
        $cats   = [];

        foreach ($query as $row) {
            if ($category_id == $row->category_id) {
                $flag = ($order == 'down') ? $i+1 : $i-1;
            } else {
                $cats[] = $row->category_id;
            }

            $i++;
        }

        array_splice($cats, ($flag -1), 0, $category_id);

        // Update the category order for all the categories within the given parent
        $i = 1;

        foreach ($cats as $val) {
            DB::table('categories')
                ->where('id', $val)
                ->update(['category_order' => $i]);

            $i++;
        }

        // Switch to custom order
        DB::table('category_groups')
            ->where('id', $category_group_id)
            ->update(['sort_order' => 'c']);

        return redirect($return);
    }

   /**
    * List Categories for a Category Group
    *
    * @return string
    */
    public function categoryManager()
    {
        if ( ! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        if (!($category_group_id = Cp::pathVar('category_group_id')) || ! is_numeric($category_group_id)) {
            return false;
        }

        $zurl  = (Cp::pathVar('Z') == 1) ? '/Z=1' : '';
        $zurl .= (Cp::pathVar('category_group_id') !== null) ? '/category_group_id='.Cp::pathVar('category_group_id') : '';
        $zurl .= (Cp::pathVar('integrated') !== null) ? '/integrated='.Cp::pathVar('integrated') : '';

        $query = DB::table('category_groups')
            ->where('id', $category_group_id)
            ->select('group_name', 'sort_order')
            ->first();

        $group_name = $query->group_name;
        $sort_order = $query->sort_order;

        $r = '';
        $r .= Cp::quickDiv('tableHeading', $group_name);

        $cp_message = session()->pull('cp-message');

        if ($cp_message) {
            $r .= Cp::quickDiv('success-message', $cp_message);
        }

        // Fetch and build category tree table
        $this->categoryTree('table', $category_group_id, '', $sort_order);

        if (count($this->categories) == 0) {
            $r .= Cp::quickDiv('box', Cp::quickDiv('highlight', __('kilvin::admin.no_category_message')));
        } else {
            $r .= Cp::table('tableBorder', '0', '0').
                  '<tr>'.PHP_EOL.
                  Cp::tableCell('tableHeadingAlt', 'ID', '2%').
                  Cp::tableCell('tableHeadingAlt', __('kilvin::admin.order'), '8%').
                  Cp::tableCell('tableHeadingAlt', __('kilvin::admin.category_name'), '50%').
                  Cp::tableCell('tableHeadingAlt', __('kilvin::admin.category_url_title'), '50%').
                  Cp::tableCell('tableHeadingAlt', __('kilvin::cp.edit'), '20%').
                  Cp::tableCell('tableHeadingAlt', __('kilvin::cp.delete'), '20%');
            $r .= '</tr>'.PHP_EOL;

            foreach ($this->categories as $val) {
                $prefix = (strlen($val[0]) == 1) ? NBS : NBS;
                $r .= $val;
            }

            $r .= '</table>'.PHP_EOL;

            $r .= Cp::quickDiv('defaultSmall', '');

            // Category order

            if (Request::input('Z') == null)
            {
                $r .= Cp::formOpen([
                    'action' => 'weblogs-administration/global-category-order/category_group_id='.$category_group_id.$zurl
                ]);

                $r .= Cp::div('box box320');
                $r .= Cp::quickDiv('defaultBold', __('kilvin::admin.global_sort_order'));
                $r .= Cp::div('littlePadding');
                $r .= '<label>'.
                	Cp::input_radio('sort_order', 'a', ($sort_order == 'a') ? 1 : '').__('kilvin::admin.alpha').'</label>';
                $r .= NBS.NBS.'<label>'.Cp::input_radio('sort_order', 'c', ($sort_order != 'a') ? 1 : '').__('kilvin::admin.custom').'</label>';
                $r .= NBS.NBS.Cp::input_submit(__('kilvin::cp.update'));
                $r .= '</div>'.PHP_EOL;
                $r .= '</div>'.PHP_EOL;
                $r .= '</form>'.PHP_EOL;
            }
        }

        // Build category tree for javascript replacement
        if (Cp::pathVar('Z') == 1) {
            $PUB = new Publish;
            $PUB->categoryTree(
                (Cp::pathVar('category_group_id') !== null) ? Cp::pathVar('category_group_id') : Cp::pathVar('category_group_id'),
                '',
                '',
                (Cp::pathVar('integrated') == 'y') ? 'y' : 'n');

            $cm = "";
            foreach ($PUB->categories as $val) {
                $cm .= $val;
            }

            $cm = addslashes(preg_replace("/(\r\n)|(\r)|(\n)/", '', $cm));

            Cp::$extra_header = <<<EOT
            <script type="text/javascript">

                $( document ).ready(function() {
				 	$('#update_publish_cats').click(function(e) {
				 		e.preventDefault();

						opener.swap_categories("{$cm}");
						window.close();
				 	});
				});

            </script>
EOT;

            $r .= '<form>';
            $r .= Cp::quickDiv('littlePadding', Cp::quickDiv('defaultCenter', '<input type="submit" id="update_publish_cats" value="'.NBS.__('kilvin::admin.update_publish_cats').NBS.'"/>'  ));
            $r .= '</form>';
        }

        // Assign output data
        Cp::$title = __('kilvin::admin.categories');
        Cp::$crumb =
            Cp::anchor('weblogs-administration/category-overview', __('kilvin::admin.category_groups')).
            Cp::breadcrumbItem(__('kilvin::admin.categories'));


        $right_links[] = [
            'weblogs-administration/edit-category-form/category_group_id='.$category_group_id,
            __('kilvin::admin.new_category')
        ];

        $r = Cp::header(__('kilvin::admin.categories'), $right_links).$r;

        Cp::$body  = $r;
    }

   /**
    * Set a Category Order
    *
    * @return string
    */
    public function globalCategoryOrder()
    {
        if ( ! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        if (!($category_group_id = Cp::pathVar('category_group_id')) || ! is_numeric($category_group_id)) {
            return false;
        }

        $order = (Request::input('sort_order') == 'a') ? 'a' : 'c';

        $query = DB::table('category_groups')->select('sort_order')->where('id', $category_group_id);

        if ($order == 'a') {
            if (Request::input('override')) {
                return $this->globalCategoryOrderConfirm();
            }

            $this->reorderCatsAlphabetically();
        }

        DB::table('category_groups')
            ->where('id', $category_group_id)
            ->update(['sort_order' => $order]);

        return redirect(kilvinCpUrl('weblogs-administration/category-manager/category_group_id='.$category_group_id));
    }

   /**
    * Confirmation form for a category order change
    *
    * @return string
    */
    public function globalCategoryOrderConfirm()
    {
        if ( ! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        if (!($category_group_id = Cp::pathVar('category_group_id')) OR ! is_numeric($category_group_id))
        {
            return false;
        }

        Cp::$title = __('kilvin::admin.global_sort_order');
        Cp::$crumb =
            Cp::anchor(
                'weblogs-administration/category-overview',
                __('kilvin::admin.category_groups')
            ).
            Cp::breadcrumbItem(
                Cp::anchor(
                    'weblogs-administration/category-manager'.
                        '/category_group_id='.$category_group_id,
                    __('kilvin::admin.categories')
                )
            ).
            Cp::breadcrumbItem(__('kilvin::admin.global_sort_order'));

        Cp::$body = Cp::formOpen(['action' => 'weblogs-administration/global-category-order/category_group_id='.$category_group_id])
                    .Cp::input_hidden('sort_order', Request::input('sort_order'))
                    .Cp::input_hidden('override', 1)
                    .Cp::quickDiv('tableHeading', __('kilvin::admin.global_sort_order'))
                    .Cp::div('box')
                    .Cp::quickDiv('defaultBold', __('kilvin::admin.category_order_confirm_text'))
                    .Cp::quickDiv('alert', BR.__('kilvin::admin.category_sort_warning').BR.BR)
                    .'</div>'.PHP_EOL
                    .Cp::quickDiv('littlePadding', Cp::input_submit(__('kilvin::cp.update')))
                    .'</form>'.PHP_EOL;
    }

   /**
    * Reorder a Category Group's categories alphabetically
    *
    * @return boolean
    */
    public function reorderCatsAlphabetically()
    {
        if ( ! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        if (!($category_group_id = Cp::pathVar('category_group_id')) or ! is_numeric($category_group_id)) {
            return false;
        }

        $data = $this->processCategoryGroup($category_group_id);

        if (count($data) == 0) {
            return false;
        }

        foreach($data as $cat_id => $cat_data) {
            DB::table('categories')
                ->where('id', $cat_id)
                ->update(['category_order' => $cat_data[1]]);
        }

        return true;
    }

   /**
    * Process category group for alphabetically ordering
    *
    * @param integer $category_group_id
    * @return array
    */
    private function processCategoryGroup($category_group_id)
    {
        $query = DB::table('categories')
            ->where('category_group_id', $category_group_id)
            ->orderBy('parent_id')
            ->orderBy('category_name')
            ->select('category_name', 'id AS category_id', 'parent_id')
            ->get();

        if ($query->count() == 0) {
            return false;
        }

        foreach($query as $row) {
            $this->cat_update[$row->category_id]  = [$row->parent_id, '1', $row->category_name];
        }

        $order = 0;

        foreach($this->cat_update as $key => $val)
        {
            if (0 == $val[0])
            {
                $order++;
                $this->cat_update[$key][1] = $order;
                $this->processSubcategories($key);
            }
        }

        return $this->cat_update;
    }

   /**
    * Process class subcategories of category group for alphabetically ordering
    *
    * @param integer $parent_id
    * @return void
    */
    public function processSubcategories($parent_id)
    {
        $order = 0;

        foreach($this->cat_update as $key => $val) {
            if ($parent_id == $val[0]) {
                $order++;
                $this->cat_update[$key][1] = $order;
                $this->processSubcategories($key);
            }
        }
    }

   /**
    * Create/Edit Category Form
    *
    * @return string
    */
    public function editCategoryForm()
    {
        if ( ! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        if (($category_group_id = Cp::pathVar('category_group_id')) === null OR ! is_numeric($category_group_id)) {
            return Cp::unauthorizedAccess();
        }

        $cat_id = Cp::pathVar('category_id');

        // Get the category sort order for the parent select field later on

        $sort_order = DB::table('category_groups')
            ->where('id', $category_group_id)
            ->value('sort_order');

        $default = ['category_name', 'category_url_title', 'category_description', 'category_image', 'category_id', 'parent_id'];

        if ($cat_id)
        {
            $query = DB::table('categories')
                ->where('id', $cat_id)
                ->select(
                    'id AS category_id',
                    'category_name',
                    'category_url_title',
                    'category_description',
                    'category_image',
                    'category_group_id',
                    'parent_id')
                ->first();

            if (!$query) {
                return Cp::unauthorizedAccess();
            }

            foreach ($default as $val) {
                $$val = $query->$val;
            }
        }
        else
        {
            foreach ($default as $val) {
                $$val = '';
            }
        }

        // Build our output

        $title = (!$cat_id) ? 'admin.new_category' : 'admin.edit_category';

        $zurl  = (Request::input('Z') == 1) ? '/Z=1' : '';
        $zurl .= (Request::input('category_group_id') !== null) ? '/category_group_id='.Request::input('category_group_id') : '';
        $zurl .= (Request::input('integrated') !== null) ? '/integrated='.Request::input('integrated') : '';

        Cp::$title = __('kilvin::'.$title);

        Cp::$crumb =
            Cp::anchor('weblogs-administration/category-overview', __('kilvin::admin.category_groups')).
            Cp::breadcrumbItem(
            	Cp::anchor('weblogs-administration/category-manager/category_group_id='.$category_group_id,
            	__('kilvin::admin.categories')
            )
            ).
        Cp::breadcrumbItem(__('kilvin::'.$title));

        $word_separator = Site::config('word_separator') != "dash" ? '_' : '-';

        // ------------------------------------
        //  Create Foreign Character Conversion JS
        // ------------------------------------

        $r = urlTitleJavascript($word_separator);

        $r .= Cp::quickDiv('tableHeading', __('kilvin::'.$title));

        $r .= Cp::formOpen(
            [
                'id'     => 'category_form',
                'action' => 'weblogs-administration/update-category'.$zurl
            ]
        ).
        Cp::input_hidden('category_group_id', $category_group_id);

        if ($cat_id) {
            $r .= Cp::input_hidden('category_id', $cat_id);
        }

        $r .= Cp::div('box');
        $r .= Cp::div('littlePadding').
              Cp::quickDiv('littlePadding', Cp::quickDiv('defaultBold', Cp::required().NBS.__('kilvin::admin.category_name'))).
              Cp::input_text(
                    'category_name',
                    $category_name,
                    '20',
                    '100',
                    'input',
                    '400px',
                    ((!$cat_id) ? 'onkeyup="liveUrlTitle(\'#category_name\', \'#category_url_title\');"' : ''),
                    true
                ).
                '</div>'.PHP_EOL;

        $r .= Cp::div('littlePadding').
              Cp::quickDiv('littlePadding', Cp::quickDiv('defaultBold', __('kilvin::admin.category_url_title'))).
              Cp::input_text('category_url_title', $category_url_title, '20', '75', 'input', '400px', '', TRUE).
              '</div>'.PHP_EOL;

        $r .= Cp::div('littlePadding').
              Cp::quickDiv('littlePadding', Cp::quickDiv('defaultBold', __('kilvin::admin.category_description'))).
              Cp::input_textarea('category_description', $category_description, 4, 'textarea', '400px').
              '</div>'.PHP_EOL;

        $r .= Cp::div('littlePadding').
              Cp::quickDiv('defaultBold', __('kilvin::admin.category_image')).
              Cp::quickDiv('littlePadding', Cp::quickDiv('', __('kilvin::admin.category_img_blurb'))).
              Cp::input_text('category_image', $category_image, '40', '120', 'input', '400px').
              '</div>'.PHP_EOL;

        $r .= Cp::div('littlePadding').
              Cp::quickDiv('littlePadding', Cp::quickDiv('defaultBold', __('kilvin::admin.category_parent'))).
              Cp::input_select_header('parent_id').
              Cp::input_select_option('0', __('kilvin::admin.none'));

        $this->categoryTree('list', $category_group_id, $parent_id, $sort_order);

        foreach ($this->categories as $val) {
            $prefix = (strlen($val[0]) == 1) ? NBS : NBS;
            $r .= $val;
        }

        $r .= Cp::input_select_footer().
              '</div>'.PHP_EOL;
        $r .= '</div>'.PHP_EOL;

        // ------------------------------------
        //  Submit Button
        // ------------------------------------

        $r .= Cp::div('paddingTop');
        $r .= ( ! $cat_id) ? Cp::input_submit(__('kilvin::cp.submit')) : Cp::input_submit(__('kilvin::cp.update'));
        $r .= '</div>'.PHP_EOL;

        $r .= '</form>'.PHP_EOL;

        Cp::$body = $r;
    }

   /**
    * Create/Update Category
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
    public function updateCategory()
    {
        if ( ! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        if (($category_group_id = Request::input('category_group_id')) === null or ! is_numeric($category_group_id)) {
            return Cp::unauthorizedAccess();
        }

        $edit = Request::filled('category_id');

        // ------------------------------------
        //  Validation
        // ------------------------------------

        $validator = Validator::make(request()->all(), [
            'category_url_title' => 'regex:#^[a-zA-Z0-9_\-]+$#i',
            'category_name'      => 'required',
            'parent_id'          => 'numeric',
            'category_group_id'  => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return Cp::errorMessage(implode(BR, $validator->errors()->all()));
        }

        $data = Request::only([
            'category_name',
            'category_url_title',
            'category_description',
            'category_image',
            'parent_id',
            'category_group_id',
            'category_id'
        ]);

        if (empty($data['category_description'])) {
            $data['category_description'] = '';
        }

        if (empty($data['parent_id'])) {
            $data['parent_id'] = 0;
        }

        // ------------------------------------
        //  Create Category URL Title
        // ------------------------------------

        if (empty($data['category_url_title'])) {
            $data['category_url_title'] = createUrlTitle($data['category_name'], true);

            // Integer? Not allowed, so we show an error.
            if (is_numeric($data['category_url_title'])) {
                return Cp::errorMessage(__('kilvin::admin.category_url_title_is_numeric'));
            }

            if (trim($data['category_url_title']) == '') {
                return Cp::errorMessage(__('kilvin::admin.unable_to_create_category_url_title'));
            }
        }

        // ------------------------------------
        //  Cat URL Title must be unique within the group
        // ------------------------------------

        $query = DB::table('categories')
            ->where('category_url_title', $data['category_url_title'])
            ->where('category_group_id', $category_group_id);

        if ($edit === true) {
            $query->where('id', '!=', $data['category_id']);
        }

        $query = $query->get();

        if ($query->count() > 0) {
            return Cp::errorMessage(__('kilvin::admin.duplicate_category_url_title'));
        }

        // ------------------------------------
        //  Finish data prep for insertion
        // ------------------------------------

        $data['category_name'] = str_replace(['<','>'], ['&lt;','&gt;'], $data['category_name']);

        // ------------------------------------
        //  Insert
        // ------------------------------------

        if ($edit == false) {
            unset($data['category_id']);
            $data['category_order'] = 0; // Temp
            $field_cat_id = DB::table('categories')->insertGetId($data);

            $update = false;

            // ------------------------------------
            //  Re-order categories
            // ------------------------------------

            // When a new category is inserted we need to assign it an order.
            // Since the list of categories might have a custom order, all we
            // can really do is position the new category alphabetically.

            // First we'll fetch all the categories alphabetically and assign
            // the position of our new category
            $query = DB::table('categories')
                ->where('category_group_id', $category_group_id)
                ->where('parent_id', $data['parent_id'])
                ->orderBy('category_name', 'asc')
                ->select('id AS category_id', 'category_name')
                ->get();

            $position = 0;
            $cat_id = '';

            foreach ($query as $row) {
                if ($data['category_name'] == $row->category_name) {
                    $cat_id = $row->category_id;
                    break;
                }

                $position++;
            }

            // Next we'll fetch the list of categories ordered by the custom order
            // and create an array with the category ID numbers
            $cat_array = DB::table('categories')
                ->where('category_group_id', $category_group_id)
                ->where('parent_id', $data['parent_id'])
                ->where('id', '!=', $cat_id)
                ->orderBy('category_order')
                ->select('id AS category_id')
                ->pluck('category_id')
                ->all();

            // Now we'll splice in our new category to the array.
            // Thus, we now have an array in the proper order, with the new
            // category added in alphabetically

            array_splice($cat_array, $position, 0, $cat_id);

            // Lastly, update the whole list

            $i = 1;
            foreach ($cat_array as $val)
            {
                DB::table('categories')
                    ->where('id', $val)
                    ->update(['category_order' => $i]);

                $i++;
            }
        }

        if ($edit !== false) {
            if ($data['category_id'] == $data['parent_id']) {
                $data['parent_id'] = 0;
            }

            // ------------------------------------
            //  Check for parent becoming child of its child...oy!
            // ------------------------------------

            $query = DB::table('categories')
                ->where('id', Request::input('category_id'))
                ->select('parent_id', 'category_group_id')
                ->first();

            if (Request::input('parent_id') !== 0 && $query && $query->parent_id !== Request::input('parent_id'))
            {
                $children  = [];
                $cat_array = $this->categoryTree('data', $query->category_group_id);

                foreach($cat_array as $key => $values) {
                    if ($values[0] == Request::input('category_id')) {
                        $children[] = $key;
                    }
                }

                if (sizeof($children) > 0) {
                    if (($key = array_search(Request::input('parent_id'), $children)) !== FALSE)
                    {
                        DB::table('categories')
                            ->where('id', $children[$key])
                            ->update(['parent_id' => $query->parent_id]);
                    }
                    // ------------------------------------
                    //  Find All Descendants
                    // ------------------------------------
                    else
                    {
                        while(sizeof($children) > 0) {
                            $now = array_shift($children);

                            foreach($cat_array as $key => $values) {
                                if ($values[0] == $now) {
                                    if ($key == Request::input('parent_id'))
                                    {
                                        DB::table('categories')
                                            ->where('id', $key)
                                            ->update(['parent_id' => $query->parent_id]);
                                        break 2;
                                    }

                                    $children[] = $key;
                                }
                            }
                        }
                    }
                }
            }

            $update = $data;
            unset($update['category_id']);

            DB::table('categories')
                ->where('id', $data['category_id'])
                ->where('category_group_id', $data['category_group_id'])
                ->update($update);

            $update = true;

            // need this later for custom fields
            $field_cat_id = Request::input('category_id');
        }

        return redirect(kilvinCpUrl('weblogs-administration/category-manager/category_group_id='.$category_group_id))
            ->with(
                'cp-message',
                ($edit) ? __('kilvin::admin.category_updated') : __('kilvin::admin.category_created')
            );
    }

   /**
    * Delete Category confirmation form
    *
    * @return string
    */
    public function deleteCategoryConfirm()
    {
        if ( ! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        if ( ! $cat_id = Cp::pathVar('category_id')) {
            return Cp::unauthorizedAccess();
        }

        $query = DB::table('categories')
            ->where('id', $cat_id)
            ->select('category_name', 'category_group_id')
            ->first();

        if (!$query) {
            return Cp::unauthorizedAccess();
        }

        // ------------------------------------
        //  Check privileges
        // ------------------------------------

        if (Cp::pathVar('Z') == 1 && Session::userdata('member_group_id') != 1 && ! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        Cp::$title = __('kilvin::admin.delete_category');

        Cp::$crumb =
            Cp::anchor('weblogs-administration/category-overview', __('kilvin::admin.category_groups')).
            Cp::breadcrumbItem(
                Cp::anchor('
                    weblogs-administration/category-manager/category_group_id='.$query->category_group_id,
                    __('kilvin::admin.categories')
                )
            ).
            Cp::breadcrumbItem(__('kilvin::admin.delete_category'));

        $zurl = (Request::input('Z') == 1) ? '/Z=1' : '';
        $zurl .= (Request::input('category_group_id') !== null) ? '/category_group_id='.Request::input('category_group_id') : '';
        $zurl .= (Request::input('integrated') !== null) ? '/integrated='.Request::input('integrated') : '';

        Cp::$body = Cp::deleteConfirmation([
            'path' => 'weblogs-administration/delete-category'.
            	'/category_group_id='.$query->category_group_id.
                '/category_id='.$cat_id.
                $zurl,
            'heading'   => 'admin.delete_category',
            'message'   => 'admin.delete_category_confirmation',
            'item'      => $query->category_name,
            'extra'     => '',
            'hidden'    => ''
        ]);
    }

   /**
    * Delete Category
    *
    * @return string
    */
    public function deleteCategory()
    {
        if (! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        if ( ! $cat_id = Cp::pathVar('category_id')) {
            return Cp::unauthorizedAccess();
        }

        if ( ! is_numeric($cat_id)) {
            return Cp::unauthorizedAccess();
        }

        $query = DB::table('categories')
            ->select('category_group_id')
            ->where('id', $cat_id)
            ->first();

        if (!$query) {
            return Cp::unauthorizedAccess();
        }

        $category_group_id = $query->category_group_id;

        DB::table('weblog_entry_categories')->where('category_id', $cat_id)->delete();
        DB::table('categories')->where('parent_id', $cat_id)->where('category_group_id', $category_group_id)->update(['parent_id' => 0]);
        DB::table('categories')->where('id', $cat_id)->where('category_group_id', $category_group_id)->delete();

        return redirect(kilvinCpUrl('weblogs-administration/category-manager/category_group_id='.$category_group_id))
            ->with('cp-message', __('kilvin::admin.category_deleted'));
    }

//=====================================================================
//  Status Functions
//=====================================================================

   /**
    * Status Groups Listing
    *
    * @return string
    */
    public function statusOverview()
    {
        Cp::$title  = __('kilvin::admin.status_groups');
        Cp::$crumb  = __('kilvin::admin.status_groups');

        $right_links[] = [
            'weblogs-administration/edit-status-group-form',
            __('kilvin::admin.create_new_status_group')
        ];

        $r = Cp::header(__('kilvin::admin.status_groups'), $right_links);

        // Fetch category groups
        $query = DB::table('status_groups')
            ->groupBy('status_groups.id')
            ->orderBy('status_groups.group_name')
            ->select(
                'status_groups.id AS status_group_id',
                'status_groups.group_name'
            )->get();

        $cp_message = session()->pull('cp-message');

        if (!empty($cp_message)) {
            $r .= Cp::quickDiv('success-message', $cp_message);
        }

        if ($query->count() == 0) {
            $r .= Cp::div('box');
            $r .= Cp::quickDiv('littlePadding', Cp::heading(__('kilvin::admin.no_status_group_message'), 5));
            $r .= Cp::quickDiv('littlePadding', Cp::anchor('weblogs-administration/edit-status-group-form', __('kilvin::admin.create_new_status_group')));
            $r .= '</div>'.PHP_EOL;

            return Cp::$body = $r;
        }

        $r .= Cp::table('tableBorder', '0', '', '100%').
              '<tr>'.PHP_EOL.
              Cp::td('tableHeading', '', '4').
              __('kilvin::admin.status_groups').
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        $i = 0;

        foreach($query as $row) {
            $r .= '<tr>'.PHP_EOL;

            $r .= Cp::tableCell('',
                  Cp::anchor(
                    'weblogs-administration/edit_status_group_form/status_group_id='.$row->status_group_id,
                    $row->group_name,
                    'class="defaultBold"'
                )
            );

            $field_count = $query = DB::table('statuses')
                ->where('statuses.status_group_id', $row->status_group_id)
                ->count();

            $r .= Cp::tableCell('',
                  Cp::anchor(
                    'weblogs-administration/status_manager/status_group_id='.$row->status_group_id,
                    __('kilvin::admin.add_edit_statuses').
                     ' ('.$field_count.')'
                )
            );


            $r .= Cp::tableCell('',
                Cp::anchor(
                    'weblogs-administration/delete_status_group_conf/status_group_id='.$row->status_group_id,
                    __('kilvin::cp.delete'),
                    'class="delete-link"'
                )
            );

            $r .= '</tr>'.PHP_EOL;
        }

        $r .= '</table>'.PHP_EOL;

        Cp::$body  = $r;
    }

   /**
    * Create/Edit Status Group form
    *
    * @return string
    */
    public function editStatusGroupForm()
    {
        // Set default values
        $edit       = false;
        $status_group_id   = '';
        $group_name = '';

        // If we have the status_group_id variable it's an edit request, so fetch the status data
        if ($status_group_id = Cp::pathVar('status_group_id')) {
            $edit = true;

            if ( ! is_numeric($status_group_id)) {
                return false;
            }

            $query = DB::table('status_groups')
            	->select('status_groups.id AS status_group_id', 'status_groups.*')
                ->where('id', $status_group_id)
                ->first();

            foreach ($query as $key => $val) {
                $$key = $val;
            }
        }

        if ($edit == false) {
            $title = __('kilvin::admin.create_new_status_group');
        }
        else {
            $title = __('kilvin::admin.edit_status_group');
        }

        // Build our output
        $r  = Cp::formOpen(['action' => 'weblogs-administration/update-status-group']);

        if ($edit == true) {
            $r .= Cp::input_hidden('status_group_id', $status_group_id);
        }

        $r .= Cp::quickDiv('tableHeading', $title);

        $r .= Cp::div('box').
              Cp::quickDiv('littlePadding', Cp::quickDiv('defaultBold', __('kilvin::admin.name_of_status_group'))).
              Cp::quickDiv('littlePadding', Cp::input_text('group_name', $group_name, '20', '50', 'input', '260px'));

        $r .= '</div>'.PHP_EOL;

        $r .= Cp::div('paddingTop');
        if ($edit == FALSE)
            $r .= Cp::input_submit(__('kilvin::cp.submit'));
        else
            $r .= Cp::input_submit(__('kilvin::cp.update'));

        $r .= '</div>'.PHP_EOL;
        $r .= '</form>'.PHP_EOL;

        Cp::$title = $title;
        Cp::$crumb =
            Cp::anchor('weblogs-administration/status-overview', __('kilvin::admin.status_groups')).
            Cp::breadcrumbItem($title);

        Cp::$body  = $r;
    }

   /**
    * Create/Update Status Group
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
    public function updateStatusGroup()
    {
        $edit = Request::filled('status_group_id');

        // ------------------------------------
        //  Validation
        // ------------------------------------

        $validator = Validator::make(request()->all(), [
            'group_name'      => 'required|regex:#^[a-zA-Z0-9_\-/\s]+$#i',
            'status_group_id' => 'integer'
        ]);

        if ($validator->fails()) {
            return Cp::errorMessage(implode(BR, $validator->errors()->all()));
        }

        $data = Request::only([
            'status_group_id',
            'group_name'
        ]);

        // Group Name taken?
        $query = DB::table('status_groups')
            ->where('group_name', $data['group_name']);

        if ($edit === true) {
            $query->where('id', '!=', $data['status_group_id']);
        }

        if ($query->count() > 0) {
            return Cp::errorMessage(__('kilvin::admin.taken_status_group_name'));
        }

        // ------------------------------------
        //  Insert/Update
        // ------------------------------------

        if ($edit == false) {
            $data['site_id'] = Site::config('site_id');
            $status_group_id = DB::table('status_groups')->insertGetId($data);

            // Add open/closed by default!
            DB::table('statuses')
                ->insert(
                    [
                        [
                            'status_group_id' => $status_group_id,
                            'status' => 'open',
                            'status_order' => 1
                        ],
                        [
                            'status_group_id' => $status_group_id,
                            'status' => 'closed',
                            'status_order' => 2
                        ]
                    ]);

            $success_msg = __('kilvin::admin.status_group_created');

            Cp::log(__('kilvin::admin.status_group_created').'&nbsp;'.$data['group_name']);
        }

        if ($edit != false) {
            $update = $data;
            unset($update['status_group_id']);
            DB::table('status_groups')
                ->where('id', $data['status_group_id'])
                ->update($update);

            $success_msg = __('kilvin::admin.status_group_updated');
        }

        $message = $success_msg.$data['group_name'];

        if ($edit === false) {
            $query = DB::table('weblogs')
                ->select('weblogs.id AS weblog_id')
                ->get();

            if ($query->count() > 0) {
                $message .= Cp::div('littlePadding').
					Cp::span('alert').
					__('kilvin::admin.assign_group_to_weblog').
					'</span>'.
					PHP_EOL.
					'&nbsp;';

                if ($query->count() == 1) {
                    $link = 'weblogs-administration/edit-weblog-fields/weblog_id='.$query->first()->weblog_id;
                } else {
                    $link = 'weblogs-administration/weblogs-overview';
                }

                $message .= Cp::anchor($link, __('kilvin::admin.click_to_assign_group')).'</div>'.PHP_EOL;
            }
        }

        return redirect(kilvinCpUrl('weblogs-administration/status-overview'))
            ->with('cp-message', $message);
    }

   /**
    * Delete Status Group Confirmation form
    *
    * @return string
    */
    public function deleteStatusGroupConf()
    {
        if (!($status_group_id = Cp::pathVar('status_group_id')) || ! is_numeric($status_group_id)) {
            return false;
        }

        $group_name = DB::table('status_groups')->where('id', $status_group_id)->value('group_name');

        if (empty($group_name)) {
            return false;
        }

        Cp::$title = __('kilvin::admin.delete_group');
        Cp::$crumb =
            Cp::anchor('weblogs-administration/status-overview', __('kilvin::admin.status_groups')).
            Cp::breadcrumbItem(__('kilvin::admin.delete_group'));

        Cp::$body = Cp::deleteConfirmation([
			'path'      => 'weblogs-administration/delete-status-group/status_group_id='.$status_group_id,
			'heading'   => 'admin.delete_group',
			'message'   => 'admin.delete_status_group_confirmation',
			'item'      => $group_name,
			'extra'     => '',
			'hidden'    => ['status_group_id' => $status_group_id]
		]);
    }

   /**
    * Delete Status Group
    *
    * @return string
    */
    public function deleteStatusGroup()
    {
        if (!($status_group_id = Request::input('status_group_id')) OR ! is_numeric($status_group_id)) {
            return false;
        }

        $group_name = DB::table('status_groups')->where('id', $status_group_id)->value('group_name');

        if (empty($group_name)) {
            return false;
        }

        DB::table('status_groups')->where('id', $status_group_id)->delete();
        DB::table('statuses')->where('status_group_id', $status_group_id)->delete();

        Cp::log(__('kilvin::admin.status_group_deleted').'&nbsp;'.$group_name);

        $message = __('kilvin::admin.status_group_deleted').'&nbsp;'.'<b>'.$group_name.'</b>';

        return redirect(kilvinCpUrl('weblogs-administration/status-overview'))
            ->with('cp-message', $message);
    }

   /**
    * List Statuses for a Status Group (include new status form)
    *
    * @return string
    */
    public function statusManager()
    {
        $status_group_id = Cp::pathVar('status_group_id');

        if ( ! is_numeric($status_group_id)) {
            abort(404);
        }

        $i = 0;
        $r = '';

        $cp_message = session()->pull('cp-message');

        if (!empty($cp_message)) {
            $r .= Cp::quickDiv('success-message', $cp_message);
        }

        $r .= Cp::table('', '0', '10', '100%').
              '<tr>'.PHP_EOL.
              Cp::td('', '55%', '', '', 'top');

        $query = DB::table('status_groups')
            ->select('group_name')
            ->where('id', $status_group_id)
            ->first();

        $r .= Cp::table('tableBorder', '0', '10', '100%').
              '<tr>'.PHP_EOL.
              Cp::td('tableHeading', '', '3').
              __('kilvin::admin.status_group').':'.'&nbsp;'.$query->group_name.
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        $query = DB::table('statuses')
            ->where('status_group_id', $status_group_id)
            ->orderBy('status_order')
            ->select('statuses.id AS status_id', 'status')
            ->get();

        $total = $query->count() + 1;

        if ($query->count() > 0) {
            foreach ($query as $row) {
                $del =
                    ($row->status != 'open' AND $row->status != 'closed')
                    ?
                    Cp::anchor(
                        'weblogs-administration/delete-status-confirm'.
                            '/status_id='.$row->status_id,
                        __('kilvin::cp.delete'),
                        'class="delete-link"'
                    )
                    :
                    '--';

                $status_name = ($row->status == 'open' OR $row->status == 'closed') ? __('kilvin::admin.'.$row->status) : $row->status;

                $r .= '<tr>'.PHP_EOL.
                      Cp::tableCell('', Cp::quickSpan('defaultBold', $status_name)).
                      Cp::tableCell(
                      	'',
                      	Cp::anchor('weblogs-administration/edit-status-form/status_id='.$row->status_id,
                      	__('kilvin::cp.edit')
                      )
                    ).
				  Cp::tableCell('', $del).
				  '</tr>'.PHP_EOL;
            }
        } else {
            $r .= '<tr>'.PHP_EOL.
                      Cp::tableCell('', '<em>No statuses yet.</em>').
                  '</tr>'.PHP_EOL;
        }

        $r .= '</table>'.PHP_EOL;

        $r .= Cp::quickDiv(
            'littlePadding',
            Cp::anchor(
                'weblogs-administration/edit-status-order/status_group_id='.$status_group_id,
                __('kilvin::admin.change_status_order')
            )
        );

        $r .= '</td>'.PHP_EOL.
              Cp::td('rightCel', '45%', '', '', 'top');

        // Build the right side output

        $r .= Cp::formOpen([
                'action' => 'weblogs-administration/update-status/status_group_id='.$status_group_id
                ]
            ).
            Cp::input_hidden('status_group_id', $status_group_id);

        $r .= Cp::quickDiv('tableHeading', __('kilvin::admin.create_new_status'));

        $r .= Cp::div('box');

        $r .= Cp::quickDiv('', Cp::quickDiv('littlePadding', __('kilvin::admin.status_name')).Cp::input_text('status', '', '30', '60', 'input', '260px'));

        $r .= Cp::quickDiv('',  Cp::quickDiv('littlePadding', __('kilvin::admin.status_order')).Cp::input_text('status_order', $total, '20', '3', 'input', '50px'));

        $r .= '</div>'.PHP_EOL;

        if (Session::userdata('member_group_id') == 1) {
            $query = DB::table('member_group_preferences')
                ->join('member_groups', 'member_groups.id', '=', 'member_group_preferences.member_group_id')
                ->whereNotIn('member_groups.id', [1])
                ->where('member_group_preferences.value', 'y')
                ->where('member_group_preferences.handle', 'can_access_content')
                ->orderBy('member_groups.group_name')
                ->select('member_groups.id AS member_group_id', 'member_groups.group_name')
                ->get();

            $table_end = true;

            if ($query->count() == 0) {
                $table_end = false;
            } else {
                $r .= Cp::quickDiv('paddingTop', Cp::heading(__('kilvin::admin.restrict_status_to_group'), 5));

                $r .= Cp::table('tableBorder', '0', '', '100%').
                      '<tr>'.PHP_EOL.
                      Cp::td('tableHeading', '', '').
                      __('kilvin::admin.member_group').
                      '</td>'.PHP_EOL.
                      Cp::td('tableHeading', '', '').
                      __('kilvin::admin.can_edit_status').
                      '</td>'.PHP_EOL.
                      '</tr>'.PHP_EOL;

                $i = 0;

                $group = [];

                foreach ($query as $row) {
                    $r .= '<tr>'.PHP_EOL.
                          Cp::td('', '50%').
                          $row->group_name.
                          '</td>'.PHP_EOL.
                          Cp::td('', '50%');

                    $selected = ( ! isset($group[$row->member_group_id])) ? 1 : '';

                    $r .= Cp::qlabel(__('kilvin::admin.yes')).NBS.
                          Cp::input_radio('access_'.$row->member_group_id, 'y', $selected).'&nbsp;';

                    $selected = (isset($group[$row->member_group_id])) ? 1 : '';

                    $r .= Cp::qlabel(__('kilvin::admin.no')).NBS.
                          Cp::input_radio('access_'.$row->member_group_id, 'n', $selected).'&nbsp;';

                    $r .= '</td>'.PHP_EOL
                         .'</tr>'.PHP_EOL;
                }
            }
        }

        if ($table_end == TRUE) {
            $r .= '</table>'.PHP_EOL;
        }

        $r .= Cp::quickDiv('paddingTop', Cp::input_submit(__('kilvin::cp.submit')));

        $r .= '</form>'.PHP_EOL;

        $r .= '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL.
              '</table>'.PHP_EOL;

        Cp::$title = __('kilvin::admin.statuses');

        Cp::$crumb =
            Cp::anchor('weblogs-administration/status-overview', __('kilvin::admin.status_groups')).
            Cp::breadcrumbItem(__('kilvin::admin.statuses'));

        Cp::$body  = $r;
    }

   /**
    * Create or Update a Status
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
    public function updateStatus()
    {
        $edit = Request::filled('status_id');

        // ------------------------------------
        //  Validation
        // ------------------------------------

        $validator = Validator::make(request()->all(), [
            'status'       => 'required|regex:#^[a-zA-Z0-9_\-/\s]+$#i',
            'status_order' => 'integer'
        ]);

        if ($validator->fails()) {
            return Cp::errorMessage(implode(BR, $validator->errors()->all()));
        }

        $data = Request::only([
                'status_group_id',
                'status',
                'status_id',
                'status_order',
            ]
        );

        if (empty($data['status_order'])) {
            $data['status_order'] = 0;
        }

        if ($edit === false) {
            $count = DB::table('statuses')
                ->where('status', $data['status'])
                ->where('status_group_id', $data['status_group_id'])
                ->count();

            if ($count > 0) {
                return Cp::errorMessage(__('kilvin::admin.duplicate_status_name'));
            }

            $status_id = DB::table('statuses')->insertGetId($data);
        }

        if ($edit === true) {
            $status_id = $data['status_id'];

            $count = DB::table('statuses')
                ->where('status', $data['status'])
                ->where('status_group_id', $data['status_group_id'])
                ->where('id', '!=', $data['status_id'])
                ->count();

            if ($count > 0) {
                return Cp::errorMessage(__('kilvin::admin.duplicate_status_name'));
            }

            $update = $data;
            unset($update['status_id']);

            DB::table('statuses')
                ->where('id', $data['status_id'])
                ->where('status_group_id', Request::input('status_group_id'))
                ->update($update);

            DB::table('status_no_access')->where('status_id', $data['status_id'])->delete();

            // If the status name has changed, we need to update weblog entries with the new status.
            if (Request::filled('old_status') && Request::input('old_status') != $data['status'])
            {
                $query = DB::table('weblogs')
                    ->where('status_group_id', $data['status_group_id'])
                    ->get();

                foreach ($query as $row)
                {
                    DB::table('weblog_entries')
                        ->where('status', $data['old_status'])
                        ->where('weblog_id', $row->weblog_id)
                        ->update(['status' => $data['status']]);
                }
            }
        }


        // Set access privs
        foreach (Request::all() as $key => $val) {
            if (substr($key, 0, 7) == 'access_' AND $val == 'n') {
                DB::table('status_no_access')
                    ->insert([
						'status_id' => $status_id,
						'member_group_id' => substr($key, 7)
					]
				);
            }
        }

        if (!Request::filled('status_id')) {
            $message = __('kilvin::admin.status_created');
        } else {
           $message = __('kilvin::admin.status_updated');
        }

        return redirect(kilvinCpUrl('weblogs-administration/status-manager/status_group_id='.$data['status_group_id']))
            ->with('cp-message', $message);
    }

   /**
    * Edit Status Form
    *
    * @return string
    */
    public function editStatusForm()
    {
        if (!($status_id = Cp::pathVar('status_id')) || ! is_numeric($status_id)) {
            return false;
        }

        $query = DB::table('statuses')->where('id', $status_id)->first();

        $status_group_id = $query->status_group_id;
        $status          = $query->status;
        $status_order    = $query->status_order;
        $status_id       = $query->id;

        // Build our output
        $r  = Cp::formOpen(['action' => 'weblogs-administration/update-status']).
            Cp::input_hidden('status_id', $status_id).
            Cp::input_hidden('old_status', $status).
            Cp::input_hidden('status_group_id', $status_group_id);

        $r .= Cp::quickDiv('tableHeading', __('kilvin::admin.edit_status'));
        $r .= Cp::div('box');

        if ($status == 'open' OR $status == 'closed') {
            $r .= Cp::input_hidden('status', $status);

            $r .= Cp::quickDiv(
                    'littlePadding',
                    Cp::quickSpan('defaultBold', __('kilvin::admin.status_name').':').
                        NBS.
                        __('kilvin::admin.'.$status));
        } else {
            $r .= Cp::quickDiv(
                '',
                Cp::quickDiv(
                    'littlePadding',
                    __('kilvin::admin.status_name')
                ).
                Cp::input_text('status', $status, '30', '60', 'input', '260px')
            );
        }

        $r .= Cp::quickDiv(
            '',
            Cp::quickDiv(
                'littlePadding',
                __('kilvin::admin.status_order')
            ).
            Cp::input_text('status_order', $status_order, '20', '3', 'input', '50px')
        );

        $r .= '</div>'.PHP_EOL;

        if (Session::userdata('member_group_id') == 1) {
            $query = DB::table('member_groups')
                ->whereNotIn('id', [1])
                ->orderBy('group_name')
                ->select('member_groups.id AS member_group_id', 'group_name')
                ->get();

            $table_end = true;

            if ($query->count() == 0) {
                $table_end = false;
            } else {
                $r .= Cp::quickDiv('paddingTop', Cp::heading(__('kilvin::admin.restrict_status_to_group'), 5));

                $r .= Cp::table('tableBorder', '0', '', '100%').
                      '<tr>'.PHP_EOL.
                      Cp::td('tableHeadingAlt', '', '').
                      __('kilvin::admin.member_group').
                      '</td>'.PHP_EOL.
                      Cp::td('tableHeadingAlt', '', '').
                      __('kilvin::admin.can_edit_status').
                      '</td>'.PHP_EOL.
                      '</tr>'.PHP_EOL;

                    $i = 0;

                $group = [];

                $result = DB::table('status_no_access')
                    ->select('member_group_id')
                    ->where('status_id', $status_id)
                    ->get();

                if ($result->count() != 0) {
                    foreach($result as $row) {
                        $group[$row->member_group_id] = true;
                    }
                }

                foreach ($query as $row) {
                        $r .= '<tr>'.PHP_EOL.
                              Cp::td('', '50%').
                              $row->group_name.
                              '</td>'.PHP_EOL.
                              Cp::td('', '50%');

                        $selected = ( ! isset($group[$row->member_group_id])) ? 1 : '';

                        $r .= Cp::qlabel(__('kilvin::admin.yes')).NBS.
                              Cp::input_radio('access_'.$row->member_group_id, 'y', $selected).'&nbsp;';

                        $selected = (isset($group[$row->member_group_id])) ? 1 : '';

                        $r .= Cp::qlabel(__('kilvin::admin.no')).NBS.
                              Cp::input_radio('access_'.$row->member_group_id, 'n', $selected).'&nbsp;';

                        $r .= '</td>'.PHP_EOL
                             .'</tr>'.PHP_EOL;
                }
            }
        }

        if ($table_end == true) {
            $r .= '</table>'.PHP_EOL;
		}

        $r .= Cp::quickDiv('paddingTop', Cp::input_submit(__('kilvin::cp.update')));
        $r .= '</form>'.PHP_EOL;

        Cp::$title = __('kilvin::admin.edit_status');
        Cp::$crumb =
            Cp::anchor('weblogs-administration/status-overview', __('kilvin::admin.status_groups')).
            Cp::breadcrumbItem(
                Cp::anchor(
                    'weblogs-administration/status-manager/status_group_id='.$status_group_id,
                    __('kilvin::admin.statuses')
                )
            ).
            Cp::breadcrumbItem(__('kilvin::admin.edit_status'));

        Cp::$body  = $r;
    }

   /**
    * Delete Status Confirmation Form
    *
    * @return string
    */
    public function deleteStatusConfirm()
    {
        if (!($status_id = Cp::pathVar('status_id')) || ! is_numeric($status_id)) {
            return false;
        }

        $query = DB::table('statuses')->where('id', $status_id)->first();

        Cp::$title = __('kilvin::admin.delete_status');
        Cp::$crumb =
            Cp::anchor(
                'weblogs-administration/status-manager'.
                    '/status_group_id='.$query->status_group_id,
                __('kilvin::admin.status_groups')
            ).
            Cp::breadcrumbItem(__('kilvin::admin.delete_status'));

        Cp::$body = Cp::deleteConfirmation([
			'path'      => 'weblogs-administration/delete-status/status_id='.$status_id,
			'heading'   => 'admin.delete_status',
			'message'   => 'admin.delete_status_confirmation',
			'item'      => $query->status,
			'extra'     => '',
			'hidden'    => ''
		]);
    }

   /**
    * Delete Status
    *
    * @return string
    */
    public function deleteStatus()
    {
        if (($status_id = Cp::pathVar('status_id')) === null OR ! is_numeric($status_id)) {
            return false;
        }

        $query = DB::table('statuses')->where('id', $status_id)->first();

        if (!$query) {
            return redirect(kilvinCpUrl('weblogs-administration/status-overview'));
        }

        $status_group_id = $query->status_group_id;
        $status   = $query->status;

        $query = DB::table('weblogs')
            ->select('weblogs.id AS weblog_id')
            ->where('status_group_id', $status_group_id)
            ->get();

        if ($query->count() > 0) {
            foreach($query as $row) {
                DB::table('weblog_entries')
                    ->where('status', $status)
                    ->where('weblog_id', $row->weblog_id)
                    ->update(['status' => 'closed']);
            }
        }

        if ($status != 'open' AND $status != 'closed') {
            DB::table('statuses')
                ->where('id', $status_id)
                ->where('status_group_id', $status_group_id)
                ->delete();
        }

        return redirect(kilvinCpUrl('weblogs-administration/status-manager/status_group_id='.$status_group_id))
            ->with('cp-message', __('kilvin::admin.status_deleted'));
    }

   /**
    * Edit Status Group Ordering
    *
    * @return string
    */
    public function editStatusOrder()
    {
        if (($status_group_id = Cp::pathVar('status_group_id')) === null OR ! is_numeric($status_group_id)) {
            return false;
        }

        $query = DB::table('statuses')
            ->where('status_group_id', $status_group_id)
            ->orderBy('status_order')
            ->get();

        if ($query->count() == 0) {
            return false;
        }

        $r  = Cp::formOpen(['action' => 'weblogs-administration/update-status-order']);
        $r .= Cp::input_hidden('status_group_id', $status_group_id);

        $r .= Cp::table('tableBorder', '0', '10', '100%').
              '<tr>'.PHP_EOL.
              Cp::td('tableHeading', '', '2').
              __('kilvin::admin.change_status_order').
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        foreach ($query as $row) {
            $status_name = ($row->status == 'open' OR $row->status == 'closed')
            	? __('kilvin::admin.'.$row->status)
            	: $row->status;

            $r .= '<tr>'.PHP_EOL;
            $r .= Cp::tableCell('', $status_name);
            $r .= Cp::tableCell(
            	'',
            	Cp::input_text('status_'.$row->id, $row->status_order, '4', '3', 'input', '30px')
            );
            $r .= '</tr>'.PHP_EOL;
        }

        $r .= '</table>'.PHP_EOL;

        $r .= Cp::quickDiv('littlePadding', Cp::input_submit(__('kilvin::cp.update')));

        $r .= '</form>'.PHP_EOL;

        Cp::$title = __('kilvin::admin.change_status_order');


        Cp::$crumb =
            Cp::anchor('weblogs-administration/status-overview', __('kilvin::admin.status_groups')).
            Cp::breadcrumbItem(
                Cp::anchor(
                    'weblogs-administration/status-manager/status_group_id='.$status_group_id,
                    __('kilvin::admin.statuses')
                )
            ).
            Cp::breadcrumbItem(__('kilvin::admin.change_status_order'));

        Cp::$body  = $r;
    }

   /**
    * Update Status Order
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
    public function updateStatusOrder()
    {
        if ( ! $status_group_id = Request::input('status_group_id')) {
            return false;
        }

        foreach (Request::all() as $key => $val) {
            if (!preg_match('/^status\_([0-9]+)$/', $key, $match)) {
                continue;
            }

            DB::table('statuses')
                ->where('id', $match[1])
                ->update(['status_order' => $val]);
        }

        return redirect(kilvinCpUrl('weblogs-administration/status-manager/status_group_id='.$status_group_id));
    }

//=====================================================================
//  Custom Fields
//=====================================================================

   /**
    * List of Custom Field Groups
    *
    * @return string
    */
    public function fieldsOverview()
    {
        // Fetch field groups
        $query = DB::table('weblog_field_groups')
            ->groupBy('weblog_field_groups.id')
            ->orderBy('weblog_field_groups.group_name')
            ->select(
                'weblog_field_groups.id AS weblog_field_group_id',
                'weblog_field_groups.group_name'
            )->get();

        if ($query->count() == 0) {
			$r = Cp::heading(__('kilvin::admin.field_groups')).
				Cp::quickDiv('success-message', $message).
				Cp::quickDiv('littlePadding', __('kilvin::admin.no_field_group_message')).
				Cp::quickDiv('itmeWrapper',
					Cp::anchor(
						'weblogs-administration/edit-field-group-form',
						__('kilvin::admin.create_new_field_group')
					 )
				);

			Cp::$title = __('kilvin::admin.field_groups');
			Cp::$body  = $r;
			Cp::$crumb = __('kilvin::admin.field_groups');

			return;
        }

        $r = '';

        $cp_message = session()->pull('cp-message');

        if (!empty($cp_message)) {
            $r .= Cp::quickDiv('success-message', $cp_message);
        }

        $r .= Cp::table('tableBorder', '0', '', '100%').
              '<tr>'.PHP_EOL.
              Cp::td('tableHeading', '', '4').
              __('kilvin::admin.field_group').
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        $i = 0;

        foreach($query as $row) {
            $field_count = DB::table('weblog_fields')
                ->where('weblog_fields.weblog_field_group_id', $row->weblog_field_group_id)
                ->count();

            $r .= '<tr>'.PHP_EOL;

            $r .= Cp::tableCell('',
                  Cp::anchor(
                    'weblogs-administration/edit-field-group-form/weblog_field_group_id='.$row->weblog_field_group_id,
                    $row->group_name,
                    'class="defaultBold"'
               ));

            $r .= Cp::tableCell('',
                  Cp::anchor(
                    'weblogs-administration/field-manager/weblog_field_group_id='.$row->weblog_field_group_id,
                    __('kilvin::admin.add_edit_fields')
                   ).
                  ' ('.$field_count.')'
                );

            $r .= Cp::tableCell('',
                  Cp::anchor(
                    'weblogs-administration/delete-field-group-conf/weblog_field_group_id='.$row->weblog_field_group_id,
                    __('kilvin::cp.delete'),
                    'class="delete-link"'
               ));

            $r .= '</tr>'.PHP_EOL;
        }

        $r .= '</table>'.PHP_EOL;

        Cp::$title  = __('kilvin::admin.field_groups');
        Cp::$crumb  = __('kilvin::admin.field_groups');

        $right_links[] = [
            'weblogs-administration/edit-field-group-form',
            __('kilvin::admin.create_new_field_group')
        ];

        $r = Cp::header(__('kilvin::admin.field_groups'), $right_links).$r;

        Cp::$body = $r;
    }

   /**
    * Create/Edit Custom Field group form
    *
    * @return string
    */
    public function editFieldGroupForm()
    {
        // Default values
        $edit = false;
        $weblog_field_group_id   = '';
        $group_name = '';

        if ($weblog_field_group_id = Cp::pathVar('weblog_field_group_id')) {
            $edit = true;

            if ( ! is_numeric($weblog_field_group_id)) {
                return false;
            }

            $query = DB::table('weblog_field_groups')
                ->where('id', $weblog_field_group_id)
                ->select('id AS weblog_field_group_id', 'group_name')
                ->first();

            foreach ($query as $key => $val) {
                $$key = $val;
            }
        }

        if ($edit == FALSE) {
            $title = __('kilvin::admin.new_field_group');
        }
        else {
            $title = __('kilvin::admin.edit_field_group_name');
        }

        // Build our output
        $r = Cp::formOpen(['action' => 'weblogs-administration/update-field-group']);

        if ($edit == true) {
            $r .= Cp::input_hidden('weblog_field_group_id', $weblog_field_group_id);
        }

        $r .= Cp::quickDiv('tableHeading', $title);

        $r .= Cp::div('box');
        $r .= Cp::quickDiv(
        	'littlePadding',
        	Cp::quickDiv('defaultBold', __('kilvin::admin.field_group_name'))
        );
        $r .= Cp::input_text('group_name', $group_name, '20', '50', 'input', '300px');
        $r .= '<br><br>';
        $r .= '</div>'.PHP_EOL;

        $r .= Cp::div('paddingTop');

        $r .= Cp::input_submit(($edit == FALSE) ? __('kilvin::cp.submit') : __('kilvin::cp.update'));

        $r .= '</form>'.PHP_EOL;

        Cp::$title = $title;
        Cp::$crumb =
            Cp::anchor('weblogs-administration/fields-overview', __('kilvin::admin.field_groups')).
            Cp::breadcrumbItem($title);

        Cp::$body  = $r;
    }

   /**
    * Create/Update Custom Field group
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
    public function updateFieldGroup()
    {
        $edit = Request::filled('weblog_field_group_id');

        // ------------------------------------
        //  Validation
        // ------------------------------------

        $validator = Validator::make(request()->all(), [
            'group_name' => 'required|regex:#^[a-zA-Z0-9_\-/\s]+$#i',
            'weblog_field_group_id' => 'numeric'
        ]);

        if ($validator->fails()) {
            return Cp::errorMessage(implode(BR, $validator->errors()->all()));
        }

        $group_id = Request::input('weblog_field_group_id');;
        $data = Request::only(['group_name']);

        // Group Name must be unique for site
        $query = DB::table('weblog_field_groups')
            ->where('site_id', Site::config('site_id'))
            ->where('group_name', $data['group_name']);

        if ($edit === true) {
            $query->where('id', '!=', $group_id);
        }

        if ($query->count() > 0) {
            return Cp::errorMessage(__('kilvin::admin.taken_field_group_name'));
        }

        // ------------------------------------
        //  Create!
        // ------------------------------------

        if ($edit === false) {
            DB::table('weblog_field_groups')->insert($data);

            $success_msg = __('kilvin::admin.field_group_created');

            Cp::log(__('kilvin::admin.field_group_created').'&nbsp;'.$data['group_name']);
        }

        // ------------------------------------
        //  Update!
        // ------------------------------------

        if ($edit === true) {
            DB::table('weblog_field_groups')->where('id', $group_id)->update($data);

            $success_msg = __('kilvin::admin.field_group_updated');
        }

        $message = $success_msg.' '. $data['group_name'];

        // ------------------------------------
        //  Message
        // ------------------------------------

        if ($edit === false) {
            $query = DB::table('weblogs')
                ->select('weblogs.id AS weblog_id')
                ->get();

            if ($query->count() > 0) {
                $message .= Cp::div('littlePadding').Cp::quickSpan('highlight', __('kilvin::admin.assign_group_to_weblog')).'&nbsp;';

                if ($query->count() == 1) {
                    $link = 'weblogs-administration/edit-weblog-fields/weblog_id='.$query->first()->weblog_id;
                } else {
                    $link = 'weblogs-administration/weblogs-overview';
                }

                $message .= Cp::anchor($link, __('kilvin::admin.click_to_assign_group'));

                $message .= '</div>'.PHP_EOL;
            }
        }

        return redirect(kilvinCpUrl('weblogs-administration/fields-overview'))
            ->with('cp-message', $message);
    }

   /**
    * Delete Custom Field group confirmation form
    *
    * @return string
    */
    public function deleteFieldGroupConf()
    {
        if (($weblog_field_group_id = Cp::pathVar('weblog_field_group_id')) === null OR ! is_numeric($weblog_field_group_id)) {
            return false;
        }

        $group_name = DB::table('weblog_field_groups')
            ->where('id', $weblog_field_group_id)
            ->value('group_name');

        if ( ! $group_name) {
            return false;
        }

        Cp::$title = __('kilvin::admin.delete_group');
        Cp::$crumb =
            Cp::anchor('weblogs-administration/fields-overview', __('kilvin::admin.field_groups')).
            Cp::breadcrumbItem(__('kilvin::admin.delete_group'));

        Cp::$body = Cp::deleteConfirmation(
            [
                'path'      => 'weblogs-administration/delete-field-group/weblog_field_group_id='.$weblog_field_group_id,
                'heading'   => 'admin.delete_field_group',
                'message'   => 'admin.delete_field_group_confirmation',
                'item'      => $group_name,
                'extra'     => '',
                'hidden'    => ['weblog_field_group_id' => $weblog_field_group_id]
            ]
        );
    }

   /**
    * Delete Custom Field group
    *
    * @return string
    */
    public function deleteFieldGroup()
    {
        if (($weblog_field_group_id = Request::input('weblog_field_group_id')) === null OR ! is_numeric($weblog_field_group_id)) {
            return false;
        }

        $name = DB::table('weblog_field_groups')->where('id', $weblog_field_group_id)->value('group_name');

        $query = DB::table('weblog_fields')
            ->where('weblog_field_group_id', $weblog_field_group_id)
            ->select('id AS field_id', 'field_type', 'field_handle')
            ->get();

        foreach ($query as $row) {
            Schema::table('weblog_entry_data', function($table) use ($row) {
                $table->dropColumn('field_'.$row->field_handle);
            });
        }

        DB::table('weblog_field_groups')->where('id', $weblog_field_group_id)->delete();
        DB::table('weblog_fields')->where('weblog_field_group_id', $weblog_field_group_id)->delete();

        Cp::log(__('kilvin::admin.field_group_deleted').$name);

        cmsClearCaching('all');

        return redirect(kilvinCpUrl('weblogs-administration/fields-overview'))
            ->with('cp-message', __('kilvin::admin.field_group_deleted').'<b>'.$name.'</b>');
    }

   /**
    * List Custom Fields for a Group
    *
    * @return string
    */
    public function fieldManager()
    {
        $weblog_field_group_id = Cp::pathVar('weblog_field_group_id');

        if ( ! is_numeric($weblog_field_group_id)) {
            abort(404);
        }

        // Fetch the name of the field group
        $query = DB::table('weblog_field_groups')->select('group_name')->where('id', $weblog_field_group_id)->first();

        $r  = Cp::quickDiv('tableHeading', __('kilvin::admin.field_group').':'.'&nbsp;'.$query->group_name);

        $cp_message = session()->pull('cp-message');

        if (!empty($cp_message)) {
            $r .= Cp::quickDiv('success-message', $cp_message);
        }

        $r .= Cp::table('tableBorder', '0', '10', '100%').
              '<tr>'.PHP_EOL.
              Cp::td('tableHeadingAlt', '30%', '1').__('kilvin::admin.field_name').'</td>'.PHP_EOL.
              Cp::td('tableHeadingAlt', '20%', '1').__('kilvin::admin.field_handle').'</td>'.PHP_EOL.
              Cp::td('tableHeadingAlt', '30%', '1').__('kilvin::admin.field_type').'</td>'.PHP_EOL.
              Cp::td('tableHeadingAlt', '10%', '1').__('kilvin::admin.Required?').'</td>'.PHP_EOL.
              Cp::td('tableHeadingAlt', '10%', '1').'</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        $query = DB::table('weblog_fields')
            ->where('weblog_field_group_id', $weblog_field_group_id)
            ->orderBy('field_name')
            ->select(
                'id AS field_id',
                'field_handle',
                'field_name',
                'field_type',
                'is_field_required'
            )->get();


        if ($query->count() == 0) {
            $r .= '<tr>'.PHP_EOL.
                  Cp::td('', '', 5).
                  '<b>'.__('kilvin::admin.no_field_groups').'</br>'.
                  '</td>'.PHP_EOL.
                  '</tr>'.PHP_EOL;
        }

        // FieldTypes!
        $field_types = Plugins::fieldTypes();

        $i = 0;

        if ($query->count() > 0) {
            foreach ($query as $row) {

                $r .= '<tr>'.PHP_EOL;
                $r .= Cp::tableCell(
                    '',
                    Cp::quickDiv(
                        'defaultBold',
                        Cp::anchor(
                            'weblogs-administration/edit-field/field_id='.$row->field_id,
                            $row->field_name
                        )
                    )
                );

                $r .= Cp::tableCell('', $row->field_name);

                $field_type = (__('kilvin::'.$row->field_type) == false) ? '' : __('kilvin::'.$row->field_type);

                switch (strtolower($row->field_type)) {
                    case 'text' :  $field_type = __('kilvin::admin.Text Input');
                        break;
                    case 'textarea' :  $field_type = __('kilvin::admin.Textarea');
                        break;
                    case 'dropdown' :  $field_type = __('kilvin::admin.Dropdown');
                        break;
                    case 'date' :  $field_type = __('kilvin::admin.Date');
                        break;
                }

                $r .= Cp::tableCell('', $field_type);

                $r .= Cp::tableCell('', $row->is_field_required ? 'Yes' : 'No');

                $r .= Cp::tableCell(
                    '',
                    Cp::anchor(
                        'weblogs-administration/delete-field-confirm'.
                            '/field_id='.$row->field_id,
                        __('kilvin::cp.delete'),
                        'class="delete-link"'
                    )
                );

                $r .= '</tr>'.PHP_EOL;
            }
        }

        $r .= '</table>'.PHP_EOL;

        Cp::$title = __('kilvin::admin.custom_fields');
        Cp::$crumb =
            Cp::anchor('weblogs-administration/fields-overview', __('kilvin::admin.field_groups')).
            Cp::breadcrumbItem(__('kilvin::admin.custom_fields'));

        $right_links[] = [
            'weblogs-administration/new-field/weblog_field_group_id='.$weblog_field_group_id,
            __('kilvin::admin.create_new_custom_field')
        ];

        $r = Cp::header(__('kilvin::admin.custom_fields'), $right_links).$r;

        Cp::$body  = $r;
    }

    /**
    * New Custom Field form
    *
    * @return string
    */
    public function newField()
    {
        return $this->editField();
    }

   /**
    * Edit Custom Field form
    *
    * @return string
    */
    public function editField()
    {
        $field_id = Cp::pathVar('field_id');

        $type = ($field_id) ? 'edit' : 'new';

        // ------------------------------------
        //  Variables
        // ------------------------------------

        $field_handle        = '';
        $field_name          = '';
        $field_instructions  = '';
        $field_type          = '';
        $settings            = [];

        $is_field_required   = false;

        $weblog_field_group_id = '';
        $group_name          = '';

        $total_fields = '';

        if ($type == 'new') {
            $total_fields = 1 + DB::table('weblog_fields')->count();
        }

        if ($field_id) {
            $query = DB::table('weblog_fields AS f')
                ->join('weblog_field_groups AS g', 'f.weblog_field_group_id', '=', 'g.id')
                ->where('f.id', $field_id)
                ->select(
                    'f.id AS field_id',
                    'f.*',
                    'g.group_name'
                )
                ->first();

            if (!$query) {
                redirect(kilvinCpUrl('weblogs-administration/fields-overview'));
            }

            foreach ($query as $key => $val) {
                $$key = $val;
            }

            $settings = (!empty($settings)) ? json_decode($settings, true) : [];

            if (!is_array($settings)) {
                $settings = [];
            }
        }

        if (empty($weblog_field_group_id)) {
            $weblog_field_group_id = Cp::pathVar('weblog_field_group_id') ?? Request::input('weblog_field_group_id');
        }

        if (empty($group_name)) {
            $group_name = DB::table('weblog_field_groups')
                ->where('id', $weblog_field_group_id)
                ->value('group_name');
        }

        // ------------------------------------
        //  JavaScript
        // ------------------------------------

        $js = <<<EOT
	<script type="text/javascript">

         $( document ).ready(function() {
            $('select[name=field_type]').change(function(e) {
                e.preventDefault();

                var field_type = $(this).val();

                $('.field-option').css('display', 'none');

                $('#field_type_settings_'+field_type).css('display', 'block');

            });

            $('select[name=field_type]').val('{$field_type}');
            $('select[name=field_type]').trigger("change");
        });

	</script>
EOT;


        $r = $js;

        // ------------------------------------
        //  Form Opening
        // ------------------------------------

        $r .= Cp::formOpen([
        	'action' => 'weblogs-administration/update-field',
        	'name' => 'field_form'
        ]);

        $r .= Cp::input_hidden('weblog_field_group_id', $weblog_field_group_id);
        $r .= Cp::input_hidden('field_id', $field_id);
        $r .= Cp::input_hidden('site_id', Site::config('site_id'));

        $title = __('kilvin::'.(($type == 'edit') ? 'admin.edit_field' : 'admin.create_new_custom_field'));

        $r .= Cp::table('tableBorder', '0', '10', '100%').
			'<tr>'.PHP_EOL.
				Cp::td('tableHeading', '', '2').
					$title.' ('.__('kilvin::admin.field_group').": {$group_name})".
				'</td>'.PHP_EOL.
			'</tr>'.PHP_EOL;

        $i = 0;

        // ------------------------------------
        //  Field Name
        // ------------------------------------

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell(
            '',
            Cp::quickSpan(
                'defaultBold',
                Cp::required().__('kilvin::admin.field_name')
            ).
            Cp::quickDiv(
                '',
                __('kilvin::admin.field_name_info')
            ),
            '50%'
        );

        $r .= Cp::tableCell(
            '',
            Cp::input_text(
                'field_name',
                $field_name,
                '20',
                '60',
                'input',
                '260px'
            ),
            '50%'
        );

        $r .= '</tr>'.PHP_EOL;

        // ------------------------------------
        //  Field handle
        // ------------------------------------

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell(
            '',
            Cp::quickSpan(
                'defaultBold',
                Cp::required().__('kilvin::admin.field_handle')
            ).
            Cp::quickDiv(
                'littlePadding',
                __('kilvin::admin.field_handle_explanation')
            ),
            '50%'
        );

        $r .= Cp::tableCell(
            '',
            Cp::input_text(
                'field_handle',
                $field_handle,
                '20',
                '60',
                'input',
                '260px'
            ),
            '50%'
        );

        $r .= '</tr>'.PHP_EOL;

        // ------------------------------------
        //  Field Instructions
        // ------------------------------------

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell(
            '',
            Cp::quickSpan(
                'defaultBold',
                __('kilvin::admin.field_instructions')
            ).
            Cp::quickDiv(
                '',
                __('kilvin::admin.field_instructions_info')
            ),
            '50%',
            'top'
        );

        $r .= Cp::tableCell(
            '',
            Cp::input_textarea(
                'field_instructions',
                $field_instructions,
                '6',
                'textarea',
                '99%'
            ),
            '50%',
            'top'
        );

        $r .= '</tr>'.PHP_EOL;

        // ------------------------------------
        //  Is field required?
        // ------------------------------------

        if (empty($is_field_required)) {
        	$is_field_required = 0;
        }

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell(
            '',
            Cp::quickSpan(
                'defaultBold',
                __('kilvin::admin.is_field_required')
            ),
            '50%'
        );

        $r .= Cp::tableCell(
            '',
                __('kilvin::admin.yes').
                ' '.
                Cp::input_radio('is_field_required', '1', ($is_field_required == 1) ? 1 : '').
                ' '.
                __('kilvin::admin.no').' '.
                Cp::input_radio('is_field_required', '0', ($is_field_required != 1) ? 1 : ''),
            '50%'
        );

        $r .= '</tr>'.PHP_EOL;

        // ------------------------------------
        //  Create the Field Type pull-down menu
        // ------------------------------------

        $r .= '<tr>'.PHP_EOL.'<td><strong>'.__('kilvin::admin.Field Type').'</strong></td>';

        $r .= '<td>';
        $r .= '<select name="field_type" class="select">';
        $r .= Cp::input_select_option('', __('kilvin::admin.Choose Field Type'));


        $field_types = Plugins::fieldTypes();

        foreach($field_types as $name => $details) {
            $r .= Cp::input_select_option($name, $name, ($name == $field_type));
        }

        $r .= Cp::input_select_footer();

        $r .= '</td></tr>'.PHP_EOL;

        // ------------------------------------
        //  Close Top Area
        // ------------------------------------

        $r .= '</table>'.PHP_EOL;

        // ------------------------------------
        //  Create the Field Type Forms!
        // ------------------------------------

        foreach($field_types as $name => $class) {
            $r .= '<div id="field_type_settings_'.$name.'" class="field-option" style="display:none;">';
            $r .= app($class)->settingsFormHtml($settings[$name] ?? []);
            $r .= '</div>';
        }

        // ------------------------------------
        //  Submit
        // ------------------------------------

        $r .= BR;

        if ($type == 'edit') {
            $r .= Cp::input_submit(__('kilvin::cp.update'));
        } else {
            $r .= Cp::input_submit(__('kilvin::cp.submit'));
        }

        $r .= '</div>'.PHP_EOL;
        $r .= '</form>'.PHP_EOL;

        Cp::$title = $title.' | '.__('kilvin::admin.custom_fields');
        Cp::$crumb =
            Cp::anchor('weblogs-administration/fields-overview', __('kilvin::admin.field_groups')).
            Cp::breadcrumbItem($title);
        Cp::$body  = $r;
    }

   /**
    * Create/Update Custom Field
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
    public function updateField()
    {
        $edit = Request::filled('field_id');

        $field_types = Plugins::fieldTypes();

        // ------------------------------------
        //  Validation
        // ------------------------------------

        $validator = Validator::make(request()->all(), [
            'field_handle'          => 'regex:#^[a-zA-Z0-9_]+$#i',
            'field_name'            => 'required|not_in:'.implode(',',Cp::unavailableFieldNames()),
            'weblog_field_group_id' => 'required|numeric',
            'field_type'            => 'required|in:'.implode(',', array_keys($field_types))
        ]);

        if ($validator->fails()) {
            return Cp::errorMessage(implode(BR, $validator->errors()->all()));
        }

        $data = Request::only(
            [
                'field_handle',
                'field_name',
                'field_instructions',
                'field_type',
                'is_field_required',
                'weblog_field_group_id',
                'field_id'
            ]
        );

        $stringable = [
            'field_instructions',
        ];

        foreach($stringable as $field) {
            if(empty($data[$field])) {
                $data[$field] = '';
            }
        }

        // Let DB defaults handle these if empty
        $unsettable = [
            'is_field_required',
        ];

        foreach($unsettable as $field) {
            if(empty($data[$field])) {
                unset($data[$field]);
            }
        }

        $weblog_field_group_id = Request::input('weblog_field_group_id');

        // ------------------------------------
        //  Field Handle or Name already taken?
        // ------------------------------------

        $query = DB::table('weblog_fields')
            ->where('field_handle', $data['field_handle'])
            ->where('site_id', Site::config('site_id'));

        if ($edit === true) {
            $query->where('id', '!=', $data['field_id']);
        }

        if ($query->count() > 0) {
            return Cp::errorMessage(__('kilvin::admin.duplicate_field_handle'));
        }

        $query = DB::table('weblog_fields')
            ->where('field_name', $data['field_name'])
            ->where('site_id',Site::config('site_id'));

        if ($edit === true) {
            $query->where('id', '!=', $data['field_id']);
        }

        if ($query->count() > 0) {
            return Cp::errorMessage(__('kilvin::admin.duplicate_field_name'));
        }

        // ------------------------------------
        //  Field Type Settings Validation
        // ------------------------------------

        if (!isset($field_types[$data['field_type']])) {
            return Cp::errorMessage(__('kilvin::admin.Invalid Field Type'));
        }

        $class = $field_types[$data['field_type']];

        $fieldType = app($class);

        $rules = $fieldType->settingsValidationRules(request()->all());

        if (is_array($rules) && !empty($rules)) {
            $validator = Validator::make(request()->all(), $rules);

            if ($validator->fails()) {
                return Cp::errorMessage(implode(BR, $validator->errors()->all()));
            }
        }

        // ------------------------------------
        //  All Settings to JSON String
        // ------------------------------------

        $data['settings'] = $settings = null;

        if (Request::filled('settings') && is_array(Request::input('settings'))) {
            $settings = Request::input('settings');
            $data['settings'] = json_encode($settings);
        }

        // ------------------------------------
        //  Updating!
        // ------------------------------------

        if ($edit === true) {
            if ( ! is_numeric($data['field_id'])) {
                return false;
            }

            unset($data['weblog_field_group_id']);

            $query = DB::table('weblog_fields')
                ->select('field_type', 'field_handle')
                ->where('id', $data['field_id'])
                ->first();

            // ------------------------------------
            //  Change Field Type?
            //  - We create a temporary field and copy data over
            //  - This allows a simpler FieldType class for developers
            //  - It also addresses the fact that Laravel/Doctrine is not very good with ALTERs
            // ------------------------------------

            if ($query->field_type != $data['field_type']) {

                $existing = Schema::getColumnType('weblog_entry_data', 'field_'.$query->field_handle);

                $field_name      = 'field_'.$query->field_handle;
                $temp_field_name = 'temp_field_'.str_random(10);

                // Create Temp Field
                try {
                    Schema::table('weblog_entry_data', function($table) use ($temp_field_name, $existing, $fieldType)
                    {
                        $fieldType->columnType($temp_field_name, $table, $existing);
                    });
                } catch (\Exception $e) {
                    return Cp::errorMessage($e->getMessage());
                }

                // Copy Data Over
                try {
                    DB::table('weblog_entry_data')
                        ->update([$temp_field_name => DB::raw($field_name)]);
                } catch (\Exception $e) {

                    // Drop Temp Column
                    Schema::table('weblog_entry_data', function($table) use ($temp_field_name) {
                        $table->dropColumn($temp_field_name);
                    });

                    return Cp::errorMessage(
                            __('kilvin::admin.Unable to convert current field data over to new field type')
                        );
                }

                // Drop Old Column
                Schema::table('weblog_entry_data', function($table) use ($field_name) {
                    $table->dropColumn($field_name);
                });

                // Rename Temp Column
                Schema::table('weblog_entry_data', function($table) use ($field_name, $temp_field_name) {
                    $table->renameColumn($temp_field_name, $field_name);
                });
            }

            // ------------------------------------
            //  Rename Field
            // ------------------------------------

            if ($query->field_handle != $data['field_handle']) {
                Schema::table('weblog_entry_data', function($table) use ($query, $data) {
                    $table->renameColumn('field_'.$query->field_handle, 'field_'.$data['field_handle']);
                });

                DB::table('weblog_layout_fields')
                    ->where('field_handle', $query->field_handle)
                    ->update([
                        'field_handle' => $data['field_handle'],
                    ]);
            }

            $update = $data;
            unset($update['field_id']);

            DB::table('weblog_fields')
                ->where('id', $data['field_id'])
                ->where('weblog_field_group_id', $weblog_field_group_id)
                ->update($update);
        }

        // ------------------------------------
        //  Creation
        // ------------------------------------

        if ($edit === false) {
            unset($data['field_id']);

            $data['site_id'] = Site::config('site_id');
            $insert_id = DB::table('weblog_fields')->insertGetId($data);

            // ------------------------------------
            //  Create Field
            // ------------------------------------

            try {
                Schema::table('weblog_entry_data', function($table) use ($data, $fieldType)
                {
                    $fieldType->columnType('field_'.$data['field_handle'], $table, null);
                });
            } catch (\Exception $e) {

                DB::table('weblog_fields')->where('id', $insert_id)->delete();

                return Cp::errorMessage($e->getMessage());
            }

            // ------------------------------------
            //  Add to Layouts for Weblogs with Field Group
            // ------------------------------------

            $weblog_ids = DB::table('weblogs')
                ->where('weblog_field_group_id', $weblog_field_group_id)
                ->pluck('id')
                ->all();

            foreach($weblog_ids as $weblog_id) {
                $tab_id = DB::table('weblog_layout_tabs')
                    ->where('weblog_id', $weblog_id)
                    ->orderBy('tab_order')
                    ->value('id');

                $max = DB::table('weblog_layout_fields')
                    ->where('weblog_layout_tab_id', $tab_id)
                    ->max('field_order');

                if ($tab_id) {
                    DB::table('weblog_layout_fields')
                    ->insert([
                        'weblog_layout_tab_id' => $tab_id,
                        'field_handle' => $data['field_handle'],
                        'field_order' => $max+1
                    ]);
                }
            }
       }

        // ------------------------------------
        //  We have done the impossible and that makes us mighty.
        // ------------------------------------

        cmsClearCaching('all');

        session()->flash('cp-message', __('kilvin::admin.Field Updated'));

        return redirect(kilvinCpUrl('weblogs-administration/field-manager/weblog_field_group_id='.$weblog_field_group_id));
    }

   /**
    * Delete Custom Field confirmation form
    *
    * @return string
    */
    public function deleteFieldConfirm()
    {
        if ( ! $field_id = Cp::pathVar('field_id')) {
            return false;
        }

        $query = DB::table('weblog_fields')
            ->select('field_name')
            ->where('id', $field_id)
            ->first();

        Cp::$title = __('kilvin::admin.delete_field');
        Cp::$crumb =
            Cp::anchor('weblogs-administration/fields-overview', __('kilvin::admin.field_groups')).
            Cp::breadcrumbItem(__('kilvin::admin.delete_field'));

        Cp::$body = Cp::deleteConfirmation(
            [
                'path'      => 'weblogs-administration/delete-field/field_id='.$field_id,
                'heading'   => 'admin.delete_field',
                'message'   => 'admin.delete_field_confirmation',
                'item'      => $query->field_name,
                'extra'     => '',
                'hidden'    => ['field_id' => $field_id]
            ]
        );
    }

   /**
    * Delete Custom Field
    *
    * @return string
    */
    public function deleteField()
    {
        if ( ! $field_id = Request::input('field_id')) {
            return false;
        }

        if ( ! is_numeric($field_id)) {
            return false;
        }

        $query = DB::table('weblog_fields')
            ->where('id', $field_id)
            ->select('weblog_field_group_id', 'field_type', 'field_name', 'field_handle')
            ->first();

        $weblog_field_group_id = $query->weblog_field_group_id;
        $field_name = $query->field_name;
        $field_type = $query->field_type;
        $field_handle = $query->field_handle;

        Schema::table('weblog_entry_data', function($table) use ($field_handle) {
            if (!Schema::hasColumn('weblog_entry_data', 'field_'.$field_handle)) {
                return;
            }

            $table->dropColumn('field_'.$field_handle);
        });

        DB::table('weblog_fields')
            ->where('id', $field_id)
            ->delete();

        DB::table('weblog_layout_fields')
            ->where('field_handle', $field_handle)
            ->delete();

        Cp::log(__('kilvin::admin.field_deleted').' '.$field_name);

        // ------------------------------------
        //  Clear Caching and Back to Field Manager
        // ------------------------------------

        cmsClearCaching('all');

        return redirect(kilvinCpUrl('weblogs-administration/field-manager/weblog_field_group_id='.$weblog_field_group_id))
            ->with('cp-message', __('kilvin::admin.field_deleted').' '.$field_name);
    }

   /**
    * List Asset Containers
    *
    * @return string
    */
    public function assetContainers()
    {
        if ( ! Session::access('can_admin_asset_containers')) {
            abort(405);
        }

        $right_links[] = [
            'weblogs-administration/edit-asset-container',
            __('kilvin::admin.create_asset_container')
        ];

        $r = Cp::header(__('kilvin::admin.asset-containers'), $right_links);

        $cp_message = session()->pull('cp-message');

        if (!empty($cp_message)) {
            $r .= Cp::quickDiv('success-message', $cp_message);
        }

        $r .= Cp::table('tableBorder', '0', '10', '100%').
              '<tr>'.PHP_EOL.
              Cp::td('tableHeading').
                 __('kilvin::admin.container_name').
              '</td>'.PHP_EOL.
              Cp::td('tableHeading').
                 __('kilvin::admin.container_handle').
              '</td>'.PHP_EOL.
              Cp::td('tableHeading').
                 __('kilvin::admin.container_driver').
              '</td>'.PHP_EOL.
              Cp::td('tableHeading').
                 __('kilvin::cp.edit').
              '</td>'.PHP_EOL.
              Cp::td('tableHeading').
                 __('kilvin::cp.delete').
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        $query = DB::table('asset_containers')
            ->orderBy('name')
            ->get();

        if ($query->count() == 0) {
            $r .= '<tr>'.PHP_EOL.
                  Cp::td('', '', '3').
                  '<b>'.__('kilvin::admin.no_asset_containers').'</b>'.
                  '</td>'.PHP_EOL.
                  '</tr>'.PHP_EOL;
        }

        $i = 0;

        foreach ($query as $row) {
            $r .= '<tr>'.PHP_EOL;
            $r .= Cp::tableCell('', Cp::quickSpan('defaultBold', $row->name), '20%');
            $r .= Cp::tableCell('', $row->handle, '20%');
            $r .= Cp::tableCell('', $row->driver, '50%');
            $r .= Cp::tableCell('', Cp::anchor('weblogs-administration/edit-asset-container/id='.$row->id, __('kilvin::cp.edit')), '5%');
            $r .= Cp::tableCell('', Cp::anchor('weblogs-administration/delete-asset-container-confirm/id='.$row->id, __('kilvin::cp.delete')), '5%');
            $r .= '</tr>'.PHP_EOL;
        }

        $r .= '</table>'.PHP_EOL;

        Cp::$title = __('kilvin::admin.asset-containers');
        Cp::$crumb = __('kilvin::admin.asset-containers');

        Cp::$body  = $r;
    }

   /**
    * Create/Edit Upload Preferences form
    *
    * @return string
    */
    public function editAssetContainer()
    {
        if ( ! Session::access('can_admin_asset_containers')) {
            abort(405);
        }

        $id = Cp::pathVar('id');
        $type = (!empty($id)) ? 'edit' : 'new';

        $site_id = Site::config('site_id');
        $name = '';
        $handle = '';
        $driver = 'local';
        $allowed_types = 'images';
        $allowed_mimes = '';
        $configuration = [
            'local' => [
                'root' => '',
                'url' => '',
            ],
            's3' => [
                'key' => '',
                'secret' => '',
                'region' => '',
                'bucket' => '',
                'url' => '',
            ],
        ];

        if ($type === 'edit') {
            $query = DB::table('asset_containers')
                ->where('id', $id)
                ->first();

            if (empty($query)) {
                return Cp::unauthorizedAccess();
            }

            foreach ($query as $key => $val) {
                if ($key == 'configuration') {
                    try {
                        $config = json_decode($val, true);
                        $configuration = array_merge($configuration, $config);
                    } catch (\Exception $e) {
                    }
                } else {
                    $$key = $val;
                }
            }
        }

        // Form declaration
        $r  = Cp::formOpen(['action' => 'weblogs-administration/update-asset-container']);
        $r .= Cp::input_hidden('asset_container_id', $id);

        $r .= Cp::table('tableBorder', '0', '', '100%').
              Cp::td('tableHeading', '50%');

        if ($type == 'edit') {
            $r .= __('kilvin::admin.edit_asset_container');
        }
        else {
            $r .= __('kilvin::admin.new_asset_container');
        }

        $r .= '</td>'.PHP_EOL.
                Cp::td('tableHeading', '50%').
                '</td>'.
                '</tr>'.
                PHP_EOL;

        $i = 0;

        $r .= Cp::tableQuickRow('',
            [
                Cp::quickSpan('defaultBold', __('kilvin::admin.container_name').Cp::required()),
                Cp::input_text('name', $name, '50', '50', 'input', '100%')
            ]
        );

        $r .= Cp::tableQuickRow('',
            [
                Cp::quickSpan('defaultBold', __('kilvin::admin.container_handle').Cp::required()),
                Cp::input_text('handle', $handle, '50', '50', 'input', '100%')
            ]
        );

        $mime_placeholder = 'ex: video/avi,video/mpeg,video/quicktime';
        $mime_url = 'https://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types';
        $mime_subtext = sprintf(__('kilvin::admin.allowed_mimes_subtext'), $mime_url);

        $mime_field = '<div style="display: none; margin-top:20px;" id="mimes_row">'.
            '<div>'.
                Cp::quickDiv('defaultBold', __('kilvin::admin.allowed_mimes')).
                $mime_subtext.
            '</div>'.
            '<div style="margin-top:5px;">'.
                '<textarea name="allowed_mimes" style="width:100%" rows="4" placeholder="'.$mime_placeholder.'">'.
                    escapeAttribute($allowed_mimes).
                '</textarea>'.
            '</div>'.
        '</div>';

        $r .= '<tr><td style="vertical-align:top;">'.
                Cp::quickSpan('defaultBold', __('kilvin::admin.allowed_types').Cp::required()).
            '</td><td>'.
                '<label>'
                    .Cp::input_radio('allowed_types', 'all', ($allowed_types == 'all') ? 1 : '')
                    .__('kilvin::admin.all_filetypes')
                .'</label>'
                .NBS.NBS.
                '<label>'
                    .Cp::input_radio('allowed_types', 'images', ($allowed_types == 'images') ? 1 : '')
                    .__('kilvin::admin.images_only')
                .'</label>'
                .NBS.NBS
                .'<label>'
                    .Cp::input_radio('allowed_types', 'list', ($allowed_types == 'list') ? 1 : '')
                    .__('kilvin::admin.list_of_mimes')
                .'</label>'.
                $mime_field.
            '</td></tr>';


        $root_placeholder = 'ex: {PUBLIC_PATH}uploads';
        $url_placeholder = 'ex: {SITE_URL}uploads';

        $local_fields = '<div style="display: none; margin-top:20px;" id="local_fields_block">'.
            '<div style="margin-top:12px;">'.
                Cp::quickDiv('defaultBold', __('kilvin::admin.local_path')).
            '</div>'.
            '<div style="margin-top:2px;">'.
                '<input
                    type="text"
                    name="configuration[local][root]"
                    value="'.escapeAttribute($configuration['local']['root']).'"
                    placeholder="'.escapeAttribute($root_placeholder).'"
                    class="input"
                    size="90"
                    style="width: 100%;">'.
            '</div>'.

            '<div style="margin-top:12px;">'.
                Cp::quickDiv('defaultBold', __('kilvin::admin.local_url')).
            '</div>'.
            '<div style="margin-top:2px;">'.
                '<input
                    type="text"
                    name="configuration[local][url]"
                    value="'.escapeAttribute($configuration['local']['url']).'"
                    placeholder="'.escapeAttribute($url_placeholder).'"
                    class="input"
                    size="90"
                    style="width: 100%;">'.
            '</div>'.
        '</div>';

        $key_placeholder = '{env.AWS_ACCESS_KEY_ID}';
        $secret_placeholder = '{env.AWS_SECRET_ACCESS_KEY}';
        $region_placeholder = '{env.AWS_DEFAULT_REGION}';
        $bucket_placeholder = '{env.AWS_BUCKET}';
        $url_placeholder = '{env.AWS_URL}';

        $s3_fields = '<div style="display: none; margin-top:20px;" id="s3_fields_block">'.
            '<div style="margin-top:12px;">'.
                Cp::quickDiv('defaultBold', __('kilvin::admin.s3_key')).
            '</div>'.
            '<div style="margin-top:2px;">'.
                '<input
                    type="text"
                    name="configuration[s3][key]"
                    value="'.escapeAttribute($configuration['s3']['key']).'"
                    placeholder="'.escapeAttribute($key_placeholder).'"
                    class="input"
                    size="90"
                    style="width: 100%;">'.
            '</div>'.

            '<div style="margin-top:12px;">'.
                Cp::quickDiv('defaultBold', __('kilvin::admin.s3_secret')).
            '</div>'.
            '<div style="margin-top:2px;">'.
                '<input
                    type="text"
                    name="configuration[s3][secret]"
                    value="'.escapeAttribute($configuration['s3']['secret']).'"
                    placeholder="'.escapeAttribute($secret_placeholder).'"
                    class="input"
                    size="90"
                    style="width: 100%;">'.
            '</div>'.

            '<div style="margin-top:12px;">'.
                Cp::quickDiv('defaultBold', __('kilvin::admin.s3_region')).
            '</div>'.
            '<div style="margin-top:2px;">'.
                '<input
                    type="text"
                    name="configuration[s3][region]"
                    value="'.escapeAttribute($configuration['s3']['region']).'"
                    placeholder="'.escapeAttribute($region_placeholder).'"
                    class="input"
                    size="90"
                    style="width: 100%;">'.
            '</div>'.


            '<div style="margin-top:12px;">'.
                Cp::quickDiv('defaultBold', __('kilvin::admin.s3_bucket')).
            '</div>'.
            '<div style="margin-top:2px;">'.
                '<input
                    type="text"
                    name="configuration[s3][bucket]"
                    value="'.escapeAttribute($configuration['s3']['bucket']).'"
                    placeholder="'.escapeAttribute($bucket_placeholder).'"
                    class="input"
                    size="90"
                    style="width: 100%;">'.
            '</div>'.

            '<div style="margin-top:12px;">'.
                Cp::quickDiv('defaultBold', __('kilvin::admin.s3_url')).
            '</div>'.
            '<div style="margin-top:2px;">'.
                '<input
                    type="text"
                    name="configuration[s3][url]"
                    value="'.escapeAttribute($configuration['s3']['url']).'"
                    placeholder="'.escapeAttribute($url_placeholder).'"
                    class="input"
                    size="90"
                    style="width: 100%;">'.
            '</div>'.
        '</div>';

        $r .= '<tr><td style="vertical-align:top;">'.
                Cp::quickSpan('defaultBold', __('kilvin::admin.container_driver').Cp::required()).
            '</td><td>'.
                '<label>'
                    .Cp::input_radio('driver', 'local', ($driver == 'local') ? 1 : '')
                    .__('kilvin::admin.driver_local')
                .'</label>'
                .NBS.NBS.
                '<label>'
                    .Cp::input_radio('driver', 's3', ($driver == 's3') ? 1 : '')
                    .__('kilvin::admin.driver_amazon_s3')
                .'</label>'.
                $local_fields.
                $s3_fields.
            '</td></tr>';

        $r .= '</table>'.PHP_EOL;


        $r .= Cp::quickDiv(
            'paddingTop',
            Cp::heading(
                __('kilvin::admin.restrict_to_group'),
                2
            ).
            Cp::quickDiv('highlight', __('kilvin::admin.restrict_notes_2'))
        );

        $query = DB::table('member_groups')
            ->whereNotIn('id',  [1])
            ->select('member_groups.id AS member_group_id', 'group_name')
            ->orderBy('group_name')
            ->get();

        if ($query->count() > 0) {
            $r .= Cp::table('tableBorder', '0', '', '100%').
                  '<tr>'.PHP_EOL.
                      Cp::td('tableHeading', '', '').
                          __('kilvin::admin.member_group').
                      '</td>'.PHP_EOL.
                      Cp::td('tableHeading', '', '').
                          __('kilvin::admin.can_upload_files').
                      '</td>'.PHP_EOL.
                  '</tr>'.PHP_EOL;

            $i = 0;

            $group = [];

            $result = DB::table('asset_container_access');

            if ($id != '') {
                $result->where('asset_container_id', $id);
            }

            $groups = $result->get()->keyBy('member_group_id')->all();

            foreach ($query as $row) {
                $r .= '<tr>'.PHP_EOL.
                      Cp::td('', '50%').$row->group_name.'</td>'.PHP_EOL.
                      Cp::td('', '50%');

                $selected = (isset($groups[$row->member_group_id])) ? 1 : '';

                $r .= Cp::qlabel(__('kilvin::admin.yes')).NBS.
                      Cp::input_radio('access['.$row->member_group_id.']', '1', $selected).'&nbsp;';

                $selected = (! isset($groups[$row->member_group_id])) ? 1 : '';

                $r .= Cp::qlabel(__('kilvin::admin.no')).NBS.
                      Cp::input_radio('access['.$row->member_group_id.']', '0', $selected).'&nbsp;';

                $r .= '</td>'.PHP_EOL.'</tr>'.PHP_EOL;
            }
            $r .= '</table>'.PHP_EOL;
        }

        $r .= Cp::div('littlePadding');

        if ($type == 'edit') {
            $r .= Cp::input_submit(__('kilvin::cp.update'));
        }
        else {
            $r .= Cp::input_submit(__('kilvin::cp.submit'));
        }

        $r .= '</div>'.PHP_EOL;
        $r .= '</form>'.PHP_EOL;

        $lang_line = ($type == 'edit') ? 'admin.edit_asset_container' : 'admin.new_asset_container';

        $js = <<<EOT
<script type="text/javascript">

$('input[name=allowed_types]').change(function (e) {
    doMimeFieldCheck();;
});

$('input[name=driver]').change(function (e) {
    doDriverFieldsCheck();;
});

$(function() {
    doMimeFieldCheck();
    doDriverFieldsCheck();
});

function doMimeFieldCheck()
{
    if ($('input[name=allowed_types]:checked').val() == 'list') {
        $('#mimes_row').show();
    } else {
        $('#mimes_row').hide();
    }
}

function doDriverFieldsCheck()
{
    var driver = $('input[name=driver]:checked').val();

    $('#local_fields_block').hide();
    $('#s3_fields_block').hide();

    if (driver == 'local') {
        $('#local_fields_block').show();
    }

    if (driver == 's3') {
        $('#s3_fields_block').show();
    }
}

</script>
EOT;

        Cp::$title = __('kilvin::'.$lang_line);
        Cp::$crumb =
            Cp::anchor('weblogs-administration/asset-containers', __('kilvin::admin.asset_containers')).
            Cp::breadcrumbItem(__('kilvin::'.$lang_line));

        Cp::$body = $r.$js;
    }

   /**
    * Create/Update Asset Containers
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
    public function updateAssetContainer()
    {
        if ( ! Session::access('can_admin_asset_containers')) {
            abort(405);
        }

        $edit = (bool) Request::filled('asset_container_id');

        // ------------------------------------
        //  Validation
        // ------------------------------------

        $validator = Validator::make(request()->all(), [
            'handle' => 'required|regex:#^[a-zA-Z0-9\_\-]+$#i',
            'name'   => 'required',
            'allowed_types' => 'required|in:all,list,images',
            'allowed_mimes' => 'required_if:allowed_types,list',
            'driver' => 'required|in:s3,local',
            'configuration.local.root' => 'required_if:driver,local',
            'configuration.local.url' => 'required_if:driver,local',
            'configuration.s3.key' => 'required_if:driver,s3',
            'configuration.s3.secret' => 'required_if:driver,s3',
            'configuration.s3.region' => 'required_if:driver,s3',
            'configuration.s3.bucket' => 'required_if:driver,s3',
            'configuration.s3.url' => 'required_if:driver,s3',
        ]);

        if ($validator->fails()) {
            return Cp::errorMessage(implode(BR, $validator->errors()->all()));
        }

        // Is the name or handle taken?
        foreach (['name', 'handle'] as $field) {
            $query = DB::table('asset_containers')
                ->where('site_id', Site::config('site_id'))
                ->where($field, Request::input($field));

            if ($edit === true) {
                $query->where('id', '!=', Request::input('asset_container_id'));
            }

            if ($query->count() > 0) {
                return Cp::errorMessage(__('kilvin::admin.taken_asset_container_'.$field));
            }
        }

        $data = [];

        $fields = [
            'asset_container_id',
            'name',
            'handle',
            'allowed_types',
            'allowed_mimes',
            'driver',
            'configuration',
        ];

        foreach($fields as $field) {
            if (Request::filled($field)) {
                $data[$field] = Request::input($field);
            }
        }

        if (empty($data['allowed_mimes']) || $data['allowed_types'] != 'list') {
            $data['allowed_mimes'] = '';
        } else {
            $data['allowed_mimes'] = preg_replace("/[^a-z\-\/\,\+\.0-9]/", '', $data['allowed_mimes']);
        }

        if (empty($data['configuration']) || !is_array($data['configuration'])) {
            $data['configuration'] = [];
        }

        // ------------------------------------
        //  Create Asset Container
        // ------------------------------------

        if ($edit === false) {
            $insert = $data;
            unset($insert['asset_container_id']);
            $insert['site_id'] = Site::config('site_id');
            $insert['configuration'] = json_encode($insert['configuration']);

            $asset_container_id = DB::table('asset_containers')->insertGetId($insert);

            $message = sprintf(__('kilvin::admin.asset_container_created'), $data['name']);
        }

        // ------------------------------------
        //  Updating Asset Container
        // ------------------------------------

        if ($edit === true) {
            $asset_container_id = $data['asset_container_id'];
            $update = $data;
            unset($update['asset_container_id']);
            $update['configuration'] = json_encode($update['configuration']);

            DB::table('asset_containers')
                ->where('id', $data['asset_container_id'])
                ->update($update);

            $message = sprintf(__('kilvin::admin.asset_container_updated'), $data['name']);
        }

        // ------------------------------------
        //  Member Group Permissions
        // ------------------------------------

        DB::table('asset_container_access')
            ->where('asset_container_id', $asset_container_id)
            ->delete();

        $group_ids = DB::table('member_groups')
            ->where('site_id', Site::config('site_id'))
            ->pluck('id')
            ->all();

        $access = (array) Request::input('access');

        foreach ($group_ids as $group_id) {
            if (isset($access[$group_id]) && $access[$group_id] == 1) {
                DB::table('asset_container_access')
                    ->insert([
                        'asset_container_id' => $asset_container_id,
                        'member_group_id' => $group_id,
                    ]);
            }
        }

        // ------------------------------------
        //  Messages and Return
        // ------------------------------------

        Cp::log($message);

        return redirect(kilvinCpUrl('weblogs-administration/asset-containers'))
                ->with('cp-message', $message);
    }

   /**
    * Delete Upload Preferences confirmation form
    *
    * @return string
    */
    public function deleteAssetContainerConfirm()
    {
        if ( ! $id = Cp::pathVar('id')) {
            return false;
        }

        if ( ! is_numeric($id)) {
            return false;
        }

        $query = DB::table('asset_containers')->select('name')->where('id', $id)->first();

        Cp::$title = __('kilvin::admin.delete_upload_preference');
        Cp::$crumb =
            Cp::anchor('weblogs-administration/asset-containers', __('kilvin::admin.file_asset_containers')).
            Cp::breadcrumbItem(__('kilvin::admin.delete_upload_preference'));

        Cp::$body = Cp::deleteConfirmation(
            [
                'path'      => 'weblogs-administration/delete-asset-container/id='.$id,
                'heading'   => 'admin.delete_upload_preference',
                'message'   => 'admin.delete_upload_pref_confirmation',
                'item'      => $query->name,
                'extra'     => '',
                'hidden'    => ['id' => $id]
            ]
        );
    }

   /**
    * Delete Upload Preferences
    *
    * @return string
    */
    public function deleteAssetContainer()
    {
        if ( ! $id = Request::input('id')) {
            return false;
        }

        if ( ! is_numeric($id)) {
            return false;
        }

        DB::table('asset_container_access')->where('asset_container_id', $id)->delete();

        $name = DB::table('asset_containers')->where('id', $id)->value('name');

        DB::table('asset_containers')->where('id', $id)->delete();

        $msg = __('kilvin::admin.asset_container_deleted').' '.$name;

        Cp::log($msg);

        return redirect(kilvinCpUrl('weblogs-administration/asset-containers'))
                ->with('cp-message', $msg);
    }
}
