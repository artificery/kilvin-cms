<?php

namespace Kilvin\Cp;

use Kilvin\Facades\Cp;
use Illuminate\Support\Facades\DB;
use Kilvin\Facades\Site;
use Kilvin\Facades\Stats;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Kilvin\Core\Session;

class SitesAdministration
{
   /**
    * Constructor
    *
    * @return  void
    */
    public function __construct()
    {
        if ( ! Session::access('can_admin_sites')) {
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
        if (Cp::segment(2) && method_exists($this, camel_case(Cp::segment(2)))) {
            return $this->{camel_case(Cp::segment(2))}();
        }

        return $this->listSites();
    }

   /**
    * Sites Manager
    *
    * @param string $message
    * @return  void
    */
    public function listSites($message = '')
    {
        if ( ! Session::access('can_admin_sites')) {
            return Cp::unauthorizedAccess();
        }

        // ------------------------------------
        //  Messaging for when Site Created or Updated
        // ------------------------------------

        if (Cp::pathVar('created_id')) {
            $query = DB::table('sites')
                ->where('sites.id', Cp::pathVar('created_id'))
                ->select('site_name')
                ->first();

            $message = __('kilvin::sites.site_created').':'.'&nbsp;'.'<b>'.$query->site_name.'</b>';
        } elseif(Cp::pathVar('updated_id')) {
            $query = DB::table('sites')
                ->where('sites.id', Cp::pathVar('updated_id'))
                ->select('site_name')
                ->first();

            $message = __('kilvin::sites.site_updated').':'.'&nbsp;'.'<b>'.$query->site_name.'</b>';
        }

        // ------------------------------------
        //  Basic Page Elements
        // ------------------------------------

        Cp::$title = __('kilvin::admin.site_management');
        Cp::$crumb = __('kilvin::admin.site_management');

        $right_links[] = [
            'sites-administration/site-configuration',
            __('kilvin::sites.create_new_site')
        ];

        $r = Cp::header(__('kilvin::admin.site_management'), $right_links);

        // ------------------------------------
        //  Fetch and Display Sites
        // ------------------------------------

        $query = Site::sitesData();

        $site_urls = DB::table('site_urls')
            ->orderBy('site_url')
            ->pluck('site_id', 'site_url')
            ->all();

        if ($message != '') {
            $r .= Cp::quickDiv('success-message', $message);
        }

        $r .= Cp::table('tableBorder', '0', '', '100%');

        $r .= '<tr>'.PHP_EOL.
              Cp::td('tableHeading', '50px').__('kilvin::sites.site_id').'</td>'.PHP_EOL.
              Cp::td('tableHeading').__('kilvin::sites.site_name').'</td>'.PHP_EOL.
              Cp::td('tableHeading').__('kilvin::sites.handle').'</td>'.PHP_EOL.
              Cp::td('tableHeading').__('kilvin::sites.site_urls').'</td>'.PHP_EOL.
              Cp::td('tableHeading').'</td>'.PHP_EOL.
              Cp::td('tableHeading').'</td>'.PHP_EOL.
              Cp::td('tableHeading').'</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        $i = 0;

        foreach($query as $row) {
            $row = (array) $row;

            $urls = array_filter(
                $site_urls,
                function($v, $k) use ($row) { return ($row['site_id'] == $v); },
                ARRAY_FILTER_USE_BOTH
            );

            $config_url = 'sites-administration/edit-configuration/site_id='.$row['site_id'];
            $prefs_url  = 'sites/load-site/site_id='.$row['site_id'].'/location=preferences';
            $delete_url = 'sites-administration/delete-site-confirm/site_id='.$row['site_id'];

            $r .= '<tr>'.PHP_EOL;
            $r .= Cp::tableCell('', Cp::quickSpan('default',      $row['site_id']));
            $r .= Cp::tableCell('', Cp::quickSpan('defaultBold',  $row['site_name']));
            $r .= Cp::tableCell('', Cp::quickSpan('default',      $row['site_handle']));
            $r .= Cp::tableCell('', Cp::quickSpan('default',      implode(', ', array_keys($urls))));
            $r .= Cp::tableCell('', Cp::anchor($config_url, __('kilvin::sites.site_configuration')));
            $r .= Cp::tableCell('', Cp::anchor($prefs_url, __('kilvin::sites.site_preferences')));

            // Cannot delete default site
            if ($row['site_id'] == 1) {
                $r .= Cp::tableCell('', '----');
            }

            if ($row['site_id'] != 1) {
                $r .= Cp::tableCell('', Cp::anchor($delete_url, __('kilvin::cp.delete')));
            }

            $r .= '</tr>'.PHP_EOL;
        }

        $r .= '</table>'.PHP_EOL;

        Cp::$body = $r;
    }

   /**
    * New/Edit Site Form
    *
    * @param integer $site_id The editSite method is simply this form with the site_id set.
    * @return string
    */
    public function siteConfiguration($site_id = '')
    {
        if ( ! Session::access('can_admin_sites')) {
            return Cp::unauthorizedAccess();
        }

        $values = [
            'site_id'            => '',
            'site_name'          => '',
            'site_handle'        => '',
            'site_description'   => ''
        ];

        $urls = [];

        if (!empty($site_id)) {
            $query = DB::table('sites')
                ->where('sites.id', $site_id)
                ->first();

            if (empty($query)) {
                return false;
            }

            $values = array_merge($values, (array) $query);

            $urls = DB::table('site_urls')
                ->where('site_id', $site_id)
                ->get();
        }

        $r = Cp::formOpen(
            ['action' => 'sites-administration/update-configuration'],
            ['site_id' => $site_id]
        );

        $page_title = (!empty($site_id)) ? __('kilvin::sites.edit_site') : __('kilvin::sites.create_new_site');

        $r .= Cp::table('tableBorder', '0', '', '100%');
        $r .= '<tr>'.PHP_EOL
              .Cp::td('tableHeading', '', '2').__('kilvin::sites.site_details').'</td>'.PHP_EOL
              .'</tr>'.PHP_EOL;

        // ------------------------------------
        //  Site Name
        // ------------------------------------

        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::required().Cp::quickSpan('defaultBold', __('kilvin::sites.site_name')), '40%').
              Cp::tableCell('', Cp::input_text('site_name', $values['site_name'], '20', '100', 'input', '260px'), '60%').
              '</tr>'.PHP_EOL;

        // ------------------------------------
        //  Site Handle
        // ------------------------------------

        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell(
                '',
                Cp::required().
                    Cp::quickSpan('defaultBold', __('kilvin::sites.site_handle')).
                    Cp::quickDiv('', __('kilvin::admin.single_word_no_spaces_with_underscores_hyphens')),
                '40%'
              ).
              Cp::tableCell('', Cp::input_text('site_handle', $values['site_handle'], '20', '50', 'input', '260px'), '60%').
              '</tr>'.PHP_EOL;

        // ------------------------------------
        //  Site Description
        // ------------------------------------

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell('', Cp::quickSpan('defaultBold', __('kilvin::sites.site_description')), '40%', 'top');
        $r .= Cp::tableCell('', Cp::input_textarea('site_description', $values['site_description'], '6', 'textarea', '99%'), '60%', 'top');
        $r .= '</tr>'.PHP_EOL;
        $r .= '</table>'.PHP_EOL;
        $r .= Cp::quickDiv('littlePadding', Cp::required(1));

        // ------------------------------------
        //  URLs to load this site on!
        // ------------------------------------

        $r .= '<br><div class="tableHeading">'.__('kilvin::sites.site_urls').'</div>';

        $r .= '<table class="tableBorder" cellpadding="0" cellspacing="0" style="width:100%">';

        $r .= '<tr>';
        $r .= '<th class="tableHeadingAlt" style="width: 10%;">'.__('kilvin::sites.ID').'</th>';
        $r .= '<th class="tableHeadingAlt" style="width: 45%;">'.__('kilvin::sites.Site URL').' <small>('.__('kilvin::sites.required').')</small></th>';
        $r .= '<th  class="tableHeadingAlt" style="width: 45%;">'.__('kilvin::sites.Public Path').'</th>';
        $r .= '</tr>';

        $site_placeholder        = 'https://example.com';
        $public_path_placeholder = public_path().DIRECTORY_SEPARATOR;

        foreach($urls as $d) {
            $r .= '<tr>';

            $r .= '<td>'.$d->id.'</td>';
            $r .= '<td><input type="text" style="width: 100%;" name="urls['.$d->id.'][site_url]" value="'.$d->site_url.'"></td>';
            $r .= '<td><input type="text" style="width: 100%;" name="urls['.$d->id.'][public_path]" value="'.$d->public_path.'"></td>';

            $r .= '</tr>';
        }

        $r .= '<tr>';
        $r .= '<td><em>New!</em></td>';
        $r .= '<td><input type="text" style="width: 100%;" name="urls[new][site_url]" placeholder="'.$site_placeholder.'" value=""></td>';
        $r .= '<td><input type="text" style="width: 100%;" name="urls[new][public_path]" placeholder="'.$public_path_placeholder.'" value=""></td>';
        $r .= '</tr>';

        $r .= '</table>';

        $r .= '<p>'.__('kilvin::sites.site_urls_explanation').'</p>';

        // ------------------------------------
        //  New Site?  Allow Moving/Copying of Existing Data
        // ------------------------------------

        if ($values['site_id'] == '')
        {
            $r .= Cp::table('tableBorder', '0', '', '100%');
            $r .=
                '<tr>'.PHP_EOL.
                    Cp::td('tableHeading', '', '2').__('kilvin::sites.move_data').'</td>'.PHP_EOL.
                '</tr>'.PHP_EOL.
                '<tr>'.PHP_EOL.
                    Cp::td('', '', '2').'<br>'.Cp::quickDiv('bigPad alert', __('kilvin::sites.timeout_warning')).'<br></td>'.PHP_EOL.
                '</tr>'.PHP_EOL.
                '<tr>'.PHP_EOL.
                    Cp::td('tableHeadingAlt', '', '1').__('kilvin::publish.weblogs').'</td>'.PHP_EOL.
                    Cp::td('tableHeadingAlt', '', '1').__('kilvin::sites.move_options').'</td>'.PHP_EOL.
                '</tr>'.PHP_EOL;

            // ------------------------------------
            //  Weblogs
            // ------------------------------------

            $query = DB::table('weblogs')
                ->orderBy('weblog_name')
                ->select('weblog_name', 'weblogs.id AS weblog_id')
                ->get();

            $i = 0;

            foreach($query as $row)
            {
                $row = (array) $row;

                $r .=  '<tr>'.PHP_EOL.
                    Cp::tableCell('', $row['weblog_name']).
                    Cp::tableCell(
                        '',
                        Cp::input_select_header('weblog_'.$row['weblog_id']).
                            Cp::input_select_option('nothing', __('kilvin::sites.do_nothing')).
                            Cp::input_select_option('move', __('kilvin::sites.move_weblog_move_data')).
                            Cp::input_select_option('duplicate', __('kilvin::sites.duplicate_weblog_no_data')).
                            Cp::input_select_option('duplicate_all', __('kilvin::sites.duplicate_weblog_all_data')).
                        Cp::input_select_footer()
                    ).
                    '</tr>'.PHP_EOL;
            }

            // ------------------------------------
            //  Upload Directories
            // ------------------------------------

            $r .=  '<tr>'.PHP_EOL
                  .     Cp::td('tableHeadingAlt', '', '1').__('kilvin::admin.file-upload-preferences').'</td>'.PHP_EOL
                  .     Cp::td('tableHeadingAlt', '', '1').__('kilvin::sites.move_options').'</td>'.PHP_EOL
                  .'</tr>'.PHP_EOL;

            $query = DB::table('asset_containers')
                ->orderBy('asset_containers.name')
                ->select('name', 'id')
                ->get();

            $i = 0;

            foreach($query as $row) {
                $row = (array) $row;

                $r .=  '<tr>'.PHP_EOL.
                    Cp::tableCell('', $row['name']).
                    Cp::tableCell(
                        '',
                        Cp::input_select_header('upload_'.$row['id']).
                            Cp::input_select_option('nothing', __('kilvin::sites.do_nothing')).
                            Cp::input_select_option('move', __('kilvin::sites.move_upload_destination')).
                            Cp::input_select_option('duplicate', __('kilvin::sites.duplicate_upload_destination')).
                        Cp::input_select_footer()
                    ).
                    '</tr>'.PHP_EOL;
            }

            // ------------------------------------
            //  Move/Copy Templates
            // ------------------------------------

            $r .=  '<tr>'.PHP_EOL
                  .     Cp::td('tableHeadingAlt', '', '1').__('kilvin::cp.templates').'</td>'.PHP_EOL
                  .     Cp::td('tableHeadingAlt', '', '1').__('kilvin::sites.move_options').'</td>'.PHP_EOL
                  .'</tr>'.PHP_EOL;

            $sites = Site::sitesList();

            $i = 0;

            foreach($sites as $row)
            {
                $row = (array) $row;

                $r .=
                    '<tr>'.PHP_EOL.
                    Cp::tableCell('', $row['site_name']).
                    Cp::tableCell(
                        '',
                        Cp::input_select_header(
                            'templates_site_'.base64_encode($row['site_id'])
                        ).
                            Cp::input_select_option('nothing', __('kilvin::sites.do_nothing')).
                            Cp::input_select_option('move', __('kilvin::sites.move_all_templates')).
                            Cp::input_select_option('copy', __('kilvin::sites.copy_all_templates')).
                        Cp::input_select_footer()).
                    '</tr>'.PHP_EOL;
            }

            // ------------------------------------
            //  Template Variables
            // ------------------------------------

            $r .=  '<tr>'.PHP_EOL
                  .     Cp::td('tableHeadingAlt', '', '1').__('kilvin::templates.template_variables').'</td>'.PHP_EOL
                  .     Cp::td('tableHeadingAlt', '', '1').__('kilvin::sites.move_options').'</td>'.PHP_EOL
                  .'</tr>'.PHP_EOL;

            $i = 0;

            foreach(Site::sitesList() as $row)
            {
                $row = (array) $row;

                $r .=  '<tr>'.PHP_EOL.
                    Cp::tableCell('', $row['site_name'].'&nbsp;'.'-'.'&nbsp;'.__('kilvin::sites.template_variables')).
                    Cp::tableCell(
                        '',
                        Cp::input_select_header('template_variables_'.$row['site_id']).
                            Cp::input_select_option('nothing', __('kilvin::sites.do_nothing')).
                            Cp::input_select_option('move', __('kilvin::sites.move_template_variables')).
                            Cp::input_select_option('duplicate', __('kilvin::sites.duplicate_template_variables')).
                        Cp::input_select_footer()
                    ).
                    '</tr>'.PHP_EOL;
            }

            $r .= '</table>'.PHP_EOL.'<br>';
        }

        // ------------------------------------
        //  Submit + Form Close
        // ------------------------------------

        $r .= '<br>'.Cp::quickDiv('', Cp::input_submit(__('kilvin::cp.submit')));

        $r .= '</form>'.PHP_EOL;

        // ------------------------------------
        //  Output page details to Display class
        // ------------------------------------

        Cp::$title = (empty($site_id)) ? __('kilvin::sites.create_new_site') : __('kilvin::sites.edit_site');
        Cp::$crumb = (empty($site_id)) ? __('kilvin::sites.create_new_site') : __('kilvin::sites.edit_site');
        Cp::$body  = $r;
    }
   /**
    * Displays Edit Site Form
    * - Simply calls siteConfiguration() with $site_id variable
    *
    * @return string
    */
    function editConfiguration()
    {
        if (Cp::pathVar('site_id') === null or ! is_numeric(Cp::pathVar('site_id'))) {
            return false;
        }

        return $this->siteConfiguration(Cp::pathVar('site_id'));
    }

   /**
    * Update/Create Site Configuration
    * - Updating a site is mostly just a DB update and things like templates directory change
    * - New Site allows copy/moving of content + templates
    *
    * @return  void
    */
    public function updateConfiguration()
    {
        if ( ! Session::access('can_admin_sites')) {
            return Cp::unauthorizedAccess();
        }

        if (!request()->filled('site_handle')) {
            return $this->siteConfiguration();
        }

        // If the $site_id variable is present we are editing
        $edit = (request()->has('site_id') && request()->get('site_id', null));

        // Validate Site Exists
        if ($edit === true) {
            $existing = DB::table('sites')
                ->where('sites.id', request()->get('site_id'))
                ->first();

            if(!$existing) {
                return $this->siteConfiguration();
            }
        }

        // ------------------------------------
        //  Validation - Details
        // ------------------------------------

        $validator = Validator::make(request()->all(), [
            'site_handle' => 'required|regex:#^[a-zA-Z0-9_\-/\s]+$#i',
            'site_name'   => 'required',
        ], __('kilvin::validation.custom'));

        if ($validator->fails()) {
            return Cp::errorMessage(implode('<br>', $validator->errors()->all()));
        }

        // Short Name Taken Already?
        $query = DB::table('sites')
            ->where('site_handle', Request::input('site_handle'));

        if ($edit === true) {
            $query->where('sites.id', '!=', Request::input('site_id'));
        }

        if ($query->count() > 0) {
            return Cp::errorMessage(__('kilvin::sites.site_handle_taken'));
        }

        // ------------------------------------
        //  Validation - Domains
        // ------------------------------------

        if (Request::filled('urls') && is_array(Request::input('urls'))) {
            $validator = Validator::make(Request::all(), [
                'urls.*.site_url'    => 'nullable|url',
            ], array_flatten(__('kilvin::validation.custom')));

            if ($validator->fails()) {
                return Cp::errorMessage(implode('<br>', $validator->errors()->all()));
            }
        }

        // ------------------------------------
        //  Create/Update Site
        // ------------------------------------

        $data = [
            'site_handle'        => Request::input('site_handle'),
            'site_name'          => Request::input('site_name'),
            'site_description'   => Request::input('site_description')
        ];

        if ($edit == false) {
            $insert_id = $site_id = DB::table('sites')->insertGetId($data);

            $success_msg = __('kilvin::sites.site_created');
        }


        if ($edit == true) {
            DB::table('sites')
                ->where('sites.id', Request::input('site_id'))
                ->update($data);

            $site_id = Request::input('site_id');

            $success_msg = __('kilvin::sites.site_updated');
        }

        // ------------------------------------
        //  Create/Update Domains
        // ------------------------------------

        if (Request::filled('urls') && is_array(Request::input('urls'))) {
            foreach(Request::input('urls') as $key => $values) {

                if (!empty($values['public_path'])) {
                    $values['public_path'] = rtrim($values['public_path'], DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
                }

                if ($key != 'new' && is_numeric($key)) {
                    if (empty($values['site_url'])) {
                        DB::table('site_urls')
                            ->where('id', $key)
                            ->delete();
                    } else {
                        DB::table('site_urls')
                            ->where('id', $key)
                            ->update($values);
                    }
                }

                if ($key == 'new' && !empty($values['site_url'])) {
                    $values['site_id'] = $site_id;

                    DB::table('site_urls')->insert($values);
                }
            }
        }

        // ------------------------------------
        //  Log Update
        // ------------------------------------

        Cp::log($success_msg.'&nbsp;'.$data['site_name']);

        // ------------------------------------
        //  Rename or Create Site Templates Folder for Site
        // ------------------------------------

        if ($edit === true && $data['site_handle'] != $existing->site_handle) {
            Storage::disk('templates')->rename($existing->site_handle, $data['site_handle']);
        }

        if ($edit === false) {
            Storage::disk('templates')->makeDirectory($data['site_handle']);
        }

        // ------------------------------------
        //  Site Specific Stats Created
        // ------------------------------------

        if ($edit === false) {
            $query = DB::table('stats')->where('site_id', 1)->get();

            foreach ($query as $row) {
                $data = (array) $row;

                $data['id'] = null;
                $data['site_id'] = $site_id;
                $data['last_entry_date'] = null;
                $data['last_cache_clear'] = null;

                DB::table('stats')->insert($data);
            }
        }

        // ------------------------------------
        //  Prefs Creation
        // ------------------------------------

        if ($edit === false) {

            $update_prefs = [];

            foreach(Site::preferenceKeys() as $value) {
                $update_prefs[$value] = Site::config($value);
            }

            if (!empty($update_prefs)) {
                foreach($update_prefs as $handle => $value) {
                    DB::table('site_preferences')
                        ->insert(
                            [
                                'site_id' => $site_id,
                                'handle'  => $handle,
                                'value'   => $value
                            ]);
                }
            }
        }

        // ------------------------------------
        //  Sites DB table updated, so clear cache!
        // ------------------------------------

        Site::flushSiteCache();

        // ------------------------------------
        //  Moving of Data?
        // ------------------------------------

        if ($edit === false) {
            $this->movingSiteData($site_id, request()->all());
        }

        // ------------------------------------
        //  Refreshes Site Specific Preference for User
        // ------------------------------------

        Session::fetchMemberData();

        // ------------------------------------
        //  Update site stats
        // ------------------------------------

        $original_site_id = Site::config('site_id');
        Site::setConfig('site_id', $site_id);

        Stats::update_member_stats();
        Stats::update_weblog_stats();

        Site::setConfig('site_id', $original_site_id);

        // ------------------------------------
        //  View Sites List
        // ------------------------------------

        if ($edit === true) {
            return redirect(kilvinCpUrl('sites-administration/updated_id='.$site_id));
        } else {
            return redirect(kilvinCpUrl('sites-administration/created_id='.$site_id));
        }
    }

   /**
    * Delete Site Confirmation Form
    *
    * @return  void
    */
    public function deleteSiteConfirm()
    {
        if ( ! $site_id = Cp::pathVar('site_id')) {
            return false;
        }

        if ( ! Session::access('can_admin_sites') OR $site_id == 1) {
            return Cp::unauthorizedAccess();
        }

        $site_name = DB::table('sites')
            ->where('sites.id', $site_id)
            ->value('site_name');

        if (empty($site_name)) {
            return $this->sitesList();
        }

        Cp::$title = __('kilvin::sites.delete_site');
        Cp::$crumb = Cp::breadcrumbItem(__('kilvin::sites.delete_site'));

        Cp::$body = Cp::deleteConfirmation(
            [
                'url'       => 'sites-administration/delete-site/site_id='.$site_id,
                'heading'   => 'sites.delete_site',
                'message'   => 'sites.delete_site_confirmation',
                'item'      => $site_name,
                'extra'     => '',
                'hidden'    => ['site_id' => $site_id]
            ]
        );
    }

   /**
    * Delete the Site!  KABLOOEY!!
    *
    * @return  void
    */
    public function deleteSite()
    {
        if ( ! Session::access('can_admin_sites')) {
            return Cp::unauthorizedAccess();
        }

        if ( ! $site_id = Request::input('site_id') or ! is_numeric($site_id)) {
            return false;
        }

        if ($site_id == 1) {
            return Cp::unauthorizedAccess();
        }

        $site_name = DB::table('sites')
            ->where('sites.id', $site_id)
            ->value('site_name');

        if (empty($site_name)) {
            return $this->listSites();
        }

        // @todo - Remove Templates Folder for Site

        // ------------------------------------
        //  Delete Entry Rows
        // ------------------------------------

        $entry_ids = DB::table('weblog_entries')
        	->select('id as entry_id')
            ->where('site_id', $site_id)
            ->pluck('entry_id')
            ->all();

        if (count($entry_ids) > 0) {
            DB::table('weblog_entry_categories')
                ->whereIn('weblog_entry_id', $entry_ids)
                ->delete();

            DB::table('weblog_entry_data')
                ->whereIn('weblog_entry_id', $entry_ids)
                ->delete();
        }

        // ------------------------------------
        //  Delete Weblog Custom Field Columns for Site
        // ------------------------------------

        $fields = DB::table('weblog_field_groups')
            ->join('weblog_fields', 'weblog_field_groups.id', '=', 'weblog_fields.weblog_field_group_id')
            ->where('weblog_field_groups.site_id', $site_id)
            ->pluck('field_handle')
            ->all();

        foreach($fields as $field_handle) {
            Schema::table('weblog_entry_data', function($table) use ($field_handle)
            {
                $table->dropColumn('field_'.$field_handle);
            });
        }

        // ------------------------------------
        //  Delete Upload Permissions for Site
        // ------------------------------------

        $upload_ids = DB::table('asset_containers')
            ->where('site_id', $site_id)
            ->pluck('id')
            ->all();

        if (!empty($upload_ids)) {
            DB::table('asset_container_access')
                ->whereIn('asset_container_id', $upload_ids)
                ->delete();
        }

        // ------------------------------------
        //  Delete Every DB Row Having to Do with the Site
        // ------------------------------------

        $tables = [
            'weblog_field_groups' => [
                'weblog_fields' => 'weblog_field_group_id',
            ],
            'category_groups' => [
                'categories' => 'category_group_id',
            ],
            'member_groups' => [
                'members' => 'member_group_id',
                'member_group_preferences' => 'member_group_id'
            ],
            'status_groups' => [
                'statuses' => 'status_group_id',
            ],
        ];

        foreach($tables as $table => $subtables) {
            foreach ($subtables as $subtable => $field) {
                $ids = DB::table($table)
                    ->where('site_id', $site_id)
                    ->pluck('id')
                    ->toArray();

                if (!empty($ids)) {
                    DB::table($subtable)->whereIn($field, $ids)->delete();
                    DB::table($table)->whereIn('id', $ids)->delete();
                }
            }
        }

        $tables = [
            'cp_log',
            'weblog_field_groups',
            'template_variables',
            'stats',
            'templates',
            'asset_containers',
            'weblogs',
            'weblog_entries',
            'site_urls',
        ];

        foreach($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where('site_id', $site_id)->delete();
            }
        }

		// All gone!
		DB::table('sites')->where('sites.id', $site_id)->delete();

        // ------------------------------------
        //  Log it!
        // ------------------------------------

        Cp::log(__('kilvin::sites.site_deleted').': '.$site_name);

        // ------------------------------------
        //  Refreshes Site Specific Member Group Preferences for us
        // ------------------------------------

        Session::fetchMemberData();

        // ------------------------------------
        //  Reload to Site Admin
        // ------------------------------------

        return redirect(kilvinCpUrl('sites-administration'));
    }

   /**
    * Create, copy, or move other sites' data
    *
    * @param integer $site_id The new site's site_id
    * @param array $input
    * @return  void
    */
    public function movingSiteData($site_id, $input)
    {

        $weblog_ids         = [];
        $moved              = [];
        $entries            = [];

        foreach($input as $key => $value)
        {
            // ------------------------------------
            //  Weblogs Moving
            // ------------------------------------

            if (substr($key, 0, strlen('weblog_')) == 'weblog_' && $value != 'nothing' && is_numeric(substr($key, strlen('weblog_'))))
            {
                $old_weblog_id = substr($key, strlen('weblog_'));

                // SO SIMPLE!
                if ($value == 'move')
                {
                    $moved[$old_weblog_id] = '';

                    DB::table('weblogs')
                        ->where('id', $old_weblog_id)
                        ->update(['site_id' => $site_id]);

                    DB::table('weblog_entries')
                        ->where('weblog_id', $old_weblog_id)
                        ->update(['site_id' => $site_id]);

                    DB::table('weblog_entry_data')
                        ->where('weblog_id', $old_weblog_id)
                        ->update(['site_id' => $site_id]);

                    $weblog_ids[$old_weblog_id] = $old_weblog_id; // Stats, Groups, For Later
                }



                if($value == 'duplicate' OR $value == 'duplicate_all')
                {
                    $query = DB::table('weblogs')
                        ->where('id', $old_weblog_id)
                        ->first();

                    if (!$query) {
                        continue;
                    }

                    $query = (array) $query;

                    // Uniqueness checks
                    foreach(['weblog_handle', 'weblog_name'] AS $check) {
                        $count = DB::table('weblogs')
                            ->where('site_id', $site_id)
                            ->where($check, 'LIKE', $query[$check].'%')
                            ->count();

                        if ($count > 0) {
                            $query[$check] = $query[$check].'-'.($count + 1);
                        }
                    }

                    $query['site_id']   = $site_id;
                    $query['id'] = null;

                    // No entries copied over, so set to 0
                    if ($value == 'duplicate') {
                        $query['total_entries']       = 0;
                        $query['last_entry_date']     = null;
                    }

                    $new_weblog_id = DB::table('weblogs')->insertGetId($query);
                    $weblog_ids[$old_weblog_id] = $new_weblog_id;

                    // ------------------------------------
                    // Duplicating Entries Too
                    //  - Duplicates Entries + Data + Comments
                    //  - Pages are NOT duplicated
                    // ------------------------------------

                    if ($value == 'duplicate_all')
                    {
                        $moved[$old_weblog_id] = '';

                        // ------------------------------------
                        //  Entries
                        // ------------------------------------

                        $query = DB::table('weblog_entries')
                            ->where('weblog_id', $old_weblog_id)
                            ->get()
                            ->toArray();

                        $entries[$old_weblog_id] = [];

                        foreach($query as $row)
                        {
                            $old_entry_id       = $row['id'];
                            unset($row['id']); // Null so new 'id' on INSERT

                            $row['site_id']     = $site_id;
                            $row['weblog_id']   = $weblog_ids[$old_weblog_id];

                            $new_entry_id = DB::table('weblog_entries')->insertGetId($row);

                            $entries[$old_weblog_id][$old_entry_id] = $new_entry_id;
                        }

                        // ------------------------------------
                        //  Entry Data
                        // ------------------------------------

                        $query = DB::table('weblog_entry_data')
                            ->where('weblog_id', $old_weblog_id)
                            ->get()
                            ->toArray();

                        foreach($query as $row)
                        {
                            $row['site_id']     = $site_id;
                            $row['entry_id']    = $entries[$old_weblog_id][$row['entry_id']];
                            $row['weblog_id']   = $weblog_ids[$old_weblog_id];

                            DB::table('weblog_entry_data')->insert($row);
                        }

                        // ------------------------------------
                        //  Category Posts
                        // ------------------------------------

                        $query = DB::table('weblog_entry_categories')
                            ->whereIn('weblog_entry_id', array_flip($entries[$old_weblog_id]))
                            ->get()
                            ->toArray();

                        foreach($query as $row) {
                            $row['weblog_entry_id'] = $entries[$old_weblog_id][$row['weblog_entry_id']];

                            DB::table('weblog_entry_categories')->insert($row);
                        }
                    }
                }
            }

            // ------------------------------------
            //  Upload Directory Moving
            // ------------------------------------

            if (substr($key, 0, strlen('upload_')) == 'upload_' && $value != 'nothing' && is_numeric(substr($key, strlen('upload_'))))
            {
                $upload_id = substr($key, strlen('upload_'));

                if ($value == 'move') {
                    DB::table('asset_containers')
                        ->where('id', $upload_id)
                        ->update(['site_id' => $site_id]);
                } else {
                    $query = (array) DB::table('asset_containers')
                        ->where('id', $upload_id)
                        ->first();

                    if (empty($query)) {
                        continue;
                    }

                    // Uniqueness checks
                    foreach(['name'] AS $check) {
                        $count = DB::table('asset_containers')
                            ->where('site_id', $site_id)
                            ->where($check, 'LIKE', $query[$check].'%')
                            ->count();

                        if ($count > 0) {
                            $count++;
                            $query[$check] = $query[$check].'-'.$count;
                        }
                    }

                    $query['site_id'] = $site_id;
                    $query['id'] = null;

                    $new_upload_id = DB::table('asset_containers')->insertGetId($query);

                    $allowed_query = DB::table('asset_container_access')
                        ->where('asset_container_id', $upload_id)
                        ->get()
                        ->toArray();

                    foreach($allowed_query as $row) {
                        DB::table('asset_container_access')
                            ->insert(
                                [
                                    'asset_container_id' => $new_upload_id,
                                    'member_group_id' => $row['member_group_id']
                                ]);
                    }
                }
            }

            // ------------------------------------
            //  Global Template Variables
            // ------------------------------------

            if (substr($key, 0, strlen('template_variables_')) == 'template_variables_' &&
                $value != 'nothing' &&
                is_numeric(substr($key, strlen('template_variables_'))))
            {
                $move_site_id = substr($key, strlen('template_variables_'));

                if ($value == 'move')
                {
                    DB::table('template_variables')
                        ->where('site_id', $move_site_id)
                        ->update(['site_id' => $site_id]);
                }
                else
                {
                    $query = DB::table('template_variables')
                        ->where('site_id', $move_site_id)
                        ->get()
                        ->toArray();

                    if (empty($query)) {
                        continue;
                    }

                    foreach($query as $row) {
                        // Uniqueness checks
                        foreach(['variable_name'] AS $check)
                        {
                            $count = DB::table('template_variables')
                                ->where('site_id', $site_id)
                                ->where($check, 'LIKE', $row[$check].'%')
                                ->count();

                            if ($count > 0)
                            {
                                $count++;
                                $row[$check] = $row[$check].'-'.$count;
                            }
                        }

                        $row['site_id']     = $site_id;
                        unset($row['id']);

                        DB::table('template_variables')->insert($row);
                    }
                }
            }

            // ------------------------------------
            //  Template Moving
            // ------------------------------------

            // @todo - Need to move the files over instead
            // Will either need to do uniqueness checks OR only allow one site's templates to be moved over
            // that would require changing the form a bit.  Yeah, let's do that...

            if (substr($key, 0, strlen('folder_')) == 'folder_' && $value != 'nothing')
            {

            }
        }

        // ------------------------------------
        //  Additional Weblog Moving Work - Stats/Groups
        // ------------------------------------

        if (sizeof($weblog_ids) > 0)
        {
            $status           = [];
            $fields           = [];
            $categories       = [];
            $category_groups  = [];
            $field_match      = [];
            $cat_field_match  = [];

            foreach($weblog_ids as $old_weblog => $new_weblog)
            {
                $query = DB::table('weblogs')
                    ->where('id', $new_weblog)
                    ->select('category_group_id', 'status_group_id', 'weblog_field_group_id')
                    ->first();

                // ------------------------------------
                //  Duplicate Status Group
                // ------------------------------------

                if (!empty($query->status_group_id)) {
                    if (!isset($status[$query->status_group_id])) {
                        $group_name = DB::table('status_groups')
                            ->where('id', $query->status_group_id)
                            ->select('group_name')
                            ->value('group_name');

                        // Uniqueness checks
                        foreach(['group_name'] AS $check)
                        {
                            $count = DB::table('status_groups')
                                ->where('site_id', $site_id)
                                ->where($check, 'LIKE', $group_name.'%')
                                ->count();

                            if ($count > 0) {
                                $count++;
                                $group_name .= '-'.$count;
                            }
                        }

                        $new_group_id = DB::table('status_groups')
                            ->insertGetId([
                                'site_id'       => $site_id,
                                'group_name'    => $group_name
                            ]);

                        $squery = DB::table('statuses')
                            ->where('status_group_id', $query->status_group_id)
                            ->get();

                        foreach($squery as $row) {
                            $row                = (array) $row;
                            $row['site_id']     = $site_id;
                            $row['id']   = null;
                            $row['status_group_id'] = $new_group_id;

                            DB::table('statuses')->insert($row);
                        }

                        // Prevent Duplication
                        $status[$query->status_group_id] = $new_group_id;
                    }

                    // ------------------------------------
                    //  Update Weblog With New Group ID
                    // ------------------------------------

                    DB::table('weblogs')
                        ->where('id', $new_weblog)
                        ->update(['status_group_id' => $status[$query->status_group_id]]);
                }


                // ------------------------------------
                //  Duplicate Field Group
                // ------------------------------------

                if ( ! empty($query->weblog_field_group_id)) {
                    if ( ! isset($fields[$query->weblog_field_group_id])) {
                        $group_name = DB::table('weblog_field_groups')
                            ->where('id', $query->weblog_field_group_id)
                            ->value('group_name');

                        // Uniqueness checks
                        $count = DB::table('weblog_field_groups')
                            ->where('site_id', $site_id)
                            ->where($check, 'LIKE', $group_name.'%')
                            ->count();

                        if ($count > 0) {
                            $count++;
                            $group_name .= '-'.$count;
                        }

                        $new_group_id = DB::table('weblog_field_groups')
                            ->insert([
                                'site_id'    => $site_id,
                                'group_name' => $group_name
                            ]);


                        // ------------------------------------
                        //  New Fields Created for New Field Group
                        // ------------------------------------

                        $fquery = DB::table('weblog_fields')
                            ->where('weblog_field_group_id', $query->weblog_field_group_id)
                            ->get();

                        foreach($fquery as $row) {
                            $row                = (array) $row;
                            $old_field_handle   = $row['field_handle'];

                            $row['site_id']     = $site_id;
                            $row['id']          = null;
                            $row['weblog_field_group_id'] = $new_group_id;

                            // Uniqueness checks
                            foreach(['field_name', 'field_handle'] AS $check) {
                                $count = DB::table('weblog_fields')
                                    ->where('site_id', $site_id)
                                    ->where($check, 'LIKE', $row[$check].'%')
                                    ->count();

                                if ($count > 0) {
                                    $count++;
                                    $row[$check] .= '-'.$count;
                                }
                            }

                            $new_field_id = DB::table('weblog_fields')->insert($row);

                            $field_handle = $row['field_handle'];
                            $field_match[$old_field_handle] = $row['field_handle'];

                            // ------------------------------------
                            //  Weblog Data Field Creation, Whee!
                            // ------------------------------------

                            switch($row['field_type'])
                            {
                                case 'date' :
                                    Schema::table('weblog_entry_data', function($table) use ($field_handle) {
                                        $table->timestamp('field_'.$field_handle)->nullable(true);
                                    });
                                break;
                                default:
                                    Schema::table('weblog_entry_data', function($table) use ($field_handle) {
                                        $table->text('field_'.$field_handle)->nullable(true);
                                    });
                                break;
                            }
                        }

                        // Prevents duplication of field group creation
                        $fields[$query->weblog_field_group_id] = $new_group_id;
                    }

                    // ------------------------------------
                    //  Update New Weblog With New Group ID
                    // ------------------------------------

                    DB::table('weblogs')
                        ->where('id', $new_weblog)
                        ->update(['weblog_field_group_id' => $fields[$query->weblog_field_group_id]]);

                    // ------------------------------------
                    //  Moved Weblog?  Need Old Field Group
                    // ------------------------------------

                    if (isset($moved[$old_weblog])) {
                        $moved[$old_weblog] = $query->weblog_field_group_id;
                    }
                }

                // ------------------------------------
                //  Duplicate Category Group(s)
                // ------------------------------------

                $new_category_group_id = '';

                if (!empty($query->category_group_id))
                {
                    $new_insert_group = [];

                    foreach(explode('|', $query->category_group_id) as $category_group_id)
                    {
                        if (isset($category_groups[$category_group_id])) {
                            $new_insert_group[] = $category_groups[$category_group_id];

                            continue;
                        }

                        $gquery = (array) DB::table('category_groups')
                            ->where('id', $category_group_id)
                            ->first();

                        if (empty($gquery)) {
                            continue;
                        }

                        // Uniqueness checks
                        $count = DB::table('category_groups')
                            ->where('site_id', $site_id)
                            ->where('group_name', $gquery['group_name'])
                            ->count();

                        if ($count > 0) {
                            $count++;
                            $gquery['group_name'] .= '-'.$count;
                        }

                        $gquery['site_id']  = $site_id;
                        $gquery['id'] = null;

                        $new_group_id = DB::table('category_groups')->insertGetId($gquery);

                        $category_groups[$category_group_id] = $new_group_id;
                        $new_insert_group[] = $new_group_id;

                        // ------------------------------------
                        //  New Categories Created for New Category Group
                        // ------------------------------------

                        $cquery = DB::table('categories')
                            ->where('category_group_id', $category_group_id)
                            ->orderBy('parent_id') // Important, insures we get parents in first
                            ->get()
                            ->toArray();

                        foreach($cquery as $row)
                        {
                            // Uniqueness checks
                            foreach(['category_url_title'] AS $check) {
                                $count = DB::table('categories')
                                    ->where('site_id', $site_id)
                                    ->where($check, 'LIKE', $row[$check].'%')
                                    ->count();

                                if ($count > 0) {
                                    $count++;
                                    $row[$check] .= '-'.$count;
                                }
                            }

                            $old_cat_id = $row['id'];

                            $row['site_id'] = $site_id;
                            $row['id'] = null;
                            $row['category_group_id'] = $new_group_id;
                            $row['parent_id']   =
                                ($row['parent_id'] == '0' OR ! isset($categories[$row['parent_id']])) ?
                                0 :
                                $categories[$row['parent_id']];

                            $categories[$old_cat_id] = DB::table('categories')->insertGetId($row);
                        }
                    }

                    $new_category_group_id = implode('|', $new_insert_group);
                }

                // ------------------------------------
                //  Update Weblog With New Group ID
                // ------------------------------------

                DB::table('weblogs')
                    ->where('id', $weblog_id)
                    ->update(['category_group_id' => $new_category_group_id]);
            }


            // ------------------------------------
            //  Move Data Over For Moved Weblogs/Entries
            //  - Find Old Fields from Old Site Field Group, Move Data to New Fields, Zero Old Fields
            //  - Reassign Categories for New Weblogs Based On $categories array
            // ------------------------------------

            if (sizeof($moved) > 0) {
                // ------------------------------------
                //  Moving Field Data for Moved Entries
                // ------------------------------------

                foreach($moved as $weblog_id => $weblog_field_group_id) {
                    $query = DB::table('weblog_fields')
                        ->select('id AS field_id', 'field_name', 'field_handle', 'field_type')
                        ->where('weblog_field_group_id', $weblog_field_group_id)
                        ->get()
                        ->toArray();

                    if (isset($entries[$weblog_id])) {
                        $weblog_id = $weblog_ids[$weblog_id]; // Moved Entries, New Weblog ID Used
                    }

                    if ($query->count() > 0) {
                        foreach($query as $row) {
                            if ( ! isset($field_match[$row['field_handle']])) {
                                continue;
                            }

                            // Move data over
                            DB::table('weblog_entry_data')
                                ->where('weblog_id', $weblog_id)
                                ->update(
                                    [
                                        DB::raw(
                                            "`field_".$field_match[$row['field_handle']]."`".
                                            " = ".
                                            "`field_".$row['field_handle']."`"
                                        )
                                    ]
                                );

                            // Clear old data
                            DB::table('weblog_entry_data')
                                ->where('weblog_id', $weblog_id)
                                ->update(
                                    [
                                        'field_'.$row['field_handle'] = ''
                                    ]
                                );

                        }
                    }

                    // ------------------------------------
                    //  Category Reassignment
                    // ------------------------------------

                    $query = DB::table('weblog_entry_categories')
                        ->join('weblog_entries', 'weblog_entries.id', '=', 'weblog_entry_categories.weblog_entry_id')
                        ->select('weblog_entry_categories.weblog_entry_id')
                        ->get();

                    $entry_ids = [];

                    foreach($query as $row) {
                        $entry_ids[] = $row->entry_id;
                    }

                    foreach($categories as $old_cat => $new_cat)
                    {
                        DB::table('weblog_entry_categories')
                            ->whereIn('weblog_entry_id', $entry_ids)
                            ->where('category_id', $old_cat)
                            ->update(
                                [
                                    'category_id' => $new_cat
                                ]
                            );
                    }
                }
            }
        }
    }
}
