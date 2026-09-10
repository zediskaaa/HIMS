<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BarcodeAlias extends Model
{
    protected $fillable = ['code', 'symbology', 'target_type', 'target_id', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
}
