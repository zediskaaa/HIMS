<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageLocationCategoryRule extends Model
{
    protected $fillable = ['storage_location_id', 'item_category_id'];
    public function location(): BelongsTo { return $this->belongsTo(StorageLocation::class, 'storage_location_id'); }
    public function category(): BelongsTo { return $this->belongsTo(ItemCategory::class, 'item_category_id'); }
}
