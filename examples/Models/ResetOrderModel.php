<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResetOrderModel extends Model
{
    const CREATED_AT = null;
    const UPDATED_AT = null;

    protected $connection = 'service_order';
    protected $primaryKey = 'id';
    protected $table = 'reset_order';
    public $timestamps = false;

    protected $fillable = [
        'order_no',
        'stock_qty',
        'amount',
    ];
}
