<?php

namespace Kilvin\Cp;

use Kilvin\Facades\Cp;
use Illuminate\Support\Facades\DB;
use Kilvin\Facades\Site;
use Kilvin\Facades\Stats;
use Kilvin\Facades\Plugins;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;
use Kilvin\Core\Regex;
use Kilvin\Core\Session;
use Kilvin\Core\Localize;
use Kilvin\Models\Member;
use Kilvin\Core\JsCalendar;
use Kilvin\Notifications\NewEntryAdminNotify;
use Illuminate\Http\Response;

class Content
{
    public $assign_cat_parent   = true;
    public $categories          = [];
    public $cat_parents         = [];
    public $nest_categories     = 'y';
    public $cat_array           = [];

    public $url_title_error      = false;

   /**
    * Request Handler
    *
    * @return mixed
    */
    public function run()
    {
        $allowed = [
            'submit-new-entry',
            'entry-form',
            'edit-entry',
            'list-entries',
            'entries-edit-form',
            'update-multiple-entries',
            'entries-category-update',
            'delete-entries-confirm',
            'delete-entries',
            'upload-file-form',
            'upload-file',
            'file-browser'
        ];

        if (in_array(Cp::segment(2), $allowed)) {
            $method = camel_case(Cp::segment(2));
            return $this->{$method}();
        }

        if (Cp::segment(1) == 'content') {
			return redirect(kilvin_cp_url('content/list-entries'));
        }

        abort(404);
    }

   /**
    * List of Available Weblogs for Publishing
    *
    * @return string
    */
    public function weblogSelectList()
    {
        $links = [];

        foreach (Session::userdata('assigned_weblogs') as $weblog_id => $weblog_name) {
            $links[] = Cp::tableQuickRow(
                '',
                Cp::quickDiv(
                    'defaultBold',
                    Cp::anchor(
                        'content/entry-form'.
                            AMP.'weblog_id='.$weblog_id,
                        $weblog_name
                    )
                )
            );
        }

        // If there are no allowed blogs, show a message
        if (empty($links)){
            return Cp::unauthorizedAccess(__('kilvin::publish.unauthorized_for_any_blogs'));
        }

        Cp::$body .= Cp::table('tableBorder', '0', '', '100%').
            Cp::tableQuickRow('tableHeading', __('kilvin::publish.select_blog_to_post_in'));

        foreach ($links as $val) {
            Cp::$body .= $val;
        }

        Cp::$body .= '</table>'.PHP_EOL;

        Cp::$title = __('kilvin::publish.publish');
        Cp::$crumb =  __('kilvin::publish.publish');
    }

   /**
    * Edit Entry Form
    *
    * @return string
    */
    public function editEntry($submission_error = null)
    {
        return $this->entryForm($submission_error);
    }

   /**
    * Create/Edit Entry Form
    *
    * @param string
    * @return string
    */
    public function entryForm($submission_error = null)
    {
        $title        = '';
        $status       = '';
        $sticky       = '';
        $author_id    = '';
        $version_id   = Cp::pathVar('version_id');
        $weblog_id    = Cp::pathVar('weblog_id');
        $entry_id     = Cp::pathVar('entry_id');
        $which        = 'new';
        $entry_data   = []; // Data from database, if any
        $request_data = (is_null($submission_error)) ? [] : Request::all(); // Data from submission, if any
        $revision     = false;
        $incoming     = $request_data;

        unset($request_data['_token']);

        // ------------------------------------
        //  Fetch Revision if Necessary
        // ------------------------------------

        if (is_numeric($version_id)) {
            $entry_id = Request::input('entry_id') ?? Cp::pathVar('entry_id');

            $revquery = DB::table('entry_versioning')
                ->select('version_data')
                ->where('weblog_entry_id', $entry_id)
                ->where('id', $version_id)
                ->first();

            if ($revquery) {
                $entry_data = unserialize($revquery->version_data);
                $incoming = array_merge($entry_data, $incoming);
                $incoming['entry_id'] = $entry_id;
                $which = 'edit';
                $revision = true;
            }

            unset($revquery);
        }

        // ------------------------------------
        //  We need to first determine which weblog to post the entry into.
        // ------------------------------------

        $assigned_weblogs = array_keys(Session::userdata('assigned_weblogs'));

        // if it's an edit, we just need the entry id and can figure out the rest
        if (!empty($entry_id)) {
            $query = DB::table('weblog_entries')
                ->select('weblog_id', 'id AS entry_id')
                ->where('id', $entry_id)
                ->first();

            if ($query) {
                $weblog_id = $query->weblog_id;
                $which = 'edit';
            }
        }

        if (empty($weblog_id)) {
            if (is_numeric(Request::input('weblog_id'))) {
                $weblog_id = Request::input('weblog_id');
            } elseif (sizeof($assigned_weblogs) == 1) {
                $weblog_id = $assigned_weblogs[0];
            }
        }

        if ( empty($weblog_id) or ! is_numeric($weblog_id)) {
            return false;
        }

        // ------------------------------------
        //  Security check
        // ------------------------------------

        if ( ! in_array($weblog_id, $assigned_weblogs)) {
            return Cp::unauthorizedAccess(__('kilvin::publish.unauthorized_for_this_blog'));
        }

        // ------------------------------------
        //  Fetch weblog preferences
        // ------------------------------------

        $query = DB::table('weblogs')->where('id', $weblog_id)->first();

        if (!$query) {
            return Cp::errorMessage(__('kilvin::publish.no_weblog_exists'));
        }

        extract((array) $query);

        // --------------------------------------------------------------------
        //  Editing entry, if not valid Revision we load up the data fresh from DB
        //  - The $incoming variable takes precedent over the database data
        // --------------------------------------------------------------------

        if ($which == 'edit' && $revision === false) {
            $entry_query = DB::table('weblog_entries')
                ->where('weblog_entries.id', $entry_id)
                ->where('weblog_entries.weblog_id', $weblog_id)
                ->first();

            $field_query = DB::table('weblog_entry_data')
                ->where('weblog_entry_data.weblog_entry_id', $entry_id)
                ->where('locale', 'en_US') // @todo - For now!
                ->first();

            $entry_query->title = $field_query->title;

            $field_query = collect($field_query)
                ->filter(function ($value, $key) {
                    return starts_with($key, 'field_');
                })
                ->keyBy(function ($item, $key) {
                    return substr($key, strlen('field_'));
                })
                ->toArray();

            if (!$entry_query) {
                return Cp::errorMessage(__('kilvin::publish.no_weblog_exists'));
            }

            if ( ! Session::access('can_edit_other_entries') && $entry_query->author_id != Session::userdata('member_id')) {
                return Cp::unauthorizedAccess();
            }

            $entry_data = (array) $entry_query;
            $entry_data['fields'] = $field_query;
            $incoming   = array_merge($entry_data, $incoming);

            unset($result);
        }

        // ------------------------------------
        //  Categories
        // ------------------------------------

        if ($which == 'edit' && $revision === false && !Request::input('category')) {
            $query = DB::table('categories')
                ->join('weblog_entry_categories', 'weblog_entry_categories.category_id', '=', 'categories.id')
                ->whereIn('categories.category_group_id', explode('|', $category_group_id))
                ->where('weblog_entry_categories.weblog_entry_id', $entry_id)
                ->select('categories.category_name', 'weblog_entry_categories.*')
                ->get();

            foreach ($query as $row) {
                $incoming['category'][] = $row->category_id;
            }
        }

        if ($which == 'new' && !Request::input('category')) {
            $incoming['category'][] = $default_category;
        }

        // ------------------------------------
        //  Extract $incoming
        // ------------------------------------

        extract($incoming);

        // ------------------------------------
        //  Versioning Enabled?
        // ------------------------------------

        $show_revision_cluster = ($enable_versioning == 'y') ? 'y' : 'n';

        $versioning_enabled = ($enable_versioning == 'y') ? 'y' : 'n';

        if ($submission_error) {
            $versioning_enabled = (Request::input('versioning_enabled')) ? 'y' : 'n';
        }

        // ------------------------------------
        //  Insane Idea to Have Defaults and Prefixes
        // ------------------------------------

        if ($which == 'edit') {
            $url_title_prefix = '';
        }

        if ($which == 'new' && empty($submission_error)) {
            $title      = '';
            $url_title  = $url_title_prefix;
        }

        // ------------------------------------
        //  Assign page title based on type of request
        // ------------------------------------

        Cp::$title = __('kilvin::publish.'.$which.'_entry');

        Cp::$crumb = Cp::$title.Cp::breadcrumbItem($weblog_name);

        $CAL = new JsCalendar;
        Cp::$extra_header .= $CAL->calendar();

        // -------------------------------------
        //  Publish Page Title Focus
        // -------------------------------------

        if ($which == 'new') {
            $load_events = "$('#title').focus();displayCatLink();";
        } else {
            $load_events = 'displayCatLink();';
        }

        Cp::$body_props .= ' onload="activate_calendars();'.$load_events.'"';

        $r = '';

        // ------------------------------------
        //  Submission Error
        // ------------------------------------

        if (!empty($submission_error)) {
            $r .= '<h1 class="alert-heading">'.__('kilvin::core.error').'</h1>'.PHP_EOL;
            $r .= '<div class="box alertBox" style="text-align:left">'.$submission_error.'</div>';
        }

        // ------------------------------------
        //  Saved Message
        // ------------------------------------

        if (Cp::pathVar('U') == 'entry-saved') {
            $r .= '<div class="success-message" id="success-message" style="text-align:left">'.
                __('kilvin::publish.entry_saved').
                '</div>';
        }

        // ------------------------------------
        //  Form header and hidden fields
        // ------------------------------------

        $right_links[] = ['weblogs-administration/weblogs-overview', __('kilvin::publish.Edit Layout')];
        $r  .= Cp::header('', $right_links);

        $r .= Cp::formOpen(
            [
                'action' => 'content/submit-new-entry',
                'name'  => 'entryform',
                'id'    => 'entryform'
            ]
        );

        $r .= Cp::input_hidden('weblog_id', $weblog_id);

        if (!empty($entry_id)) {
            $r .= Cp::input_hidden('entry_id', $entry_id);
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

        $field_query = DB::table('weblog_fields')
                ->where('weblog_field_group_id', $weblog_field_group_id)
                ->orderBy('field_handle')
                ->get()
                ->keyBy('field_handle')
                ->toArray();

        // ------------------------------------
        //  Layout Array
        // ------------------------------------

        foreach($layout_query as $row) {

            if (!isset($layout[$row->weblog_layout_tab_id])) {
                $layout[$row->weblog_layout_tab_id] = [];
                $publish_tabs[$row->weblog_layout_tab_id] = $row->tab_name;
            }

            if (isset($field_query[$row->field_handle])) {
                $layout[$row->weblog_layout_tab_id][$row->field_handle] = $field_query[$row->field_handle];
            }
        }

        $publish_tabs['meta']       = __('kilvin::publish.meta');
        $publish_tabs['categories'] = __('kilvin::publish.categories');
        $publish_tabs['revisions']  = __('kilvin::publish.revisions');

        // ------------------------------------
        //  Javascript stuff
        // ------------------------------------

        $word_separator = Site::config('word_separator') != "dash" ? '_' : '-';

        // ------------------------------------
        //  Various Bits of JS
        // ------------------------------------

        $js = <<<EOT

<script type="text/javascript">

    // ------------------------------------
    //  Swap out categories
    // ------------------------------------

    function displayCatLink()
    {
        $('#cateditlink').css('display', 'block');
    }

    function swap_categories(str)
    {
        $('#categorytree').html(str);
    }

    $( document ).ready(function() {

        // ------------------------------------
        // Publish Option Tabs Open/Close
        // ------------------------------------
        $('.publish-tab-link').click(function(e){
            e.preventDefault();
            var active_tab = $(this).data('tab');

            $('.publish-tab-block').css('display', 'none');
            $('#publish_block_'+active_tab).css('display', 'block');

            $('.publish-tab-link').removeClass('selected');
            $(this).addClass('selected');
        });

        $('.publish-tab-link').first().trigger('click');

        // ------------------------------------
        // Toggle element hide/show (calendar mostly)
        // ------------------------------------
        $('.toggle-element').click(function(e){
            e.preventDefault();
            var id = $(this).data('toggle');

            id.split('|').forEach(function (item) {
                $('#' + item).toggle();
            });
        });

        // Quick Save
        $(window).keydown(function (e){
            if ((e.metaKey || e.ctrlKey) && e.keyCode == 83) { /*ctrl+s or command+s*/
                $('button[name=save]').click();
                e.preventDefault();
                return false;
            }
        });
    });

</script>
EOT;

        $r .=
            url_title_javascript($word_separator, $url_title_prefix).
            $js.
            PHP_EOL.
            PHP_EOL;

        // ------------------------------------
        //  NAVIGATION TABS
        // ------------------------------------

        if ($show_categories_tab != 'y') {
            unset($publish_tabs['categories']);
        }

        if ($show_revision_cluster != 'y') {
            unset($publish_tabs['revisions']);
        }

        $r .= '<ul class="publish-tabs">';

        foreach($publish_tabs as $short => $long)
        {
            $selected = ($short == 'form') ? 'selected' : '';

            $r .= PHP_EOL.
                '<li class="publish-tab">'.
                    '<a href="#" class="'.$selected.' publish-tab-link" data-tab="'.$short.'">'.
                         $long.
                    '</a>'.
                '</li>';
        }

        $r .= "</ul>";

        // ------------------------------------
        //  Title, URL Title, Save buttons
        //  - Always at top
        // ------------------------------------

        $r .= PHP_EOL.'<div class="publish-box">';
        $r .= $this->publishFormTitleCluster($entry_id, $title, $url_title, $weblog_field_group_id, $show_url_title);
        $r .= '</div>'.PHP_EOL;

        // ------------------------------------
        //  Meta TAB
        // ------------------------------------

        $r .= '<div id="publish_block_meta" class="publish-tab-block" style="display: none; padding:0; margin:0;">';
        $r .= PHP_EOL.'<div class="publish-box">';

        $r .= $this->publishFormDateBlock($which, $submission_error, $incoming);
        $r .= $this->publishFormOptionsBlock($which, $weblog_id, $author_id, $status, $sticky);

        $r .= '</div>'.PHP_EOL;
        $r .= '</div>'.PHP_EOL;

        // ------------------------------------
        //  Categories TAB
        // ------------------------------------

        if ($show_categories_tab == 'n' && !empty($incoming['category'])) {
            foreach ($incoming['category'] as $cat_id) {
                $r .= Cp::input_hidden('category[]', $cat_id);
            }
        }

        if ($show_categories_tab == 'y') {
            $r .= '<div id="publish_block_categories" class="publish-tab-block" style="display: none; padding:0; margin:0;">';
            $r .= PHP_EOL.'<div class="publish-box">';

            $r .= $this->publishFormCategoriesBlock($category_group_id, $incoming);

            $r .= '</div>'.PHP_EOL;
            $r .= '</div>'.PHP_EOL;
        }

        if ($show_categories_tab !== 'y') {
            if ($which == 'new' and $default_category != '') {
                $r .= Cp::input_hidden('category[]', $default_category);
            }
        }

        // ------------------------------------
        //  Revisions TAB
        // ------------------------------------

        if ($show_revision_cluster == 'y') {
            $r .= '<div id="publish_block_revisions" class="publish-tab-block" style="display: none; padding:0; margin:0;">';
            $r .= PHP_EOL.'<div class="publish-box">';

            $r .= $this->publishFormVersioningBlock($version_id, $entry_id, $versioning_enabled);

            $r .= '</div>'.PHP_EOL;
            $r .= '</div>'.PHP_EOL;
        }

        // ------------------------------------
        //  Layout/Field TABs
        // ------------------------------------

        foreach($layout as $tab => $fields) {

            $r .= '<div id="publish_block_'.$tab.'" class="publish-tab-block" style="display: none; padding:0; margin:0;">';
            $r .= PHP_EOL.'<div class="publish-box">';

            // ------------------------------------
            //  Custom Fields for Tab
            // -----------------------------------

            foreach ($fields as $row) {
                // $row => the field's information from database
                // $which => new or edit
                // $entry_data => The entry's data currently in database, if any
                // $request_data => The entry's data submitted (i.e. entry submitted but there were errors)
                // $submisison_error = Any submisison error(s)
                $r .= $this->publishFormCustomField($row, $which, $entry_data, $request_data, $submission_error);
            }

            $r .= '</div>'.PHP_EOL;
            $r .= "</div>";
        }

        // ------------------------------------
        //  End Form
        // ------------------------------------

        $r .= '</form>'.PHP_EOL;

        Cp::$body = $r;
    }

   /**
    * Entry Versioning Block
    *
    * @param integer $version_id
    * @param integer $entry_id
    * @param string $versioning_enabled
    * @return string
    */
    private function publishFormVersioningBlock($version_id, $entry_id, $versioning_enabled)
    {
        $r  = PHP_EOL."<table class='clusterBox' border='0' cellpadding='0' cellspacing='0' style='width:99%'><tr>";
        $r .= PHP_EOL.'<td class="publishItemWrapper">'.BR;

        $revs_exist = false;

        if (is_numeric($entry_id))
        {
            $revquery = DB::table('entry_versioning AS v')
                ->select('v.author_id', 'v.id AS version_id', 'v.version_date', 'm.screen_name')
                ->join('members AS m', 'v.author_id', '=', 'm.id')
                ->orderBy('v.id', 'desc')
                ->get();

            if ($revquery->count() > 0)
            {
                $revs_exist = true;

                $r .= Cp::tableOpen(['class' => 'tableBorder', 'width' => '100%']);
                $r .= Cp::tableRow([
                        ['text' => __('kilvin::publish.revision'), 'class' => 'tableHeading', 'width' => '25%'],
                        ['text' => __('kilvin::publish.rev_date'), 'class' => 'tableHeading', 'width' => '25%'],
                        ['text' => __('kilvin::publish.rev_author'), 'class' => 'tableHeading', 'width' => '25%'],
                        ['text' => __('kilvin::publish.load_revision'), 'class' => 'tableHeading', 'width' => '25%']
                    ]
                );

                $i = 0;
                $j = $revquery->count();

                foreach($revquery as $row)
                {
                    if ($row->version_id == $version_id) {
                        $revlink = Cp::quickDiv('highlight', __('kilvin::publish.current_rev'));
                    } else {
                        $warning = "onclick=\"if(!confirm('".__('kilvin::publish.revision_warning')."')) return false;\"";

                        $revlink = Cp::anchor(
                            'content/edit-entry/'.$entry_id.
                            'versions/'.$row->version_id,
                                '<b>'.__('kilvin::publish.load_revision').'</b>',
                                $warning);
                    }

                    $r .= Cp::tableRow([
                        ['text' => '<b>'.__('kilvin::publish.revision').' '.$j.'</b>'],
                        ['text' => Localize::createHumanReadableDateTime($row->version_date)],
                        ['text' => $row->screen_name],
                        ['text' => $revlink]
                    ]
                );

                    $j--;
                } // End foreach

                $r .= '</table>'.PHP_EOL;
            }
        }

        if ($revs_exist == false) {
            $r .= Cp::quickDiv('highlight', __('kilvin::publish.no_revisions_exist'));
        }

        $r .= Cp::quickDiv(
            'paddingTop',
            '<label>'.
                Cp::input_checkbox(
                    'versioning_enabled',
                    'y',
                    $versioning_enabled
                ).
            ' '.
            __('kilvin::publish.versioning_enabled').
            '</label>'
        );

        $r .= "</tr></table>";

        return $r;
    }

   /**
    * Categories Block for the Categories Tab
    *
    * @param string $category_group_id
    * @param array $incoming
    * @return string
    */
    private function publishFormCategoriesBlock($category_group_id, $incoming)
    {
        $r  = PHP_EOL."<table class='clusterBox' border='0' cellpadding='0' cellspacing='0' style='width:99%'><tr>";
        $r .= PHP_EOL.'<td class="publishItemWrapper">'.BR;
        $r .= Cp::heading(__('kilvin::publish.categories'), 5);

        // Normal Category Display
        $this->categoryTree(
            $category_group_id,
            (empty($incoming['category'])) ?
                [] :
                $incoming['category']
        );

        if (count($this->categories) == 0)
        {
            $r .= Cp::quickDiv('littlePadding', Cp::quickDiv('highlight', __('kilvin::publish.no_categories')), 'categorytree');
        }
        else
        {
            $r .= "<div id='categorytree'>";

            foreach ($this->categories as $val)
            {
                $r .= $val;
            }

            $r .= '</div>';
        }

        if ($category_group_id != '' && (Session::access('can_edit_categories'))) {
            $r .= '<div id="cateditlink" style="display: none; padding:0; margin:0;">';

            if (stristr($category_group_id, '|'))
            {
                $catg_query = DB::table('category_groups')
                    ->whereIn('id', explode('|', $category_group_id))
                    ->select('group_name', 'category_groups.id AS category_group_id')
                    ->get();

                $links = '';

                foreach($catg_query as $catg_row)
                {
                    $links .= Cp::anchorpop(
                        'weblogs-administration/category_manager'.
                        '/category_group_id='.$catg_row->category_group_id.
                        '/Z=1',
                        '<b>'.$catg_row->group_name.'</b>'
                    ).', ';
                }

                $r .= Cp::quickDiv('littlePadding', '<b>'.__('kilvin::publish.edit_categories').': </b>'.substr($links, 0, -2), '750');
            }
            else
            {
                $r .= Cp::quickDiv(
                    'littlePadding',
                    Cp::anchorpop(
                        'weblogs-administration/category_manager'.
                        '/category_group_id='.$category_group_id.
                        '/Z=1',
                        '<b>'.__('kilvin::publish.edit_categories').'</b>',
                        '750'
                    )
                );
            }

            $r .= '</div>';
        }

        $r .= '</td>';
        $r .= "</tr></table>";

        return $r;
    }

   /**
    * The Title and URL Title cluster for Publish Form
    * - Includes the save buttons
    *
    * @param integer $entry_id
    * @param string $title
    * @param string $url_title
    * @param string $weblog_field_group_id
    * @param string $show_url_title
    * @return string
    */
    private function publishFormTitleCluster($entry_id, $title, $url_title, $weblog_field_group_id, $show_url_title)
    {
        // Table + URL Title + Publish Buttons Table
        $r  = PHP_EOL."<table border='0' cellpadding='0' cellspacing='0' style='width:99%'><tr><td>";

        $r .= Cp::quickDiv(
                'littlePadding',
                Cp::quickDiv('itemTitle', Cp::required().
                    NBS.
                    __('kilvin::publish.title')).
                    Cp::input_text(
                        'title',
                        $title,
                        '20',
                        '100',
                        'input',
                        '100%',
                        (($entry_id == '') ? 'onkeyup="liveUrlTitle(\'#title\', \'#url_title\');"' : ''),
                        false
                )
            );

        // ------------------------------------
        //  "URL title" input Field
        //  - url_title_error triggers the showing of the field, even if supposed to be hidden
        // ------------------------------------

        if ($show_url_title == 'n' and $this->url_title_error === false) {
            $r .= Cp::input_hidden('url_title', $url_title);
        } else {
            $r .= Cp::quickDiv('littlePadding',
                  Cp::quickDiv('itemTitle', __('kilvin::publish.url_title')).
                  Cp::input_text('url_title', $url_title, '20', '75', 'input', '100%')
            );
        }

        $r .= '</div>'.PHP_EOL;
        $r .= '</td>'.PHP_EOL;
        $r .= '<td style="width:350px;padding-top: 4px;" valign="top">'; // <--- someone is a GREAT developer

        // ------------------------------------
        //  Save
        // ------------------------------------

        $r .= Cp::div('publishButtonBox').
            '<button name="save" type="submit" value="save" class="option">'.
                'Quick Save <span style="font-size: 0.8em; letter-spacing: 1px;" class="shortcut">⌘S</span>'.
            '</button>'.
            NBS;

        $r .= (Request::input('C') == 'publish') ?
            Cp::input_submit(__('kilvin::publish.save_and_finish'), 'submit') :
            Cp::input_submit(__('kilvin::publish.save_and_finish'), 'submit');

        $r .= '</div>'.PHP_EOL;

        // ------------------------------------
        //  Upload link
        // ------------------------------------

        $r .= Cp::div('publishButtonBox');

        $r .= Cp::buttonpop(
                'content/upload-file-form/field_groups/'.$weblog_field_group_id.'/Z=1',
                    '⇪&nbsp;'.__('kilvin::publish.upload_file'),
                '520',
                '600',
                'upload');

        $r .= "</td></tr></table>";

        return $r;
    }

   /**
    * Create a Custom Field for Publish Form
    *
    * - We need to send both the existing entry data and request data because of things like dates that are displayed
    * and stored differently. Not sure if the new/edit information is needed, but I cannot see a reason to not include it.
    *
    * @param object $row the field's information from database
    * @param string $which new/edit
    * @param array|null $entry_data The Entry's current data in database, if any
    * @param array|null $request_data If entry was submitted (and there were errors), this is data submitted
    * @param string $submission_error
    * @return string
    */
    private function publishFormCustomField($row, $which, $entry_data, $request_data, $submission_error)
    {
        $r = '';

        $required = ($row->is_field_required == 1) ? '&nbsp;'.Cp::required() : '';

        // Enclosing DIV for each row
        $r .= Cp::div('publishRows');

        // ------------------------------------
        //  Instructions for Field
        // ------------------------------------

        if (trim($row->field_instructions) != '') {
            $r .= Cp::quickDiv(
                'littlePadding',
                '<h5>'.$row->field_name.$required.'</h5>'.
                 Cp::quickSpan(
                    'defaultBold',
                    __('kilvin::publish.instructions')
                ).
                $row->field_instructions
            );
        } else {
             $r .= Cp::quickDiv(
                'littlePadding',
                '<h5>'.$row->field_name.$required.'</h5>'
            );
        }

        $field_types = Plugins::fieldTypes();

        if (isset($field_types[$row->field_type])) {
            $class = $field_types[$row->field_type];
            $r .= (new $class)->setField($row)->publishFormHtml($which, $entry_data, $request_data, $submission_error);
        }

        // Close outer DIV
        $r .= '</div>'.PHP_EOL;

        return $r;
    }


   /**
    * The Options Block for Publish Form (sticky, weblog, status, author)
    *
    * @param string $which
    * @param integer $weblog_id
    * @param integer $author_id
    * @param string $status
    * @param string $sticky
    * @return string
    */
    private function publishFormOptionsBlock($which, $weblog_id, $author_id, $status, $sticky)
    {
        $query = DB::table('weblogs')->where('id', $weblog_id)->first();

        extract((array) $query);

        // ------------------------------------
        //  Author pull-down menu
        // ------------------------------------

        $menu_author = '';

        // First we'll assign the default author.
        if ($author_id == '') {
            $author_id = Session::userdata('member_id');
        }

        $menu_author .= Cp::input_select_header('author_id');
        $query = DB::table('members')
            ->where('id', $author_id)
            ->select('screen_name')
            ->first();

        $menu_author .= Cp::input_select_option($author_id, $query->screen_name);

        // Next we'll gather all the authors that are allowed to be in this list
        $query = DB::table('members')
            ->select('members.id AS member_id', 'members.member_group_id', 'screen_name', 'members.member_group_id')
            ->join('member_group_preferences', 'member_group_preferences.member_group_id', '=', 'members.member_group_id')
            ->where('members.id', '!=', $author_id)
            ->where('member_group_preferences.value', 'y')
            ->whereIn('member_group_preferences.handle', ['in_authorlist', 'include_in_authorlist'])
            ->orderBy('screen_name', 'asc')
            ->get()
            ->unique();

        foreach ($query as $row) {
            if (Session::access('can_assign_post_authors')) {
                if (isset(Session::userdata('assigned_weblogs')[$weblog_id])) {
                    $selected = ($author_id == $row->member_id) ? 1 : '';
                    $menu_author .= Cp::input_select_option($row->member_id, $row->screen_name, $selected);
                }
            }
        }

        $menu_author .= Cp::input_select_footer();

        // ------------------------------------
        //  Weblog pull-down menu
        // ------------------------------------

        $menu_weblog = '';

        if($which == 'edit') {
            $query = DB::table('weblogs')
                ->select('id AS weblog_id', 'weblog_name')
                ->where('status_group_id', $status_group_id)
                ->where('category_group_id', $category_group_id)
                ->where('weblog_field_group_id', $weblog_field_group_id)
                ->orderBy('weblog_name')
                ->get();

            if ($query->count() > 0) {
                foreach ($query as $row) {
                    if (in_array($row->weblog_id, Session::userdata('assigned_weblogs')))
                    {
                        if (isset($incoming['new_weblog']) && is_numeric($incoming['new_weblog'])) {
                            $selected = ($incoming['new_weblog'] == $row->weblog_id) ? 1 : '';
                        } else {
                            $selected = ($weblog_id == $row->weblog_id) ? 1 : '';
                        }

                        $menu_weblog .= Cp::input_select_option($row->weblog_id, escape_attribute($row->weblog_name), $selected);
                    }
                }

                if ($menu_weblog != '') {
                    $menu_weblog = Cp::input_select_header('new_weblog').$menu_weblog.Cp::input_select_footer();
                }
            }
        }

        // ------------------------------------
        //  Status pull-down menu
        // ------------------------------------

        $menu_status = '';

        if ($default_status == '') {
            $default_status = 'open';
        }

        if ($status == '') {
            $status = $default_status;
        }

        $menu_status .= Cp::input_select_header('status');

        // ------------------------------------
        //  Fetch disallowed statuses
        // ------------------------------------

        $no_status_access = [];

        if (Session::userdata('member_group_id') != 1) {
            $query = DB::table('status_no_access')
                ->select('status_id')
                ->where('member_group_id', Session::userdata('member_group_id'))
                ->get();

            foreach ($query as $row) {
                $no_status_access[] = $row->status_id;
            }
        }

        // ------------------------------------
        //  Create status menu
        //  - if no status group assigned, only Admins can create 'open' entries
        // ------------------------------------

        $query = DB::table('statuses')
            ->where('status_group_id', $status_group_id)
            ->orderBy('status_order')
            ->get();

        if ($query->count() == 0) {
            if (Session::userdata('member_group_id') == 1) {
                $menu_status .= Cp::input_select_option('open', __('kilvin::publish.open'), ($status == 'open') ? 1 : '');
            }

            $menu_status .= Cp::input_select_option('closed', __('kilvin::publish.closed'), ($status == 'closed') ? 1 : '');
        }  else {
            $no_status_flag = true;

            foreach ($query as $row) {
                $selected = ($status == $row->status) ? 1 : '';

                if (in_array($row->id, $no_status_access)) {
                    continue;
                }

                $no_status_flag = false;
                $status_name = ($row->status == 'open' OR $row->status == 'closed') ? __('kilvin::publish.'.$row->status) : $row->status;
                $menu_status .= Cp::input_select_option(escape_attribute($row->status), escape_attribute($status_name), $selected);
            }

            // ------------------------------------
            //  No statuses?
            // ------------------------------------

            // If the current user is not allowed to submit any statuses
            // we'll set the default to closed

            if ($no_status_flag == true) {
                $menu_status .= Cp::input_select_option('closed', __('kilvin::publish.closed'));
            }
        }

        $menu_status .= Cp::input_select_footer();

        // ------------------------------------
        //  Author, Weblog, Status, Sticky
        // ------------------------------------

        $meta  = PHP_EOL."<table class='clusterBox' border='0' cellpadding='0' cellspacing='0' style='width:99%'><tr>";

        $meta .= PHP_EOL.'<td class="publishItemWrapper" valign="top">'.BR;
        $meta .= Cp::div('clusterLineR');
        $meta .= Cp::heading(NBS.__('kilvin::publish.author'), 5);
        $meta .= $menu_author;
        $meta .= '</div>'.PHP_EOL;
        $meta .= '</td>';

        if ($menu_weblog != '')
        {
            $meta .= PHP_EOL.'<td class="publishItemWrapper" valign="top">'.BR;
            $meta .= Cp::div('clusterLineR');
            $meta .= Cp::heading(NBS.__('kilvin::publish.weblog'), 5);
            $meta .= $menu_weblog;
            $meta .= '</div>'.PHP_EOL;
            $meta .= '</td>';
        }

        $meta .= PHP_EOL.'<td class="publishItemWrapper" valign="top">'.BR;
        $meta .= Cp::div('clusterLineR');
        $meta .= Cp::heading(NBS.__('kilvin::publish.status'), 5);
        $meta .= $menu_status;
        $meta .= '</div>'.PHP_EOL;
        $meta .= '</td>';

        $meta .= PHP_EOL.'<td class="publishItemWrapper" valign="top">'.BR;
        $meta .= Cp::heading(NBS.__('kilvin::publish.sticky'), 5);
        $meta .= '<label style="display:inline-block;margin-top:3px;">'.
                    Cp::input_checkbox('sticky', 'y', $sticky).' '.__('kilvin::publish.make_entry_sticky').
                '</label>'.
            '</td>';

        $meta .= "</tr></table>";

        return $meta;
    }

   /**
    * The Entry and Expiration Date Block for Publish Form
    *
    * @param string $which
    * @param string $submission_error
    * @param array $incoming
    * @return string
    */
    private function publishFormDateBlock($which, $submission_error, $incoming)
    {
        // ------------------------------------
        //  Entry and Expiration Date Calendars
        // ------------------------------------

        Cp::$extra_header .= '<script type="text/javascript">
        // depending on timezones, local settings and localization prefs, its possible for js to misinterpret the day,
        // but the humanized time is correct, so we activate the humanized time to sync the calendar

        function activate_calendars() {
            update_calendar(\'entry_date\', $(\'#entry_date\').val());
            update_calendar(\'expiration_date\', $(\'#expiration_date\').val());';

        Cp::$extra_header .= "\n\t\t\t\t"."current_month   = '';
            current_year    = '';
            last_date       = '';";

        Cp::$extra_header .= "\n".'}
        </script>';


        // ------------------------------------
        //  DATE BLOCK
        //  $entry_date - Always UTC
        //  $expiration_date - Always UTC
        //  $entry_date_string - Localized and formatted
        //  $expiration_date_string - empty OR Localized and formatted
        // ------------------------------------

        if (!empty($submission_error)) {
            // From POST!
            $entry_date_string      = $incoming['entry_date'];
            $expiration_date_string = $incoming['expiration_date'];

            $entry_date      =
                (empty($incoming['entry_date'])) ?
                Carbon::now() :
                Localize::humanReadableToUtcCarbon($incoming['entry_date']);

            $expiration_date =
                (empty($incoming['expiration_date'])) ?
                '' :
                Localize::humanReadableToUtcCarbon($incoming['expiration_date']);
        }
        elseif ($which == 'new')
        {
            $entry_date        = (empty($entry_date)) ? Carbon::now() : Carbon::parse($entry_date);
            $entry_date_string = Localize::createHumanReadableDateTime($entry_date);

            $expiration_date_string =
                (empty($expiration_date)) ?
                '' :
                Localize::createHumanReadableDateTime($expiration_date);
        }
        else
        {
            $entry_date        = Carbon::parse($incoming['entry_date']);
            $entry_date_string = Localize::createHumanReadableDateTime($entry_date);

            $expiration_date =
                (empty($incoming['expiration_date'])) ?
                '' :
                Carbon::parse($incoming['expiration_date']);

            $expiration_date_string =
                (empty($expiration_date)) ?
                '' :
                Localize::createHumanReadableDateTime($expiration_date);
        }

        $date_object     = $entry_date->copy();
        $date_object->tz = Site::config('site_timezone');
        $cal_entry_date  = $date_object->timestamp * 1000;

        $date_object     = (empty($expiration_date)) ? Carbon::now() : $expiration_date->copy();
        $date_object->tz = Site::config('site_timezone');
        $cal_expir_date  = $date_object->timestamp * 1000;

        // ------------------------------------
        //  Meta Tab
        //  - Entry Date + Expiration Date Calendar
        //  - Weblog, Status, and Author Pulldowns
        //  - Sticky Checkbox
        // ------------------------------------

        $meta = PHP_EOL."<table class='clusterBox' border='0' cellpadding='0' cellspacing='0' style='width:99%'><tr>";

        // ------------------------------------
        //  Entry Date Field
        // ------------------------------------

        $meta .= '<td class="publishItemWrapper">'.BR;
        $meta .= Cp::div('clusterLineR');
        $meta .= Cp::div('defaultCenter');

        $meta .= Cp::heading(__('kilvin::publish.entry_date'), 5);

        $cal_img =
            '<a href="#" class="toggle-element" data-toggle="calendar_entry_date|calendar_expiration_date">
                <span style="display:inline-block; height:25px; width:25px; vertical-align:top;">
                    '.Cp::calendarImage().'
                </span>
            </a>';

        $meta .= Cp::quickDiv(
            'littlePadding',
            Cp::input_text(
                'entry_date',
                $entry_date_string,
                '18',
                '23',
                'input',
                '150px',
                'onkeyup="update_calendar(\'entry_date\', this.value);" '
            ).
            $cal_img
        );

        $meta .= '<div id="calendar_entry_date" style="display:none;margin:4px 0 0 0;padding:0;">';
        $meta .= PHP_EOL.'<script type="text/javascript">

                var entry_date  = new calendar(
                                        "entry_date",
                                        new Date('.$cal_entry_date.'),
                                        true
                                        );

                document.write(entry_date.write());
                </script>';

        $meta .= '</div>';

        $meta .=
            Cp::div('littlePadding').
                '<a href="javascript:void(0);" onclick="set_to_now(\'entry_date\')" >'.
                    __('kilvin::publish.today').
                '</a>'.
                NBS.'|'.NBS.
                '<a href="javascript:void(0);" onclick="clear_field(\'entry_date\');" >'.
                    __('kilvin::cp.clear').
                '</a>'.
            '</div>'.PHP_EOL;

        $meta .= '</div>'.PHP_EOL;
        $meta .= '</div>'.PHP_EOL;
        $meta .= '</td>';

        // ------------------------------------
        //  Expiration Date Field
        // ------------------------------------

        $meta .= '<td class="publishItemWrapper">'.BR;
        $meta .= Cp::div('clusterLineR');
        $meta .= Cp::div('defaultCenter');

        $meta .= Cp::heading(__('kilvin::publish.expiration_date'), 5);

        $cal_img =
            '<a href="#" class="toggle-element" data-toggle="calendar_entry_date|calendar_expiration_date">
                <span style="display:inline-block; height:25px; width:25px; vertical-align:top;">
                    '.Cp::calendarImage().'
                </span>
            </a>';

        $meta .= Cp::quickDiv(
            'littlePadding',
            Cp::input_text(
                'expiration_date',
                $expiration_date_string,
                '18',
                '23',
                'input',
                '150px',
                'onkeyup="update_calendar(\'expiration_date\', this.value);" '
            ).
            $cal_img
        );

        $meta .= '<div id="calendar_expiration_date" style="display:none;margin:4px 0 0 0;padding:0;">';
        $meta .= PHP_EOL.'<script type="text/javascript">

                var expiration_date  = new calendar(
                                        "expiration_date",
                                        new Date('.$cal_entry_date.'),
                                        true
                                        );

                document.write(expiration_date.write());
                </script>';

        $meta .= '</div>';

        $meta .=
            Cp::div('littlePadding').
                '<a href="javascript:void(0);" onclick="set_to_now(\'expiration_date\')" >'.
                    __('kilvin::publish.today').
                '</a>'.
                NBS.'|'.NBS.
                '<a href="javascript:void(0);" onclick="clear_field(\'expiration_date\');" >'.
                    __('kilvin::cp.clear').
                '</a>'.
            '</div>'.PHP_EOL;

        $meta .= '</div>'.PHP_EOL;
        $meta .= '</div>'.PHP_EOL;
        $meta .= '</td>';

        // END Calendar Table
        $meta .= "</tr></table>";

        return $meta;
    }

   /**
    * Process an Entry Form submission
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
    public function submitNewEntry()
    {
        $url_title  = '';
        $incoming   = Request::all();

        if ( ! $weblog_id = Request::input('weblog_id') OR ! is_numeric($weblog_id)) {
            return Cp::unauthorizedAccess();
        }

        $assigned_weblogs = array_keys(Session::userdata('assigned_weblogs'));

        // ------------------------------------
        //  Security check
        // ------------------------------------

        if ( ! in_array($weblog_id, $assigned_weblogs)) {
            return Cp::unauthorizedAccess();
        }

        // ------------------------------------
        //  Does entry ID exist?  And is valid for this weblog?
        // ------------------------------------

        if (($entry_id = Request::input('entry_id')) && is_numeric($entry_id)) {
            // we grab the author_id now as we use it later for author validation
            $query = DB::table('weblog_entries')
                ->select('id AS entry_id', 'author_id')
                ->where('id', $entry_id)
                ->where('weblog_id', $weblog_id)
                ->first();

            if (!$query) {
                return Cp::unauthorizedAccess();
            }

            $entry_id = $query->entry_id;
            $orig_author_id = $query->author_id;
        } else {
            $entry_id = '';
        }

        // ------------------------------------
        //  Weblog Switch?
        // ------------------------------------

        $old_weblog = '';

        if (($new_weblog = Request::input('new_weblog')) !== false && $new_weblog != $weblog_id) {
            $query = DB::table('weblogs')
                ->whereIn('id', [$weblog_id, $new_weblog])
                ->select('status_group_id', 'category_group_id', 'weblog_field_group_id', 'weblogs.id AS weblog_id')
                ->get();

            if ($query->count() == 2) {
                if ($query->first()->status_group == $query->last()->status_group &&
                    $query->first()->category_group_id == $query->last()->category_group_id &&
                    $query->first()->weblog_field_group_id == $query->last()->weblog_field_group_id)
                {
                    if (Session::userdata('member_group_id') == 1) {
                        $old_weblog = $weblog_id;
                        $weblog_id = $new_weblog;
                    }
                    else
                    {
                        $assigned_weblogs = array_keys(Session::userdata('assigned_weblogs'));

                        if (in_array($new_weblog, $assigned_weblogs))
                        {
                            $old_weblog = $weblog_id;
                            $weblog_id = $new_weblog;
                        }
                    }
                }
            }
        }


        // ------------------------------------
        //  Fetch Weblog Prefs
        // ------------------------------------

        $query = DB::table('weblogs')
            ->where('id', $weblog_id)
            ->first();

        $weblog_name                = $query->weblog_name;
        $weblog_url                 = $query->weblog_url;
        $default_status             = $query->default_status;
        $enable_versioning          = $query->enable_versioning;
        $enable_qucksave_versioning = $query->enable_qucksave_versioning;
        $max_revisions              = $query->max_revisions;

         $notify_address            =
            ($query->weblog_notify == 'y' and !empty($query->weblog_notify_emails) )?
            $query->weblog_notify_emails :
            '';

        // ------------------------------------
        //  Error trapping
        // ------------------------------------

        $error = [];

        // ------------------------------------
        //  No entry title or title too long? Assign error.
        // ------------------------------------

        if ( ! $title = strip_tags(trim(Request::input('title')))) {
            $error[] = __('kilvin::publish.missing_title');
        }

        if (strlen($title) > 100) {
            $error[] = __('kilvin::publish.title_too_long');
        }

        // ------------------------------------
        //  No date? Assign error.
        // ------------------------------------

        if ( ! Request::input('entry_date')) {
            $error[] = __('kilvin::publish.missing_date');
        }

        // ------------------------------------
        //  Convert the date to a Unix timestamp
        // ------------------------------------

        $entry_date = Localize::humanReadableToUtcCarbon(Request::input('entry_date'));

        if ( ! $entry_date instanceof Carbon) {
            if ($entry_date !== FALSE) {
                $error[] = $entry_date.NBS.'('.__('kilvin::publish.entry_date').')';
            } else {
                $error[] = __('kilvin::publish.invalid_date_formatting');
            }
        }

        // ------------------------------------
        //  Convert expiration date to a Unix timestamp
        // ------------------------------------

        if ( ! Request::input('expiration_date')) {
            $expiration_date = 0;
        } else {
            $expiration_date = Localize::humanReadableToUtcCarbon(Request::input('expiration_date'));

            if ( ! $expiration_date instanceof Carbon)
            {
                // Localize::humanReadableToUtcCarbon() returns verbose errors
                if ($expiration_date !== FALSE)
                {
                    $error[] = $expiration_date.NBS.'('.__('kilvin::publish.expiration_date').')';
                }
                else
                {
                    $error[] = __('kilvin::publish.invalid_date_formatting');
                }
            }
        }

        // ------------------------------------
        //  Custom Fields validation
        // ------------------------------------

        $fields = DB::table('weblog_fields')
            ->select('weblog_fields.*')
            ->join('weblog_layout_fields', 'weblog_layout_fields.field_handle', '=', 'weblog_fields.field_handle')
            ->join('weblog_layout_tabs', 'weblog_layout_tabs.id', '=', 'weblog_layout_fields.weblog_layout_tab_id')
            ->where('weblog_layout_tabs.weblog_id', $weblog_id)
            ->get();

        // ------------------------------------
        //  Are all requred fields filled out?
        // ------------------------------------

        foreach ($fields as $field) {
            if ($field->is_field_required == 1 && empty($incoming['fields'][$field->field_handle])) {
                $error[] = __('kilvin::publish.The following field is required').NBS.$field->field_name;
            }
        }

        // ------------------------------------
        //  Fetch Custom Field Validation rules
        // ------------------------------------

        $field_types = Plugins::fieldTypes();
        $rules = [];
        $messages = [];

        foreach($fields as $field) {

            if (!isset($field_types[$field->field_type])) {
                continue;
            }

            $type_class = $field_types[$field->field_type];
            $field_type = (new $type_class)->setField($field);

            $results = $field_type->publishFormValidation(
                $which = (empty($entry_id) ? 'new' : 'edit'),
                $entry_data = null,
                $request_data = $incoming,
                $submission_error = null
            );

            if (!empty($results)) {
                list($field_rules, $field_messages) = $results;
            }

            $rules = array_merge($rules, $field_rules);
            $messages = array_merge($messages, $field_messages);
        }

        if (!empty($rules)) {
            $validator = Validator::make($incoming, $rules, $messages);

            if ($validator->fails()) {
                $error = array_merge($error, $validator->errors()->all());
            }
        }

        // ------------------------------------
        //  Is the title unique?
        // ------------------------------------

        if ($title != '') {
            // Do we have a URL title?
            $url_title = Request::input('url_title');

            if (empty($url_title)) {
                // Forces a lowercased version
                $url_title = create_url_title($title, true);
            }

            // Kill all the extraneous characters.
            // We want the URL title to be pure alpha text
            if ($entry_id != '') {
                $url_query = DB::table('weblog_entries')
                    ->select('url_title')
                    ->where('id', $entry_id)
                    ->first();

                if ($url_query->url_title != $url_title) {
                    $url_title = create_url_title($url_title);
                }
            } else {
                $url_title = create_url_title($url_title);
            }

            // Is the url_title a pure number?  If so we show an error.
            if (is_numeric($url_title)) {
                $this->url_title_error = true;
                $error[] = __('kilvin::publish.url_title_is_numeric');
            }

            // ------------------------------------
            //  Is the URL Title empty? Error!
            // ------------------------------------

            if (trim($url_title) == '')  {
                $this->url_title_error = true;
                $error[] = __('kilvin::publish.unable_to_create_url_title');

                $msg = '';

                foreach($error as $val) {
                    $msg .= Cp::quickDiv('littlePadding', $val);
                }

                return $this->entryForm($msg);
            }

            // Is the url_title too long?  Warn them
            if (strlen($url_title) > 75)
            {
                $this->url_title_error = true;
                $error[] = __('kilvin::publish.url_title_too_long');
            }

            // ------------------------------------
            //  Is URL title unique?
            // ------------------------------------

            // Field is limited to 75 characters, so trim url_title before querying
            $url_title = substr($url_title, 0, 75);

            $query = DB::table('weblog_entries')
                ->where('url_title', $url_title)
                ->where('weblog_id', $weblog_id)
                ->where('id', '!=', $entry_id);

            $count = $query->count();

            if ($count > 0) {
                // We may need some room to add our numbers- trim url_title to 70 characters
                // Add hyphen separator
                $url_title = substr($url_title, 0, 70).'-';

                $recent = DB::table('weblog_entries')
                    ->select('url_title')
                    ->where('weblog_id', $weblog_id)
                    ->where('id', '!=', $entry_id)
                    ->where('url_title', 'LIKE', $url_title.'%')
                    ->orderBy('url_title', 'desc')
                    ->first();

                $next_suffix = 1;

                if ($recent && preg_match("/\-([0-9]+)$/", $recent->url_title, $match)) {
                    $next_suffix = sizeof($match) + 1;
                }

                // Is the appended number going to kick us over the 75 character limit?
                if ($next_suffix > 9999) {
                    $url_create_error = true;
                    $error[] = __('kilvin::publish.url_title_not_unique');
                }

                $url_title .= $next_suffix;

                $double_check = DB::table('weblog_entries')
                    ->where('url_title', $url_title)
                    ->where('weblog_id', $weblog_id)
                    ->where('id', '!=', $entry_id)
                    ->count();

                if ($double_check > 0) {
                    $url_create_error = true;
                    $error[] = __('kilvin::publish.unable_to_create_url_title');
                }
            }
        }

        // Did they name the URL title "index"?  That's a bad thing which we disallow
        if ($url_title == 'index') {
            $this->url_title_error = true;
            $error[] = __('kilvin::publish.url_title_is_index');
        }

        // ------------------------------------
        //  Validate Author ID
        // ------------------------------------

        $author_id = ( ! Request::input('author_id')) ? Session::userdata('member_id'): Request::input('author_id');

        if ($author_id != Session::userdata('member_id') && ! Session::access('can_edit_other_entries')) {
            $error[] = __('kilvin::core.not_authorized');
        }

        if (
            isset($orig_author_id) &&
            $author_id != $orig_author_id &&
            (! Session::access('can_edit_other_entries') OR ! Session::access('can_assign_post_authors'))
        ) {
            $error[] = __('kilvin::core.not_authorized');
        }

        if ($author_id != Session::userdata('member_id') && Session::userdata('member_group_id') != 1)
        {
            // we only need to worry about this if the author has changed
            if (! isset($orig_author_id) OR $author_id != $orig_author_id) {
                if (! Session::access('can_assign_post_authors')) {
                    $error[] = __('kilvin::core.not_authorized');
                } else {
                    $allowed_authors = DB::table('members')
                        ->select('members.id AS member_id')
                        ->join('member_groups', 'member_groups.id', '=', 'member.member_group_id')
                        ->where(function($q)
                        {
                            $q->where('members.in_authorlist', 'y')->orWhere('member_groups.include_in_authorlist', 'y');
                        })
                        ->get()
                        ->pluck('member_id')
                        ->all();

                    if (! in_array($author_id, $allowed_authors)) {
                        $error[] = __('kilvin::publish.invalid_author');
                    }
                }
            }
        }

        // ------------------------------------
        //  Validate status
        // ------------------------------------

        $status = (Request::input('status') == null) ? $default_status : Request::input('status');

        if (Session::userdata('member_group_id') != 1) {
            $disallowed_statuses = [];
            $valid_statuses = [];

            $query = DB::table('statuses AS s')
                ->select('s.id AS status_id', 's.status')
                ->join('status_groups AS sg', 'sg.id', '=', 's.status_group_id')
                ->leftJoin('weblogs AS w', 'w.status_group_id', '=', 'sg.status_group_id')
                ->where('w.id', $weblog_id)
                ->get();

            if ($query->count() > 0) {
                foreach ($query as $row) {
                    $valid_statuses[$row->status_id] = strtolower($row->status); // lower case to match MySQL's case-insensitivity
                }
            }

            $query = DB::table('status_no_access')
                ->join('statuses', 'statuses.id', '=', 'status_no_access.status_id')
                ->where('status_no_access.member_group_id', Session::userdata('member_group_id'))
                ->select('status_no_access', 'statuses')
                ->get();

            if ($query->count() > 0) {
                foreach ($query as $row) {
                    $disallowed_statuses[$row->status_id] = strtolower($row->status);
                }

                $valid_statuses = array_diff_assoc($valid_statuses, $disallowed_statuses);
            }

            // if there are no valid statuses, set to closed
            if (! in_array(strtolower($status), $valid_statuses)) {
                $status = 'closed';
            }
        }

        // ------------------------------------
        //  Do we have an error to display?
        // ------------------------------------

         if (count($error) > 0) {
            $msg = '';

            foreach($error as $val) {
                $msg .= Cp::quickDiv('littlePadding', $val);
            }

            return $this->entryForm($msg);
         }

        // ------------------------------------
        //  Fetch Categories
        // ------------------------------------

        if (isset($incoming['category']) and is_array($incoming['category'])) {
            foreach ($incoming['category'] as $cat_id) {
                $this->cat_parents[] = $cat_id;
            }

            if ($this->assign_cat_parent == true) {
                $this->fetchCategoryParents($incoming['category']);
            }
        }

        // $this->cat_parents will be used for saving
        unset($incoming['category']);

        // ------------------------------------
        //  Build our query data
        // ------------------------------------

        if ($enable_versioning == 'n') {
            $version_enabled = 'y';
        } else {
            $version_enabled = (Request::input('versioning_enabled')) ? 'y' : 'n';
        }

        $data = [
            'id'                  => null,
            'weblog_id'           => $weblog_id,
            'author_id'           => $author_id,
            'url_title'           => $url_title,
            'entry_date'          => $entry_date,
            'updated_at'          => Carbon::now(),
            'versioning_enabled'  => $version_enabled,
            'expiration_date'     => (empty($expiration_date)) ? null : $expiration_date,
            'sticky'              => (Request::input('sticky') == 'y') ? 'y' : 'n',
            'status'              => $status,
        ];

        // ------------------------------------
        //  Insert the entry
        // ------------------------------------

        if ($entry_id == '') {
            $data['created_at'] = Carbon::now();
            $entry_id = DB::table('weblog_entries')->insertGetId($data);

            // ------------------------------------
            //  Insert the custom field data
            // ------------------------------------

            $cust_fields = [
                'entry_id' => $entry_id,
                'weblog_id' => $weblog_id,
                'title'     => $title,
                'locale'    => 'en_US' // @todo - For now!
            ];

            $cust_fields = (array) Request::input('fields');

            // Save the custom field data
            if (count($cust_fields) > 0) {
                DB::table('weblog_entry_data')->insert($cust_fields);
            }

            // ------------------------------------
            //  Update member stats
            // ------------------------------------

            if ($data['author_id'] == Session::userdata('member_id')) {
                $total_entries = Session::userdata('total_entries') +1;
            } else {
                $total_entries = DB::table('members')
                    ->where('id', $data['author_id'])
                    ->value('total_entries') + 1;
            }

            DB::table('members')
                ->where('id', $data['author_id'])
                ->update(['total_entries' => $total_entries, 'last_entry_date' => Carbon::now()]);

            // ------------------------------------
            //  Set page title and success message
            // ------------------------------------

            $type = 'new';
            $message = __('kilvin::publish.entry_has_been_added');

            // ------------------------------------
            //  Admin Notification of New Weblog Entry
            // ------------------------------------

            if (!empty($notify_address)) {

                $notify_ids = explode(',', $notify_address);

                // Remove author
                $notify_ids = array_diff($notify_ids, [Session::userdata('member_id')]);

                if (!empty($notify_ids)) {

                    $members = Member::whereIn('id', $notify_ids)->get();

                    if ($members->count() > 0) {
                        Notification::send($members, new NewEntryAdminNotify($entry_id, $notify_address));
                    }
                }
            }
        } else {
            // ------------------------------------
            //  Update an existing entry
            // ------------------------------------

            // First we need to see if the author of the entry has changed.
            $query = DB::table('weblog_entries')
                ->select('author_id')
                ->where('id', $entry_id)
                ->first();

            $old_author = $query->author_id;

            if ($old_author != $data['author_id'])
            {
                // Lessen the counter on the old author
                $query = DB::table('members')->select('total_entries')->where('id', $old_author);

                $total_entries = $query->total_entries - 1;

                DB::table('members')->where('id', $old_author)->update(['total_entries' => $total_entries]);

                // Increment the counter on the new author
                $query = DB::table('members')->select('total_entries')->where('id', $data['author_id']);

                $total_entries = $query->total_entries + 1;

                DB::table('members')->where('id', $data['author_id']) ->update(['total_entries' => $total_entries]);
            }

            // ------------------------------------
            //  Update the entry
            // ------------------------------------

            unset($data['id']);

            DB::table('weblog_entries')
                ->where('id', $entry_id)
                ->update($data);

            // ------------------------------------
            //  Update the custom fields
            // ------------------------------------

            $cust_fields = [
                'weblog_id' =>  $weblog_id,
                'title'     => $title
            ];

            $cust_fields = collect(Request::input('fields'))
                ->keyBy(function ($value, $name) {
                    return 'field_'.$name;
                })
                ->toArray();

            DB::table('weblog_entry_data')
                ->where('weblog_entry_id', $entry_id)
                ->where('locale', 'en_US') // @todo - For now!
                ->update($cust_fields);

            // ------------------------------------
            //  Delete categories
            //  - We will resubmit all categories next
            // ------------------------------------

            DB::table('weblog_entry_categories')->where('weblog_entry_id', $entry_id)->delete();

            // ------------------------------------
            //  Set page title and success message
            // ------------------------------------

            $type = 'update';
            $message = __('kilvin::publish.entry_has_been_updated');
        }

        // ------------------------------------
        //  Insert categories
        // ------------------------------------

        if ($this->cat_parents > 0) {
            $this->cat_parents = array_unique($this->cat_parents);

            sort($this->cat_parents);

            foreach($this->cat_parents as $val) {
                if ($val != '') {
                    DB::table('weblog_entry_categories')
                        ->insert(
                            [
                                'weblog_entry_id' => $entry_id,
                                'category_id'     => $val
                            ]);
                }
            }
        }

        // ------------------------------------
        //  Save revisions if needed
        // ------------------------------------

        if (!Request::input('versioning_enabled')) {
            $enable_versioning = 'n';
        }

        if (Request::filled('save') and $enable_qucksave_versioning == 'n') {
            $enable_versioning = 'n';
        }

        if ($enable_versioning == 'y') {
            $version_data = [
                'entry_id'     => $entry_id,
                'weblog_id'    => $weblog_id,
                'author_id'    => Session::userdata('member_id'),
                'version_date' => Carbon::now(),
                'version_data' => serialize(Request::all())
            ];


            DB::table('entry_versioning')->insert($version_data);

            // Clear old revisions if needed
            $max = (is_numeric($max_revisions) AND $max_revisions > 0) ? $max_revisions : 10;

            $version_count = DB::table('entry_versioning')->where('weblog_entry_id', $entry_id)->count();

            // Prune!
            if ($version_count > $max) {
                $ids = DB::table('entry_versioning')
                    ->select('v.id AS version_id')
                    ->where('weblog_entry_id', $entry_id)
                    ->orderBy('id', 'desc')
                    ->limit($max)
                    ->pluck('version_id')
                    ->all();

                if (!empty($ids)) {
                    DB::table('entry_versioning')
                        ->whereNotIn('id', $ids)
                        ->where('weblog_entry_id', $entry_id)
                        ->delete();
                }
            }
        }

        //---------------------------------
        // Quick Save Returns Here
        //  - does not update stats
        //  - does not empty caches
        //---------------------------------

        if (isset($incoming['save'])) {
            $loc = kilvin_cp_url('content/edit-entry/entry_id='.$entry_id);
            return redirect($loc)->with('cp-message', __('kilvin::publish.entry_has_been_updated'));
        }

        // ------------------------------------
        //  Update global stats
        // ------------------------------------

        if ($old_weblog != '') {
            Stats::update_weblog_stats($old_weblog);
        }

        Stats::update_weblog_stats($weblog_id);

        // ------------------------------------
        //  Clear caches if needed
        // ------------------------------------

        if (Site::config('new_posts_clear_caches') == 'y') {
            cms_clear_caching('all');
        }

        // ------------------------------------
        //  Redirect to ths "success" page
        // ------------------------------------

        return redirect(kilvin_cp_url('content/list-entries'))->with('cp-message', $message);
    }

   /**
    * Fetch the Parents for the Categories
    *
    * @param array The array of cats to find the parents for
    * @return void
    */
    public function fetchCategoryParents($cat_array = '')
    {
        if (count($cat_array) == 0) {
            return;
        }

        $query = DB::table('categories')
            ->select('parent_id')
            ->whereIn('id', $cat_array)
            ->get();

        if ($query->count() == 0) {
            return;
        }

        $temp = [];

        foreach ($query as $row)
        {
            if ($row->parent_id != 0)
            {
                $this->cat_parents[] = $row->parent_id;

                $temp[] = $row->parent_id;
            }
        }

        $this->fetchCategoryParents($temp);
    }

   /**
    * Builds the Categories into their Nested <select> form for Publish page
    *
    * @param integer $category_group_id The category group number
    * @param array $selected The array of category ids selected in form
    * @return void
    */
    public function categoryTree($category_group_id = '', $selected = [])
    {
        // Fetch category group ID number
        if ($category_group_id == '') {
            if ( ! $category_group_id = Request::input('category_group_id')) {
                return false;
            }
        }

        $catarray = [];

        if (is_array($selected)) {
            foreach ($selected as $val) {
                $catarray[$val] = $val;
            }
        }

        // Fetch category groups
        if ( ! is_numeric(str_replace('|', '', $category_group_id))) {
            return false;
        }

        $query = DB::table('categories')
            ->whereIn('category_group_id', explode('|', $category_group_id))
            ->orderBy('category_group_id')
            ->orderBy('parent_id')
            ->orderBy('category_order')
            ->select('category_name', 'id AS category_id', 'parent_id', 'category_group_id')
            ->get();

        if ($query->count() == 0) {
            return false;
        }

        // Assign the result to multi-dimensional array
        foreach($query as $row) {
            $cat_array[$row->category_id] = [
                $row->parent_id,
                $row->category_name,
                $row->category_group_id
            ];
        }

        $size = count($cat_array) + 1;

        $this->categories[] = Cp::input_select_header('category[]', 1, $size);

        // Build our output...

        $sel = '';

        foreach($cat_array as $key => $val)
        {
            if (0 == $val[0])
            {
                if (isset($last_group) && $last_group != $val[2])
                {
                    $this->categories[] = Cp::input_select_option('', '-------');
                }

                $sel = (isset($catarray[$key])) ? '1' : '';

                $this->categories[] = Cp::input_select_option($key, $val[1], $sel);
                $this->categorySubtree($key, $cat_array, $depth=1, $selected);

                $last_group = $val[2];
            }
        }

        $this->categories[] = Cp::input_select_footer();
    }

   /**
    * Recursive method to build nested categories
    *
    * @param integer $cat_id The parent category_id
    * @param array $cat_array The array of all categories
    * @param integer $depth The current depth
    * @param array The selected categories
    * @return void
    */
    private function categorySubtree($cat_id, $cat_array, $depth, $selected = [])
    {
        $spcr = "&nbsp;";
        $catarray = [];

        if (is_array($selected))
        {
            foreach ($selected as $key => $val)
            {
                $catarray[$val] = $val;
            }
        }

        $indent = $spcr.$spcr.$spcr.$spcr;

        if ($depth == 1)
        {
            $depth = 4;
        }
        else
        {
            $indent = str_repeat($spcr, $depth).$indent;

            $depth = $depth + 4;
        }

        $sel = '';

        foreach ($cat_array as $key => $val)
        {
            if ($cat_id == $val[0])
            {
                $pre = ($depth > 2) ? "&nbsp;" : '';

                $sel = (isset($catarray[$key])) ? '1' : '';

                $this->categories[] = Cp::input_select_option($key, $pre.$indent.$spcr.$val[1], $sel);
                $this->categorySubtree($key, $cat_array, $depth, $selected);
            }
        }
    }


//=====================================================================
//  "Content" Page
//=====================================================================

   /**
    * List Entries page
    *
    * @return string
    */
    public function listEntries()
    {
        $allowed_blogs = array_keys(Session::userdata('assigned_weblogs'));

        if (empty($allowed_blogs)) {
            return Cp::unauthorizedAccess(__('kilvin::publish.no_weblogs'));
        }

        $total_blogs = count($allowed_blogs);

        // ------------------------------------
        //  @todo - Store most recent search in session and retrieve
        // ------------------------------------

        // ------------------------------------
        //  Determine Weblog(s) to Show
        // ------------------------------------

        $weblog_id = Request::input('weblog_id');

        if ($weblog_id == 'null' OR $weblog_id === false OR ! is_numeric($weblog_id)) {
            $weblog_id = '';
        }

        $category_group_id = '';
        $cat_id = Request::input('category_id');
        $status = Request::input('status');
        $order  = Request::input('order');
        $date_range = Request::input('date_range');

        // -----------------------------------------
        //  CP Message?
        // -----------------------------------------

        $r = '';

        // JS for this is in the ControlPanel::dropMenuJavascript() for now
        if (sizeof(Session::userdata('assigned_weblogs')) > 0) {
            $dropdown = '<ul class="weblog-drop-menu">';

            foreach(Session::userdata('assigned_weblogs') as $id => $label) {
                $dropdown .=
                    '<li class="weblog-drop-menu-inner">'.
                    '<a href="'.
                        kilvin_cp_url(
                            'content/entry-form/weblog_id='.$id
                        ).'" title="'.Cp::htmlAttribute($label).'">'.
                        htmlentities($label).
                    '</a></li>';
            }

            $dropdown .= '</ul>';

            $js = 'onclick="weblogMenuSwitch(event);"';

            $r .= '<div style="text-align:right"><div class="cp-form">'.
                Cp::input_submit(
                    __('kilvin::cp.new_entry'),
                    'new_entry',
                    'class="btn btn-primary" '.$js
                ).
                $dropdown.
                '</div></div>';
        }

        // -----------------------------------------
        //  CP Message?
        // -----------------------------------------

        $cp_message = session()->pull('cp-message');

        if (!empty($cp_message)) {
            $r .= Cp::quickDiv('success-message', $cp_message);
        }

        // ------------------------------------
        //  Begin Page Output
        // ------------------------------------

        $r .= Cp::quickDiv('tableHeading', __('kilvin::publish.edit_weblog_entries'));

        // Declare the "filtering" form
        $s = Cp::formOpen(
            [
                'action'    => 'content/list-entries',
                'name'      => 'filterform',
                'id'        => 'filterform'
            ]
        );

        // If we have more than one weblog we'll write the JavaScript menu switching code
        if ($total_blogs > 1) {
            $s .= $this->editFilteringMenus();
        }

        // Table start
        $s .= Cp::div('box');
        $s .= Cp::table('', '0', '', '100%').
              '<tr>'.PHP_EOL.
              Cp::td('littlePadding', '', '7').PHP_EOL;

        // ------------------------------------
        //  Weblog Pulldown
        //  - Each weblog has its assigned categories/statuses so we updated the form when weblog chosen
        // ------------------------------------

        if ($total_blogs > 1) {
            $s .= "<select name='weblog_id' class='select' onchange='changeFilterMenu();'>\n";
        } else {
            $s .= "<select name='weblog_id' class='select'>\n";
        }

        // Weblog selection pull-down menu
        // Fetch the names of all weblogs and write each one in an <option> field
        $query = DB::table('weblogs')
            ->select('weblog_name', 'id AS weblog_id', 'category_group_id');

        // If the user is restricted to specific blogs, add that to the query
        if (Session::userdata('member_group_id') != 1) {
            $query->whereIn('weblog_id', $allowed_blogs);
        }

        $query = $query->orderBy('weblog_name')->get();

        if ($query->count() == 1) {
            $weblog_id = $query->first()->weblog_id;
            $category_group_id = $query->first()->category_group_id;
        } elseif($weblog_id != '') {
            foreach($query as $row) {
                if ($row->weblog_id == $weblog_id) {
                    $weblog_id = $row->weblog_id;
                    $category_group_id = $row->category_group_id;
                }
            }
        }

        $s .= Cp::input_select_option('null', __('kilvin::publish.filter_by_weblog'));

        if ($query->count() > 1) {
            $s .= Cp::input_select_option('null',  __('kilvin::cp.all'));
        }

        $selected = '';

        foreach ($query as $row) {
            if ($weblog_id != '') {
                $selected = ($weblog_id == $row->weblog_id) ? 'y' : '';
            }

            $s .= Cp::input_select_option($row->weblog_id, $row->weblog_name, $selected);
        }

        $s .= Cp::input_select_footer().'&nbsp;';

        // ------------------------------------
        //  Category Pulldown
        // ------------------------------------

        $s .= Cp::input_select_header('category_id').
              Cp::input_select_option('', __('kilvin::publish.filter_by_category'));

        if ($total_blogs > 1) {
            $s .= Cp::input_select_option('all', __('kilvin::cp.all'), ($cat_id == 'all') ? 'y' : '');
        }

        $s .= Cp::input_select_option('none', __('kilvin::publish.none'), ($cat_id == 'none') ? 'y' : '');

        if ($category_group_id != '') {
            $query = DB::table('categories')
                ->select('id AS category_id', 'category_name', 'category_group_id', 'parent_id');

            if ($this->nest_categories == 'y') {
                $query->orderBy('category_group_id')->orderBy('parent_id');
            }

            $query = $query->orderBy('category_name')->get();

            $categories = [];

            if ($query->count() > 0) {
                foreach ($query as $row) {
                    $categories[] = [$row->category_group_id, $row->category_id, $row->category_name, $row->parent_id];
                }

                if ($this->nest_categories == 'y') {
                    $this->cat_array = [];

                    foreach($categories as $key => $val) {
                        if (0 == $val[3]) {
                            $this->cat_array[] = array($val[0], $val[1], $val[2]);
                            $this->categoryEditSubtree($val[1], $categories, $depth=1);
                        }
                    }
                } else {
                    $this->cat_array = $categories;
                }
            }

            foreach($this->cat_array as $key => $val) {
                if ( ! in_array($val[0], explode('|',$category_group_id))) {
                    unset($this->cat_array[$key]);
                }
            }

            foreach ($this->cat_array as $ckey => $cat) {
                if ($ckey-1 < 0 OR ! isset($this->cat_array[$ckey-1])) {
                    $s .= Cp::input_select_option('', '-------');
                }

                $s .= Cp::input_select_option($cat[1], str_replace('!-!', '&nbsp;', $cat[2]), (($cat_id == $cat[1]) ? 'y' : ''));

                if (isset($this->cat_array[$ckey+1]) && $this->cat_array[$ckey+1][0] != $cat[0]) {
                    $s .= Cp::input_select_option('', '-------');
                }
            }
        }

        $s .= Cp::input_select_footer().'&nbsp;';

        // ------------------------------------
        //  Status Pulldown
        // ------------------------------------

        $s .= Cp::input_select_header('status').
              Cp::input_select_option('', __('kilvin::publish.filter_by_status')).
              Cp::input_select_option('all', __('kilvin::cp.all'), ($status == 'all') ? 1 : '');

        if ($weblog_id != '') {
            $rez = DB::table('weblogs')
                ->select('status_group_id')
                ->where('id', $weblog_id)
                ->first();

            $query = DB::table('statuses')
                ->select('status')
                ->where('status_group_id', $rez->status_group_id)
                ->orderBy('status_order')
                ->get();

            if ($query->count() > 0) {
                foreach ($query as $row) {
                    $selected = ($status == $row->status) ? 1 : '';
                    $status_name = ($row->status == 'closed' OR $row->status == 'open') ?  __('kilvin::publish.'.$row->status) : $row->status;
                    $s .= Cp::input_select_option($row->status, $status_name, $selected);
                }
            }
        } else {
             $s .= Cp::input_select_option('open', __('kilvin::publish.open'), ($status == 'open') ? 1 : '');
             $s .= Cp::input_select_option('closed', __('kilvin::publish.closed'), ($status == 'closed') ? 1 : '');
        }

        $s .= Cp::input_select_footer().
              '&nbsp;';

        // ------------------------------------
        //  Date Range Pulldown
        // ------------------------------------

        $sel_1 = ($date_range == '1')   ? 1 : '';
        $sel_2 = ($date_range == '7')   ? 1 : '';
        $sel_3 = ($date_range == '31')  ? 1 : '';
        $sel_4 = ($date_range == '182') ? 1 : '';
        $sel_5 = ($date_range == '365') ? 1 : '';

        $s .= Cp::input_select_header('date_range').
              Cp::input_select_option('', __('kilvin::publish.date_range')).
              Cp::input_select_option('1', __('kilvin::publish.today'), $sel_1).
              Cp::input_select_option('7', __('kilvin::publish.past_week'), $sel_2).
              Cp::input_select_option('31', __('kilvin::publish.past_month'), $sel_3).
              Cp::input_select_option('182', __('kilvin::publish.past_six_months'), $sel_4).
              Cp::input_select_option('365', __('kilvin::publish.past_year'), $sel_5).
              Cp::input_select_option('', __('kilvin::publish.any_date')).
              Cp::input_select_footer().
              '&nbsp;';

        // ------------------------------------
        //  Order By Pulldown
        // ------------------------------------

        $options = [
            'entry_date-asc'  => __('kilvin::publish.ascending'),
            'entry_date-desc' => __('kilvin::publish.descending'),
            'title-asc'       => __('kilvin::publish.title_asc'),
            'title-desc'      => __('kilvin::publish.title_desc'),
        ];

        $s .= Cp::input_select_header('order').
              Cp::input_select_option('', __('kilvin::publish.order'));

        foreach( $options as $k => $v) {
            $s .= Cp::input_select_option(
                $k,
                $v,
                ($order == $k));
        }

        $s .= Cp::input_select_footer().'&nbsp;';

        // ------------------------------------
        //  Per Page, can come from multiple sources
        //  - Form submission
        //  - A CP path variable
        //  - A session variable (we store a "preference")
        // ------------------------------------

        if (Request::filled('perpage')) {
            $perpage = Request::input('perpage');
        } elseif (Cp::pathVar('perpage')) {
            $perpage = Cp::pathVar('perpage');
        } elseif (session()->has('perpage')) {
            $perpage = session()->has('perpage');
        }

        // Sanity check
        if (empty($perpage) || !is_numeric($perpage) || $perpage > 150) {
            $perpage = 50;
        }

        session('perpage', $perpage);

        $s .= Cp::input_select_header('perpage').
              Cp::input_select_option('25', '25 '.__('kilvin::publish.results'), ($perpage == 25)  ? 1 : '').
              Cp::input_select_option('50', '50 '.__('kilvin::publish.results'), ($perpage == 50)  ? 1 : '').
              Cp::input_select_option('75', '75 '.__('kilvin::publish.results'), ($perpage == 75)  ? 1 : '').
              Cp::input_select_option('100', '100 '.__('kilvin::publish.results'), ($perpage == 100)  ? 1 : '').
              Cp::input_select_option('150', '150 '.__('kilvin::publish.results'), ($perpage == 150)  ? 1 : '').
              Cp::input_select_footer().
              '&nbsp;';

        $s .= '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        // ------------------------------------
        //  New Row! Keywords!
        // ------------------------------------

        $s .= '<tr>'.PHP_EOL.
              Cp::td('littlePadding', '', '7').PHP_EOL;

        $keywords = '';

        // Form Keywords
        if (Request::filled('keywords')) {
            $keywords = Request::input('keywords');
        }

        // Pagination Keywords
        if (Request::filled('pkeywords')) {
            $keywords = trim(base64_decode(Request::input('pkeywords')));
        }

        // IP Search! WHEE!
        if (substr(strtolower($keywords), 0, 3) == 'ip:') {
            $keywords = str_replace('_','.',$keywords);
        }

        $exact_match = (Request::input('exact_match') != '') ? Request::input('exact_match') : '';

        $s .= Cp::div('default').__('kilvin::publish.keywords').NBS;
        $s .= Cp::input_text('keywords', $keywords, '40', '200', 'input', '200px').NBS;
        $s .= Cp::input_checkbox('exact_match', 'yes', $exact_match).NBS.__('kilvin::publish.exact_match').NBS;

        $search_in = (Request::input('search_in') != '') ? Request::input('search_in') : 'title';

        $s .= Cp::input_select_header('search_in').
              Cp::input_select_option('title', __('kilvin::publish.title_only'), ($search_in == 'title') ? 1 : '').
              Cp::input_select_option('body', __('kilvin::publish.title_and_body'), ($search_in == 'body') ? 1 : '').
              Cp::input_select_footer().
              '&nbsp;';

        // ------------------------------------
        //  Submit! Submit!
        // ------------------------------------

        $s .= Cp::input_submit(__('kilvin::publish.search'), 'submit');
        $s .= '</div>'.PHP_EOL;

        $s .= '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL.
              '</table>'.PHP_EOL;

        $s .= '</div>'.PHP_EOL;
        $s .= '</form>'.PHP_EOL;

        $r .= $s;

        // ------------------------------------
        //  Fetch the searchable fields
        // ------------------------------------

        $fields = [];

        $query = DB::table('weblogs');

        if ($weblog_id != '') {
            $query->where('id', $weblog_id);
        }

        $weblog_field_group_ids = $query->pluck('weblog_field_group_id')->all();

        if (!empty($weblog_field_group_id)) {
            $fields = DB::table('weblog_fields')
                ->whereIn('weblog_field_group_id', $weblog_field_group_ids)
                ->whereIn('field_type', ['text', 'textarea', 'select'])
                ->pluck('field_handle')
                ->all();
        }

        // ------------------------------------
        //  Build the main query
        // ------------------------------------

        $pageurl = [];

        $search_query = DB::table('weblog_entries')
            ->join('weblogs', 'weblog_entries.weblog_id', '=', 'weblogs.id')
            ->join('weblog_entry_data', 'weblog_entries.id', '=', 'weblog_entry_data.weblog_entry_id')
            ->leftJoin('members', 'members.id', '=', 'weblog_entries.author_id')
            ->select('weblog_entries.id AS entry_id');

        // ---------------------------------------
        //  JOINS
        // ---------------------------------------

        if ($cat_id == 'none' or (!empty($cat_id) && is_numeric($cat_id))) {
            $search_query->leftJoin('weblog_entry_categories', 'weblog_entries.id', '=', 'weblog_entry_categories.weblog_entry_id')
                  ->leftJoin('categories', 'weblog_entry_categories.category_id', '=', 'categories.id');
        }

        // ---------------------------------------
        //  Limit to weblogs assigned to user
        // ---------------------------------------

        $search_query->whereIn('weblog_entries.weblog_id', $allowed_blogs);

        if ( ! Session::access('can_edit_other_entries') AND ! Session::access('can_view_other_entries')) {
            $search_query->where('weblog_entries.author_id', Session::userdata('member_id'));
        }

        // ---------------------------------------
        //  Exact Values
        // ---------------------------------------

        if ($weblog_id) {
            $pageurl['weblog_id'] = $weblog_id;

            $search_query->where('weblog_entries.weblog_id', $weblog_id);
        }

        if ($date_range) {
            $pageurl['date_range'] = $date_range;

            $search_query->where('weblog_entries.entry_date', '>', Carbon::now()->subDays($date_range));
        }

        if (is_numeric($cat_id)) {
            $pageurl['category_id'] = $cat_id;

            $search_query->where('weblog_entry_categories.category_id', $cat_id);
        }

        if ($cat_id == 'none') {
            $pageurl['category_id'] = $cat_id;

            $search_query->whereNull('weblog_entry_categories.weblog_entry_id');
        }

        if ($status && $status != 'all') {
            $pageurl['status'] = $status;

            $search_query->where('weblog_entries.status', $status);
        }

        // ---------------------------------------
        //  Keywords
        // ---------------------------------------

        if ($keywords != '') {
            $search_query = $this->editKeywordsSearch($search_query, $keywords, $search_in, $exact_match, $fields);

            $pageurl['pkeywords'] = $keywords;

            if ($exact_match == 'yes') {
                $pageurl['exact_match'] = 'yes';
            }

            $pageurl['search_in'] = $search_in;
        }

        // ---------------------------------------
        //  Order By!
        // ---------------------------------------

        if ($order) {
            $pageurl['order'] = $order;

            switch ($order) {
                case 'entry_date-asc'   : $search_query->orderBy('entry_date', 'asc');
                    break;
                case 'entry_date-desc'  :  $search_query->orderBy('entry_date', 'desc');
                    break;
                case 'title-asc'        :  $search_query->orderBy('title', 'asc');
                    break;
                case 'title-desc'       :  $search_query->orderBy('title', 'desc');
                    break;
                default                 :  $search_query->orderBy('entry_date', 'desc');
            }
        } else {
             $search_query->orderBy('entry_date', 'desc');
        }

        // For entries with the same date, we add Title in there to insure
        // consistency in the displaying of results
        $search_query->orderBy('title', 'desc');

        // ------------------------------------
        //  Are there results?
        // ------------------------------------

        $total_query = clone $search_query;

        $total_count = $total_query->count();

        if ($total_count == 0) {
            $r .= Cp::quickDiv('highlight', BR.__('kilvin::publish.no_entries_matching_that_criteria'));

            Cp::$title = __('kilvin::cp.content').Cp::breadcrumbItem(__('kilvin::publish.edit_weblog_entries'));
			Cp::$body  = $r;
			Cp::$crumb = __('kilvin::publish.edit_weblog_entries');

			return;
        }

        // Get the current row number and add the LIMIT clause to the SQL query
        if ( ! $rownum = Request::input('rownum')) {
            $rownum = 0;
        }

        // ------------------------------------
        //  Run the query again, fetching ID numbers
        // ------------------------------------

        $query = clone $search_query;
        $query = $query->offset($rownum)->limit($perpage)->get();

        $pageurl['perpage'] = $perpage;

        $entry_ids = $query->pluck('entry_id')->all();

        // ------------------------------------
        //  Fetch the weblog information we need later
        // ------------------------------------

        $w_array = DB::table('weblogs')
            ->pluck('weblog_name', 'id')
            ->all();

        $r .= Cp::magicCheckboxesJavascript();

        // Build the item headings
        // Declare the "multi edit actions" form
        $r .= Cp::formOpen(
           [
                'action' => 'content/entries-edit-form',
                'name'  => 'target',
                'id'    => 'target'
            ]
        );

        // ------------------------------------
        //  Build the output table
        // ------------------------------------

        $r .=
            Cp::table('tableBorderNoTop row-hover', '0', '', '100%').
            '<tr>'.PHP_EOL.
                Cp::tableCell('tableHeadingAlt', '#').
                Cp::tableCell('tableHeadingAlt', __('kilvin::publish.title')).
                Cp::tableCell('tableHeadingAlt', __('kilvin::publish.author')).
                Cp::tableCell('tableHeadingAlt', __('kilvin::publish.entry_date')).
                Cp::tableCell('tableHeadingAlt', __('kilvin::publish.weblog')).
                Cp::tableCell('tableHeadingAlt', __('kilvin::publish.status')).
                Cp::tableCell('tableHeadingAlt', Cp::input_checkbox('toggle_all')).
            '</tr>'.
            PHP_EOL;

        // ------------------------------------
        //  Build and run the full SQL query
        // ------------------------------------

        $query = DB::table('weblog_entries')
            ->leftJoin('weblogs', 'weblog_entries.weblog_id', '=', 'weblogs.id')
            ->leftJoin('weblog_entry_data', 'weblog_entries.id', '=', 'weblog_entry_data.weblog_entry_id')
            ->leftJoin('members', 'members.id', '=', 'weblog_entries.author_id')
            ->select(
                'weblog_entries.id AS entry_id',
                'weblog_entries.weblog_id',
                'weblog_entry_data.title',
                'weblog_entries.author_id',
                'weblog_entries.status',
                'weblog_entries.entry_date',
                'weblogs.live_look_template',
                'members.email',
                'members.screen_name')
            ->whereIn('weblog_entries.id', $entry_ids);

        // ---------------------------------------
        //  Order By!
        // ---------------------------------------

        if ($order) {
            switch ($order) {
                case 'entry_date-asc'   : $query->orderBy('entry_date', 'asc');
                    break;
                case 'entry_date-desc'  :  $query->orderBy('entry_date', 'desc');
                    break;
                case 'title-asc'        :  $query->orderBy('title', 'asc');
                    break;
                case 'title-desc'       :  $query->orderBy('title', 'desc');
                    break;
                default                 :  $query->orderBy('entry_date', 'desc');
            }
        } else {
             $query->orderBy('entry_date', 'desc');
        }

        // For entries with the same date, we add Title in there to insure
        // consistency in the displaying of results
        $query->orderBy('title', 'desc');

        $query = $query->get();

        // load the site's templates
        $templates = [];

        $tquery = DB::table('templates')
        	->join('sites', 'sites.id', '=', 'templates.site_id')
            ->select('templates.folder', 'templates.template_name', 'templates.id as template_id', 'sites.site_name')
            ->orderBy('templates.folder')
            ->orderBy('templates.template_name')
            ->get();


        foreach ($tquery as $row) {
            $templates[$row->template_id] = $row->site_name.': '.$row->folder.'/'.$row->template_name;
        }

        // Loop through the main query result and write each table row
        $i = 0;

        foreach($query as $row) {
            $tr  = '<tr>'.PHP_EOL;

            // Entry ID number
            $tr .= Cp::tableCell('', $row->entry_id);

            // Weblog entry title (view entry)
            $tr .= Cp::tableCell('',
                Cp::anchor(
                    'content/edit-entry/entry_id='.$row->entry_id,
                    '<b>'.$row->title.'</b>'
                )
            );

            // Username
            $name = Cp::mailto($row->email, $row->screen_name, 'title="Send an email to '.$row->screen_name.'"');

            $tr .= Cp::tableCell('', $name);
            $tr .= Cp::td().
                Cp::quickDiv(
                    'noWrap',
                    Localize::createHumanReadableDateTime($row->entry_date)
                ).
                '</td>'.PHP_EOL;

            // Weblog
            $tr .= Cp::tableCell('', (isset($w_array[$row->weblog_id])) ? Cp::quickDiv('noWrap', $w_array[$row->weblog_id]) : '');

            // Status
            $tr .= Cp::td();
            $tr .= $row->status;
            $tr .= '</td>'.PHP_EOL;

            // Delete checkbox
            $tr .= Cp::tableCell('', Cp::input_checkbox('toggle[]', $row->entry_id, '' , ' id="delete_box_'.$row->entry_id.'"'));

            $tr .= '</tr>'.PHP_EOL;
            $r .= $tr;

        } // End foreach

        $r .= '</table>'.PHP_EOL;

        $r .= Cp::table('', '0', '', '100%');
        $r .= '<tr>'.PHP_EOL.
              Cp::td();

        $pageurl = kilvin_cp_url('content/list-entries').'?'.http_build_query($pageurl);

        // Pass the relevant data to the paginate class
        $r .=  Cp::div('crumblinks').
               Cp::pager(
                $pageurl,
                $total_count,
                $perpage,
                $rownum,
                'rownum'
              ).
              '</div>'.PHP_EOL.
              '</td>'.PHP_EOL.
              Cp::td('defaultRight');

        // Delete button
        $r .= Cp::div('littlePadding');

        $r .= Cp::input_submit(__('kilvin::cp.submit'));

        $r .= NBS.Cp::input_select_header('action').
              Cp::input_select_option('edit', __('kilvin::publish.edit_selected')).
              Cp::input_select_option('delete', __('kilvin::publish.delete_selected')).
              Cp::input_select_option('edit', '------').
              Cp::input_select_option('add_categories', __('kilvin::publish.add_categories')).
              Cp::input_select_option('remove_categories', __('kilvin::publish.remove_categories')).
              Cp::input_select_footer();

        $r .= '</div>'.PHP_EOL;

        $r .= '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL.
              '</table>'.PHP_EOL.
              '</form>'.PHP_EOL;


        Cp::$title = __('kilvin::cp.content').Cp::breadcrumbItem(__('kilvin::publish.edit_weblog_entries'));
        Cp::$crumb = __('kilvin::publish.edit_weblog_entries');

        // Set output data
        return Cp::$body = $r;
    }

   /**
     * Keywords search for Edit Page
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $keywords
     * @param string $search_in title/body/everywhere
     * @param string $exact_match  yes/no
     * @param array $fields
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function editKeywordsSearch($query, $keywords, $search_in, $exact_match, $fields)
    {
        return $query->where(function($q) use ($keywords, $search_in, $exact_match, $fields) {
            if ($exact_match == 'yes') {
                $q->where('weblog_entry_data.title', '=', $keywords);
            } else {
                $q->where('weblog_entry_data.title', 'LIKE', '%'.$keywords.'%');
            }

            if ($search_in == 'body' OR $search_in == 'everywhere') {
                foreach ($fields as $val) {
                    if ($exact_match == 'yes') {
                        $q->orWhere('weblog_entry_data.field_'.$val, '=', $keywords);
                    } else {
                        $q->orWhere('weblog_entry_data.field_'.$val, 'LIKE', '%'.$keywords.'%');
                    }
                }
            }
        });
    }

   /**
    * Category dropdown for "Edit" section
    *
    * @param string $cat_id Category ID building from
    * @param array $categories List of all categories
    * @param integer $depth The current depth
    * @return void
    */
    public function categoryEditSubtree($cat_id, $categories, $depth)
    {
        $spcr = '!-!';

        $indent = $spcr.$spcr.$spcr.$spcr;

        if ($depth == 1)
        {
            $depth = 4;
        }
        else
        {
            $indent = str_repeat($spcr, $depth).$indent;

            $depth = $depth + 4;
        }

        $sel = '';

        foreach ($categories as $key => $val)
        {
            if ($cat_id == $val[3])
            {
                $pre = ($depth > 2) ? $spcr : '';

                $this->cat_array[] = array($val[0], $val[1], $pre.$indent.$spcr.$val[2]);

                $this->categoryEditSubtree($val[1], $categories, $depth);
            }
        }
    }

   /**
    * JS Filtering code for "Edit" page
    *
    * @return string
    */
    private function editFilteringMenus()
    {
        // ------------------------------------
        //  All Categories
        // ------------------------------------

        $query = DB::table('categories')
            ->select('id AS category_id', 'category_name', 'category_group_id', 'parent_id');

        if ($this->nest_categories == 'y') {
            $query->orderBy('category_group_id')
                ->orderBy('parent_id');
        }

        $query = $query->orderBy('category_name')->get();

        $categories = [];

        if ($query->count() > 0) {
            foreach ($query as $row) {
                $categories[] = [$row->category_group_id, $row->category_id, $row->category_name, $row->parent_id];
            }

            if ($this->nest_categories == 'y')
            {
                foreach($categories as $key => $val)
                {
                    if (0 == $val[3])
                    {
                        $this->cat_array[] = [$val[0], $val[1], $val[2]];
                        $this->categoryEditSubtree($val[1], $categories, $depth=1);
                    }
                }
            }
            else
            {
                $this->cat_array = $categories;
            }
        }

        // ------------------------------------
        //  All Statuses
        // ------------------------------------

        $query = DB::table('statuses')
            ->orderBy('status_order')
            ->select('status_group_id', 'status')
            ->get();

        foreach ($query as $row)
        {
            $statuses[$row->status_group_id][]  = $row->status;
        }

        // ------------------------------------
        //  Build Weblogs Array - Simplified
        // ------------------------------------

        $weblogs = [];

        $allowed_blogs = array_keys(Session::userdata('assigned_weblogs'));

        if (count($allowed_blogs) > 0)
        {
            $query = DB::table('weblogs')
                ->select('id AS weblog_id', 'category_group_id', 'status_group_id');

            if (Session::userdata('member_group_id') != 1) {
                $query->whereIn('weblog_id', $allowed_blogs);
            }

            $query = $query->orderBy('weblog_name')->get();

            foreach ($query as $row)
            {
                $weblogs[$row->weblog_id] = [
                    'categories' => [],
                    'statuses'   => []
                ];

                if (!empty($statuses[$row->status_group_id])) {
                    foreach($statuses[$row->status_group_id] as $status) {
                        $weblogs[$row->weblog_id]['statuses'][] =
                            '<option value="">'.$status.'</option>';
                    }
                }

                if (!empty($row->category_group_id)) {
                    $groups = explode('|', $row->category_group_id);

                    foreach($groups as $group) {

                        $weblogs[$row->weblog_id]['categories'][] =
                                '<option value="">------</option>';

                        foreach($this->cat_array as $v) {
                            if($v[0] != $group) {
                                continue;
                            }

                            $weblogs[$row->weblog_id]['categories'][] =
                                '<option value="'.$v[1].'">'.addslashes($v[2]).'</option>';

                        }
                    }
                }
            }
        }

        ob_start();

?>

<script type="text/javascript">

function changeFilterMenu()
{
    var categories = new Array();
    var statuses   = new Array();

    var c = 0;
    var s = 0;

    categories[c] = '<option value=""><?php echo __('kilvin::cp.all'); ?></option>'; c++;
    categories[c] = '<option value="none"><?php echo __('kilvin::publish.none'); ?></option>'; c++;
    statuses[s]   = '<option value="all"><?php echo __('kilvin::cp.all'); ?></option>'; s++;

    var blog = $('select[name=weblog_id]').first().val();

    if (blog == "null")
    {
        statuses[s] = '<option value="open">open</option>'; s++;
        statuses[s] = '<option value="open">closed</option>'; s++;
    }

<?php foreach ($weblogs as $weblog_id => $groups) { ?>

    if (blog == <?php echo $weblog_id; ?>)
    {
        <?php foreach($groups['categories'] as $option) { ?>

            categories[c] = '<?php echo $option;?>'; c++;

        <?php } ?>

        <?php foreach($groups['statuses'] as $option) { ?>

            statuses[s] = '<?php echo $option;?>'; s++;

        <?php } ?>
    }

    <?php } ?>


    spaceString = eval("/!-!/g");

    $('select[name=category_id] option').remove();
    $('select[name=status] option').remove();

    var _select = $('select[name=category_id]');

    for (i = 0; i < categories.length; i++)
    {
        _select.append(categories[i].replace(spaceString, String.fromCharCode(160)));
    }

    var _select = $('select[name=status]');

    for (i = 0; i < statuses.length; i++)
    {
        _select.append(statuses[i]);
    }

}

</script>

<?php

        $javascript = ob_get_contents();

        ob_end_clean();

        return $javascript;
    }

   /**
    * Simple Multi-Entry Edit Form
    *
    * @return string
    */
    public function entriesEditForm()
    {
        if ( ! in_array(Request::input('action'), ['edit', 'delete', 'add_categories', 'remove_categories'])) {
            return Cp::unauthorizedAccess();
        }

        if ( ! Request::filled('toggle')) {
        	return redirect(kilvin_cp_url('content'));
        }

        if (Request::input('action') == 'delete') {
            return $this->deleteEntriesConfirm();
        }

        // ------------------------------------
        //  Fetch the entry IDs
        // ------------------------------------

        foreach (Request::input('toggle') as $key => $val) {
            if (!empty($val) && is_numeric($val)) {
                $entry_ids[] = $val;
            }
        }

        if (empty($entry_ids)) {
            return Cp::unauthorizedAccess(__('kilvin::publish.unauthorized_to_edit'));
        }

        // ------------------------------------
        //  Build and run the query
        // ------------------------------------

        $base_query = DB::table('weblog_entries AS t')
            ->join('weblog_entry_data AS d', 'd.weblog_entry_id', '=', 't.id')
            ->join('weblogs AS w', 'w.id', '=', 't.weblog_id')
            ->select('t.id AS entry_id',
                't.weblog_id',
                't.author_id',
                'd.title',
                't.url_title',
                't.entry_date',
                't.status',
                't.sticky')
            ->whereIn('t.weblog_id', array_keys(Session::userdata('assigned_weblogs')))
            ->orderBy('entry_date', 'asc');

        $query = clone $base_query;
        $query = $query->whereIn('t.id', $entry_ids)->get();

        // ------------------------------------
        //  Security check...
        // ------------------------------------

        // Before we show anything we have to make sure that the user is allowed to
        // access the blog the entry is in, and if the user is trying
        // to edit an entry authored by someone else they are allowed to

        $weblog_ids     = [];
        $disallowed_ids = [];
        $assigned_weblogs = array_keys(Session::userdata('assigned_weblogs'));

        foreach ($query as $row) {
            if (! Session::access('can_edit_other_entries') && $row->author_id != Session::userdata('member_id')) {
               $disallowed_ids = $row->entry_id;
            } else {
                $weblog_ids[] = $row->weblog_id;
            }
        }

        // ------------------------------------
        //  Are there disallowed posts?
        //  - If so, we have to remove them....
        // ------------------------------------

        if (count($disallowed_ids) > 0) {
            $disallowed_ids = array_unique($disallowed_ids);

            $new_ids = array_diff($entry_ids, $disallowed_ids);

            // After removing the disallowed entry IDs are there any left?
            if (count($new_ids) == 0) {
                return Cp::unauthorizedAccess(__('kilvin::publish.unauthorized_to_edit'));
            }

            // Run the query one more time with the proper IDs.
            $query = clone $base_query;
            $query = $query->whereIn('t.id', $new_ids)->get();
        }

        // ------------------------------------
        //  Adding/Removing of Categories Breaks Off to Their Own Function
        // ------------------------------------

        if (Request::input('action') == 'add_categories') {
            return $this->multipleCategoriesEdit('add', $query);
        } elseif (Request::input('action') == 'remove_categories') {
            return $this->multipleCategoriesEdit('remove', $query);
        }

        // ------------------------------------
        //  Fetch the status details for weblogs
        // ------------------------------------

        $weblog_query = DB::table('weblogs')
            ->select('id AS weblog_id', 'status_group_id', 'default_status')
            ->whereIn('id', $weblog_ids)
            ->get();

        // ------------------------------------
        //  Fetch disallowed statuses
        // ------------------------------------

        $no_status_access = [];

        if (Session::userdata('member_group_id') != 1) {
            $no_status_access = DB::table('status_no_access')
                ->select('status_id')
                ->where('member_group_id', Session::userdata('member_group_id'))
                ->pluck('status_id')
                ->all();
        }

        // ------------------------------------
        //  Build the output
        // ------------------------------------

        $r  = Cp::formOpen(['action' => 'content/update-multiple-entries']);
        $r .= '<div class="tableHeading">'.__('kilvin::publish.multi_entry_editor').'</div>';

        foreach ($query as $row) {
            $r .= Cp::input_hidden('entry_id['.$row->entry_id.']', $row->entry_id);
            $r .= Cp::input_hidden('weblog_id['.$row->entry_id.']', $row->weblog_id);

            $r .= PHP_EOL.'<div class="publish-box">';

            $r .= PHP_EOL."<table class='clusterBox' border='0' cellpadding='0' cellspacing='0' style='width:99%'><tr>";

            $r .= PHP_EOL.'<td class="publishItemWrapper" valign="top" style="width:45%;">'.BR;
            $r .= Cp::div('clusterLineR');

            $r .= Cp::heading(__('kilvin::publish.title'), 5).
                  Cp::input_text('title['.$row->entry_id.']', $row->title, '20', '100', 'input', '95%');

            $r .= Cp::quickDiv('defaultSmall', NBS);

            $r .= Cp::heading(__('kilvin::publish.url_title'), 5).
                  Cp::input_text('url_title['.$row->entry_id.']', $row->url_title, '20', '75', 'input', '95%');

            $r .= '</div>'.PHP_EOL;
            $r .= '</td>';

            // ------------------------------------
            //  Status pull-down menu
            // ------------------------------------

            $status_queries = [];
            $status_menu = '';

            foreach ($weblog_query as $weblog_row)
            {
                if ($weblog_row->weblog_id != $row->weblog_id) {
                    continue;
                }

                $status_query = DB::table('statuses')
                    ->where('status_group_id', $weblog_row->status_group_id)
                    ->orderBy('status_order')
                    ->get();

                $menu_status = '';

                if ($status_query->count() == 0)
                {
                    // No status group assigned, only Admins can create 'open' entries
                    if (Session::userdata('member_group_id') == 1) {
                        $menu_status .= Cp::input_select_option('open', __('kilvin::cp.open'), ($row->status == 'open') ? 1 : '');
                    }

                    $menu_status .= Cp::input_select_option('closed', __('kilvin::cp.closed'), ($row->status == 'closed') ? 1 : '');
                }
                else
                {
                    $no_status_flag = true;

                    foreach ($status_query as $status_row)
                    {
                        $selected = ($row->status == $status_row->status) ? 1 : '';

                        if (in_array($status_row->id, $no_status_access))
                        {
                            continue;
                        }

                        $no_status_flag = false;

                        $status_name =
                            ($status_row->status == 'open' OR $status_row->status == 'closed') ?
                            __('kilvin::publish.'.$status_row->status) :
                            escape_attribute($status_row->status);

                        $menu_status .= Cp::input_select_option(escape_attribute($status_row->status), $status_name, $selected);
                    }

                    // ------------------------------------
                    //  No Statuses? Default is Closed
                    // ------------------------------------

                    if ($no_status_flag == TRUE) {
                        $menu_status .= Cp::input_select_option('closed', __('kilvin::cp.closed'));
                    }
                }

                $status_menu = $menu_status;
            }

            $r .= PHP_EOL.'<td class="publishItemWrapper" valign="top" style="width:25%;">'.BR;
            $r .= Cp::div('clusterLineR');
            $r .= Cp::heading(__('kilvin::publish.entry_status'), 5);
            $r .= Cp::input_select_header('status['.$row->entry_id.']');
            $r .= $status_menu;
            $r .= Cp::input_select_footer();

            $r .= Cp::div('paddingTop');
            $r .= Cp::heading(__('kilvin::publish.entry_date'), 5);
            $r .= Cp::input_text('entry_date['.$row->entry_id.']', Localize::createHumanReadableDateTime($row->entry_date), '18', '23', 'input', '150px');
            $r .= '</div>'.PHP_EOL;

            $r .= '</div>'.PHP_EOL;
            $r .= '</td>';

            $r .= PHP_EOL.'<td class="publishItemWrapper" valign="top" style="width:30%;">'.BR;

            $r .= Cp::heading(NBS.__('kilvin::publish.sticky'), 5);
            $r .= '<label>'.
                Cp::input_checkbox('sticky['.$row->entry_id.']', 'y', $row->sticky).
                ' '.
                __('kilvin::publish.sticky').
            '</label>';

            $r .= '</td>';

            $r .= "</tr></table>";

            $r .= '</div>'.PHP_EOL;
        }

        $r .= Cp::quickDiv('paddingTop', Cp::input_submit(__('kilvin::cp.update'))).
              '</form>'.PHP_EOL;

        Cp::$title = __('kilvin::publish.multi_entry_editor');
        Cp::$crumb = __('kilvin::publish.multi_entry_editor');
        Cp::$body  = $r;
    }

   /**
    * Multi-Entry Edit submission processing
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
    public function updateMultipleEntries()
    {
        if ( ! is_array(Request::input('entry_id')) or ! is_array(Request::input('weblog_id'))) {
            return Cp::unauthorizedAccess();
        }

        $titles      = Request::input('title');
        $url_titles  = Request::input('url_title');
        $entry_dates = Request::input('entry_date');
        $statuses    = Request::input('status');
        $stickys     = Request::input('sticky');
        $weblog_ids  = Request::input('weblog_id');

        foreach (Request::input('entry_id') as $id) {
            $weblog_id = $weblog_ids[$id];

            if(empty($titles[$id])) {
                continue;
            }

            $data = [
                'title'             => strip_tags($titles[$id]),
                'url_title'         => $url_titles[$id],
                'entry_date'        => $entry_dates[$id],
                'status'            => $statuses[$id],
                'sticky'            => (isset($stickys[$id]) AND $stickys[$id] == 'y') ? 'y' : 'n',
            ];

            $error = [];

            // ------------------------------------
            //  No entry title? Assign error.
            // ------------------------------------

            if ($data['title'] == '') {
                $error[] = __('kilvin::publish.missing_title');
            }

            // ------------------------------------
            //  Is the title unique?
            // ------------------------------------

            if ($data['title'] != '') {
				// Do we have a URL title?
                // If not, create one from the title
                if ($data['url_title'] == '') {
                    // Forces a lower case
                    $data['url_title'] = create_url_title($data['title'], true);
                }

                // Kill all the extraneous characters.
                // We want the URL title to pure alpha text
                $data['url_title'] = create_url_title($data['url_title']);

                // Is the url_title a pure number?  If so we show an error.
                if (is_numeric($data['url_title'])) {
                    $error[] = __('kilvin::publish.url_title_is_numeric');
                }

                // Field is limited to 75 characters, so trim url_title before unique work below
                $data['url_title'] = substr($data['url_title'], 0, 70);

                // ------------------------------------
                //  Is URL title unique?
                // ------------------------------------

                $unique = false;
                $i = 0;

                while ($unique == false) {
                    $temp = ($i == 0) ? $data['url_title'] : $data['url_title'].'-'.$i;
                    $i++;

                    $unique_query = DB::table('weblog_entries')
                        ->where('url_title', $temp)
                        ->where('weblog_id', $weblog_id);

                    if ($id != '') {
                        $unique_query->where('id', '!=', $id);
                    }

                     if ($unique_query->count() == 0) {
                        $unique = true;
                     }

                     // Safety
                     if ($i >= 50) {
                        $error[] = __('kilvin::publish.url_title_not_unique');
                        break;
                     }
                }

                $data['url_title'] = $temp;
            }

            // ------------------------------------
            //  No date? Assign error.
            // ------------------------------------

            if ($data['entry_date'] == '') {
                $error[] = __('kilvin::publish.missing_date');
            }

            // ------------------------------------
            //  Convert the date to a Unix timestamp
            // ------------------------------------

            $data['entry_date'] = Localize::humanReadableToUtcCarbon($data['entry_date']);

            if ( ! $data['entry_date'] instanceof Carbon) {
                $error[] = __('kilvin::publish.invalid_date_formatting');
            }

            // ------------------------------------
            //  Do we have an error to display?
            // ------------------------------------

             if (count($error) > 0) {
                $msg = '';

                foreach($error as $val) {
                    $msg .= Cp::quickDiv('littlePadding', $val);
                }

                return Cp::errorMessage($msg);
             }

            // ------------------------------------
            //  Update the entry
            // ------------------------------------

             DB::table('weblog_entry_data')
                ->where('weblog_entry_id', $id)
                ->update(['title' => $data['title']]);

            unset($data['title']);

            DB::table('weblog_entries')
                ->where('id', $id)
                ->update($data);
        }

        // ------------------------------------
        //  Clear caches if needed
        // ------------------------------------

        if (Site::config('new_posts_clear_caches') == 'y') {
            cms_clear_caching('all');
        }

        return redirect(kilvin_cp_url('content/list-entries'))
            ->with('cp-message', __('kilvin::publish.multi_entries_updated'));
    }

   /**
    * Add/Remove Categories to/from Multiple Entries form
    *
    * @param string $type Edit or Remove?
    * @param object $query
    * @return string
    */
    public function multipleCategoriesEdit($type, $query)
    {
        if ($query->count() == 0) {
            return Cp::unauthorizedAccess(__('kilvin::publish.unauthorized_to_edit'));
        }

        // ------------------------------------
        //  Fetch the category_group_id
        // ------------------------------------

        $sql = "SELECT DISTINCT category_group_id FROM weblogs WHERE weblog_id IN(";

        $weblog_ids = [];
        $entry_ids  = [];

        foreach ($query as $row) {
            $weblog_ids[] = $row->weblog_id;
            $entry_ids[] = $row->entry_id;
        }

        $group_query = DB::table('weblogs')
            ->whereIn('id', $weblog_ids)
            ->distinct()
            ->select('category_group_id')
            ->get();

        $valid = 'n';

        if ($group_query->count() > 0) {
            $valid = 'y';
            $last  = explode('|', $group_query->last()->category_group_id);

            foreach($group_query as $row) {
                $valid_cats = array_intersect($last, explode('|', $row->category_group_id));

                if (sizeof($valid_cats) == 0) {
                    $valid = 'n';
                    break;
                }
            }
        }

        if ($valid == 'n') {
            return Cp::userError( __('kilvin::publish.no_category_group_match'));
        }

        $this->categoryTree(($category_group_id = implode('|', $valid_cats)));

        if (count($this->categories) == 0) {
            $cats = Cp::quickDiv('littlePadding', Cp::quickDiv('highlight', __('kilvin::publish.no_categories')), 'categorytree');
        } else {
            $cats = "<div id='categorytree'>";

            foreach ($this->categories as $val)
            {
                $cats .= $val;
            }

            $cats .= '</div>';
        }

        if (Session::access('can_edit_categories')) {
            $cats .= '<div id="cateditlink" style="padding:0; margin:0;display:none;">';

            if (stristr($category_group_id, '|')) {
                $catq_query = DB::table('category_groups')
                    ->where('id', explode('|', $category_group_id))
                    ->select('group_name', 'category_groups.id AS category_group_id')
                    ->get();

                $links = '';

                foreach($catg_query as $catg_row)
                {
                    $links .= Cp::anchorpop(
                        'weblogs-administration/category-manager'.
                        '/category_group_id='.$catg_row['category_group_id'].
                        '/Z=1',
                        '<b>'.$catg_row['group_name'].'</b>'
                    ).', ';
                }

                $cats .= Cp::quickDiv('littlePadding', '<b>'.__('kilvin::publish.edit_categories').': </b>'.substr($links, 0, -2), '750');
            }
            else
            {
                $cats .= Cp::quickDiv(
                    'littlePadding',
                    Cp::anchorpop(
                        'weblogs-administration/category_editor'.
                        '/category_group_id='.$category_group_id.
                        '/Z=1',
                        '<b>'.__('kilvin::publish.edit_categories').'</b>',
                        '750'
                    )
                );
            }

            $cats .= '</div>';
        }

        // ------------------------------------
        //  Build the output
        // ------------------------------------

        $r  = Cp::formOpen( [
                'action'    => 'content/entries-category-update',
                'name'      => 'entryform',
                'id'        => 'entryform'
            ], [
                'entry_ids' => implode('|', $entry_ids),
                'type'      => ($type == 'add') ? 'add' : 'remove'
            ]
        );

        $r .= <<<EOT

<script type="text/javascript">

    function set_catlink()
    {
        $('#cateditlink').css('display', 'block');
    }

    function swap_categories(str)
    {
    	$('#categorytree').html(str);
    }
</script>
EOT;

        $r .= '<div class="tableHeading">'.__('kilvin::publish.multi_entry_category_editor').'</div>';

        $r .= PHP_EOL.'<div class="publish-box">';

        $r .= Cp::heading(($type == 'add') ? __('kilvin::publish.add_categories') : __('kilvin::publish.remove_categories'), 5);

        $r .= PHP_EOL."<table class='clusterBox' border='0' cellpadding='0' cellspacing='0' style='width:99%'><tr>";
        $r .= PHP_EOL.'<td class="publishItemWrapper" valign="top" style="width:45%;">'.BR;
        $r .= $cats;
        $r .= '</td>';
        $r .= "</tr></table>";

        $r .= '</div>'.PHP_EOL;

        $r .= Cp::quickDiv('paddingTop', Cp::input_submit(__('kilvin::cp.update'))).
              '</form>'.PHP_EOL;

        Cp::$body_props .= ' onload="displayCatLink();" ';
        Cp::$title = __('kilvin::publish.multi_entry_category_editor');
        Cp::$crumb = __('kilvin::publish.multi_entry_category_editor');
        Cp::$body  = $r;
    }

   /**
    * Add/Remove Categories to/from Multiple Entries processing
    *
    * @return string
    */
    public function entriesCategoryUpdate()
    {
        if (!Request::filled('entry_ids') or !Request::filled('type')) {
            return Cp::unauthorizedAccess(__('kilvin::publish.unauthorized_to_edit'));
        }

        if (
            !Request::filled('category') or
            ! is_array(Request::input('category')) or
            sizeof(Request::input('category')) == 0
        ) {
            return Cp::userError( __('kilvin::publish.no_categories_selected'));
        }

        // ------------------------------------
        //  Fetch categories
        // ------------------------------------

        $this->cat_parents = Request::input('category');

        if ($this->assign_cat_parent == true) {
            $this->fetchCategoryParents(Request::input('category'));
        }

        $this->cat_parents = array_unique($this->cat_parents);

        sort($this->cat_parents);

        $entry_ids = [];

        foreach (explode('|', Request::input('entry_ids')) as $entry_id) {
            $entry_ids[] = $entry_id;
        }

        // ------------------------------------
        //  Get Category Group IDs
        // ------------------------------------

        $query = DB::table('weblogs')
            ->select('weblogs.category_group_id')
            ->join('weblog_entries', 'weblog_entries.weblog_id', '=', 'weblogs.id')
            ->whereIn('weblog_entries.id', $entry_ids)
            ->get();

        $valid = 'n';

        if ($query->count() > 0)
        {
            $valid = 'y';
            $last  = explode('|', $query->last()->category_group_id);

            foreach($query as $row)
            {
                $valid_cats = array_intersect($last, explode('|', $row->category_group_id));

                if (sizeof($valid_cats) == 0)
                {
                    $valid = 'n';
                    break;
                }
            }
        }

        if ($valid == 'n') {
            return Cp::userError(__('kilvin::publish.no_category_group_match'));
        }

        // ------------------------------------
        //  Remove Cats, Then Add Back In
        // ------------------------------------

        $valid_cat_ids = DB::table('categories')
            ->where('category_group_id', $valid_cats)
            ->whereIn('id', $this->cat_parents)
            ->pluck('id')
            ->all();

        if (!empty($valid_cat_ids)) {
            DB::table('weblog_entry_categories')
                ->whereIn('category_id', $valid_cat_ids)
                ->whereIn('weblog_entry_id', $entry_ids)
                ->delete();
        }

        if (Request::input('type') == 'add') {

            $insert_cats = array_intersect($this->cat_parents, $valid_cat_ids);

            // How brutish...
            foreach($entry_ids as $id)
            {
                foreach($insert_cats as $val)
                {
                    DB::table('weblog_entry_categories')
                        ->insert(
                        [
                            'weblog_entry_id' => $id,
                            'category_id'     => $val
                        ]);
                }
            }
        }

        // ------------------------------------
        //  Clear caches if needed
        // ------------------------------------

        if (Site::config('new_posts_clear_caches') == 'y') {
            cms_clear_caching('all');
        }

        return redirect(kilvin_cp_url('content'))
        	->with('cp-message', __('kilvin::publish.multi_entries_updated'));
    }

   /**
    * Delete Entries confirmation page
    *
    * @return string
    */
    public function deleteEntriesConfirm()
    {
        if ( ! Session::access('can_delete_self_entries') AND
             ! Session::access('can_delete_all_entries')) {
            return Cp::unauthorizedAccess();
        }

        if ( ! Request::filled('toggle') or !is_array(Request::input('toggle'))) {
            return redirect(kilvin_cp_url('content'));
        }

        $r  = Cp::formOpen(['action' => 'content/delete-entries']);

        $i = 0;
        foreach (Request::input('toggle') as $key => $val) {
            if (!empty($val)) {
                $r .= Cp::input_hidden('delete[]', $val);
                $i++;
            }
        }

        $r .= Cp::quickDiv('alertHeading', __('kilvin::publish.delete_confirm'));
        $r .= Cp::div('box');

        if ($i == 1) {
            $r .= Cp::quickDiv('defaultBold', __('kilvin::publish.delete_entry_confirm'));
        }
        else{
            $r .= Cp::quickDiv('defaultBold', __('kilvin::publish.delete_entries_confirm'));
        }

        // if it's just one entry, let's be kind and show a title
        if ($i == 1) {
            $ids = Request::input('toggle');
            $entry_id = array_pop($ids);

            $query = DB::table('weblog_entry_data')
                ->where('weblog_entry_id', $entry_id)
                ->first(['title']);

            if ($query) {
                $r .= '<br>'.
                      Cp::quickDiv(
                        'defaultBold',
                        str_replace(
                            '%title',
                            $query->title,
                            __('kilvin::publish.entry_title_with_title')
                        )
                      );
            }
        }

        $r .= '<br>'.
              Cp::quickDiv('alert', __('kilvin::cp.action_can_not_be_undone')).
              '<br>'.
              Cp::input_submit(__('kilvin::cp.delete')).
              '</div>'.PHP_EOL.
              '</form>'.PHP_EOL;

        Cp::$title = __('kilvin::publish.delete_confirm');
        Cp::$crumb = __('kilvin::publish.delete_confirm');
        Cp::$body  = $r;
    }

   /**
    * Delete Entries processing
    *
    * @return string
    */
    public function deleteEntries()
    {
        if ( ! Session::access('can_delete_self_entries') AND
             ! Session::access('can_delete_all_entries'))
        {
            return Cp::unauthorizedAccess();
        }

        if ( ! Request::filled('delete') && is_array(Request::input('delete'))) {
            return redirect(kilvin_cp_url('content'));
        }

        $ids = Request::input('delete');

        $query = DB::table('weblog_entries')
            ->whereIn('id', $ids)
            ->select('weblog_id', 'author_id', 'id AS entry_id')
            ->get();

        $allowed_blogs = array_keys(Session::userdata('assigned_weblogs'));

        foreach ($query as $row)
        {
            if (Session::userdata('member_group_id') != 1) {
                if ( ! in_array($row->weblog_id, $allowed_blogs)) {
                    return redirect(kilvin_cp_url('content'));
                }
            }

            if ($row->author_id == Session::userdata('member_id')) {
                if ( ! Session::access('can_delete_self_entries')) {
                    return Cp::unauthorizedAccess(__('kilvin::publish.unauthorized_to_delete_self'));
                }
            } else {
                if ( ! Session::access('can_delete_all_entries')) {
                    return Cp::unauthorizedAccess(__('kilvin::publish.unauthorized_to_delete_others'));
                }
            }
        }

        $entry_ids = [];

        foreach ($query as $row) {
            $entry_ids[] = $row->entry_id;
            $weblog_id = $row->weblog_id;

            DB::table('weblog_entries')->where('id', $row->entry_id)->delete();
            DB::table('weblog_entry_data')->where('weblog_entry_id', $row->entry_id)->delete();
            DB::table('weblog_entry_categories')->where('weblog_entry_id', $row->entry_id)->delete();

            $tot = DB::table('members')
                ->where('id', $row->author_id)
                ->value('total_entries');

            if ($tot > 0) {
                $tot -= 1;
            }

            DB::table('members')
                ->where('id', $row->author_id)
                ->update(['total_entries' => $tot]);

            // Update statistics
            Stats::update_weblog_stats($row->weblog_id);
        }

        // ------------------------------------
        //  Clear caches
        // ------------------------------------

        cms_clear_caching('all');

        // ------------------------------------
        //  Return success message
        // ------------------------------------

        return redirect(kilvin_cp_url('content'))
        	->with('cp-message', __('kilvin::publish.entries_deleted'));
    }

   /**
    * Upload Files Form
    *
    * @return string
    */
    public function uploadFileForm()
    {
        Cp::$title = __('kilvin::publish.file_upload');

        Cp::$body .= Cp::quickDiv('tableHeading', __('kilvin::publish.file_upload'));

        Cp::$body .= Cp::div('box').BR;


        if (Session::userdata('member_group_id') == 1) {
        	$ids = DB::table('asset_container_access')
                ->pluck('asset_container_id')
                ->all();
    	} else {
            $ids = DB::table('asset_container_access')
                ->where('member_group_id', Session::userdata('member_group_id'))
                ->pluck('asset_container_id')
                ->all();
        }

        $query = DB::table('asset_containers')
            ->select('id', 'name')
            ->orderBy('name')
            ->whereIn('id', $ids)
            ->get();

        if ($query->count() == 0) {
            return Cp::unauthorizedAccess();
        }

        Cp::$body .= '<form method="post" action="'.
                kilvin_cp_url('content/upload-file/Z=1').
            '" enctype="multipart/form-data">'.
            "\n";

        Cp::$body .= Cp::input_hidden('weblog_field_group_id', Request::input('weblog_field_group_id'));

        Cp::$body .= Cp::quickDiv('', "<input type=\"file\" name=\"userfile\" size=\"20\" />".BR.BR);

        Cp::$body .= Cp::quickDiv('littlePadding', __('kilvin::publish.select_destination_dir'));

        Cp::$body .= Cp::input_select_header('destination');

        foreach ($query as $row) {
            Cp::$body .= Cp::input_select_option($row->id, $row->name);
        }

        Cp::$body .= Cp::input_select_footer();


        Cp::$body .= Cp::quickDiv('', BR.Cp::input_submit(__('kilvin::publish.upload')).'<br><br>');

        Cp::$body .= '</form>'.PHP_EOL;

        Cp::$body .= '</div>'.PHP_EOL;

        // ------------------------------------
        //  File Browser
        // ------------------------------------

        Cp::$body .= Cp::quickDiv('', BR.BR);

        Cp::$body .= Cp::quickDiv('tableHeading', __('kilvin::filebrowser.file_browser'));
        Cp::$body .= Cp::div('box');

        Cp::$body .= '<form method="post" action="'.kilvin_cp_url('content/file-browser/Z=1')."\" enctype=\"multipart/form-data\">\n";

        Cp::$body .= Cp::input_hidden('weblog_field_group_id', Request::input('weblog_field_group_id'));

        Cp::$body .= Cp::quickDiv('paddingTop', __('kilvin::publish.select_destination_dir'));

        Cp::$body .= Cp::input_select_header('directory');

        foreach ($query as $row)
        {
            Cp::$body .= Cp::input_select_option($row->id, $row->name);
        }

        Cp::$body .= Cp::input_select_footer();


        Cp::$body .= Cp::quickDiv('', BR.Cp::input_submit(__('kilvin::publish.view')));

        Cp::$body .= '</form>'.PHP_EOL;
        Cp::$body .= BR.BR.'</div>'.PHP_EOL;

        Cp::$body .= Cp::quickDiv('littlePadding', BR.'<div align="center"><a href="JavaScript:window.close();">'.__('kilvin::cp.close_window').'</a></div>');
    }

   /**
    * Upload Files - DISABLEd
    *
    * @return string
    */
    public function uploadFile()
    {
        return Cp::errorMessage('Disabled for the time being, sorry');
    }

   /**
    * File Browser
    *
    * @return string
    */
    public function fileBrowser()
    {
        $id = Request::input('directory');
        $weblog_field_group_id = Request::input('weblog_field_group_id');

        Cp::$title = __('kilvin::filebrowser.file_browser');

        $r  = Cp::quickDiv('tableHeading', __('kilvin::filebrowser.file_browser'));
        $r .= Cp::quickDiv('box', 'Disabled for the time being, sorry');

        $query = DB::table('asset_containers')->where('id', $id);

        if ($query->count() == 0) {
            return;
        }

        if (Session::userdata('member_group_id') != 1) {
            $safety_count = DB::table('asset_container_access')
                ->where('asset_container_id', $query->id)
                ->where('member_group_id', Session::userdata('member_group_id'))
                ->count();

            if ($safety_count == 0) {
                return Cp::unauthorizedAccess();
            }
        }

        Cp::$body = $r;
    }
}
