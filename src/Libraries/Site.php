<?php

namespace Kilvin\Libraries;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Kilvin\Facades\Plugins;
use Carbon\Carbon;
use Kilvin\Core\Session;
use Kilvin\Exceptions\CmsFatalException;

/**
 * Site Data and Functionality
 */
class Site
{
    private $config = [];
    private $original_config = [];

    // seven special TLDs for cookie domains
    private $special_tlds = [
        'com', 'edu', 'net', 'org', 'gov', 'mil', 'int'
    ];

    /**
     * Set a Config value
     *
     * @param   string
     * @param   string
     * @return  void
     */
    public function setConfig($which, $value)
    {
        if ( ! isset($this->config[$which])) {
            return;
        }

        $this->config[$which] = $value;
    }

    /**
     * Fetch config value
     *
     * @param   string
     * @param   boolean
     * @return  mixed
     */
    public function config($which = '', $add_slash = false)
    {
       if ($which == '') {
            return null;
        }

        if ( ! isset($this->config[$which])) {
            return null;
        }

        $pref = $this->config[$which];

        if (is_string($pref)) {
            if ($add_slash !== false) {
                $pref = rtrim($pref, '/').'/';
            }

            $pref = str_replace('\\\\', '\\', $pref);
        }

        return $pref;
    }

    /**
     * Fetch original config value (no paths or urls changed for Site/Domain)
     *
     * @param   string
     * @param   boolean
     * @return  mixed
     */
    public function originalConfig($which = '', $add_slash = false)
    {
       if ($which == '') {
            return null;
        }

        if ( ! isset($this->original_config[$which])) {
            return null;
        }

        $pref = $this->original_config[$which];

        if (is_string($pref)) {
            if ($add_slash !== false) {
                $pref = rtrim($pref, '/').'/';
            }

            $pref = str_replace('\\\\', '\\', $pref);
        }

        return $pref;
    }

    /**
     * Determine Site URL based off request host + uri
     *
     * @return  void
     */
    public function loadSiteMagically()
    {
        $host       = request()->getHost();
        $http_host  = request()->getHttpHost(); // includes port
        $uri        = request()->getRequestUri(); // Hey, maybe there's a folder!

        try {
            $query = DB::table('site_urls')
                ->select('site_id', 'site_urls.id AS site_url_id')
                ->where('site_url', 'LIKE', '%'.$host.'%')
                ->get();
        } catch (\InvalidArgumentException $e) {
            throw new CmsFatalException('Unable to Load CMS. Database is either not up or credentials are invalid.');
        }

        if ($query->count() == 0) {
            throw new CmsFatalException('Unable to Load Site; No Matching Site URLs Found');
        }

        if ($query->count() == 1) {
            $this->loadSiteUrlPrefs($query->first()->site_url_id);
        }

        // @todo - We have two matches? Figure out which one is the best based off domain + uri
        $this->loadSiteUrlPrefs($query->first()->site_url_id);
    }

    /**
     * Load Site URL Preferences
     *
     * - We use this when someone puts a SITE_URL_ID constant in an index.php,
     * which allows them to load a site on multiple URLs
     *
     * @param integer $site_url_id
     * @return void
     */
    public function loadSiteUrlPrefs($site_url_id)
    {
        $query = DB::table('site_urls')
            ->join('sites', 'sites.id', '=', 'site_urls.site_id')
            ->join('site_preferences', 'site_preferences.site_id', '=', 'sites.id')
            ->where('site_urls.id', $site_url_id)
            ->get();

        if ($query->count() === 0) {
            abort(500, 'Unable to Load Site Preferences. Invalid Site URL ID.');
        }

        $this->parseSitePrefs($query);
    }

    /**
     * Load Site Preferences
     *
     * Load the site data
     *
     * @param integer $site_id
     * @return void
     */
    public function loadSitePrefs($site_id)
    {
        $query = DB::table('sites')
            ->join('site_preferences', 'site_preferences.site_id', '=', 'sites.id')
            ->where('sites.id', $site_id)
            ->get();

        if ($query->count() === 0) {
            abort(500, 'Unable to Load Site Preferences. Invalid Site ID');
        }

        $this->parseSitePrefs($query);
    }

    /**
     * Parse Domain Preferences from Query Result
     *
     * @param   object
     * @return  void
     */
    public function parseSitePrefs($query)
    {
        // ------------------------------------
        //  Reset Preferences
        // ------------------------------------

        $this->config = $cms_config = config('cms');

        // ------------------------------------
        //  Fold in the Preferences in the Database
        // ------------------------------------

        $first = $query->first();

        $this->config['site_id'] = $first->site_id;
        $this->config['site_handle'] = $first->site_handle;
        $this->config['site_description'] = $first->site_description;

        foreach($query as $row) {
            $this->config[$row->handle] = $row->value;
        }

        // ------------------------------------
        //  A PATH, A PATH!!
        // ------------------------------------

        $public_path =
            (!empty($query->first()->public_path)) ?
            $query->first()->public_path :
            base_path('public');

        $this->config['PUBLIC_PATH'] = rtrim($public_path, '/').'/';
        $this->config['STORAGE_PATH'] = storage_path('app').'/';

        // ------------------------------------
        //  Few More Variables
        // ------------------------------------

        $this->config['site_id']         = (int) $query->first()->site_id;
        $this->config['site_name']       = (string) $query->first()->site_name;
        $this->config['site_short_name'] = $this->config['site_handle'] = (string) $query->first()->site_handle;

        $this->config['site_url']        = (REQUEST != 'CP') ? (string) $query->first()->site_url : '';

        $this->original_config           = $this->config;

        // ------------------------------------
        //  Paths and URL special vars!
        // ------------------------------------

        foreach($this->config as $key => &$value) {

            // Keep the booleans
            if (isset($cms_config[$key])) {
                continue;
            }

            $value = str_replace('{SITE_URL}', $this->config['site_url'], $value);
            $value = str_replace('{PUBLIC_PATH}', $this->config['PUBLIC_PATH'], $value);
            $value = str_replace('{STORAGE_PATH}', $this->config['STORAGE_PATH'], $value);
        }

        // If we just reloaded, then we reset a few things automatically
        if ($this->config('show_queries') == 'y' or REQUEST == 'CP') {
            DB::enableQueryLog();
        }
    }

    // ------------------------------------------------

    /**
     * List all plugins
     *
     * @return array
     */
    public static function pluginsList()
    {
        return Plugins::installedPlugins();
    }

    // ------------------------------------------------

    /**
     * List all sites
     *
     * @return array
     */
    public static function sitesList()
    {
        $storeTime = Carbon::now()->addMinutes(1);

        $query = static function()
        {
            return DB::table('sites')
                ->select('sites.id AS site_id', 'site_name')
                ->orderBy('site_name')
                ->get();
        };

        // File and database storage stores do not support tags
        // And Laravel throws an exception if you even try ::rolls eyes::
        if (Cache::getStore() instanceof TaggableStore) {
            return Cache::tags('sites')->remember('cms.libraries.site.sitesList', $storeTime, $query);
        }

        return Cache::remember('cms.libraries.site.sitesList', $storeTime, $query);
    }

    /**
     * Return preferences located in sites table's fields
     *
     * @param   string
     * @return  array
     */
    public function preferenceKeys()
    {
        return [
            'site_debug',
            'is_site_on',
            'cp_url',
            'site_index',
            'notification_sender_email',
            'show_queries',
            'template_debugging',
            'include_seconds',
            'cookie_domain',
            'cookie_path',
            'default_language',
            'date_format',
            'time_format',
            'site_timezone',
            'cp_theme',
            'enable_censoring',
            'censored_words',
            'censor_replacement',
            'banned_ips',
            'banned_emails',
            'banned_screen_names',
            'ban_action',
            'ban_message',
            'ban_destination',
            'recount_batch_total',
            'enable_throttling',
            'banish_masked_ips',
            'max_page_loads',
            'time_interval',
            'lockout_time',
            'banishment_type',
            'banishment_url',
            'banishment_message',

            'password_min_length',
            'default_member_group',
            'enable_photos',
            'photo_url',
            'photo_path',
            'photo_max_width',
            'photo_max_height',
            'photo_max_kb',

            'save_tmpl_revisions',
            'max_tmpl_revisions',

            'enable_image_resizing',
            'image_resize_protocol',
            'image_library_path',
            'thumbnail_prefix',
            'word_separator',
            'new_posts_clear_caches',
        ];
    }

    // ------------------------------------------------

    /**
     * All the Data for All Sites
     *
     * @return array
     */
    public static function sitesData()
    {
        $storeTime = Carbon::now()->addMinutes(1);

        $query = static function()
        {
            return DB::table('sites')
                ->select('sites.id AS site_id', 'site_name', 'site_handle', 'site_description')
                ->orderBy('site_name')
                ->get();
        };

        // File and database storage stores do not support tags
        // And Laravel throws an exception if you even try ::rolls eyes::
        if (Cache::getStore() instanceof TaggableStore) {
            return Cache::tags('sites')->remember('cms.libraries.site.sitesData', $storeTime, $query);
        }

        return Cache::remember('cms.libraries.site.sitesData', $storeTime, $query);
    }

    // ------------------------------------------------

    /**
     * Flush all Site Caches
     *
     * @return void
     */
    public static function flushSiteCache()
    {
        // File and database storage stores do not support tags
        // And Laravel throws an exception if you even try ::rolls eyes::
        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags('sites')->flush();
            return;
        }

        Cache::forget('cms.libraries.site.sitesList');
    }
}
