<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = ['base','quote','rate_date','rate'];

    protected $casts = [
        'rate_date' => 'date',
        'rate'      => 'decimal:8',
    ];
}
