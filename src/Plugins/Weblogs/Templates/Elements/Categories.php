<?php

namespace Kilvin\Plugins\Weblogs\Templates\Elements;

use Kilvin\Libraries\Twig\Templates\ModelElement;
use Kilvin\Plugins\Weblogs\Models\Category as BaseModel;
use Illuminate\Database\Eloquent\Builder;

class Categories extends BaseModel implements \IteratorAggregate
{
    use ModelElement;

	/**
     * Built in page limit for this element
     *
     * @var integer
     */
    protected static $pageLimit = 150;

    /**
     * The "booting" method of the model.
     * - Adds default scopes for Element
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('currentSite', function (Builder $builder) {
            return BaseModel::scopeCurrentSite($builder); // must call scopeLive method directly
        });

        static::addPageLimitScope();
    }
}
