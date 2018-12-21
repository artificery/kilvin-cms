<?php

namespace Kilvin\Core;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Kilvin\Facades\Site;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request;
use Kilvin\Exceptions\CmsFailureException;

class Session
{
    protected static $userdata         = [];
    protected static $tracker          = [];

    /**
     * Constructor
     *
     * @return void
     */
    public static function boot()
    {
        $ban_status = false;

        // Ban checks only happen on non-CP requests
        if (REQUEST != 'CP') {
            if (self::banCheck('ip')) {
                $ban_status = true;
            }
        }

		// ------------------------------------
        //  Default Values
        // ------------------------------------

        static::$userdata = [
            'member_id'         => null,
            'screen_name'       => '',
            'email'             => Request::cookie('my_email'),
            'url'               => Request::cookie('my_url'),
            'location'          => Request::cookie('my_location'),
            'language'          => 'en_US',
            'member_group_id'   => null,
            'is_banned'         => $ban_status,
            'site_id'           => Site::config('site_id'),
            'ip_address'        => Request::ip(),
            'user_agent'        => substr(Request::header('User-Agent'), 0, 50),
            'last_activity'     => now(),
        ];

		// ------------------------------------
		//  Fetch Session Data
		// ------------------------------------

		if (Auth::check()) {
            if (self::fetchMemberData() === false) {
                Auth::logout();
            }
		}

		// ------------------------------------
        //  Form Redirect Tracker
        // ------------------------------------

        if (REQUEST != 'CP') {
            self::tracker();
		}
    }

    /**
     * Load up Member Data for Authenticated User
     *
     * @return void
     */
    public static function fetchMemberData()
    {
		$query = DB::table('members')
            ->select('members.id AS member_id', 'members.*', 'member_groups.*')
			->join('member_groups', 'member_groups.id', '=', 'members.member_group_id')
            ->where('members.id', Auth::id())
            ->first();

        if (!$query) {
            return false;
        }

        foreach ($query as $field => $value) {
            if ($field == 'is_banned') {
                static::$userdata[$field] = ($value == 1 || static::$userdata[$field] == true) ? true : false;
            } else {
                static::$userdata[$field] = $value;
            }
        }

        // Stop here
        if ($query->is_banned == true) {
            return true;
        }

        $query = DB::table('member_group_preferences')
            ->select('handle', 'value')
            ->where('member_group_id', $query->member_group_id)
            ->get();


        $special = [
            'can_access_cp_site_id_'        => 'assigned_sites',
            'can_access_offline_site_id_'   => 'offline_sites',
            'weblog_id_'                    => 'assigned_weblogs',
            'plugin_name_'                  => 'assigned_plugins'
        ];

        $special_data = [];

        // Turn the query rows into array values
		foreach ($query as $row) {
            foreach($special as $prefix => $key) {
                if (substr($row->handle, 0, strlen($prefix)) == $prefix && $row->value == 'y'){
                    $special_data[$key][] = substr($row->handle, strlen($prefix));
                    continue(2);
                }
            }

			static::$userdata[$row->handle] = $row->value;
		}

		static::$userdata['display_photos'] = 'y';

        // ------------------------------------
        //  Are users allowed to localize?
        // ------------------------------------

		static::$userdata['timezone'] = Site::config('site_timezone');

        // ------------------------------------
        //  Assign Site, Weblog, and Plugin Access Privs
        // ------------------------------------

        static::parseSpecialPreferences(static::$userdata['member_group_id'], $special_data);

        // ------------------------------------
        //  Member's Last Activity Column
        //  - Updated every 5 mins.
        //  - Not sure how much I care about this
        // ------------------------------------

        $last_activity = Carbon::parse(static::$userdata['last_activity'])->addSeconds(300);

        if ($last_activity->lte(Carbon::now()))
        {
        	DB::table('members')
        		->where('id', Auth::id())
        		->update(
        			[
        				'last_activity' => Carbon::now()
        			]
        		);
        }

        return true;
    }

    /**
     * Cache the Special Preferences Data
     *
     * @return array
     */
    public static function fetchSpecialPreferencesCache($group_id)
    {
        $key = 'cms.member_group:'.$group_id.'.specialPreferences';

        if (!Cache::has($key)) {
            return false;
        }

        $data = Cache::get($key);

        if (!is_array($data)) {
            return false;
        }

        static::$userdata = array_merge(static::$userdata, $data);

        return true;
    }

    /**
     * Store Cache the Special Preferences Data
     *
     * @return array
     */
    public static function storeSpecialPreferencesCache($group_id, $data)
    {
        $key = 'cms.member_group:'.$group_id.'.specialPreferences';

        $storeTime = Carbon::now()->addMinutes(30);

        // File and database storage stores do not support tags
        // And Laravel throws an exception if you even try ::rolls eyes::
        if (Cache::getStore() instanceof TaggableStore) {
            return Cache::tags('member_groups', 'member_group:'.$group_id)->put($key, $data, $storeTime);
        }

        return Cache::put($key, $data, $storeTime);
    }

    /**
     * Parse Out Special Group Preferences
     *
     * @param integer  $group_id The group we are loading this for
     * @param array $special    The special group preferences we need to parse
     * @return void
     */
    private static function parseSpecialPreferences($group_id, $special)
    {
        // -----------------------------------
        //  Method Level Cache
        // -----------------------------------

        $cache_result = static::fetchSpecialPreferencesCache($group_id);

        if ($cache_result === true) {
            return;
        }

        // -----------------------------------
        //  Assigned Weblogs
        // -----------------------------------

        $assigned_weblogs = [];

        // Congrats, SuperAdmin, you get 'em all
        if ($group_id == 1) {
            $result = DB::table('weblogs')
                ->select('id AS weblog_id', 'weblog_name')
                ->orderBy('weblog_name')
                ->get();
        } elseif (!empty($special['assigned_blogs'])) {
            $result = DB::table('weblogs')
                ->select('id AS weblog_id', 'weblog_name')
                ->whereIn('id', $special['assigned_weblogs'])
                ->orderBy('weblog_name')
                ->get();
        }

        if (isset($result) && $result->count() > 0) {
            foreach ($result as $row) {
                $assigned_weblogs[$row->weblog_id] = $row->weblog_name;
            }
        }

        $data['assigned_weblogs'] = $assigned_weblogs;

        unset($result);

        // -----------------------------------
        //  Assigned Plugins
        // -----------------------------------

        $assigned_plugins = [];

        // Congrats, SuperAdmin, you get 'em all
        if ($group_id == 1) {
            $result = DB::table('plugins')
                ->select('plugin_name', 'id')
                ->get();
        } elseif (!empty($special['assigned_plugins'])) {
            $result = DB::table('plugins')
                ->select('plugin_name', 'id')
                ->whereIn('plugin_name', $special['assigned_plugins'])
                ->get();
        }

        if (isset($result) && $result->count() > 0) {
            foreach ($result as $row) {
                $assigned_plugins[$row->id] = $row->plugin_name;
            }
        }

        unset($result);

        $data['assigned_plugins'] = $assigned_plugins;

        // -----------------------------------
        //  Offline Sites Member Can View
        // -----------------------------------

        $offline_sites = [];

        // Congrats, SuperAdmin, you get 'em all
        if ($group_id == 1) {
            $result = DB::table('sites')
                ->select('sites.id AS site_id')
                ->get();
        } elseif (!empty($special['offline_sites'])) {
            $result = DB::table('sites')
                ->select('sites.id AS site_id')
                ->whereIn('id', $special['offline_sites'])
                ->get();
        }

        if (isset($result) && $result->count() > 0) {
            foreach ($result as $row) {
                $offline_sites[$row->site_id] = $row->site_id;
            }
        }

        unset($result);

        $data['offline_sites'] = $offline_sites;

        // -----------------------------------
        //  Load Assigned Sites
        // -----------------------------------

        $assigned_sites = [];

        // Congrats, SuperAdmin, you get 'em all
        if ($group_id == 1) {
            $result = DB::table('sites')
                ->select('sites.id AS site_id', 'site_name')
                ->get();
        } elseif (!empty($special['assigned_sites'])) {
            $result = DB::table('sites')
                ->select('sites.id AS site_id', 'site_name')
                ->whereIn('id', $special['assigned_sites'])
                ->get();
        }

        if (isset($result) && $result->count() > 0) {
            foreach ($result as $row) {
                $assigned_sites[$row->site_id] = $row->site_name;
            }
        }

        unset($result);

        $data['assigned_sites'] = $assigned_sites;

        // -----------------------------------
        //  Cache It!
        // -----------------------------------

        static::$userdata = array_merge(static::$userdata, $data);

        static::storeSpecialPreferencesCache($group_id, $data);
    }

    /**
     * Fetch Session Userdata item
     *
     * @param  string  $which
     * @return null|string
     */
    public static function userdata($which, $value = null)
    {
        if ($value !== null) {
            return static::$userdata[$which] = $value;
        }

    	return ( ! isset(static::$userdata[$which])) ? null : static::$userdata[$which];
	}

    /**
     * Access Check
     *
     * @param  string  $which
     * @return boolean
     */
    public static function access($which)
    {
        if (empty($which)) {
            throw new CmsFailureException('You attempted to check the access on nothing.');
        }

        // Admins always have access
        if (static::userdata('member_group_id') == 1) {
            return true;
        }

        return (Session::userdata($which) === 'y');
    }

    /**
     * Tracker
     * - Stores the visitor's last five pages viewed
     * -  We use this to facilitate redirection after form submissions
     *
     * @return void
     */
    public static function tracker()
    {
		$tracker = session('tracker');

		if ($tracker != false)
		{
			if (preg_match("#(http:\/\/|https:\/\/|www\.|[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})#i", $tracker))
			{
				return array();
			}

			if (strpos($tracker, ':') !== FALSE)
			{
				$tracker_parts = explode(':', $tracker);

				if (current($tracker_parts) != 'a' OR sizeof($tracker_parts) < 3 OR ! is_numeric(next($tracker_parts)))
				{
					return array();
				}
			}

			$tracker = unserialize($tracker);
		}

		if ( ! is_array($tracker))
		{
			$tracker = [];
		}

		$URI = (Url::$URI == '') ? 'index' : Url::$URI;

		$URI = str_replace("\\", "/", $URI);

		// If someone is messing with the URI we won't set the cookie

		 if ( ! preg_match("#^[a-z0-9\%\_\/\-]+$#i", $URI) && ! isset($_GET['ACT']))
		 {
			return array();
		 }

		if ( ! isset($_GET['ACT']))
		{
			if ( ! isset($tracker['0']))
			{
				$tracker[] = $URI;
			}
			else
			{
				if (count($tracker) == 5)
				{
					array_pop($tracker);
				}

				if ($tracker['0'] != $URI)
				{
					array_unshift($tracker, $URI);
				}
			}

		}

	    if (REQUEST == 'SITE') {
            session('tracker', $tracker);
		}

		static::$tracker = $tracker;
    }

    /**
     * Check to see if request is banned
     *
     * @return void
     */
    public static function banCheck($type = 'ip', $match = '')
    {
		switch ($type)
		{
			case 'ip'			: $ban = Site::config('banned_ips');
								  $match = Request::ip();
				break;
			case 'email'		: $ban = Site::config('banned_emails');
				break;
			case 'screen_name'	: $ban = Site::config('banned_screen_names');
				break;
		}

        if ($ban == '') {
            return false;
        }

        foreach (explode('|', $ban) as $val) {
        	if ($val == '*') continue;

        	if (substr($val, -1) == '*') {
				$val = str_replace("*", "", $val);

				if (preg_match("#^".preg_quote($val,'#')."#", $match)) {
					return true;
				}
			} elseif (substr($val, 0, 1) == '*') {
				$val = str_replace("*", "", $val);

				if (preg_match("#".preg_quote($val, '#')."$#", $match))
				{
					return true;
				}
			} else {
				if (preg_match("#^".preg_quote($val, '#')."$#", $match))
				{
					return true;
				}
			}
        }

        return false;
    }
}
