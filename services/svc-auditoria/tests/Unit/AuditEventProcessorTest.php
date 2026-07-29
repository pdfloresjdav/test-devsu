<?php

namespace Tests\Unit;

use App\Contracts\AuditRepository;
use App\Services\AuditEventProcessor;
use App\Services\WormArchiver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AuditEventProcessorTest extends TestCase
{
    public function test_processes_a_valid_event_and_archives_it(): void
    {
        $record = [
            'audit_id' => 'audit-1',
            'actor' => 'user-abc',
            'action' => 'MovementRegistered',
            'detail' => ['account_id' => 'X', 'actor' => 'user-abc'],
            'hash' => 'deadbeef',
            'timestamp' => '2026-01-01T00:00:00Z',
        ];

        $repository = $this->createMock(AuditRepository::class);
        $repository->expects($this->once())
            ->method('register')
            ->with('user-abc', 'MovementRegistered', ['account_id' => 'X', 'actor' => 'user-abc'])
            ->willReturn($record);

        $archiver = $this->createMock(WormArchiver::class);
        $archiver->expects($this->once())->method('archive')->with($record);

        $processor = new AuditEventProcessor($repository, $archiver);

        $processor->process([
            'detail-type' => 'MovementRegistered',
            'source' => 'bp.svc-movimientos',
            'detail' => ['account_id' => 'X', 'actor' => 'user-abc'],
        ]);
    }

    public function test_uses_system_as_the_actor_if_the_event_does_not_carry_one(): void
    {
        $repository = $this->createMock(AuditRepository::class);
        $repository->expects($this->once())
            ->method('register')
            ->with('system', 'TransferCompleted', ['transfer_id' => 'T1'])
            ->willReturn(['audit_id' => 'a', 'actor' => 'system', 'action' => 'x', 'detail' => [], 'hash' => 'h', 'timestamp' => 't']);

        $archiver = $this->createMock(WormArchiver::class);

        $processor = new AuditEventProcessor($repository, $archiver);

        $processor->process([
            'detail-type' => 'TransferCompleted',
            'detail' => ['transfer_id' => 'T1'],
        ]);
    }

    public function test_rejects_an_event_without_detail_type(): void
    {
        $processor = new AuditEventProcessor(
            $this->createMock(AuditRepository::class),
            $this->createMock(WormArchiver::class),
        );

        $this->expectException(RuntimeException::class);
        $processor->process(['detail' => ['foo' => 'bar']]);
    }

    public function test_rejects_an_event_without_detail(): void
    {
        $processor = new AuditEventProcessor(
            $this->createMock(AuditRepository::class),
            $this->createMock(WormArchiver::class),
        );

        $this->expectException(RuntimeException::class);
        $processor->process(['detail-type' => 'MovementRegistered']);
    }
}
