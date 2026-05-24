<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradingJob extends Model
{
    //
    protected $keyType = 'string';
    public $incrementing = false;
    protected $casts = ['result' => 'array'];
    protected $fillable = ['id', 'status', 'result'];
}
