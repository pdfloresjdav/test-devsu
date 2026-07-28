<?php

namespace Tests\Unit;

use App\Contracts\AuditRepository;
use App\Services\AuditEventProcessor;
use App\Services\WormArchiver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AuditEventProcessorTest extends TestCase
{
    public function test_procesa_un_evento_valido_y_lo_archiva(): void
    {
        $registro = [
            'audit_id' => 'audit-1',
            'actor' => 'user-abc',
            'accion' => 'MovementRegistered',
            'detalle' => ['cuenta_id' => 'X', 'actor' => 'user-abc'],
            'hash' => 'deadbeef',
            'timestamp' => '2026-01-01T00:00:00Z',
        ];

        $repository = $this->createMock(AuditRepository::class);
        $repository->expects($this->once())
            ->method('registrar')
            ->with('user-abc', 'MovementRegistered', ['cuenta_id' => 'X', 'actor' => 'user-abc'])
            ->willReturn($registro);

        $archiver = $this->createMock(WormArchiver::class);
        $archiver->expects($this->once())->method('archivar')->with($registro);

        $processor = new AuditEventProcessor($repository, $archiver);

        $processor->procesar([
            'detail-type' => 'MovementRegistered',
            'source' => 'bp.svc-movimientos',
            'detail' => ['cuenta_id' => 'X', 'actor' => 'user-abc'],
        ]);
    }

    public function test_usa_system_como_actor_si_el_evento_no_trae_uno(): void
    {
        $repository = $this->createMock(AuditRepository::class);
        $repository->expects($this->once())
            ->method('registrar')
            ->with('system', 'TransferCompleted', ['transferencia_id' => 'T1'])
            ->willReturn(['audit_id' => 'a', 'actor' => 'system', 'accion' => 'x', 'detalle' => [], 'hash' => 'h', 'timestamp' => 't']);

        $archiver = $this->createMock(WormArchiver::class);

        $processor = new AuditEventProcessor($repository, $archiver);

        $processor->procesar([
            'detail-type' => 'TransferCompleted',
            'detail' => ['transferencia_id' => 'T1'],
        ]);
    }

    public function test_rechaza_un_evento_sin_detail_type(): void
    {
        $processor = new AuditEventProcessor(
            $this->createMock(AuditRepository::class),
            $this->createMock(WormArchiver::class),
        );

        $this->expectException(RuntimeException::class);
        $processor->procesar(['detail' => ['foo' => 'bar']]);
    }

    public function test_rechaza_un_evento_sin_detail(): void
    {
        $processor = new AuditEventProcessor(
            $this->createMock(AuditRepository::class),
            $this->createMock(WormArchiver::class),
        );

        $this->expectException(RuntimeException::class);
        $processor->procesar(['detail-type' => 'MovementRegistered']);
    }
}
