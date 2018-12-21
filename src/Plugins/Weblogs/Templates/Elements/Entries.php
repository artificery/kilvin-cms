<?php

namespace Kilvin\Plugins\Weblogs\Templates\Elements;

use Kilvin\Libraries\Twig\Templates\Element as TemplatesElement;
use Kilvin\Plugins\Weblogs\Models\Entry as BaseModel;
use Illuminate\Database\Eloquent\Builder;

class Entries extends BaseModel implements \IteratorAggregate
{
    use TemplatesElement;

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

        // No closed entries can go out
        static::addGlobalScope('live', function (Builder $builder) {
            return BaseModel::scopeLive($builder); // must call scopeLive method directly
        });

        // Default limit
        static::addGlobalScope('pageLimit', function (Builder $builder) {
            $builder->limit(static::$pageLimit);
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

        $dbFields = $this->fields()->getResults()->toArray();

        $fields['title'] = $dbFields['title'];

        foreach($dbFields as $dbField => $dbValue) {
            if (substr($dbField, 0, strlen('field_')) == 'field_') {
                $fields[substr($dbField, strlen('field_'))] = $dbValue;
            }
        }

        return $fields;

    }
}


