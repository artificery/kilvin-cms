<?php

namespace Kilvin\Plugins\Weblogs\Templates\Elements;

use Kilvin\Libraries\Twig\Templates\ModelElement;
use Kilvin\Plugins\Weblogs\Models\Entry as BaseModel;
use Illuminate\Database\Eloquent\Builder;

class Entries extends BaseModel implements \IteratorAggregate
{
    use ModelElement;

	/**
     * Built in page limit for this element
     *
     * @var integer
     */
    protected static $pageLimit = 20;

    /**
     * The "booting" method of the model.
     * - Adds default scopes for Element
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // By default, only send out entries that are NOT closed,
        // have an entry date in the past, and an expiration date in the future
        // This global scope can be overridden by removing all global scopes
        // or adding the inactive() scope to Model/Element chain
        static::addGlobalScope('live', function (Builder $builder) {
            return BaseModel::scopeLive($builder); // must call scopeLive method directly
        });

        // Default limit for the Model
        // If one is already set for query, we ignore our default limit
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

	/**
     * entry.fields returns an object that outputs the data fields for an entry
     *
     * @return array
     */
    public function getFieldsAttribute()
    {
        // Will need to see if this is a plugin field, if so we will
        // send it to the plugin for parsing first.

        $fields = [];

        foreach($this->fieldsData->toArray() as $name => $value) {

            if ($name == 'title') {
                $fields['title'] = $value;
            }

            if (substr($name, 0, strlen('field_')) == 'field_') {
                $fields[substr($name, strlen('field_'))] = $value;
            }
        }

        return $fields;
    }
}


