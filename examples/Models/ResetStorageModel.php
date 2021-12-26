<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResetStorageModel extends Model
{
    const CREATED_AT = null;
    const UPDATED_AT = null;

    protected $connection = 'service_storage';
    protected $primaryKey = 'id';
    protected $table = 'reset_storage';
    public $timestamps = false;

    protected $fillable = [
        'stock_qty',
    ];
}
