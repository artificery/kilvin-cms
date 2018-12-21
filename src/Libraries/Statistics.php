<?php

namespace Kilvin\Libraries;

use Illuminate\Support\Facades\DB;
use Kilvin\Facades\Site;
use Carbon\Carbon;
use Kilvin\Core\Session;

/**
 * Statistics Functionality
 */
class Statistics
{
    private $stats_loaded = false;
    public  $stats = [];

    // ------------------------------------
    //  Update statistics
    // ------------------------------------

    public function fetchSiteStats()
    {

        if ($this->stats_loaded === true) {
            return $this->stats;
        }

        // ------------------------------------
        //  Fetch global statistics
        // ------------------------------------

        $query = DB::table('stats')
            ->whereNull('weblog_id')
            ->first();

        // ------------------------------------
        //  Assign the stats
        // ------------------------------------

        $this->stats = [
            'recent_member'             => $query->recent_member,
            'recent_member_id'          => $query->recent_member_id,
            'total_members'             => $query->total_members,
            'total_entries'             => $query->total_entries,
            'last_entry_date'           => $query->last_entry_date,
        ];

        $this->stats_loaded = true;

        return $this->stats;
    }

    // ------------------------------------
    //  Fetch Weblog ID numbers for query
    // ------------------------------------

    public function fetch_weblog_ids()
    {
        return DB::table('weblogs')
            ->pluck('id')
            ->all();
    }

    // ------------------------------------
    //  Update Member Stats
    // ------------------------------------

    public function update_member_stats()
    {
        $query = DB::table('members')
        	->select('screen_name', 'members.id AS member_id')
        	->orderBy('id', 'desc')
        	->first();
        	
        $total_members = DB::table('members')->where('is_banned', false)->count();

        DB::table('stats')
            ->whereNull('weblog_id')
            ->update(
                [
                    'total_members' => $total_members,
                    'recent_member' => $query->screen_name,
                    'recent_member_id' => $query->member_id
                ]
            );
    }

    // ------------------------------------
    //  Update Weblog Stats
    // ------------------------------------

    public function update_weblog_stats($weblog_id = null)
    {

        // Update global stats table

        $weblog_ids = $this->fetch_weblog_ids();

        $query = DB::table('weblog_entries')
            ->whereIn('weblog_entries.weblog_id', $weblog_ids)
            ->where('entry_date', '<=', Carbon::now())
            ->where(function($q) {
                $q->whereNull('expiration_date')->orWhere('expiration_date', '>', Carbon::now());
            })
            ->where('status', '!=', 'closed');

        $total_query = clone $query;
        $max_query = clone $query;

        $total = $total_query->count();
        $max = $max_query->max('entry_date');

        DB::table('stats')
            ->whereNull('weblog_id')
            ->update(
            [
                'total_entries' =>  $total,
                'last_entry_date' => (empty($max)) ? null : $max
            ]);

        // Update specific weblog?
        if ($weblog_id)
        {
            $query = DB::table('weblog_entries')
                ->where('weblog_entries.weblog_id', $weblog_id)
                ->where('entry_date', '<=', Carbon::now())
                ->where(function($q) {
                    $q->whereNull('expiration_date')->orWhere('expiration_date', '>', Carbon::now());
                })
                ->where('status', '!=', 'closed');

            $total_query = clone $query;
            $max_query = clone $query;

            $total = $total_query->count();
            $max = $max_query->max('entry_date');

            DB::table('stats')
                ->where('weblog_id', $weblog_id)
                ->update(
                [
                    'total_entries' =>  $total,
                    'last_entry_date' => (empty($max)) ? 0 : $max
                ]);
        }
    }
}
