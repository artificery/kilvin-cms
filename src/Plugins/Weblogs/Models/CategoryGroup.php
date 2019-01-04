<?php

namespace Kilvin\Plugins\Weblogs\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Kilvin\Traits\LocalizedModel;

class CategoryGroup extends Model
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
    protected $table = 'category_groups';

    /**
     * Get the categories associated with this Group
     */
    public function categories()
    {
        return $this->hasMany(Category::class, 'category_group_id', 'id');
    }
}

