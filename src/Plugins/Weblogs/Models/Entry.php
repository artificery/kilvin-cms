<?php

namespace Kilvin\Plugins\Weblogs\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Entry extends Model
{
	 /**
     * The primary key for this model
     * @var string
     */
	public $primaryKey = 'id';

	 /**
     * The table associated with the model.
     * @var string
     */
    protected $table = 'weblog_entries';

    /**
     * Get the fields data associated with this Entry
     */
    public function fields()
    {
        return $this->hasOne(EntryData::class, 'weblog_entry_id', 'id');
    }

    /**
     * Scope a query to only include live entries.
     *
     * Needs to be static because of usage in Entries Element's boot method
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function scopeLive($query)
    {
        // Let's not duplicate if already exists
        if (static::hasGlobalScope('live')) {
            return $query;
        }

        $timestamp = Carbon::now()->toDateTimeString();

        return
            $query->where('status', '!=', 'closed')
                ->where('entry_date', '<=', $timestamp)
                ->where(function($q) use ($timestamp) {
                    $q->whereNull('expiration_date')->orWhere('expiration_date', '>', $timestamp);
                });
    }

    /**
     * Scope a query to show inactive entries.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInactive($query)
    {
        // Disable 'live' scope
        return $query->withoutGlobalScope('live');
    }

    /**
     * Scope a query to ONLY show inactive entries.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOnlyInactive($query)
    {
        // Disable global 'live' scope
        $query->withoutGlobalScope('live');

        $timestamp = Carbon::now()->toDateTimeString();

        return
            $query->where('status', '=', 'closed')
                ->orWhere('entry_date', '>', $timestamp)
                ->orWhere('expiration_date', '<', $timestamp);
    }

    /**
     * Scope a query to show most recent entries first.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeMostRecent($query, $limit = null)
    {
        if (!is_null($limit)) {
            // Disable 'pageLimit' scope as it will override this value
            // since scopes are parsed near the end of execution
            $query->withoutGlobalScope('pageLimit');
            $query->limit($limit);
        }

        return $query->orderBy('entry_date', 'asc');
    }

    /**
     * Scope to indicate status
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeStatus($query, $status = [])
    {
        $status = is_array($status) ? $status : array_slice(func_get_args(), 1);

        return $query->whereIn('status', $status);
    }
}

