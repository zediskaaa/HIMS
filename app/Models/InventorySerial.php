<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventorySerial extends Model
{
    protected $fillable = ['item_id', 'item_batch_id', 'storage_location_id', 'serial_number', 'status', 'source_type', 'source_id'];

    public function item(): BelongsTo { return $this->belongsTo(InventoryItem::class); }
    public function batch(): BelongsTo { return $this->belongsTo(ItemBatch::class, 'item_batch_id'); }
    public function location(): BelongsTo { return $this->belongsTo(StorageLocation::class, 'storage_location_id'); }
    public function source(): MorphTo { return $this->morphTo(); }
}
