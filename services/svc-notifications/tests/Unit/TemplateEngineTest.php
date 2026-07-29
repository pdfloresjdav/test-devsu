<?php

namespace Tests\Unit;

use App\Services\TemplateEngine;
use Tests\TestCase;

class TemplateEngineTest extends TestCase
{
    public function test_generates_the_content_for_a_registered_movement(): void
    {
        $result = (new TemplateEngine)->render('MovementRegistered', [
            'account_id' => 'ACCOUNT-1',
            'type' => 'debit',
            'amount' => 150,
        ]);

        $this->assertSame('New movement on your BP account', $result['subject']);
        $this->assertStringContainsString('ACCOUNT-1', $result['body']);
        $this->assertStringContainsString('150.00', $result['body']);
    }

    public function test_uses_the_generic_template_for_an_event_without_its_own_template(): void
    {
        $result = (new TemplateEngine)->render('UnknownEvent', []);

        $this->assertStringContainsString('UnknownEvent', $result['body']);
    }
}
