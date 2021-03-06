<?php

namespace Kilvin\Plugins\Weblogs\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Kilvin\Traits\LocalizedModel;
use Kilvin\Models\Member;

class Entry extends Model
{
    use LocalizedModel;

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
    public function fieldsData()
    {
        return $this->hasOne(EntryData::class, 'weblog_entry_id', 'id');
    }

    /**
     * Get the Author
     */
    public function author()
    {
        return $this->belongsTo(Member::class, 'author_id', 'id');
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

    /**
     * Begin querying a model with eager loading.
     *
     * - We made a change here to allow the Weblog Entries Element to have
     * an eager loading name of 'fields' that loads the data with fieldsData()
     * but the attributes still come out via a 'fields' array
     *
     * @param  array|string  $relations
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public static function with($relations)
    {
        if (is_string($relations) && $relations == 'fields') {
            $relations = 'fieldsData';
        }

        if (is_array($relations) && in_array('fields', $relations)) {
            $key = array_search('fields', $relations);
            unset($relations[$key]);
            $relations[] = 'fieldsData';
        }

        return (new static)->newQuery()->with(
            is_string($relations) ? func_get_args() : $relations
        );
    }

    /**
     * Get entry date as month_year
     *
     * @return bool
     */
    public function getEntryDateMonthYearAttribute()
    {
        return $this->attributes['entry_date_month_year'] = Carbon::parse($this->attributes['entry_date'])->format('M Y');
    }
}

