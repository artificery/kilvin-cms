<?php

namespace Kilvin\Libraries;

use Illuminate\Support\Facades\DB;
use Kilvin\Facades\Cp;
use Kilvin\Facades\Site;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Kilvin\Core\Regex;
use Kilvin\Core\Session;
use Kilvin\Core\Paginate;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Kilvin\Exceptions\CmsFatalException;

class ControlPanel
{
    public $title           = '';    // Page title
    public $body            = '';    // Main content area
    public $crumb           = '';    // Breadcrumb.
    public $auto_crumb      = '';    // Breadcrumb.
    public $url_append      = '';    // This variable lets us globally append something onto URLs
    public $body_props      = '';    // Code that can be addded the the <body> tag
    public $extra_header    = '';    // Additional headers we can add manually
    public $footer_javascript = '';  // Footer javascript
    private $path            = '';   // The path of request with CP path removed

    private $output         = '';

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        $full_path = trim(request()->decodedPath(), '/');
        $cp_path = config('cms.cp_path');

        if (Str::startsWith($full_path, $cp_path.'/')) {
            $this->path = Str::replaceFirst($cp_path.'/', '', $full_path);
        }
    }

    /**
     * Return the Control Panel path
     *
     * @return string
     */
    public function path()
    {
        return $this->path;
    }

    /**
     * Find a Path Variable
     *
     * @param string $name
     * @param mixed $default If var not found in URL
     * @return mixed
     */
    public function pathVar($name, $default = null, $search='*')
    {
        switch($search) {
            case 'letters':
                $search = '[a-zA-Z]+';
            break;
            case 'numbers':
                $search = '[0-9]+';
            break;
            case '*':
                $search = '[a-zA-Z0-9\_\-]+';
            break;
        }

        $name = preg_replace('~[^a-z\-\_]~i', '', $name);

        if (preg_match('~\/'.preg_quote($name).'=('.$search.')~', Cp::path(), $match)) {
            return $match[1];
        }

        return $default;
    }

    /**
     * Get a segment from the CP URI (1 based index).
     *
     * @param  int  $index
     * @param  string|null  $default
     * @return string|null
     */
    public function segment($index, $default = null)
    {
        return Arr::get($this->segments(), $index - 1, $default);
    }

    /**
     * Get all of the segments for the request path.
     *
     * @return array
     */
    public function segments()
    {
        $segments = explode('/', $this->path);

        return array_values(array_filter($segments, function ($value) {
            return $value !== '';
        }));
    }

    /**
     * Build Full Control Panel HTML
     *
     * @return void
     */
    public function buildFullControlPanel()
    {
        $this->csrfField();

        $out =  $this->htmlHeader().
                "<div class='pageHeader'>".PHP_EOL.
                    $this->pageHeader().
                    $this->pageNavigation().PHP_EOL.
                "</div>".
                $this->breadcrumb().
                PHP_EOL.
                '<div class="main-content">'.
                    PHP_EOL.
                    $this->body.
                    PHP_EOL.
                '</div>'.
                PHP_EOL.
                $this->copyright().
                '<script type="text/javascript">'.
                $this->footer_javascript.
                '</script>'.
                '</body>'.
                PHP_EOL.
                '</html>';

        $this->output = $out;
    }

    /**
     * Build Lighterweight CP page, typically for popups
     *
     * @return void
     */
    public function buildSimpleControlPanel()
    {
        $this->csrfField();

        $r = $this->htmlHeader();
        $r .= $this->simplePageHeader('quickLinksLeft');
        $r .= PHP_EOL.
                '<div class="main-content paddingTop">'.
                    PHP_EOL.
                    $this->body.
                    PHP_EOL.
                '</div>'.
              PHP_EOL;
        $r .= '</div>'.PHP_EOL;
        $r .= '</body>'.PHP_EOL.'</html>';

        $this->output = $r;
    }

    /**
     * HTML header for CP page
     *
     * @return string
     */
    public function htmlHeader()
    {
        $header =
"<html>
<head>
<title>".$this->title." - ".Site::config('site_name')." | ".CMS_NAME."</title>
<meta http-equiv='content-type' content='text/html; charset=utf-8'>
<meta http-equiv='expires' content='-1' >
<meta http-equiv='expires' content='Mon, 01 Jan 1970 23:59:59 GMT'>
<meta http-equiv='pragma' content='no-cache'>
";

        $header .=
            $this->jsAndCssHeaderElements().
            PHP_EOL.
            $this->dropMenuJavascript().
            $this->extra_header.
            "</head>".PHP_EOL.
            '<body'.$this->body_props.'>'.PHP_EOL;

        return $header;
    }

    /**
     * Script and Link elements for CP header
     *
     * @return string
     */
    public function jsAndCssHeaderElements()
    {
        $cp_path = rtrim(config('cms.cp_path'), '/');
        $manifest_path = KILVIN_THEMES.'/mix-manifest.json';
        $js_suffix = 'v'.KILVIN_VERSION;
        $css_suffix = 'v'.KILVIN_VERSION;

        if (file_exists($manifest_path)) {
            try {
                $manifest = json_decode(file_get_contents($manifest_path), true);
            } catch (\Exception $e) {
                throw new CmsFatalException('The Mix manifest could not be parsed for Kilvin Themes.');
            }

            $js_file = '/cp/cp.js';
            $css_file = '/cp/default.css';

            $cp_theme = (!Session::userdata('cp_theme') or Session::userdata('cp_theme') == '') ?
                Site::config('cp_theme') :
                Session::userdata('cp_theme');

            if (empty($cp_theme) && file_exists(KILVIN_CP_THEMES.$cp_theme.'.css')) {
                $css_file = '/cp/'.$cp_theme.'.css';
            }

            if (isset($manifest[$js_file]) && preg_match('/\?id=(.+)$/', $manifest[$js_file], $match)) {
                $js_suffix = $match[1];
            }

            if (isset($manifest[$css_file]) && preg_match('/\?id=(.+)$/', $manifest[$css_file], $match)) {
                $css_suffix = $match[1];
            }
        }

        return
            "<script type='text/javascript' src='/{$cp_path}/javascript/{$js_suffix}'></script>".PHP_EOL.
            "<link rel='stylesheet' type='text/css' href='/{$cp_path}/css/{$css_suffix}' />".PHP_EOL;
    }


    /**
     * Sites Pulldown
     *
     * @return string
     */
    public function buildSitesPulldown()
    {
        $label = Site::config('site_name');

        $query = DB::table('sites')
            ->whereIn('sites.id', array_keys(Session::userdata('assigned_sites')))
            ->get();

        $sites_url = kilvin_cp_url('sites');

        $d = <<<EOT

    <a href="{$sites_url}" class="sitePulldown" onclick="siteDropdown(event);">
        <span class="currentSite">{$label} ▾</span>
    </a>
    <ul class="siteDropMenu">
EOT;

        foreach($query as $row) {
            $d .= '<li>
                <a href="'.kilvin_cp_url('sites/load-site').
                    '/site_id='.$row->id.'" title="'.
                    $row->site_name.'">'.
                    $row->site_name.
                '</a>
            </li>';
        }

        $sites_url = kilvin_cp_url('sites-administration/list-sites');


        $d .= <<<EOT

        <li>
            <a href="{$sites_url}" title="Edit Sites">
                <em>»&nbsp;Edit Sites</em>
            </a>
        </li>
    </ul>
EOT;
        return $d;
    }

    /**
     * Header of CP Page
     *
     * Site pulldown, quicklinks, tabs
     *
     * @return string
     */
    public function pageHeader()
    {
        $r = $this->div('quickLinks').
             $this->div('quickLinksLeft').
             $this->quickDiv('simpleHeader', $this->buildSitesPulldown()).
             '</div>'.PHP_EOL.
             $this->div('quickLinksRight');

        $r .= $this->anchor('account/quicklinks/'.Session::userdata('member_id'),
                            '✎ edit',
                            'title="Edit Quicklinks" style="color:#ff3232"')
                            .'&nbsp;'
                            .$this->quickSpan('spacer','|')
                            .'&nbsp;';

        $r .= $this->buildQuickLinks();

        $screen_name = Session::userdata('screen_name');
        $photo_filename = Session::userdata('photo_filename');

        $icon = '
<span class="account-image">
    <svg class="svg-icon" viewBox="0 0 20 20">
        <path d="M12.075,10.812c1.358-0.853,2.242-2.507,2.242-4.037c0-2.181-1.795-4.618-4.198-4.618S5.921,4.594,5.921,6.775c0,1.53,0.884,3.185,2.242,4.037c-3.222,0.865-5.6,3.807-5.6,7.298c0,0.23,0.189,0.42,0.42,0.42h14.273c0.23,0,0.42-0.189,0.42-0.42C17.676,14.619,15.297,11.677,12.075,10.812 M6.761,6.775c0-2.162,1.773-3.778,3.358-3.778s3.359,1.616,3.359,3.778c0,2.162-1.774,3.778-3.359,3.778S6.761,8.937,6.761,6.775 M3.415,17.69c0.218-3.51,3.142-6.297,6.704-6.297c3.562,0,6.486,2.787,6.705,6.297H3.415z"></path>
    </svg>
</span>';

        if (!empty($photo_filename)) {
            $icon =
                '<img
                   src="'.Site::config('photo_url').$photo_filename.'"
                   border="0"
                   style="width: 1em; height:1em; display:inline-block; vertical-align: bottom;"
                   alt="'.$screen_name.' Photo" />';
        }

        // @todo - Switch View Site link to be pulldown of Site URLs
        $r .=
            $this->anchor(Site::config('site_url'), __('kilvin::cp.view_site')).
            '&nbsp;'.$this->quickSpan('spacer','|').'&nbsp;'.
            $this->anchor('account', trim($icon).'&nbsp;'.$screen_name).
            '&nbsp;'.$this->quickSpan('spacer','|').'&nbsp;'.
            $this->anchor('logout', __('kilvin::cp.logout')).
            '</div>'.PHP_EOL.
            '</div>'.PHP_EOL;

        return $r;
    }

    /**
     * Build Quicklinks
     *
     * @return string
     */
    public function buildQuickLinks()
    {
        if (!Session::userdata('quick_links') || Session::userdata('quick_links') == '') {
            return '';
        }

        $r = '';

        foreach (explode("\n", Session::userdata('quick_links')) as $row)
        {
            $x = explode('|', $row);

            $title = (isset($x[0])) ? $x[0] : '';
            $link  = (isset($x[1])) ? $x[1] : '';

            $r .= $this->anchor($link, $this->htmlAttribute($title), '', 1).'&nbsp;'.$this->quickSpan('spacer','|').'&nbsp;';
        }

        return $r;
    }

    /**
     * Fetch User's Quicktabs
     *
     * @return array
     */
    public function fetchQuickTabs()
    {
        $tabs = [];

        if (!Session::userdata('quick_tabs') || Session::userdata('quick_tabs') == '')
        {
            return $tabs;
        }

        foreach (explode("\n", Session::userdata('quick_tabs')) as $row)
        {
            $x = explode('|', $row);

            $title = (isset($x[0])) ? $x[0] : '';
            $link  = (isset($x[1])) ? $x[1] : '';

            $tabs[] = array($title, $link);
        }

        return $tabs;
    }

    /**
     * Build Quicktab for CP
     *
     * @return string
     */
    public function buildQuickTab()
    {
        $link  = '';
        $linkt = '';
        $show_link = true;

        if ($this->segment(2) != 'tab_manager' && $this->segment(2) != null) {
            $link = base64_encode($this->path);
        }

        // Do not allow creation of existing tabs
        if (Session::userdata('quick_tabs') and Session::userdata('quick_tabs') != '') {
            if (in_array($link, explode('|', Session::userdata('quick_tabs')))) {
                $show_link = false;
            }
        }

        $tablink = ($link != '' and $show_link === true) ? '/link='.$link.'/title='.base64_encode($this->title) : '';

        return $tablink;
    }

    /**
     * Simple CP page header
     *
     * No tabs
     *
     * @return string
     */
    public function simplePageHeader()
    {
        $site_name = Site::config('site_name');

        return <<<EOT
<div class="pageHeader">
    <div class="quickLinks">
        <div class="quickLinksLeft">
            <div class="simpleHeader">
                <span class="currentSite">{$site_name}</span>
            </div>
        </div>

        <div class="quickLinksRight">
            &nbsp;
        </div>
    </div>
</div>
EOT;

    }

    /**
     * Main control panel navigation
     *
     * @return string
     */
    public function pageNavigation()
    {
        // First we'll gather the navigation menu text in the selected language.
        $text = [
            'content'     => __('kilvin::cp.content'),
            'templates'   => __('kilvin::cp.templates'),
            'plugins'     => __('kilvin::cp.plugins'),
            'admin'       => __('kilvin::cp.administration')
        ];

        // Fetch the custom tabs if there are any
        $quicktabs = $this->fetchQuickTabs();

        // Set access flags
        $cells = [
            'c_lock' => 'can_access_content',
            't_lock' => 'can_access_templates',
            'p_lock' => 'can_access_plugins',
            'a_lock' => 'can_access_admin'
        ];

        // Dynamically set the table width based on the number
        // of tabs that will be shown.
        $tab_total  = sizeof($text) + count($quicktabs); // Total possible tabs + 1 for the Add Tab tab

        foreach ($cells as $key => $val) {
            if ( ! Session::access($val)) {
                $$key = 1;
                $tab_total--;
            } else {
                $$key = 0;
            }
        }

        if ($tab_total == 0) {
            $menu_padding = 0;
            $tab_width = 0;
        } else {
            $tab_padding  = ($tab_total > 6) ? 0 : 2; // in percentage
            $menu_padding = ($tab_total > 6) ? 0 : 2; // Padding on each side of menu

            $base_width = ($tab_total > 6) ? 100 : (100 - (2 * $menu_padding));

            $base_width -= 3; // Add Tab tab reduction

            $tab_width = floor(
                ($base_width - ($tab_total * $tab_padding))
                /
                $tab_total);
        }

        /*
        Does a custom tab need to be highlighted?
        Since users can have custom tabs we need to highlight them when the page is
        accessed.  However, when we do, we need to prevent any of the default tabs
        from being highlighted.  Say, for example, that someone creates a tab pointing
        to the Pages module.  When that tab is accessed it needs to be highlighted (obviously)
        but we don't want the MODULES tab to also be highlighted or it'll look funny.
        Since the Pages module is within the MODULES tab it'll hightlight itself automatically.
        So... we'll use a variable called:  $highlight_override
        When set to TRUE, this variable turns off all default tabs.
        The following code blasts thorough the GET variables to see if we have
        a custom tab to show.  If we do, we'll highlight it, and turn off
        all the other tabs.
        */

        $highlight_override = false;

        $tabs = '';
        $tabct = 1;
        if (count($quicktabs) > 0) {
            foreach ($quicktabs as $val) {
                // @todo - Add code for highlighting
                $tab_nav_on = false;

                $linktext = ( ! isset($text[$val[0]])) ? $val[0] : $text[$val[0]];
                $linktext = $this->cleanTabText($linktext);

                $class = ($tab_nav_on == true) ? 'tabActive' : 'tabInactive';
                $tabid = 'tab'.$tabct;
                $tabct ++;

                $tabs .= "<td class='mainMenuTab' width='".$tab_width."%'>";
                $tabs .= $this->anchor($val[1], $linktext, 'class="'.$class.'"');
                $tabs .= '</td>'.PHP_EOL;
            }
        }

        $r = '';

        // ------------------------------------
        //  Create Navigation Tabs
        // ------------------------------------

        // Define which nav item to show based on the group
        // permission settings and render the finalized navigaion
        $r .= $this->table('', '0', '0', '100%')
             .'<tr>'.PHP_EOL;

        // Spacer Tab
        $r .= $this->td('mainMenuTab', (($menu_padding <= 1) ? '': $menu_padding.'%'));
        $r .= '&nbsp;';
        $r .= '</td>'.PHP_EOL;

        // ------------------------------------
        //  Publish Tab
        // ------------------------------------

        // Define which nav item to show based on the group
        // permission settings and render the finalized navigaion

        $C = $this->segment(1);

        if ($c_lock == 0) {
            $r .= "<td class='mainMenuTab' width='".$tab_width."%'>";

            $class = ($C == 'content' AND $highlight_override == false) ? 'tabActive' : 'tabInactive';

            $r .= $this->anchor(
                'content',
                $this->cleanTabText($text['content']),
                'class="'.$class.'" ');
            $r .= '</td>'.PHP_EOL.PHP_EOL.PHP_EOL;
        }

        // ------------------------------------
        //  Custom Tabs
        // ------------------------------------

        $r .= $tabs;

        if ($t_lock == 0) {
            $class = ($C == 'templates' && $highlight_override == false) ? 'tabActive' : 'tabInactive';

            $r .= "<td class='mainMenuTab' width='".$tab_width."%'>";
            $r .= $this->anchor(
                'templates',
                $this->cleanTabText($text['templates']),
                'class="'.$class.'"');
            $r .= '</td>'.PHP_EOL;
        }

        if ($p_lock == 0) {
            $class = ($C == 'plugins' && $highlight_override == false) ? 'tabActive' : 'tabInactive';

            $r .= "<td class='mainMenuTab' width='".$tab_width."%'>";
            $r .= $this->anchor(
                'plugins',
                $this->cleanTabText($text['plugins']),
                'class="'.$class.'"');
            $r .= '</td>'.PHP_EOL;
        }

        if ($a_lock == 0) {
            $class = ((stristr($C, 'administration') || $C == 'members') && $highlight_override == false) ? 'tabActive' : 'tabInactive';

            $r .= "<td class='mainMenuTab' width='".$tab_width."%'>";
            $r .= $this->anchor(
                'administration',
                $this->cleanTabText($text['admin']),
                'class="'.$class.'"');
            $r .= '</td>'.PHP_EOL;
        }


        $r .= "<td class='mainMenuTab' width='2%'>";
            $r .= $this->anchor(
                'account/tabManager'.$this->buildQuickTab(),
                '+',
                'class="tabInactive"');
            $r .= '</td>'.PHP_EOL;


        $r .= $this->td('mainMenuTab', (($menu_padding <= 1) ? '': $menu_padding.'%'));
        $r .= '&nbsp;';
        $r .= '</td>'.PHP_EOL;

        $r .= '</tr>'.PHP_EOL.
              '</table>'.PHP_EOL.
              PHP_EOL;

        return $r;
    }

    /**
     * Quicktab Text cleanup
     *
     * @param string $str
     * @return string
     */
    private function cleanTabText($str = '')
    {
        if ($str == '') {
            return '';
        }

        $str = str_replace(' ', NBS, $str);
        $str = str_replace('"', '&quot;', $str);
        $str = str_replace("'", "&#39;", $str);

        return $str;
    }
    /**
     * Add CSRF fields to Forms
     *
     * @param string
     * @return string
     */
    private function csrfField($str = '')
    {
        $check = ($str != '') ? $str : $this->body;

        if (preg_match_all("/<form.*?>/", $check, $matches, PREG_SET_ORDER))
        {
            foreach ($matches as $val) {
                $check = str_replace($val[0], $val[0].PHP_EOL.csrf_field(), $check);
            }
        }

        if ($str != '') {
            return $check;
        }

        $this->body = $check;
    }

    /**
     * Build Page Breadcrumb
     *
     * @return string
     */
    public function breadcrumb($raw = false)
    {
        if ($C = Cp::segment(1)) {
            $link = $this->anchor('', $this->htmlAttribute(Site::config('site_name')));
        } else {
            $link = $this->anchor(
                '',
                $this->htmlAttribute(Site::config('site_name'))).
                    '&nbsp;'."&#8250;".
                    '&nbsp;'.__('kilvin::cp.homepage');
        }

        if (!empty($C)) {
            // The $special variable let's us add additional data to the query string
            // There are a few occasions where this is necessary

            $special = '';

            if ($this->pathVar('weblog_id')) {
                $special = '/weblog_id='.$this->pathVar('weblog_id');
            }

            // Build the link
            $name = ($C == 'templates') ? __('kilvin::cp.design') : __('kilvin::cp.'.$C);

            if (empty($name)) {
                $name = ucfirst($C);
            }

            if ($C == 'account') {
                if ($id = request()->input('id'))
                {
                    if ($id != Session::userdata('member_id'))
                    {
                        $name = __('kilvin::cp.user_account');

                        $special = AMP.'id='.$id;
                    }
                    else
                    {
                        $name = __('kilvin::cp.my_account');
                    }
                }
            }

            $url = $C.$special;

            if ($C == 'weblogs-administration') {
                $url = 'administration/weblogs-administration'.$special;
            }

            $link .= '&nbsp;'."&#8250;".'&nbsp;'.$this->anchor($url, $name);
        }

        if ($this->auto_crumb != '') {
            $link .= $this->auto_crumb;
        }


        // $this->crumb indicates the page being currently viewed.
        // It does not need to be a link.

        if ($this->crumb != '') {
            $link .= '&nbsp;'."&#8250;".'&nbsp;'.$this->crumb;
        }

        // Used in Views
        if ($raw === true) {
            return $link;
        }

        // This is the right side of the breadcrumb area.

        $ret = "<div class='breadcrumb'>";

        $ret .= $this->table('', '0', '0', '100%');
        $ret .= '<tr>'.PHP_EOL;
        $ret .= $this->tableCell('breadcrumbLeft', $this->span('crumblinks').$link.'</span>'.PHP_EOL);
        $ret .= $this->tableCell('breadcrumbRight', '', '270px', 'bottom', 'right');
        $ret .= '</tr>'.PHP_EOL;
        $ret .= '</table>'.PHP_EOL;
        $ret .= '</div>'.PHP_EOL;

        return $ret;
    }

    /**
     * Format simple breadcrumb item
     *
     * @return string
     */
    public function breadcrumbItem($item)
    {
        return '&nbsp;&#8250;&nbsp;'.$item;
    }

    /**
     * Add Required Span for Field
     *
     * @return string
     */
    public function required($blurb = '')
    {
        if ($blurb == 1)
        {
            $blurb = "<span class='default'>".'&nbsp;'.__('kilvin::cp.required_fields').'</span>';
        }
        elseif($blurb != '')
        {
            $blurb = "<span class='default'>".'&nbsp;'.$blurb.'</span>';
        }

        return "<span class='alert'>*</span>".$blurb.PHP_EOL;
    }

    /**
     * Copyright HTML for bottom of page
     *
     * @return string
     */
    public function copyright()
    {
        $logo = '<svg style="display: block; margin:5px auto;" version="1.0" xmlns="http://www.w3.org/2000/svg"
 width="30pt" height="30pt" viewBox="0 0 550.000000 550.000000"
 preserveAspectRatio="xMidYMid meet">
<g transform="translate(0.000000,550.000000) scale(0.100000,-0.100000)"
fill="#000000" stroke="none">
<path d="M2545 5424 c-108 -9 -336 -47 -445 -74 -962 -242 -1708 -988 -1950
-1950 -57 -227 -74 -379 -74 -650 0 -271 17 -423 74 -650 242 -962 988 -1708
1950 -1950 227 -57 379 -74 650 -74 206 0 268 3 390 23 590 92 1094 346 1504
757 411 410 665 914 757 1504 33 209 33 571 0 780 -92 590 -346 1094 -757
1504 -409 410 -919 667 -1499 756 -136 21 -471 34 -600 24z m560 -433 c474
-79 882 -285 1224 -616 360 -349 583 -782 667 -1300 25 -153 26 -496 0 -650
-80 -494 -284 -906 -621 -1254 -349 -360 -782 -583 -1300 -667 -82 -14 -165
-18 -325 -18 -222 0 -328 11 -520 55 -226 53 -517 177 -715 306 -269 176 -525
438 -692 706 -161 261 -267 550 -319 872 -25 154 -25 496 0 650 80 494 284
906 621 1254 381 393 889 634 1445 685 108 10 418 -4 535 -23z"/>
<path fill="blue" d="M2542 4515 c16 -46 32 -104 36 -127 13 -81 6 -191 -18 -264 -56 -171
-140 -297 -420 -634 -248 -298 -385 -494 -459 -655 -10 -22 -28 -61 -39 -86
-25 -54 -59 -168 -77 -259 -47 -225 -18 -460 83 -685 49 -110 138 -252 241
-385 116 -150 529 -546 549 -526 2 3 -19 31 -48 63 -62 68 -138 174 -187 261
-143 255 -146 520 -8 793 18 37 51 94 72 129 l40 62 6 -49 c8 -67 34 -141 67
-185 29 -41 78 -83 86 -75 3 3 0 79 -7 170 -23 311 20 512 153 712 47 70 155
184 214 225 l41 29 -13 -39 c-38 -109 -33 -218 15 -325 26 -59 105 -180 212
-325 121 -164 161 -224 207 -315 90 -178 124 -342 103 -503 -23 -179 -133
-392 -291 -565 -29 -32 -50 -60 -48 -63 10 -10 197 153 338 295 677 680 733
1280 189 2018 -21 28 -43 54 -49 58 -6 3 -7 11 -4 17 4 7 2 8 -4 4 -9 -5 -9
-12 0 -26 29 -46 -25 -278 -85 -365 -44 -65 -169 -176 -182 -163 -2 2 4 76 14
163 26 226 22 570 -8 705 -89 396 -302 705 -657 952 -27 20 -59 38 -70 42 -19
6 -19 2 8 -79z"/>
</g>
</svg>';

        return
            "<div class='copyright'>".
                $logo.
                PHP_EOL.
                '<br>'.
                PHP_EOL.
                $this->anchor(
                    'https://arliden.com/',
                    CMS_NAME." ".KILVIN_VERSION
                ).
                ' • '.
                __('kilvin::cp.copyright').
                ' &#169; 2019 Arliden, LLC'.
                BR.PHP_EOL.
                str_replace(
                    "%x",
                    "{elapsed_time}",
                    __('kilvin::cp.page_rendered')
                ).
                ' • '.
                str_replace(
                    "%x",
                    sizeof(DB::getQueryLog()),
                    __('kilvin::cp.queries_executed')
                ).
            "</div>".
            PHP_EOL;
    }

    /**
     * Build Error Message Page
     *
     * @param string $message The error message
     * @param integer $n How many steps to go back
     * @return string
     */
    public function errorMessage($message = '', $n = 1)
    {
        $this->title = __('kilvin::core.error');
        $this->crumb = __('kilvin::core.error');

        if (is_array($message)) {
            $message = implode(BR, $message);
        }

        $this->body = $this->quickDiv('alert-heading defaultCenter', __('kilvin::core.error'))
                .$this->div('box')
                .$this->div('defaultCenter')
                .$this->quickDiv('defaultBold', $message);

       if ($n != 0) {
            $this->body .=
                BR.
                PHP_EOL.
                "<a href='javascript:history.go(-".$n.")' style='text-transform:uppercase;'>&#171; ".
                "<b>".
                    __('kilvin::core.back').
                "</b></a>";
       }

        $this->body .= BR.BR.'</div>'.PHP_EOL.'</div>'.PHP_EOL;
    }

    /**
     * Unauthorized Access message page
     *
     * @param string $message
     * @return string
     */
    public function unauthorizedAccess($message = '')
    {
        $this->title = __('kilvin::unauthorized');

        $msg = ($message == '') ? __('kilvin::cp.unauthorized_access') : $message;

        $this->body = $this->quickDiv('highlight', BR.$msg);
    }

    /**
     * Add in Footer Javascript
     *
     * @param $script string
     * @return void
     */
    public function footerJavascript($script)
    {
        $this->footer_javascript .= PHP_EOL.$script;
    }

    /**
     * Javascript for CP DropMenus (Sites + Weblogs)
     *
     * @return string
     */
    private function dropMenuJavascript()
    {
        ob_start();
        ?>
            <script type="text/javascript">

                $(document).ready(function() {
                    // Hide success message after a couple seconds
                    if ($('.success-message').length) {
                        setTimeout(function(){
                            $('.success-message').slideUp(600);
                        }, 3000);
                    }
                });

                function contentDropMenuSwitch(e, visible)
                {
                    e.preventDefault();
                    e.stopPropagation();

                    $('.siteDropMenu').css('display', 'none').css('visibility', 'hidden');

                    var el = $(e.target).parent().children('.tabDropMenu').first();

                    if (visible || el.css('visibility') == 'visible')
                    {
                        el.css('visibility', 'hidden');
                        el.css('display', 'none');
                    }
                    else
                    {
                        el.css('visibility', 'visible');
                        el.css('display', 'block');
                    }
                }

                function weblogMenuSwitch(e, visible)
                {
                    e.preventDefault();
                    e.stopPropagation();

                    var el = $(e.target).parent().children('.weblog-drop-menu').first();

                    if (visible || el.css('visibility') == 'visible')
                    {
                        el.css('visibility', 'hidden');
                        el.css('display', 'none');
                    }
                    else
                    {
                        el.css('visibility', 'visible');
                        el.css('display', 'inline-block');
                    }
                }

                function siteDropdown(e, visible)
                {
                    e.preventDefault();
                    e.stopPropagation();

                    $('.tabDropMenu').css('display', 'none').css('visibility', 'hidden');

                    var el = $(e.target).parent().parent().children('.siteDropMenu').first();

                    if (visible || el.css('visibility') == 'visible')
                    {
                        el.css('visibility', 'hidden');
                        el.css('display', 'none');
                    }
                    else
                    {
                        el.css('visibility', 'visible');
                        el.css('display', 'block');
                    }
                }

                $(document).click(function(){
                  $('.siteDropMenu').css('display', 'none').css('visibility', 'hidden');
                  $('.tabDropMenu').css('display', 'none').css('visibility', 'hidden');
                });
            </script>

        <?php

        $out = ob_get_contents();
        ob_end_clean();
        return $out;
    }

    /**
     * Build Simple Pagination Links
     *
     * @param string $base_path Path for the CP Page
     * @param integer $total_count Total number of results
     * @param integer $per_page Number of results per page
     * @param integer $cur_page Current page being displayed
     * @param string $qstr_var The Query string variable holding the row number
     * @return string
     */
    public function pager($base_path = '', $total_count = '', $per_page = '', $cur_page = '', $qstr_var = '')
    {
        $PGR = new Paginate;

        $PGR->base_url     = kilvin_cp_url($base_path);
        $PGR->total_count  = $total_count;
        $PGR->per_page     = $per_page;
        $PGR->cur_page     = $cur_page;
        $PGR->qstr_var     = $qstr_var;

        return $PGR->showLinks();
    }

    /**
     * Delete Confirmation Page
     *
     * Creates a standardized confirmation message used whenever something needs to be deleted
     *
     * @param array
     * @return string
     */
    public function deleteConfirmation(array $data = [])
    {
        $vals = ['url', 'path', 'heading', 'message', 'item', 'hidden', 'extra'];

        foreach ($vals as $val) {
            if ( ! isset($data[$val])) {
                $data[$val] = '';
            }
        }

        $data['url'] = $data['url'] ? $data['url'] :  kilvin_cp_url($data['path']);

        $r = $this->formOpen(['action' => $data['url']]);

        if (is_array($data['hidden'])) {
            foreach ($data['hidden'] as $key => $val) {
                $r .= $this->input_hidden($key, $val);
            }
        }

        foreach (['heading', 'message', 'extra'] as $key) {
            if (!empty($data[$key]) && !starts_with($data[$key], 'kilvin::')) {
                $data[$key] = 'kilvin::'.$data[$key];
            }
        }

        $r  .=   $this->quickDiv('alertHeading', __($data['heading']))
                .$this->div('box')
                .$this->quickDiv('littlePadding', '<b>'.__($data['message']).'</b>')
                .$this->quickDiv('littlePadding', $this->quickDiv('highlight_alt', $data['item']));

        if ($data['extra'] != '') {
            $r .= $this->quickDiv('littlePadding', '<b>'.__($data['extra']).'</b>');
        }

        $r .=    $this->quickDiv('littlePadding', $this->quickDiv('alert', __('kilvin::cp.action_can_not_be_undone')))
                .$this->quickDiv('paddingTop', $this->input_submit(__('kilvin::cp.delete')))
                .'</div>'.PHP_EOL
                .'</form>'.PHP_EOL;

        return $r;
    }

    /**
     * Create a <div> opening tag
     *
     * @param string $class The CSS class
     * @param string $align
     * @param string $id
     * @param string $name
     * @param string $extra
     * @return string
     */
    public function div($class='default', $align = '', $id = '', $name = '', $extra='')
    {
        if ($align != '') {
            $align = " align='{$align}'";
        }

        if ($id != '') {
            $id = " id='{$id}' ";
        }

        if ($name != '') {
            $name = " name='{$name}' ";
        }

        $extra = ' '.trim($extra);

        return PHP_EOL."<div class='{$class}' {$id}{$name}{$align}{$extra}>".PHP_EOL;
    }

    /**
     * Create a <div> tag, including data
     *
     * @param string $class The CSS class
     * @param string $data
     * @param string $id
     * @param string $extra
     * @return string
     */
    public function quickDiv($class='', $data = '', $id = '', $extra = '')
    {
        if ($class == '') {
            $class = 'default';
        }

        if ($id != '') {
            $id = " id='{$id}' ";
        }

        $extra = ' '.trim($extra);

        return PHP_EOL."<div class='{$class}' {$id}{$extra}>".$data.'</div>'.PHP_EOL;
    }

    /**
     * Create a <label> tag
     *
     * @param string $data
     * @param string $for
     * @param string $extra
     * @return string
     */
    public function qlabel($data = '', $for = '', $extra = '')
    {
        return PHP_EOL."<label for='{$for}' {$extra}>".$data.'</label>'.PHP_EOL;
    }

    /**
     * Create a <span> opening tag
     *
     * @param string $style
     * @param string $extra
     * @return string
     */
    public function span($style='default', $extra = '')
    {
        if ($extra != '')
            $extra = ' '.$extra;

        return "<span class='{$style}'{$extra}>".PHP_EOL;
    }

    /**
     * Create a <span> tag with data
     *
     * @param string $style
     * @param string $data
     * @param string $id
     * @param string $extra
     * @return string
     */
    public function quickSpan($style='', $data = '', $id = '', $extra = '')
    {
        if ($style == '') {
            $style = 'default';
        }
        if ($id != '') {
            $id = " name = '{$id}' id='{$id}' ";
        }
        if ($extra != '') {
            $extra = ' '.$extra;
        }

        return PHP_EOL."<span class='{$style}'{$id}{$extra}>".$data.'</span>'.PHP_EOL;
    }

    /**
     * Create a <h#> tag with data
     *
     * @param string $data
     * @param integer|string $h
     * @return string
     */
    public function heading($data = '', $h = '1')
    {
        return PHP_EOL."<h".$h.">".$data."</h".$h.">".PHP_EOL;
    }

    /**
     * Create a page header inside a table
     *
     * @param string $title
     * @param array $right_links An array of links to go on the right side of the page
     * @return string
     */
    public function header($title, $right_links = [])
    {
        $r = $this->table('', '', '', '97%')
             .'<tr>'.PHP_EOL
             .$this->td('', '', '', '', 'top')
             .$this->heading($title ?? '&nbsp;');

        $r .= '</td>'.PHP_EOL
             .$this->td('', '', '', '', 'top');

        $r .= $this->div('defaultRight');

        $anchors = [];
        foreach($right_links as $right_link) {
            $anchors[] = $this->anchor($right_link[0], '<strong>'.$right_link[1].'</strong>');
        }

        $r .= implode('<span class="menu-spacer"></span>', $anchors);

        $r .= '</div>'.PHP_EOL;

        $r .= '</td>'.PHP_EOL
             .'</tr>'.PHP_EOL
             .'</table>'.PHP_EOL
             .PHP_EOL;

        return $r;
    }

    /**
     * Create an <a> tag
     *
     * @param string $path
     * @param string $name
     * @param string $extra
     * @return string
     */
    public function anchor($path, $name = '', $extra = '')
    {
        if ($name == '') {
            return '';
        }

        $url = kilvin_cp_url($path);

        $url .= $this->url_append;

        return "<a href='{$url}' ".$extra.">$name</a>";
    }

    /**
     * Create an <a> tag
     *
     * @param string $address
     * @param string $label
     * @param string $extra
     * @return string
     */
    public function mailto($address, $label = '', $extra = '')
    {
        return "<a href='mailto:{$address}' ".$extra.">$label</a>";
    }

    /**
     * Create an <a> tag for a popup
     *
     * @param string $path
     * @param string $name
     * @param integer $width
     * @param integer $height
     * @param string $class
     * @return string
     */
    public function anchorpop($path, $name, $width='500', $height='480', $class = '')
    {
        $url = kilvin_cp_url($path);

        return "<a href='javascript:nullo();' onclick=\"window.open('{$url}', '_blank', 'width={$width},height={$height},scrollbars=yes,status=yes,screenx=0,screeny=0,resizable=yes'); return false;\" class='{$class}'>$name</a>";
    }

    /**
     * Create an <button> tag for a popup
     *
     * @param string $path CP Path
     * @param string $name
     * @param integer $width
     * @param integer $height
     * @param string $class
     * @return string
     */
    public function buttonpop($path, $name, $width='500', $height='480', $class = '')
    {
        $url = kilvin_cp_url($path);

        return "<button href='javascript:nullo();' onclick=\"window.open('{$url}', '_blank', 'width={$width},height={$height},scrollbars=yes,status=yes,screenx=0,screeny=0,resizable=yes'); return false;\" type='submit' class='{$class}'>$name</button>";
    }

    /**
     * Create a group for an item
     *
     * @param string $top
     * @param string $bottom
     * @return string
     */
    public function itemgroup($top = '', $bottom = '')
    {
        return $this->div('littlePadding').
               $this->quickDiv('itemTitle', $top).
               $bottom.
               '</div>'.PHP_EOL;
    }

    /**
     * Opening <table> tag
     *
     * @param array $props Properties for the tag
     * @return string
     */
    public function tableOpen($props = [])
    {
        $str = '';

        foreach ($props as $key => $val)
        {
            if ($key == 'width')
            {
                $str .= " style='width:{$val};' ";
            }
            else
            {
                $str .= " {$key}='{$val}' ";
            }
        }

        $required = array('cellspacing' => '0', 'cellpadding' => '0', 'border' => '0');

        foreach ($required as $key => $val)
        {
            if ( ! isset($props[$key]))
            {
                $str .= " {$key}='{$val}' ";
            }
        }

        return "<table{$str}>".PHP_EOL;
    }

    /**
     * Create a <tr> tag with enclosed <td> cells
     *
     * @param array $array It's complicated
     * @return string
     */
    public function tableRow($array = array())
    {
        $params     = '';
        $content    = '';
        $end_row    = false;

        $str = "<tr>".PHP_EOL;

        foreach($array as $key => $val)
        {
            if (is_array($val))
            {
                $params     = '';
                $content    = '';

                foreach($val as $k => $v)
                {
                    if ($k == 'width')
                    {
                        $params .= " style='width:{$v};'";
                    }
                    else
                    {
                        if ($k == 'text')
                        {
                            $content = $v;
                        }
                        else
                        {
                            $params .= " {$k}='{$v}'";
                        }
                    }
                }

                $str .= "<td".$params.">";
                $str .= $content;
                $str .= "</td>".PHP_EOL;
            }
            else
            {
                $end_row = true;

                if ($key == 'width')
                {
                    $params .= " style='width:{$val};'";
                }
                else
                {
                    if ($key == 'text')
                    {
                        $content .= $val;
                    }
                    else
                    {
                        $params .= " {$key}='{$val}'";
                    }
                }
            }
        }

        if ($end_row == true)
        {
            $str .= "<td".$params.">";
            $str .= $content;
            $str .= "</td>".PHP_EOL;
        }

        $str .= "</tr>".PHP_EOL;

        return $str;
    }

    /*  EXAMPLE:

        The first parameter is an array containing the "action" and any other items that
        are desired in the form opening.  The second optional parameter is an array of hidden fields

        $r = Cp::formOpen(
				array(
						'action'    => 'plugins/Groot',
						'method'    => 'post',
						'name'      => 'entryform',
						'id'        => 'entryform'
					 ),
				array(
						'page_id' => 23
					)
			 );

        The above code will produce:

        <form action="admin/plugins/Groot" method="post" name="entryform" id="entryform" />
        <input type="hidden" name="page_id" value="23" />
        <input type="hidden" name="status" value="open" />

        Notes:
                The 'method' in the first parameter is not required.  It ommited it'll be set to "post".

                If the first parameter does not contain an array it is assumed that it contains
                the "action" and will be treated as such.
    */

    /**
     * Create an opening <form> tag
     *
     * @param string $data
     * @param array $hidden Hidden fields for form
     * @return string
     */
    public function formOpen($data = '', $hidden = [])
    {
        if ( ! is_array($data)) {
            $data = ['action' => kilvin_cp_url($data)];
        }

        if ( ! isset($data['action'])) {
            $data['action'] = '';
        }

        if ( ! isset($data['method'])) {
            $data['method'] = 'post';
        }

        if ( ! isset($data['class'])) {
            $data['class'] = 'cp-form';
        } else {
            $data['class'] .= ' cp-form';
        }

        $str = '';
        foreach ($data as $key => $val) {
            if ($key == 'action') {
                $str .= " {$key}='".kilvin_cp_url($val).$this->url_append."'";
            } else {
                $str .= " {$key}='{$val}'";
            }
        }

        $form = PHP_EOL."<form{$str}>".PHP_EOL.csrf_field().PHP_EOL;

        if (count($hidden) > 0) {
            foreach ($hidden as $key => $val) {
                $form .= "<div class='hidden'><input type='hidden' name='{$key}' value='".escape_attribute($val)."' /></div>".PHP_EOL;
            }
        }

        return $form;
    }

    /**
     * Input tag of type hidden
     *
     * @param string $name
     * @param string $value
     * @return string
     */
    public function input_hidden($name, $value = '')
    {
        if ( ! is_array($name)) {
            return "<div class='hidden'><input type='hidden' name='{$name}' value='".escape_attribute($value)."' /></div>".PHP_EOL;
        }

        $form = '';

        foreach ($name as $key => $val) {
            $form .= "<div class='hidden'><input type='hidden' name='{$key}' value='".escape_attribute($val)."' /></div>".PHP_EOL;
        }

        return $form;
    }

    /**
     * Input tag of type text
     *
     * @param string $name
     * @param string $value
     * @param integer $size
     * @param integer $maxl
     * @param string $style
     * @param integer $width
     * @param string $extra
     * @param bool $convert
     * @return string
     */
    public function input_text(
        $name,
        $value='',
        $size = '90',
        $maxl = '100',
        $style='input',
        $width='100%',
        $extra = '',
        $convert = false)
    {
        $value = escape_attribute($value);

        $id = (stristr($extra, 'id=')) ? '' : "id='".str_replace(['[',']', ' '], '', $name)."'";

        return "<input style='width:{$width}' type='text' name='{$name}' {$id} value='".$value."' size='{$size}' maxlength='{$maxl}' class='{$style}' $extra />".PHP_EOL;
    }

    /**
     * Input tag of type password
     *
     * @param string $name
     * @param string $value
     * @param integer $size
     * @param integer $maxl
     * @param string $style
     * @param integer $width
     * @return string
     */
    public function input_pass($name, $value='', $size = '20', $maxl = '100', $style='input', $width='100%')
    {
        $id = "id='".str_replace(array('[',']'), '', $name)."'";

        return "<input style='width:{$width}' type='password' name='{$name}' {$id} value='{$value}' size='{$size}' maxlength='{$maxl}' class='{$style}' />".PHP_EOL;
    }

    /**
     * Create a textarea
     *
     * @param string $name
     * @param string $value
     * @param integer $rows
     * @param string $style
     * @param integer $width
     * @param string $extra
     * @param bool $convert
     * @return string
     */
    public function input_textarea($name, $value='', $rows = '20', $style='textarea', $width='100%', $extra = '', $convert = false)
    {
        if (!empty($width)) {
            $width = "width:{$width};";
        }

        $value = escape_attribute($value);

        $id = (stristr($extra, 'id=')) ? '' : "id='".str_replace(array('[',']'), '', $name)."'";

        return "<textarea style='{$width}' name='{$name}' {$id} cols='90' rows='{$rows}' class='{$style}' $extra>".$value."</textarea>".PHP_EOL;
    }

    /**
     * Create an opening <select> tag
     *
     * @param string $name
     * @param string $multi
     * @param integer $size
     * @param string $width
     * @param string $extra
     * @return string
     */
    public function input_select_header($name, $multi = '', $size=3, $width='', $extra='')
    {
        if ($multi != '')
            $multi = " size='".$size."' multiple='multiple'";

        if ($multi == '')
        {
            $class = 'select';
        }
        else
        {
            $class = 'multiselect';

            if ($width == '')
            {
                $width = '45%';
            }
        }

        if ($width != '')
        {
            $width = "style='width:".$width."'";
        }

        $extra = ($extra != '') ? ' '.trim($extra) : '';

        return PHP_EOL."<select name='{$name}' class='{$class}'{$multi} {$width}{$extra}>".PHP_EOL;
    }

    /**
     * Create an <select> tag option
     *
     * @param string $value
     * @param string $item
     * @param mixed $selected
     * @param string $extra
     * @return string
     */
    public function input_select_option($value, $item, $selected = '', $extra='')
    {
        $selected = (! empty($selected) and $selected != '') ? " selected='selected'" : '';
        $extra    = ($extra != '') ? " ".trim($extra)." " : '';

        return "<option value='".$value."'".$selected.$extra.">".$item."</option>".PHP_EOL;
    }

    /**
     * Closing select tag.
     *
     * @return string
     */
    public function input_select_footer()
    {
        return "</select>".PHP_EOL;
    }

    /**
     * Create input field of type checkbox
     *
     * @param string $name
     * @param string $value
     * @param mixed $checked
     * @param string $extra
     * @return string
     */
    public function input_checkbox($name, $value='', $checked = '', $extra = '')
    {
        $checked = (empty($checked) or $checked === 'n') ? '' : "checked='checked'";

        return "<input class='checkbox' type='checkbox' name='{$name}' value='{$value}' {$checked} {$extra}>".PHP_EOL;
    }

    /**
     * Create input field of type radio
     *
     * @param string $name
     * @param string $value
     * @param mixed $checked
     * @param string $extra
     * @return string
     */
    public function input_radio($name, $value='', $checked = 0, $extra = '')
    {
        $checked = ($checked == 0) ? '' : "checked='checked'";

        return "<input class='radio' type='radio' name='{$name}' value='{$value}' {$checked}{$extra} />".PHP_EOL;
    }

    /**
     * Create input field of type submit
     *
     * @param string $value
     * @param string $name
     * @param string $extra
     * @return string
     */
    public function input_submit($value='', $name = '', $extra='')
    {
        $value = ($value == '') ? __('kilvin::cp.submit') : $value;
        $name  = ($name == '') ? '' : "name='".$name."'";

        if ($extra != '') {
            $extra = ' '.$extra.' ';
        }

        return PHP_EOL."<input $name type='submit' value='{$value}' {$extra} />".PHP_EOL;
    }

    /**
     * Javascript for Magic Checkboxes
     *
     * @return string
     */
    public function magicCheckboxesJavascript()
    {
        ob_start();

        ?>

<script type="text/javascript">

var lastChecked = null;

$( document ).ready(function() {

	$('input[name=toggle_all]').on('click', function(e) {
		var check_all = $(this).is(':checked');

		// The double usage of prop() and attr() are because of a Chrome bug
		if($(this).is(':checked')) {
			$('input[name=toggle\\[\\]').prop('checked', true).attr('checked', 'checked');
		} else {
			$('input[name=toggle\\[\\]').prop('checked', false).removeAttr('checked');
		}
    });

    var $chkboxes = $('input[name=toggle\\[\\]');
    $chkboxes.click(function(e) {
        if(!lastChecked) {
            lastChecked = this;
            return;
        }

        if(e.shiftKey) {
            var start = $chkboxes.index(this);
            var end = $chkboxes.index(lastChecked);

            if (lastChecked.checked) {
				$chkboxes.slice(Math.min(start,end), Math.max(start,end)).attr('checked', 'checked').prop('checked', true);
			} else {
				$chkboxes.slice(Math.min(start,end), Math.max(start,end)).removeAttr('checked').prop('checked', false);
			}
        }

        lastChecked = this;
    });
});

</script>
        <?php

        $buffer = ob_get_contents();

        ob_end_clean();

        return $buffer;
    }



    /**
     * Opening <table> tag
     *
     * @param string $class
     * @param integer $cellspacing
     * @param integer $cellpadding
     * @param string $width
     * @param integer $border
     * @param string $align
     * @return string
     */
    public function table($class='', $cellspacing='0', $cellpadding='0', $width='100%', $border='0', $align='')
    {
        $class   = ($class != '') ? " class='{$class}' " : '';
        $width   = ($width != '') ? " style='width:{$width};' " : '';
        $align   = ($align != '') ? " align='{$align}' " : '';

        if ($border == '')      $border = 0;
        if ($cellspacing == '') $cellspacing = 0;
        if ($cellpadding == '') $cellpadding = 0;

        return PHP_EOL.
            "<table border='{$border}' cellspacing='{$cellspacing}' cellpadding='{$cellpadding}'{$width}{$class}{$align}>".
            PHP_EOL;
    }

    /**
     * Make a Full Row for a Table, Including Cells
     *
     * @param string $style
     * @param string|array $data If string, single cell, if array then multiples
     * @param boolean $auto_width
     * @return string
     */
    public function tableQuickRow($style='', $data = '', $auto_width = false)
    {
        $width = '';
        $style = ($style != '') ? " class='{$style}' " : '';

        if (is_array($data))
        {
            if ($auto_width != false AND count($data) > 1)
            {
                $width = floor(100/count($data)).'%';
            }

            $width = ($width != '') ? " style='width:{$width};' " : '';

            $r = "<tr>";

            foreach($data as $val)
            {
                $r .=  "<td".$style.$width.">".
                       $val.
                       '</td>'.PHP_EOL;
            }

            $r .= "</tr>".PHP_EOL;

            return $r;
        }
        else
        {
            return

                "<tr>".
                "<td".$style.$width.">".
                $data.
                '</td>'.PHP_EOL.
                "</tr>".PHP_EOL;
        }
    }

    /**
     * Create Single Table Cell
     *
     * @param string $class
     * @param string|array $data
     * @param string $width
     * @param string $valign
     * @param string $align
     * @return string
     */
    public function tableCell($class = '', $data = '', $width = '', $valign = '', $align = '')
    {
        if (is_array($data))
        {
            $r = '';

            foreach($data as $val)
            {
                $r .=  $this->td($class, $width, '', '', $valign, $align).
                       $val.
                       '</td>'.PHP_EOL;
            }

            return $r;
        }
        else
        {
            return

                $this->td($class, $width, '', '', $valign, $align).
                $data.
                '</td>'.PHP_EOL;
        }
    }

    /**
     * Table header row and cells
     *
     * @param string $style
     * @param string|array $data
     * @param string $width
     * @param string $valign
     * @param string $align
     * @return string
     */
    public function tableQuickHeader($style = '', $data = '', $width = '', $valign = '', $align = '')
    {
        if (is_array($data))
        {
            $r = '';

            foreach($data as $val)
            {
                $r .= $this->th('', $width, '', '', $valign, $align).
                       $val.
                       '</th>'.PHP_EOL;
            }

            return $r;
        }
        else
        {
            return

                $this->th('', $width, '', '', $valign, $align).
                $data.
                '</th>'.PHP_EOL;
        }
    }

    /**
     * Opening Table header cell
     *
     * @param string $class
     * @param string $width
     * @param integer $colspan
     * @param integer $rowspan
     * @param string $valign
     * @param string $align
     * @return string
     */
    public function th($class='', $width='', $colspan='', $rowspan='', $valign = '', $align = '')
    {

        if (!empty($class)) {
            $class = " class='".$class."' ";
        }

        $width   = ($width   != '') ? " style='width:{$width};'" : '';
        $colspan = ($colspan != '') ? " colspan='{$colspan}'"   : '';
        $rowspan = ($rowspan != '') ? " rowspan='{$rowspan}'"   : '';
        $valign  = ($valign  != '') ? " valign='{$valign}'"     : '';
        $align   = ($align  != '')  ? " align='{$align}'"       : '';

        return PHP_EOL."<th ".$class.$width.$colspan.$rowspan.$valign.$align.">".PHP_EOL;
    }

    /**
     * Single table cell
     *
     * @param string $class
     * @param string $width
     * @param integer $colspan
     * @param integer $rowspan
     * @param string $valign
     * @param string $align
     * @return string
     */
    public function td($class='', $width='', $colspan='', $rowspan='', $valign = '', $align = '')
    {
        if (!empty($class) && $class != 'none') {
            $class = " class='".$class."' ";
        }

        $width   = ($width   != '') ? " style='width:{$width};'" : '';
        $colspan = ($colspan != '') ? " colspan='{$colspan}'"   : '';
        $rowspan = ($rowspan != '') ? " rowspan='{$rowspan}'"   : '';
        $valign  = ($valign  != '') ? " valign='{$valign}'"     : '';
        $align   = ($align  != '')  ? " align='{$align}'"       : '';

        return PHP_EOL."<td ".$class.$width.$colspan.$rowspan.$valign.$align.">".PHP_EOL;
    }

    /**
     * Unavailable Names for Weblog and Member Custom Fields
     *
     * @return array
     */
    public function unavailableFieldNames()
    {
        $weblog_vars = [
            'author', 'author_id', 'bday_d', 'bday_m',
            'bday_y', 'bio',
            'email', 'entry_date', 'entry_id',
            'expiration_date', 'interests',
            'ip_address', 'location',
            'occupation', 'permalink',
            'photo_image_height', 'photo_image_width', 'photo_url',
            'screen_name', 'status',
            'switch', 'title', 'total_results',
            'trimmed_url', 'url',
            'url_title', 'weblog',
            'weblog_id', 'id',
        ];

        $global_vars = [
            'cms', 'now',
            'debug_mode', 'elapsed_time', 'email',
            'group_description', 'member_group_id',
            'ip_address', 'location',
            'member_group', 'member_id',
            'screen_name', 'site_name', 'site_handle',
            'site_url', 'total_entries',
            'total_queries', 'notification_sender_email', 'version'
        ];

        $orderby_vars = [
            'date', 'entry_date', 'expiration_date',
            'random', 'screen_name', 'title',
            'url_title'
        ];

        return array_unique(array_merge($weblog_vars, $global_vars, $orderby_vars));
    }

    /**
     * Used with Tabs to prevent user inputed data from breaking HTML
     *
     * @param string $label
     * @param integer $quotes
     * @return string
     */
    public function htmlAttribute($label, $quotes = ENT_QUOTES)
    {
        return htmlspecialchars($label, $quotes, 'UTF-8');
    }

    /**
     * Returns the final output for a CP page built with this class
     *
     * Slowly but surely we will convert to Twig stuff but we need all the above FOR NOW
     *
     * @return string
     */
    public function output()
    {
        return $this->output;
    }

   /**
     * Log a Control Panel action
     *
     * @param string|array $action
     * @return void
     */
    public function log($action)
    {
		if ($action == '') {
			return;
		}

        if (is_array($action)) {
        	if (count($action) == 0) {
        		return;
        	}

            $action = implode("\n", $action);
        }

        DB::table('cp_log')
        	->insert(
        		[
					'member_id'  	=> Session::userdata('member_id'),
					'screen_name'	=> Session::userdata('screen_name'),
					'ip_address' 	=> request()->ip(),
					'act_date'   	=> Carbon::now(),
					'action'     	=> $action,
					'site_id'	 	=> Site::config('site_id')
				]);
    }

    /**
     * Output CP Message Screen  (What did you do this time, user?!)
     *
     * @param string
     * @return void
     */
    public function userError($errors)
    {

        $vars = [
            'title'     => __('kilvin::core.error'),
            'errors'    => (array) $errors,
            'link'      => [
                'url' => 'JavaScript:history.go(-1)',
                'name' => __('kilvin::core.go_back')
            ]
        ];

        return view('kilvin::cp.errors.error', $vars);
    }

    /**
     * SVG Calendar Image
     *
     * @return string
     */
    public function calendarImage()
    {
        return '<!-- Calendar icon by Icons8 -->
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.0" x="0px" y="0px" viewBox="0 0 30 30" style="enable-background:new 0 0 30 30;" class="icon icons8-Calendar" ><g> <rect x="2.5" y="2.5" style="fill:#FFFFFF;" width="25" height="25"></rect>  <g>     <path style="fill:#788B9C;" d="M27,3v24H3V3H27 M28,2H2v26h26V2L28,2z"></path>   </g></g><g> <rect x="2.5" y="2.5" style="fill:#F78F8F;" width="25" height="5"></rect>   <g>     <path style="fill:#C74343;" d="M27,3v4H3V3H27 M28,2H2v6h26V2L28,2z"></path> </g></g><rect x="10" y="11" style="fill:#C5D4DE;" width="2" height="2"></rect><rect x="14" y="11" style="fill:#C5D4DE;" width="2" height="2"></rect><rect x="18" y="11" style="fill:#C5D4DE;" width="2" height="2"></rect><rect x="22" y="11" style="fill:#C5D4DE;" width="2" height="2"></rect><rect x="6" y="15" style="fill:#C5D4DE;" width="2" height="2"></rect><rect x="10" y="15" style="fill:#C5D4DE;" width="2" height="2"></rect><rect x="14" y="15" style="fill:#C5D4DE;" width="2" height="2"></rect><rect x="18" y="15" style="fill:#C5D4DE;" width="2" height="2"></rect><rect x="22" y="15" style="fill:#C5D4DE;" width="2" height="2"></rect><rect x="6" y="19" style="fill:#C5D4DE;" width="2" height="2"></rect><rect x="10" y="19" style="fill:#C5D4DE;" width="2" height="2"></rect><rect x="14" y="19" style="fill:#C5D4DE;" width="2" height="2"></rect><rect x="18" y="19" style="fill:#C5D4DE;" width="2" height="2"></rect><rect x="3" y="25" style="fill:#E1EBF2;" width="24" height="2"></rect></svg>';
    }
}
