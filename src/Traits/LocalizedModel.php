<?php

namespace Kilvin\Traits;

use Illuminate\Database\Eloquent\Builder;
use Kilvin\Facades\Site;

// @stop - Maybe merge ModelElement and this one together?

trait LocalizedModel
{
    /**
     * Built in page limit for this element
     *
     * @var integer
     */
    protected static $pageLimit = 100;

    /**
     * Scope a query to only include categories from this site
     *
     * Needs to be static because of usage in boot method
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function scopeCurrentSite($query)
    {
        // Let's not duplicate if already exists
        if (static::hasGlobalScope('currentSite')) {
            return $query;
        }

        return $query->where('site_id', '=', Site::config('site_id'));
    }

    /**
     * Add Default pageLimit Scope for Model
     *
     * If one is already set for query, we ignore our default limit
     *
     * @return void
     */
    public static function addPageLimitScope()
    {
        static::addGlobalScope('pageLimit', function (Builder $builder) {
            $limit = null;

            try {
                $limit = $builder->getQuery()->limit;
            } catch (\Exception $e) {}

            if (is_null($limit)) {
                $builder->limit(static::$pageLimit);
            }
        });
    }
}
