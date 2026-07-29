<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $primaryKey = 'transfer_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'transfer_id',
        'idempotency_key',
        'source_account',
        'destination_account',
        'amount',
        'description',
        'status',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'transfer_id';
    }

    public function uniqueIds(): array
    {
        return ['transfer_id'];
    }
}
