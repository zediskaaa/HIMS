<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PdeaDangerousDrugsRegister extends Model
{
    use HasFactory;

    protected $table = 'pdea_dangerous_drugs_register';

    protected $fillable = [
        'register_number',
        'item_id',
        'item_batch_id',
        'movement_id',
        'storage_location_id',
        'pdea_spf_number',
        'physician_s2_license',
        'prescriber_name',
        'patient_encounter_id',
        'quantity',
        'running_balance',
        'custodian_id',
        'witness_pharmacist_id',
        'witness_authenticated_at',
        'notes',
        'recorded_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'running_balance' => 'integer',
        'witness_authenticated_at' => 'datetime',
        'recorded_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class, 'item_batch_id');
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'movement_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'storage_location_id');
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    public function witnessPharmacist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'witness_pharmacist_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('PDEA Dangerous Drugs Register entries are append-only.'));
        static::deleting(fn () => throw new LogicException('PDEA Dangerous Drugs Register entries are append-only.'));
    }
}
