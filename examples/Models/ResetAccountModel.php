<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResetAccountModel extends Model
{
    const CREATED_AT = null;
    const UPDATED_AT = null;

    protected $connection = 'service_account';
    protected $primaryKey = 'id';
    protected $table = 'reset_account';
    public $timestamps = false;

    protected $fillable = [
        'amount',
    ];
}
