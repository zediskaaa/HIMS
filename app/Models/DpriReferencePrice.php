<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DpriReferencePrice extends Model
{
    use HasFactory;

    protected $table = 'dpri_reference_prices';

    protected $fillable = [
        'pndf_code',
        'drug_name',
        'dosage_form_strength',
        'unit_of_measure',
        'ceiling_price',
        'edition_year',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'ceiling_price' => 'decimal:4',
        'edition_year' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForYear($query, int $year)
    {
        return $query->where('edition_year', $year);
    }
}
