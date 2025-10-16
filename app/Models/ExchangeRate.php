<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExchangeRate extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['base','quote','rate_date','rate'];

    protected $casts = [
        'rate'      => 'float',
        'rate_date' => 'date',
    ];
}
