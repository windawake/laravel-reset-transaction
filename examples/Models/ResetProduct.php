<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResetProduct extends Model {
    const CREATED_AT = null;
    const UPDATED_AT = null;

    protected $primaryKey = 'pid';
    protected $table = 'reset_product';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'product_name',
        'status',
    ];
    
}