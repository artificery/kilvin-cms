<?php

namespace Kilvin\Cp;

use Kilvin\Facades\Cp;
use Illuminate\Support\Facades\DB;
use Kilvin\Facades\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Request;
use Carbon\Carbon;
use Kilvin\Core\Regex;
use Kilvin\Core\Session;
use Kilvin\Core\Localize;

class Templates
{
    public $template_map   = [];

    // Reserved Template names
    public $reserved_names = ['act', 'css'];

    // Reserved Template Variable names
    public $reserved_vars  =
    [
        'version',
        'elapsed_time',
        'total_queries',
    ];

    private $template_types = [];

    public $site_path = '';
    private $difference_threshold = 30;

   /**
    * Constructor
    *
    * @return void
    */
    public function __construct()
    {
        if (Cp::pathVar('tgpref') AND Cp::segment(2) != '') {
            Cp::$url_append = '/tgpref='.Cp::pathVar('tgpref');
        }

        $this->site_path = KILVIN_TEMPLATES_PATH.Site::config('site_handle').DIRECTORY_SEPARATOR;

        $this->template_types = [
            'atom',
            'css',
            'js',
            'json',
            'html',
            'rss',
            'xml'
        ];
    }

   /**
    * Request Handler
    *
    * @return mixed
    */
    public function run()
    {
        if (Cp::segment(2))
        {
            $method = camel_case(Cp::segment(2));

            if (method_exists($this, $method)) {
                return $this->{$method}();
            }
        }

        return $this->templateManager();
    }

   /**
    * Verify access privileges for Templates Section
    *
    * @param array $data Array of data parameters to check again (unused, so far)
    * @return bool
    */
    private function checkAccess($data = [])
    {
        if (Session::userdata('member_group_id') == 1 or Session::access('can_admin_templates')) {
            return true;
        }

        return false;
    }

   /**
    * Edit Template Settings for Folder
    *
    * @param string $folder
    * @param bool $success
    * @return string
    */
    public function editTemplateNames($folder = null)
    {
        if ( ! $this->checkAccess()) {
            return Cp::unauthorizedAccess();
        }

        if (empty($folder)) {
            if (! $folder = $this->urlFolder(Cp::pathVar('folder'))) {
                return false;
            }
        }

        $query = DB::table('templates')
            ->where('folder', $folder)
            ->select('folder')
            ->first();

        if (!$query) {
            return false;
        }

        $folder = $query->folder;

        Cp::$title  = __('kilvin::templates.template_names');
        Cp::$crumb  = __('kilvin::templates.template_names');

        $r = '';

        if (Cp::pathVar('msg') == 'success') {
            $r .= Cp::quickDiv('success-message', __('kilvin::templates.template_names_updated'));
        }

        $r  .= '<h2>'.sprintf(__('kilvin::templates.templates_within_x_directory'), $folder).'</h2>';

        $r  .= Cp::formOpen(['action' => 'templates/update-template-names'])
              .Cp::input_hidden('folder', $folder);

        $r .= '<table class="tableBorder" cellpadding="0" cellspacing="0" style="width:300px;">'
             .'<tr>'.PHP_EOL
             .Cp::tableCell('tableHeadingAlt', __('kilvin::templates.name_of_template'))
             .Cp::tableCell('tableHeadingAlt', __('kilvin::templates.type'))
             .'</tr>'.PHP_EOL;

        // Fetch template preferences

        $query = DB::table('templates')
            ->select(
                'templates.id AS template_id',
                'template_name',
                'template_type',
                'folder'
            )
            ->where('folder', $folder)
            ->orderBy('template_name')
            ->get();

        foreach ($query as $i => $row) {
            $id = $row->template_name.'__';

            $r .= '<tr>'.PHP_EOL;

            $r .= Cp::input_hidden($id.'old_template_name', $row->template_name);
            $r .= Cp::input_hidden($id.'old_template_type', $row->template_type);

            if ($row->template_name == 'index') {
                $r .= Cp::tableCell('', Cp::quickDiv('defaultBold', $row->template_name));
                $t  = __('kilvin::templates.template_type_'.$row->template_type);
            } else {
                $r .= Cp::tableCell('', Cp::input_text($id.'template_name', $row->template_name, '15', '50', 'input', '110px'));

                $t  = Cp::input_select_header($id.'template_type');

                foreach($this->template_types as $type) {

                    $selected = ($type == $row->template_type) ? true : false;

                    $t .= Cp::input_select_option($type, __('kilvin::templates.template_type_'.$type), $selected);
                }

                $t .= Cp::input_select_footer();
            }

            $r .= Cp::tableCell('', $t);

            $r .='</tr>'.PHP_EOL;
        }

        $r .= '</table>'.PHP_EOL;

        $r .= Cp::quickDiv('littlePadding', Cp::input_submit(__('kilvin::cp.update')))
             .'</form>'.PHP_EOL;

        Cp::$body = $r;
    }

   /**
    * Update Template Names for Folder
    *
    * @return string
    */
    public function updateTemplateNames()
    {
        if ( ! $folder = Request::input('folder')) {
            return false;
        }

        if ( ! $this->checkAccess()) {
            return Cp::unauthorizedAccess();
        }

        $folder_path = $this->site_path.$folder.DIRECTORY_SEPARATOR;

        // Double check folder exists before we do any more work
        if (!File::isDirectory($folder_path)) {
            return Cp::errorMessage(__('kilvin::templates.no_folder_exists'));
        }

        $idx = [];

        $conversion = [];

        foreach (Request::all() as $k => $val) {
            if ( ! stristr($k, "__")) {
                continue;
            }

            $temp = explode("__", $k);
            $id   = $temp[0];

            if ($k == $id.'__template_name') {
                $new_name = Request::input($id.'__template_name');
                $old_name = Request::input($id.'__old_template_name');
                $new_type = Request::input($id.'__template_type');
                $old_type = Request::input($id.'__old_template_type');

                if ($new_name == '') {
                    return Cp::errorMessage(__('kilvin::templates.missing_name'));
                }

                if ( ! preg_match("#^[a-zA-Z0-9_-]+$#i", $new_name)) {
                    return Cp::errorMessage(__('kilvin::templates.template_illegal_characters'));
                }

                if (in_array($new_name, $this->reserved_names)) {
                    return Cp::errorMessage(__('kilvin::templates.reserved_name'));
                }

                $old_path = remove_double_slashes($folder_path.$old_name.'.twig.'.$old_type);
                $new_path = remove_double_slashes($folder_path.$new_name.'.twig.'.$new_type);

                if ($old_path === $new_path) {
                    continue;
                }

                // Could error out but for now just skipping
                if (!File::exists($old_path)) {
                    continue;
                }

                File::move($old_path, $new_path);

                DB::table('templates')
                    ->where('folder', $folder)
                    ->where('template_name', $old_name)
                    ->where('template_type', $old_type)
                    ->where('site_id', Site::config('site_id'))
                    ->update([
                        'template_name' => $new_name,
                        'template_type' => $new_type
                    ]);
            }
        }

        $redirect = 'templates/edit-template-names'.
            '/folder='.$this->safeFolder($folder).
            '/msg=success';

        return redirect(kilvin_cp_url($redirect));
    }

   /**
     * Sync Templates
     *
     * Insures the DB is up to date with existing file Templates
     * Files ALWAYS take priority
     *
     * @return redirect
     */
    public function syncTemplates()
    {
        $number = $this->outOfSyncCheck();

        if ($number == 0) {
            return redirect(kilvin_cp_url('templates'));
        }
    }

   /**
    * Determine Number of Template Files out of Sync with DB
    *
    * @param bool $sync Whether to fix the problem or just do a count
    * @return integer
    */
    private function outOfSyncCheck($sync = true)
    {
        $files     = File::allFiles(rtrim($this->site_path, '/'));

        $templates = DB::table('templates')
            ->where('site_id',  Site::config('site_id'))
            ->select('template_name', 'template_type', 'folder', 'updated_at', 'templates.id AS template_id')
            ->orderby('folder')
            ->orderby('template_name')
            ->get()
            ->keyBy('template_id');

        $different = 0;
        $file_paths = [];

        foreach($files as $file) {

            $ext = $file->getExtension();

            // Invalid extension
            if (!in_array($ext, $this->template_types)) {
                continue;
            }

            $ending = '/.twig.'.$ext.'$/';

            // Missing '.twig.' portion of file name
            if (!preg_match($ending, $file->getFilename())) {
                continue;
            }

            $name    = preg_replace($ending, '', $file->getFilename());
            $folder  = str_replace(rtrim($this->site_path, '/'), '', $file->getPath());
            $file_updated = Localize::createCarbonObject($file->getMTime());

            // getPath() returns no closing slash for path
            // So, if folder is empty, it is the site root which means the folder is /
            if (empty($folder)) {
                $folder = '/';
            }

            $data = [
                'folder' => $folder,
                'template_type' => $ext,
                'template_name' => $name,
                'site_id' => Site::config('site_id'),
                'updated_at' => $file_updated
            ];

            $match = $templates
                ->where('folder', $data['folder'])
                ->where('template_type', $data['template_type'])
                ->where('template_name', $data['template_name'])
                ->first();

            // Does not exist
            if (empty($match)) {
                $different++;

                if ($sync === true) {
                    $this->syncFileTemplate($file, $data);
                }

                continue;
            }

            // Remove template from DB list so we know what templates have been confirmed
            $templates->forget($match->template_id);

            // ------------------------------------
            //  Check for Time Difference between DB + File System
            // ------------------------------------

            $db_updated = Localize::createCarbonObject($match->updated_at);
            $difference = $file_updated->diffInSeconds($db_updated);

            if($difference > $this->difference_threshold) {
                $different++;

                if ($sync === true) {
                    $this->syncFileTemplate($file, $data, $match->template_id);
                }

                continue;
            }
        }

        // ------------------------------------
        //  tTmplates in DB but not on file system => EXTERMINATE!
        // ------------------------------------

        if ($sync === true && $templates->count() > 0) {
            $ids = $templates->pluck('template_id');

            DB::table('templates')->whereIn('id', $ids)->delete();
        }

        return $different;
    }

   /**
     * Sync File Template
     *
     * @param object The File info object
     * @param array Array of information
     * @param integer|null Template id if exists already in DB
     * @return bool
     */
    private function syncFileTemplate($file, $data, $template_id = null)
    {
        $data['template_data'] = file_get_contents($file->getPathname());

        if (empty($template_id)) {
            DB::table('templates')->insert($data);
        }

        if (!empty($template_id)) {
            DB::table('templates')->where('id', $template_id)->update($data);
        }

        return true;
    }

   /**
     * Templates Manager
     *
     * Displays all the folders + templates for the current CP site
     *
     * @return string
     */
    public function templateManager()
    {
        // ------------------------------------
        //  You Do Not Belong Here!
        // ------------------------------------

        if (!$this->checkAccess()) {
            return Cp::unauthorizedAccess();
        }

        // ------------------------------------
        //  Check for Out of Sync Templates
        // ------------------------------------

        $sync_check = $this->outOfSyncCheck();

        // ------------------------------------
        //  Titles + Crumbs
        // ------------------------------------

        Cp::$title  = __('kilvin::cp.design');
        Cp::$crumb = __('kilvin::templates.template_management');

        // ------------------------------------
        //  Can Admin Templates Option at Top
        // ------------------------------------

        $right_links = [];

        $right_links[] = [
            'templates/new-template-folder-form',
            __('kilvin::templates.new_folder')
        ];

        $right_links[] = [
            'templates/template-variables',
            __('kilvin::templates.template_variables')
        ];

        $right_links[] = [
            'administration/config-manager/template-preferences/',
            __('kilvin::templates.template_preferences')
        ];

        $r  = Cp::header(__('kilvin::templates.template_management'), $right_links);

        // ------------------------------------
        //  Messages
        // ------------------------------------

        switch (Cp::pathVar('msg')) {
            case '01' : $message = __('kilvin::templates.folder_created');
                break;
            case '02' : $message = __('kilvin::templates.folder_updated');
                break;
            case '03' : $message = __('kilvin::templates.folder_deleted');
                break;
            case '04' : $message = __('kilvin::templates.template_created');
                break;
            case '05' : $message = __('kilvin::templates.template_deleted');
                break;
            case '06' : $message = __('kilvin::templates.template_updated');
                break;
            default   : $message = '';
                break;
        }

        if ($message != '') {
            $r .= Cp::quickDiv('success-message', $message);
        }

        if (Request::input('keywords') !== null && trim(Request::input('keywords')) != '') {
            $r .= Cp::quickDiv('success-message', __('kilvin::templates.search_terms').NBS. Request::input('keywords'));
        }

        // ------------------------------------
        //  Fetch Templates
        // ------------------------------------

        $query = DB::table('templates')
            ->where('site_id', Site::config('site_id'))
            ->select(
                'folder',
                'templates.id AS template_id',
                'template_name',
                'template_type'
            );

        // ------------------------------------
        //  Add in Keywords Search
        // ------------------------------------

        if (Request::filled('keywords') && trim(Request::input('keywords')) != '') {
            $keywords = Request::input('keywords');

            if (trim($keywords) == '') {
                Cp::$body .= Cp::quickDiv('alert', __('kilvin::templates.no_results'));
                Cp::$body .= Cp::quickDiv('littlePadding', Cp::anchor('templates', __('kilvin::cp.back')));
                return;
            }

            $terms = [];

            if (preg_match_all("/\-*\"(.*?)\"/", $keywords, $matches)) {
                for($m=0; $m < sizeof($matches[1]); $m++)
                {
                    $terms[] = trim(str_replace('"','',$matches[0][$m]));
                    $keywords = str_replace($matches[0][$m],'', $keywords);
                }
            }

            if (trim($keywords) != '') {
                $terms  = array_merge($terms, preg_split("/\s+/", trim($keywords)));
            }

            rsort($terms);

            $query->where(function($q) use ($terms) {
                foreach($terms as $term) {
                    $type  = (substr($term, 0,1) == '-') ? 'NOT LIKE' : 'LIKE';
                    $term  = (substr($term, 0,1) == '-') ? substr($term, 1) : $term;
                    $q->where('template_data', $type, '%'.$term.'%');
                }

            });
        }

        // Final Query!
        $query = $query->orderBy('folder')
            ->orderBy('template_name')
            ->get();

        // ------------------------------------
        //  We're Sorry, No Templates For You
        // ------------------------------------

        if ($query->count() == 0) {
            if (isset($keywords)) {
                Cp::$body .= Cp::quickDiv('alert', __(isset($keywords) ? 'kilvin::templates.no_results' : 'kilvin::templates.no_templates_available'));
                Cp::$body .= Cp::quickDiv('littlePadding', Cp::anchor('templates', __('kilvin::cp.back')));
            } else {
                Cp::$body .= Cp::quickDiv('alert', __('kilvin::templates.no_templates_available'));
            }

            return;
        }

        // ------------------------------------
        //  Begin Template Groups Table
        // ------------------------------------

        $r .= Cp::tableOpen(array('width' => '99%', 'cellpadding' => '1'))
                .'<tr>'.PHP_EOL
                    ."<td valign='top' style='width:210px; padding-top:1px; text-align:left;'>"
                        .Cp::div('')
                            .Cp::quickDiv('tableHeadingAlt', __('kilvin::templates.choose_folder'))
                            .Cp::div('templateAreaBox')
                                ."<select name='folders' class='multiselect' size='15' multiple='multiple' style='width:100%;border:none;'>";

        $current_folder = false;

        // ------------------------------------
        //  Left Hand Column
        //  - Contains select list of template groups
        //  - Also Search Form
        // ------------------------------------

        $tgpref = Cp::pathVar('tgpref');

        foreach($query as $e => $row) {
            if ($row->folder === $current_folder) {
                continue;
            }

            $current_folder = $row->folder;

            if ($tgpref == $this->safeFolder($row->folder)) {
                $r .= Cp::input_select_option(
                    $this->safeFolder($row->folder),
                    escape_attribute($row->folder),
                    'y');
            } else {
                $r .= Cp::input_select_option(
                    $this->safeFolder($row->folder),
                    escape_attribute($row->folder),
                    ($e == 0 && empty($tgpref)) ? 'y' : '');
            }
        }

        $r .= Cp::input_select_footer().
              '</div>'.PHP_EOL.
              '</div>'.PHP_EOL.
              '<br>'.
              Cp::quickDiv('tableHeadingAlt', __('kilvin::templates.search'))
                    .Cp::div('profileMenuInner')
                    .   Cp::formOpen(['action' => 'templates', 'class' => 'cp-form top-margin'])
                    .       Cp::input_text('keywords', '', '20', '120', 'input', '100%')
                    .       Cp::quickDiv('littlePadding', Cp::quickDiv('defaultRight', Cp::input_submit(__('kilvin::templates.search'))))
                    .   '</form>'.PHP_EOL
                    .'</div>'.PHP_EOL.
              '</td>'.PHP_EOL.
              Cp::tableCell('', '', '8px').
              Cp::td('', '', '', '', 'top');

        // ------------------------------------
        //  Right Hand Column (templates in each folder)
        // ------------------------------------

        $current_folder = $query->first()->folder;
        $sitepath = Site::config('site_url');

        $t = $this->templateFolderOpening($query->first());

        foreach ($query as $row) {
            if ($row->folder !== $current_folder) {
                $t .= $this->templateFolderClosing();
                $t .= $this->templateFolderOpening($row);
            }

            // Template Row
            $t .= $this->templateRow($row, $row->folder, $sitepath);

            $current_folder = $row->folder;
        }

        $t .= $this->templateFolderClosing();

        $r .= $t;

        // ------------------------------------
        //  Close Enclosing Table
        // ------------------------------------

        $r .= '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL.
              '</table>'.PHP_EOL;

        // ------------------------------------
        //  Bit of JS
        // ------------------------------------

        $r .= "
<script type='text/javascript'>
$(function() {

    $('select[name=folders] option:selected').each(function() {
        $('#folder_'+$( this ).val()).show();
    });

    $('select[name=folders]').change(function()
    {
        $('.templateFolder').hide();
        $('select[name=folders] option:selected').each(function() {
            $('#folder_'+$( this ).val()).show();
        });
    });
});

</script>";

        Cp::$body = $r;
    }

    // ------------------------------------------------------

    /**
     *  Makes a Folder name safe for URLs and JavaScript (i.e. no slashes)
     *
     *  @param string $folder
     *  @return string
     */
    private function safeFolder($folder)
    {
        return str_replace('/', '_SLASH_', $folder);
    }

    // ------------------------------------------------------

    /**
     *  Takes a folder name from URL and converts back to path
     *
     *  @param string $folder
     *  @return string
     */
    private function urlFolder($string)
    {
        if (empty($string)) {
            return false;
        }

        return str_replace('_SLASH_', '/', $string);
    }

    // ------------------------------------------------------

    /**
     *  Takes a template's data row and creates a row in Template Group table for it.
     *
     *  @param  Collection $row
     *  @param string $current_folder
     *  @param string $sitepath
     *  @return string
     */
    private function templateRow($row, $current_folder, $sitepath)
    {
        $t = '<tr>'.PHP_EOL;

        $viewurl  = rtrim($sitepath, '/');
        $viewurl .= ($row->folder == '/') ? '/' : $row->folder.'/';
        $viewurl .= $row->template_name.'.'.$row->template_type;

        $edit_url = 'templates/edit-template/id='.$row->template_id.'/tgpref='.$this->safeFolder($row->folder);

        $t .= Cp::tableCell(
            '',
            Cp::anchor(
                $edit_url,
                '<strong>'.$row->template_name.'</strong>')
            );

        $t .= Cp::tableCell('', $row->template_type);

        if (substr($row->template_name, 0, 1) == '_') {
            $t .= Cp::tableCell('', '––');
        } else {
            $t .= Cp::tableCell('', Cp::anchor($viewurl, __('kilvin::cp.view')));
        }

        $del_url = 'templates/template-delete-confirm'.
            '/id='.$row->template_id.
            '/tgpref='.$this->safeFolder($row->folder);

        $delete =  ($row->template_name == 'index') ? '--' : Cp::anchor($del_url, __('kilvin::cp.delete'));

        $t .= Cp::tableCell('', $delete).'</tr>'.PHP_EOL;
        return $t;
    }

    // ------------------------------------------------------

    /**
     *  Creates Template Folder <table> closing
     *
     *  @param  Collection $row
     *  @return string
     */
    private function templateFolderClosing()
    {
        return
            '</table>'.PHP_EOL.
            '</td>'.PHP_EOL.
            '</tr>'.PHP_EOL.
            '</table>'.PHP_EOL.
            '</div>'.PHP_EOL;
    }

    // ------------------------------------------------------

    /**
     *  Creates Template Folder <table> opening
     *
     *  @param  Collection $row
     *  @return string
     */
    private function templateFolderOpening($row)
    {
        $folder  = $row->folder;

        $t  = '<div id="folder_'.$this->safeFolder($row->folder).'" class="templateFolder" style="display: none;">';

        $t .=  Cp::table('', '', '', '100%')
             .'<tr>'.PHP_EOL
             .Cp::td('templateBorderBox', '20%', '', '', 'top');

        $t .= "<div class='tableHeadingAlt'>".$folder."</div>";

        $t .= Cp::table('', '', '', '100%')
             .'<tr>'.PHP_EOL
             .Cp::td('leftPad', '', '', '', 'top');

        $t .= Cp::quickDiv(
            'littlePadding',
            Cp::anchor(
                'templates/new-template-form'.
                    '/folder='.$this->safeFolder($row->folder).
                    '/tgpref='.$this->safeFolder($row->folder),
                __('kilvin::templates.create_new_template')));


        $t .= Cp::quickDiv(
            'littlePadding',
            Cp::anchor(
                'templates/edit-template-names'.
                    '/folder='.$this->safeFolder($row->folder),
                __('kilvin::templates.edit_template_names')));


        // You cannot delete the root folder
        if ($row->folder != '/') {
            $t .= Cp::quickDiv(
                    'littlePadding',
                    Cp::anchor(
                        'templates/edit-template-folder-form'.
                            '/folder='.$this->safeFolder($row->folder),
                        __('kilvin::templates.edit_folder')));

            $t .= Cp::quickDiv(
                'littlePadding',
                Cp::anchor(
                    'templates/delete-folder-confirm'.
                        '/folder='.$this->safeFolder($row->folder),
                    __('kilvin::templates.delete_folder')));
        }


        $t .= '</td>'.PHP_EOL
             .'</tr>'.PHP_EOL
             .'</table>'.PHP_EOL;

        $t .= '</td>'.PHP_EOL
             .Cp::td('defaultSmall', '1%').NBS;

        $t .= '</td>'.PHP_EOL
             .Cp::td('templateAreaBox', '79%', '', '', 'top');


        $t .= Cp::table('', '0', '', '100%')
             .'<tr>'.PHP_EOL
             .Cp::tableCell('tableHeading', __('kilvin::templates.template_name'), '45%')
             .Cp::tableCell('tableHeading', __('kilvin::templates.template_type'), '15%')
             .Cp::tableCell('tableHeading', __('kilvin::cp.view'), '15%')
             .Cp::tableCell('tableHeading', __('kilvin::cp.delete'), '15%')
             .'</tr>'.PHP_EOL;

        return $t;
    }

   /**
     * New Template Folder Form
     *
     * @return string
     */
    public function newTemplateFolderForm()
    {
        return $this->editTemplateFolderForm();
    }

   /**
     * Edit Template Folder Form
     *
     * @return string
     */
    public function editTemplateFolderForm()
    {
        if ( ! $this->checkAccess()) {
            return Cp::unauthorizedAccess();
        }

        $edit = false;
        $folder = '';

        if ($folder = $this->urlFolder(Cp::pathVar('folder')))
        {
            $edit = true;

            $query = DB::table('templates')
                ->where('folder', $folder)
                ->first();

            foreach ($query as $key => $val) {
                $$key = $val;
            }
        }

        $title = ($edit == FALSE) ? __('kilvin::templates.new_folder_form') : __('kilvin::templates.edit_folder_form');

        // Build the output
        Cp::$title = $title;
        Cp::$crumb = $title;

        Cp::$body = Cp::formOpen(['action' => 'templates/update-template-folder']);

        if ($edit === true) {
            Cp::$body .= Cp::input_hidden('old_name', $folder);
        }

        Cp::$body .= Cp::quickDiv('tableHeading', $title);

        Cp::$body .= Cp::div('box');

         Cp::$body .=
            Cp::div('paddedWrapper').
                Cp::quickDiv('littlePadding', '<strong>'.__('kilvin::templates.name_of_folder').'</strong>').
                Cp::quickDiv('littlePadding', __('kilvin::templates.folder_instructions')).
                Cp::quickDiv('littlePadding', Cp::input_text('folder', $folder, '20', '50', 'input', '300px')).
            '</div>'.
            PHP_EOL;


        // New folders can duplicate existing folders
        if ($edit === false)
        {
            Cp::$body .= Cp::div('paddedWrapper').
                Cp::quickDiv('littlePadding', Cp::quickDiv('defaultBold', __('kilvin::templates.duplicate_existing_folder')));

            $query = DB::table('templates')
                ->distinct()
                ->select('folder', 'site_name')
                ->join('sites', 'sites.id', '=', 'templates.site_id')
                ->orderBy('folder')
                ->get();

            Cp::$body .= Cp::input_select_header('duplicate_folder');

            Cp::$body .= Cp::input_select_option('false', __('kilvin::templates.do_not_duplicate_folder'));

            foreach ($query as $row)
            {
                Cp::$body .= Cp::input_select_option($row->folder, $row->site_name.' - '.$row->folder);
            }

            Cp::$body .= Cp::input_select_footer().
                '</div>';
        }

        // Closes 'box' div
        Cp::$body .= '</div>'.PHP_EOL;


        if ($edit == false) {
            Cp::$body .= Cp::quickDiv('paddingTop', Cp::input_submit(__('kilvin::cp.submit')));
        }
        else {
            Cp::$body .= Cp::quickDiv('paddingTop', Cp::input_submit(__('kilvin::cp.update')));
        }

        Cp::$body .= '</form>'.PHP_EOL;
    }

   /**
     * Update Template Folder
     *
     * Creates a Folder, Renames a Folder. If creating, have option to duplicate existing folder's templates
     *
     * @return string
     */
    public function updateTemplateFolder()
    {
        if ( ! $this->checkAccess()) {
            return Cp::unauthorizedAccess();
        }

        if ( ! $folder = Request::input('folder')){
            return Cp::errorMessage(__('kilvin::templates.form_is_empty'));
        }

        if ( ! preg_match("#^[a-zA-Z0-9_\-\/]+$#i", $folder)) {
            return Cp::errorMessage(__('kilvin::templates.template_illegal_characters'));
        }

        // Clean up slashes
        $folder = '/'.trim(remove_double_slashes($folder), '/');

        if (in_array($folder, $this->reserved_names)) {
            return Cp::errorMessage(__('kilvin::templates.reserved_name'));
        }

        $edit = (bool) Request::input('old_name');

        $template_count = DB::table('templates')
            ->where('site_id', Site::config('site_id'))
            ->where('folder', $folder)
            ->count();

        // Existing folder
        if ($edit) {
            if (Request::input('old_name') != $folder and $template_count > 0) {
                return Cp::errorMessage(__('kilvin::templates.folder_taken'));
            }
        }

        // New folder
        if (!$edit && $template_count > 0) {
            return Cp::errorMessage(__('kilvin::templates.folder_taken'));
        }

        // New Folder!
        if (!$edit) {
            $duplicate = false;

            if (Request::filled('duplicate_folder')) {
                $templates_query = DB::table('templates')
                    ->where('folder', Request::input('duplicate_folder'))
                    ->get();

                if ($templates_query->count() > 0) {
                    $duplicate = true;
                }
            }

            // Give them a simple index
            if ($duplicate === false) {
                // Give them a simple index
                $data = [
                    'folder'          => $folder,
                    'template_data'   => '',
                    'template_name'   => 'index',
                    'template_type'   => 'html',
                    'created_at'      => Carbon::now(),
                    'updated_at'      => Carbon::now(),
                    'site_id'         => Site::config('site_id')
                ];

                DB::table('templates')->insert($data);

                $this->saveTemplateToFilesystem($data);
            }

            if ($duplicate === true)
            {
                foreach ($templates_query as $row)
                {
                    $data = [
                        'folder'                => $folder,
                        'template_name'         => $row->template_name,
                        'template_notes'        => $row->template_notes,
                        'template_type'         => $row->template_type,
                        'template_data'         => $row->template_data,
                        'updated_at'            => Carbon::now(),
                        'site_id'               => Site::config('site_id')
                     ];

                     DB::table('templates')->insert($data);

                     $this->saveTemplateToFilesystem($data);
                }
            }

            $message = '01';
        }


        // Existing Folder
        if ($edit) {

            $old_path = remove_double_slashes($this->site_path.Request::input('old_name'));
            $new_path = remove_double_slashes($this->site_path.$folder);

            if ($old_path != $new_path) {
                File::moveDirectory($old_path, $new_path);
            }

            DB::table('templates')
                ->where('folder', Request::input('old_name'))
                ->update(
                    [
                        'folder' => $folder,
                    ]
                );

            $message = '02';
        }

        $append = '/tgpref='.$this->safeFolder($folder);

        return redirect(kilvin_cp_url('templates/msg='.$message.$append));
    }

   /**
     * Delete Template Folder Confirmation
     *
     * Be very very careful here, user...
     *
     * @return string
     */
    public function deleteFolderConfirm()
    {
        if ( ! $this->checkAccess()) {
            return Cp::unauthorizedAccess();
        }

        Cp::$title  = __('kilvin::templates.folder_delete_confirm');
        Cp::$crumb  = __('kilvin::templates.folder_delete_confirm');

        if ( ! ($folder = $this->urlFolder(Cp::pathVar('folder')))) {
            return false;
        }

        // Cannot delete base folder
        if ($folder === '/') {
            return false;
        }

        $query = DB::table('templates')
            ->where('folder', $folder)
            ->select('folder')
            ->first();

        Cp::$body = Cp::deleteConfirmation(
            [
                'url'       => 'templates/delete-template-folder',
                'heading'   => 'templates.delete_folder',
                'message'   => 'templates.delete_this_folder',
                'item'      => $query->folder,
                'extra'     => 'templates.all_templates_will_be_nuked',
                'hidden'    => ['folder' => $folder]
            ]
        );
    }

   /**
     * Delete Template Folder
     *
     * @return void
     */
    public function deleteTemplateFolder()
    {
        if ( ! $this->checkAccess()) {
            return Cp::unauthorizedAccess();
        }

        if ( ! $folder = Request::input('folder')) {
            return false;
        }

        // Cannot delete base folder
        if ($folder === '/') {
            return false;
        }

        $folder_path = remove_double_slashes($this->site_path.$folder);

        // We need to delete all the saved template data in the versioning table
        $template_ids = DB::table('templates')
            ->where('folder', $folder)
            ->pluck('id')
            ->all();

        if (!empty($template_ids)) {
            DB::table('revision_tracker')
            	->where('item_table', 'templates')
            	->where('item_field', 'template_data')
                ->whereIn('item_id', $template_ids)
                ->delete();
        }

        DB::table('templates')->where('folder', $folder)->delete();

        File::deleteDirectory($folder_path);

        return redirect(kilvin_cp_url('templates/msg=03'));
    }

   /**
     * New Template Form!
     *
     * @return string
     */
    public function newTemplateForm()
    {
        if ( ! $folder = $this->urlFolder(Cp::pathVar('folder'))) {
            return false;
        }

        if ( ! $this->checkAccess()) {
            return Cp::unauthorizedAccess();
        }

        Cp::$title = __('kilvin::templates.new_template_form');
        Cp::$crumb = __('kilvin::templates.new_template_form');

        $r  = Cp::formOpen(['action' => 'templates/create-new-template']);
        $r .= Cp::input_hidden('folder', $folder);

        $r .= Cp::quickDiv('tableHeading', __('kilvin::templates.new_template_form'));

        $r .= Cp::div('box');
        $r .= Cp::quickDiv('littlePadding', '<b>'.__('kilvin::templates.name_of_template').'</b>')
             .Cp::quickDiv('littlePadding', __('kilvin::templates.folder_instructions'))
             .Cp::quickDiv('', Cp::input_text('template_name', '', '20', '50', 'input', '240px'));


        $r .= Cp::div('littlePadding').'<b>'.__('kilvin::templates.template_type').'</b>';
        $r .= Cp::input_select_header('template_type');

        foreach($this->template_types as $type) {

            $selected = ($type == 'html') ? true : false;

            $r .= Cp::input_select_option($type, __('kilvin::templates.template_type_'.$type), $selected);
        }

        $r .= Cp::input_select_footer();
        $r .= '</div>'.PHP_EOL;
        $r .= '</div>'.PHP_EOL;

        $r .= Cp::table('tableBorder', '0', '', '100%')
             .'<tr>'.PHP_EOL
             .Cp::td('tableHeadingAlt', '', '3').__('kilvin::templates.choose_default_data').'</td>'.PHP_EOL
             .'</tr>'.PHP_EOL;

        $r .= '<tr class="has-form">'.PHP_EOL.
             Cp::tableCell(
                '',
                '<label>'.
                    Cp::input_radio('data', 'none', 1).
                    ' &nbsp;'.
                    __('kilvin::templates.blank_template').
                '</label>'
             ).
             Cp::tableCell('', NBS).
             '</tr>'.PHP_EOL;

        $query = DB::table('templates')
            ->select('folder', 'template_name', 'templates.id AS template_id')
            ->orderBy('templates.folder')
            ->orderBy('templates.template_name')
            ->get();

        $d  = Cp::input_select_header('template');

        foreach ($query as $row)
        {
            $d .= Cp::input_select_option(
                $row->template_id,
                rtrim($row->folder,'/').'/'.$row->template_name
            );
        }

        $d .= Cp::input_select_footer();

        $r .= '<tr class="has-form">'.PHP_EOL.
                 Cp::tableCell(
                    '',
                    '<label>'.
                        Cp::input_radio('data', 'template', '').
                        ' &nbsp;'.
                        __('kilvin::templates.an_existing_template').
                    '</label>'
                    ).
                 Cp::tableCell('', $d, '','','left').
             '</tr>'.PHP_EOL.
             '</table>'.PHP_EOL;

        $r .= Cp::quickDiv('paddingTop', Cp::input_submit(__('kilvin::cp.submit')))
             .'</form>'.PHP_EOL;

        Cp::$body = $r;
    }

   /**
     * Create the Template, yo ho!
     *
     * @return string
     */
    public function createNewTemplate()
    {
        if ( ! $this->checkAccess()) {
            return Cp::unauthorizedAccess();
        }

        if ( ! $template_name = Request::input('template_name')) {
            return Cp::errorMessage(__('kilvin::templates.you_must_submit_a_name'));
        }

        if ( ! $folder = Request::input('folder')) {
            return Cp::unauthorizedAccess();
        }

        if ( ! preg_match("#^[a-zA-Z0-9_\.-]+$#i", $template_name)) {
            return Cp::errorMessage(__('kilvin::templates.template_illegal_characters'));
        }

        if (in_array($template_name, $this->reserved_names)) {
            return Cp::errorMessage(__('kilvin::templates.reserved_name'));
        }

        // At least index should exist...
        $folder_count = DB::table('templates')
            ->where('folder', $folder)
            ->count();

        if ($folder_count == 0) {
            return Cp::errorMessage(__('kilvin::templates.no_folder_exists'));
        }

        // No duplicate name in folder
        $template_count = DB::table('templates')
            ->where('folder', $folder)
            ->where('template_name', Request::input('template_name'))
            ->count();

        if ($template_count > 0) {
            return Cp::errorMessage(__('kilvin::templates.template_name_taken'));
        }

        $template_type = Request::input('template_type');

        if (!in_array($template_type, $this->template_types)) {
            return Cp::errorMessage(__('kilvin::templates.invalid_template_type'));
        }

        $template_data = '';

        if (Request::input('data') == 'template')
        {
            $query = DB::table('templates')
                ->where('site_id', Site::config('site_id'))
                ->where('id', Request::input('template'))
                ->select(
                    'folder',
                    'template_name',
                    'template_data',
                    'template_type',
                    'template_notes'
                )->first();

            if (!$query) {
                return Cp::errorMessage(__('kilvin::templates.unable_to_find_duplicate_template'));
            }

            $template_path = remove_double_slashes(
                $this->site_path.
                $query->folder.DIRECTORY_SEPARATOR.
                $query->template_name.'.twig.'.$query->template_type
            );

            $template_data = file_get_contents($template_path);

            // They selected a different template type than what the original was?
            if ($template_type != $query->template_type) {
                $template_type = $query->template_type;
            }

            $data = [
                'folder'                => $folder,
                'template_name'         => Request::input('template_name'),
                'template_notes'        => $query->template_notes,
                'template_type'         => $template_type,
                'template_data'         => $template_data,
                'created_at'            => Carbon::now(),
                'updated_at'            => Carbon::now(),
                'site_id'               => Site::config('site_id')
            ];
        }
        else
        {
            $data = [
                'folder'         => $folder,
                'template_name'  => Request::input('template_name'),
                'template_type'  => $template_type,
                'template_data'  => '',
                'created_at'            => Carbon::now(),
                'updated_at'     => Carbon::now(),
                'site_id'        => Site::config('site_id')
            ];
        }

        $save_result = $this->saveTemplateToFilesystem($data);

        DB::table('templates')->insert($data);

        $append = (Cp::pathVar('tgpref')) ? '/tgpref='.Cp::pathVar('tgpref') : '';

        return redirect(kilvin_cp_url('templates/msg=04'.$append));
    }

   /**
     * Template Deletion Confirmation Form
     *
     * @return string
     */
    public function templateDeleteConfirm()
    {
        if ( ! $this->checkAccess()) {
            return Cp::unauthorizedAccess();
        }

        if ( ! $id = Cp::pathVar('id')) {
            return false;
        }

        if ( ! is_numeric($id)) {
            return false;
        }

        $query = DB::table('templates')
            ->where('id', $id)
            ->select('folder', 'template_name')
            ->first();

        if (!$query) {
            return redirect(kilvin_cp_url('templates'));
        }

        Cp::$title  = __('kilvin::templates.template_delete_confirm');
        Cp::$crumb  = __('kilvin::templates.template_delete_confirm');

        Cp::$body = Cp::deleteConfirmation(
            [
                'url'       => 'templates/delete-template',
                'heading'   => 'templates.delete_template',
                'message'   => 'templates.delete_this_template',
                'item'      => rtrim($query->folder, '/').'/'.$query->template_name,
                'extra'     => '',
                'hidden'    => ['template_id' => $id]
            ]
        );
    }

   /**
     * Delete a Template. SAD!
     *
     * @return void
     */
    public function deleteTemplate()
    {
        if ( ! $id = Request::input('template_id')) {
            return false;
        }

        if ( ! is_numeric($id)) {
            return false;
        }

        if (!$this->checkAccess()) {
            return Cp::unauthorizedAccess();
        }

        $query = DB::table('templates')
            ->where('id', $id)
            ->first();

        if (!$query) {
            return Cp::errorMessage(__('kilvin::templates.unable_to_find_template_in_database'));
        }

        DB::table('revision_tracker')
            ->where('item_id', $id)
            ->where('item_table', 'templates')
            ->where('item_field', 'template_data')
            ->delete();

        DB::table('templates')
            ->where('id', $id)
            ->delete();

        $folder_path = $this->site_path.$query->folder;
        $file_path = remove_double_slashes(
            $folder_path.DIRECTORY_SEPARATOR.
            $query->template_name.'.twig.'.$query->template_type
        );

        File::delete($file_path);

        return redirect(kilvin_cp_url('templates/msg=05'));
    }

   /**
     * Edit Template Form
     *
     * @param integer $template_id
     * @param string $message Message from previous page submission
     * @return string
     */
    public function editTemplate($template_id = '', $message = '')
    {
        if ( ! $this->checkAccess()) {
            return Cp::unauthorizedAccess();
        }

        if ($template_id == '') {
            $template_id = Cp::pathVar('id');
        }

        if ( ! is_numeric($template_id)) {
            return false;
        }

        // ------------------------------------
        //  Load Template Data
        // ------------------------------------

        $query = DB::table('templates')
            ->where('id', $template_id)
            ->select(
                'folder',
                'template_name',
                'template_data',
                'template_notes',
                'template_type',
                'updated_at')
            ->first();

        $template_type   = $query->template_type;
        $folder          = $query->folder;
        $template_data   = $query->template_data;
        $template_name   = $query->template_name;
        $template_notes  = $query->template_notes;
        $updated_at      = Localize::createHumanReadableDateTime($query->updated_at);

        // ------------------------------------
        //  Clear old revisions
        // ------------------------------------

        if (Site::config('save_tmpl_revisions') == 'y') {
            $maxrev = Site::config('max_tmpl_revisions');

            if (!empty($maxrev) and is_numeric($maxrev) and $maxrev > 0) {
                $ids = DB::table('revision_tracker')
                    ->where('item_id', $template_id)
                    ->where('item_table', 'templates')
                    ->where('item_field', 'template_data')
                    ->orderBy('id', 'desc')
                    ->skip($maxrev)
                    ->limit(100)
                    ->pluck('id')
                    ->all();

                if (!empty($ids)) {
                    DB::table('revision_tracker')
                        ->whereIn('id', $ids)
                        ->delete();
                }
            }
        }

        $template_path = rtrim($folder, '/').DIRECTORY_SEPARATOR.$template_name.'.twig.'.$template_type;

        // @todo = Load this via Storage facade
        if ($file = file_get_contents(remove_double_slashes($this->site_path.$template_path))) {
            $template_data = $file;
        }

        // ------------------------------------
        //  Javascript for Page
        // ------------------------------------

        ob_start();

        ?>
        <script type="text/javascript">

            function viewRevision()
            {
                var id = $('select[name=revision_history]').val();

                if (!id) {
                    return false;
                }

                if (id == "clear") {
                    var items = $('select[name=revision_history] option');

                    for (i = (items.length-1); i >= 1; i--)
                    {
                        $(items[i]).remove();
                    }


                    $('select[name=revision_history]').first().attr('selected', 'selected');

                    flipButtonText(1);

                    window.open ("<?php echo 'templates/clear-revisions/id='.$template_id.'/Z=1'; ?>" ,"Revision", "width=500, height=260, location=0, menubar=0, resizable=0, scrollbars=0, status=0, titlebar=0, toolbar=0, screenX=60, left=60, screenY=60, top=60");

                    return false;
                }

				window.open ("<?php echo 'templates&M=viewRevisionHistory&Z=1'; ?>&id="+id ,"Revision");

				return false;
            }

            function flipButtonText()
            {
            	var which = $('select[name=revision_history]').val();

                if (which == "clear")
                {
                	$('#revisions_button').attr('value', '<?php echo __('kilvin::cp.clear'); ?>');
                }
                else
                {
                	$('#revisions_button').attr('value', '<?php echo __('kilvin::cp.view'); ?>');
                }
            }

            function switchNotesDisplay()
            {
                if ($('#notes').css('display') == "block") {
                	$('#notes').css('display', 'none');
                	$('#noteslink').css('display', 'block');
                } else {
                    $('#notes').css('display', 'block');
                	$('#noteslink').css('display', 'none');
                }
            }
        </script>

        <?php

        $javascript = ob_get_contents();

        ob_end_clean();

        // ------------------------------------
        //  Begin Page Creation
        // ------------------------------------

        Cp::$title  = __('kilvin::templates.edit_template').' | '.$template_name;
        Cp::$crumb  = __('kilvin::templates.edit_template');

        $r  = $javascript;

        $r .= Cp::formOpen(
            [
                'action'    => '',
                'name'      => 'revisions',
                'id'        => 'revisions'
            ],
            [
                'template_id' => $template_id
            ]
        );

        if (!empty($message)) {
            $r .= Cp::quickDiv('success-message', $message);
        }

        $r .= Cp::heading($template_path);

        $r .= Cp::table('', '', '', '100%').
             '<tr>'.PHP_EOL.
                 Cp::td('tableHeading').
                     '<strong>'.__('kilvin::templates.last_edit').'</strong> '.$updated_at.
                '</td>'.
            PHP_EOL;

        $r .= Cp::td('tableHeading')
             .Cp::div('defaultRight');

         $r .= "<select name='revision_history' class='select' onchange='flipButtonText();'>"
             .PHP_EOL
             .Cp::input_select_option('', __('kilvin::templates.revision_history'));

        $rquery = DB::table('revision_tracker')
            ->leftJoin('members', 'members.id', '=', 'revision_tracker.item_author_id')
            ->where('item_table', 'templates')
            ->where('item_field', 'template_data')
            ->where('item_id', $template_id)
            ->orderBy('id', 'desc')
            ->select('revision_tracker.id AS tracker_id', 'item_date', 'screen_name')
            ->get();

        if ($rquery->count() > 0) {
            foreach ($rquery as $row) {
                $r .= Cp::input_select_option(
                    $row->tracker_id,
                    Localize::createHumanReadableDateTime($row->item_date).' ('.$row->screen_name.')'
                );
            }

            $r .= Cp::input_select_option('clear', __('kilvin::templates.clear_revision_history'));
        }

        $r .= Cp::input_select_footer()
             .'&nbsp;&nbsp;'
             .Cp::input_submit(__('kilvin::cp.view'), 'submit', 'id="revisions_button" onclick="return viewRevision();"');

        $r .=  '</div>'.PHP_EOL
              .'</td>'.PHP_EOL
              .'</tr>'.PHP_EOL
              .'</table>'.PHP_EOL
              .'</form>'.PHP_EOL;

        $r .= Cp::formOpen(['action' => 'templates/update-template'])
             .Cp::input_hidden('template_id', $template_id);

        $r .= Cp::quickDiv('templatepad', Cp::input_textarea('template_data', $template_data, Session::userdata('template_size'), 'textarea', '100%'));

        $expand     = '<img src="'.PATH_CP_IMG.'expand_white.gif" border="0"  width="10" height="10" alt="Expand" />';
        $collapse   = '<img src="'.PATH_CP_IMG.'collapse_white.gif" border="0"  width="10" height="10" alt="Collapse" />';

        $r .= '<div id="noteslink" style="display: block; padding:0; margin: 0; cursor:pointer;">';
        $r .= '<div class="tableHeadingAlt" id="noteopen" onclick="switchNotesDisplay();return false;">';
        $r .= $expand.' '.__('kilvin::templates.template_notes');
        $r .= '</div>'.PHP_EOL;
        $r .= '</div>'.PHP_EOL;

        $r .= '<div id="notes" style="display: none; padding:0; margin: 0; cursor:pointer;">';
        $r .= '<div class="tableHeadingAlt" id="noteclose" onclick="switchNotesDisplay();return false;">';
        $r .= $collapse.' '.__('kilvin::templates.template_notes');
        $r .= '</div>'.PHP_EOL;
        $r .= Cp::div('templatebox');
        $r .= Cp::quickDiv('littlePadding', __('kilvin::templates.template_notes_desc'));
        $r .= Cp::input_textarea('template_notes', $template_notes, '24', 'textarea', '100%');
        $r .= '</div>'.PHP_EOL;
        $r .= '</div>'.PHP_EOL;

        $r .= Cp::div('templatebox');
        $r .= Cp::table('', '', '6', '100%')
             .'<tr>'.PHP_EOL
             .Cp::td('', '25%', '', '', 'top')
             .Cp::div('littlePadding');

        if (Site::config('save_tmpl_revisions') == 'y') {
            $r .= '<label>'.
                    __('kilvin::templates.save_revision').' &nbsp;'.
                    Cp::input_checkbox('save_revision', 'y', true).
                '</label>';
        }

        $r .= '</td>'.PHP_EOL
             .Cp::td('', '25%', '', '', 'top')
             .Cp::input_text('columns', Session::userdata('template_size'), '4', '2', 'input', '30px').
                NBS.
                __('kilvin::templates.template_size')
             .
             '</td>'.PHP_EOL.
             '</tr>'.PHP_EOL.
             '<tr>'.PHP_EOL.
             '<td>'.PHP_EOL.
                Cp::quickDiv(
                    'littlePadding',
                    Cp::input_submit(__('kilvin::cp.update')).
                        NBS.
                        Cp::input_submit(__('kilvin::cp.update_and_return'),
                    'return')
                ).
             '</td>'.PHP_EOL.
             '</tr>'.PHP_EOL.
             '</table>'.PHP_EOL.
             '</div>'.PHP_EOL;

        $r .= '</form>'.PHP_EOL;

        Cp::$body = $r;
    }

   /**
     * Update Template
     *
     * @return void
     */
    public function updateTemplate()
    {
        if ( ! $template_id = Request::input('template_id')) {
            return false;
        }

        if ( ! is_numeric($template_id)) {
            return false;
        }

        if ( ! $this->checkAccess()) {
            return Cp::unauthorizedAccess();
        }

        // ------------------------------------
        //  Save template as file
        // ------------------------------------

        $query = DB::table('templates')
            ->where('template_id', $template_id)
            ->select('template_name', 'folder', 'template_type')
            ->first();

        if (!$query) {
            return Cp::errorMessage(__('kilvin::templates.unable_to_find_template_in_database'));
        }

        $tdata = [
                'template_id'       => $template_id,
                'folder'            => $query->folder,
                'template_name'     => $query->template_name,
                'template_type'     => $query->template_type,
                'template_data'     => Request::input('template_data'),
                'updated_at'        => (string) Carbon::now(),
        ];

        $save_result = $this->saveTemplateToFilesystem($tdata);

        // ------------------------------------
        //  Save revision cache
        // ------------------------------------

        if (Request::input('save_revision') == 'y') {
            $data = [
                'item_id'           => $template_id,
                'item_table'        => 'templates',
                'item_field'        => 'template_data',
                'item_data'         => Request::input('template_data'),
                'item_date'         => Carbon::now(),
                'item_author_id'    => Session::userdata('member_id')
            ];

            DB::table('revision_tracker')->insert($data);
        }

        // ------------------------------------
        //  Save Template
        // ------------------------------------

        DB::table('templates')
            ->where('id', $template_id)
            ->update(
                [
                    'template_data' => Request::input('template_data'),
                    'updated_at'     => Carbon::now(),
                    'template_notes' => Request::input('template_notes')
                ]
            );

        if (is_numeric(Request::input('columns')))
        {
            if (Session::userdata('template_size') != Request::input('columns'))
            {
                Session::userdata('template_size', Request::input('columns'));

                DB::table('members')
                    ->where('id', Session::userdata('member_id'))
                    ->update(['template_size' => Request::input('columns')]);
            }
        }

        $message = __('kilvin::templates.template_updated');

        if (Request::has('return')) {
            return redirect(kilvin_cp_url('templates/&msg=06'));
        }

        return $this->editTemplate($template_id, $message);
    }

   /**
     * Update Template File with Data
     *
     * @param array $data The insert statement data so we can get what we need
     * @return bool
     */
    public function saveTemplateToFilesystem($data)
    {
        if ( ! $this->checkAccess()) {
            return false;
        }

        if (!File::isDirectory($this->site_path)){
            File::makeDirectory($this->site_path);
        }

        if ( ! is_writable($this->site_path)) {
            return false;
        }

        $folder_path = remove_double_slashes($this->site_path.$data['folder']);

        if (!File::isDirectory($folder_path)){
            File::makeDirectory($folder_path);
        }

        $file_path = $folder_path.DIRECTORY_SEPARATOR.$data['template_name'].'.twig.'.$data['template_type'];

        File::put($file_path, $data['template_data']);

        return true;
    }

   /**
     * View Revision History of Template
     *
     * @return string
     */
    public function viewRevisionHistory()
    {
        if ( ! $id = Request::input('id')) {
            return false;
        }

        $query = DB::table('revision_tracker')
            ->where('id', $id)
            ->where('item_table', 'templates')
            ->where('item_field', 'template_data')
            ->select('item_id', 'item_data')
            ->first();

        if (!$query) {
            return false;
        }

        if ( ! $this->checkAccess()) {
            return Cp::unauthorizedAccess();
        }

        Cp::$title  = __('kilvin::templates.revision_history');
        Cp::$crumb  = __('kilvin::templates.revision_history');

        Cp::$body = Cp::input_textarea('template_data', $query->item_data, 26, 'textarea', '100%');
        Cp::$body .= Cp::quickDiv(
            'littlePadding',
            BR.
                '<div align="center"><a href="JavaScript:window.close();"><b>'.
                __('kilvin::cp.close_window').
                '</b></a></div>'
        );
    }

   /**
     * Clear Revisions for Template
     *
     * @return string
     */
    public function clearRevisions()
    {
        if ( ! $this->checkAccess()) {
            return Cp::unauthorizedAccess();
        }

        if ( ! $id = Request::input('id'))
        {
            return false;
        }

        Cp::$title  = __('kilvin::templates.revision_history');
        Cp::$crumb  = __('kilvin::templates.revision_history');

        DB::table('revision_tracker')
            ->where('item_id', $id)
            ->where('item_table', 'templates')
            ->where('item_field', 'template_data')
            ->delete();

        Cp::$body = Cp::quickDiv('defaultCenter', BR.BR.'<b>'.__('kilvin::templates.history_cleared').'</b>'.BR.BR.BR);

        Cp::$body .= Cp::quickDiv('defaultCenter', "<a href='javascript:window.close();'>".__('kilvin::cp.close_window')."</a>".BR.BR.BR);

    }

   /**
     * Template Variables page
     *
     * @param string $message
     * @return string
     */
    public function templateVariables($message = '')
    {
        if ( ! $this->checkAccess()) {
            return Cp::unauthorizedAccess();
        }

        Cp::$title = __('kilvin::templates.template_variables');
        Cp::$crumb = __('kilvin::templates.template_variables');
        $right_links[] = [
            'templates/edit-template-variable',
            __('kilvin::templates.create_new_template_variable')
        ];

        Cp::$body .= Cp::header(Cp::$title, $right_links);

        if ($message != '')
        {
            Cp::$body .= Cp::quickDiv('success-message', $message);
        }

        $i = 0;


        $query = DB::table('template_variables')
            ->where('site_id', Site::config('site_id'))
            ->orderBy('variable_name', 'asc')
            ->select('template_variables.id AS variable_id', 'variable_name', 'variable_data')
            ->get();

        // ------------------------------------
        //  Table Header
        // ------------------------------------

        Cp::$body .= Cp::table('tableBorder', '0', '0', '100%').
                      '<tr>'.PHP_EOL.
                      Cp::tableCell('tableHeading',
                                        ($query->count() == 0) ?
                                            array(__('kilvin::templates.template_variables')) :
                                            array(__('kilvin::templates.template_variables'),
                                                  __('kilvin::templates.template_variable_syntax'),
                                                  __('kilvin::cp.delete')
                                                 )
                                        ).
                      '</tr>'.PHP_EOL;

        // ------------------------------------
        //  Table Rows
        // ------------------------------------

        if ($query->count() == 0) {
            Cp::$body .= Cp::tableQuickRow(
                '',
                [
                    Cp::quickDiv('littlePadding', Cp::quickDiv('highlight', __('kilvin::templates.no_template_variables')))
                ]
            );
        } else {
            foreach ($query as $row)
            {
                Cp::$body .= Cp::tableQuickRow(
                    '',
                    [
                        Cp::quickSpan(
                            'defaultBold',
                            Cp::anchor(
                                'templates/edit-template-variable/id='.$row->variable_id,
                                $row->variable_name
                            )
                        ),
                        Cp::quickSpan('defaultBold', $this->templateVariableSyntax($row->variable_name)),
                        Cp::quickSpan(
                            'defaultBold',
                            Cp::anchor(
                                'templates/delete-template-variable-confirm/id='.$row->variable_id,
                                __('kilvin::cp.delete')
                            )
                        ),
                    ]
                );
            }
        }

        Cp::$body .= '</table>'.PHP_EOL;
    }

    /**
     * Template Variable Syntax for the Name
     *
     * @param string $var
     * @return string
     */
    public function templateVariableSyntax($var)
    {
        return "{{ tv.".$var." }}";
    }

   /**
     * Template Variable Form
     *
     * @return string
     */
    public function editTemplateVariable()
    {
        if ( ! $this->checkAccess()) {
            return Cp::unauthorizedAccess();
        }

        $variable_name = '';
        $variable_data = '';

        $id = Request::input('id');

        $variable_name = '';
        $variable_data = '';

        if (!empty($id)) {
            $query = DB::table('template_variables')
                ->where('id', $id)
                ->where('site_id', Site::config('site_id'))
                ->first();

            if(empty($query)) {
                return Cp::unauthorizedAccess();
            }

            $variable_name = $query->variable_name;
            $variable_data = $query->variable_data;
         }

        Cp::$title = __('kilvin::templates.template_variables');
        Cp::$crumb = __('kilvin::templates.template_variables');

        Cp::$body  = Cp::quickDiv('tableHeading', __('kilvin::templates.template_variables'));

        Cp::$body .= Cp::formOpen(
            ['action' => 'templates/update-template-variable'],
            ['id' => $id]
        );

        Cp::$body .=
            Cp::div('box').
                Cp::heading(__('kilvin::templates.variable_name'), 5).
                Cp::quickDiv('littlePadding',  __('kilvin::templates.variable_instructions')).
                Cp::quickDiv('littlePadding', Cp::input_text('variable_name', $variable_name, '20', '50', 'input', '240px')).
                Cp::heading(BR.__('kilvin::templates.variable_data'), 5).
                Cp::input_textarea('variable_data', $variable_data, '15', 'textarea', '100%').
            '</div>'.PHP_EOL;

        Cp::$body .=  Cp::div('paddingTop');

        if (!Request::filled('id')) {
            Cp::$body .= Cp::input_submit(__('kilvin::cp.submit'));
        }
        else {
            Cp::$body .= Cp::input_submit(__('kilvin::cp.update'));
        }

        Cp::$body .= '</div>'.PHP_EOL;
        Cp::$body .= '</form>'.PHP_EOL;
    }

   /**
     * Create or Update Template Variable
     *
     * @return void
     */
    public function updateTemplateVariable()
    {
        if ( ! $this->checkAccess()) {
            return Cp::unauthorizedAccess();
        }

        if (!Request::filled('variable_name') or !Request::filled('variable_data')) {
            return Cp::errorMessage(__('kilvin::templates.all_fields_required'));
        }

        if ( ! preg_match("#^[a-zA-Z0-9_]+$#i", Request::input('variable_name'))) {
            return Cp::errorMessage(__('kilvin::templates.variable_illegal_characters'));
        }

        if (in_array(Request::input('variable_name'), $this->reserved_vars)) {
            return Cp::errorMessage(__('kilvin::templates.reserved_name'));
        }

        // Edit
        if (Request::filled('id')) {
            DB::table('template_variables')
                ->where('id', Request::input('id'))
                ->update(
                    [
                        'variable_name' => Request::input('variable_name'),
                        'variable_data' => Request::input('variable_data')
                    ]
                );

            $msg = __('kilvin::templates.global_var_updated');
        }

        // New
        if (!Request::filled('id')) {
            $count = DB::table('template_variables')
                ->where('site_id', Site::config('site_id'))
                ->where('variable_name', Request::input('variable_name'))
                ->count();

            if ($count > 0) {
                return Cp::errorMessage(__('kilvin::templates.duplicate_var_name'));
            }

            DB::table('template_variables')
                ->insert(
                    [
                        'site_id' => Site::config('site_id'),
                        'variable_name' => Request::input('variable_name'),
                        'variable_data' => Request::input('variable_data')
                    ]
                );

            $msg = __('kilvin::templates.template_variable_created');
        }

        return $this->templateVariables($msg);
    }

   /**
     * Delete Template Variable Confirmation Form
     *
     * @return string
     */
    public function deleteTemplateVariableConfirm()
    {
        if ( ! $this->checkAccess()) {
            return Cp::unauthorizedAccess();
        }

        Cp::$title  = __('kilvin::templates.delete_template_variable');
        Cp::$crumb  = __('kilvin::templates.delete_template_variable');

        if ( ! $id = Cp::pathVar('id')) {
            return false;
        }

        $variable_name = DB::table('template_variables')
            ->where('id', $id)
            ->value('variable_name');

        Cp::$body = Cp::deleteConfirmation(
            array(
                'url'       => 'templates/delete-template-variable',
                'heading'   => 'templates.delete_template_variable',
                'message'   => 'templates.delete_this_variable',
                'item'      => $variable_name,
                'extra'     => '',
                'hidden'    => ['id' => $id]
            )
        );
    }

   /**
     * Delete Template Variable
     *
     * @return void
     */
    public function deleteTemplateVariable()
    {
        if ( ! $this->checkAccess()) {
            return Cp::unauthorizedAccess();
        }

        if ( ! $id = Request::input('id')) {
            return false;
        }

        $count = DB::table('template_variables')
            ->where('id', $id)
            ->count();

        if ($count == 0) {
            return false;
        }

        DB::table('template_variables')
            ->where('id', $id)
            ->delete();

        return $this->templateVariables(__('kilvin::templates.variable_deleted'));
    }
}
