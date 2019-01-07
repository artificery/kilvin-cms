<?php

namespace Kilvin\Plugins\Weblogs\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Kilvin\Traits\LocalizedModel;

class Category extends Model
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
    protected $table = 'categories';

    /**
     * Get the fields data associated with this Entry
     */
    public function group()
    {
        return $this->belongsTo(CategoryGroup::class, 'id', 'category_group_id');
    }

    /**
     * Children
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id', 'id')
            ->orderBy('category_order');
    }

    /**
     * Entries
     */
    public function entries()
    {
        return $this->belongsToMany(Entry::class, 'weblog_entry_categories', 'category_id', 'weblog_entry_id');
    }

    /**
     * Is Category a Parent
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeIsParent($query)
    {
        return $query->where('parent_id', '=', 0);
    }
}

