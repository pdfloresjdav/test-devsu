<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cuenta extends Model
{
    protected $fillable = ['cuenta_id', 'saldo'];

    protected function casts(): array
    {
        return [
            'saldo' => 'decimal:2',
        ];
    }
}
