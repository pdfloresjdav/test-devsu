<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = ['account_id', 'balance'];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
        ];
    }
}
