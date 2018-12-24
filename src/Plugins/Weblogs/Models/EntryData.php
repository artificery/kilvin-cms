<?php

namespace Kilvin\Plugins\Weblogs\Models;

use Illuminate\Database\Eloquent\Model;

class EntryData extends Model
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
    protected $table = 'weblog_entry_data';

    /**
     * Get the entry associated with this data
     */
    public function entry()
    {
        return $this->belongsTo(Entry::class, 'id', 'weblog_entry_id');
    }
}
