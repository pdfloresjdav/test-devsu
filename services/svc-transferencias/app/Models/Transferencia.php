<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Transferencia extends Model
{
    use HasUuids;

    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_COMPLETADA = 'completada';
    public const ESTADO_FALLIDA = 'fallida';

    protected $primaryKey = 'transferencia_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'transferencia_id',
        'idempotency_key',
        'cuenta_origen',
        'cuenta_destino',
        'monto',
        'descripcion',
        'estado',
        'motivo_falla',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'transferencia_id';
    }

    public function uniqueIds(): array
    {
        return ['transferencia_id'];
    }
}
