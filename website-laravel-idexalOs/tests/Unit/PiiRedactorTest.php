<?php

namespace Tests\Unit;

use App\Services\Gateway\PiiRedactor;
use PHPUnit\Framework\TestCase;

class PiiRedactorTest extends TestCase
{
    public function test_it_replaces_every_configured_identifier_with_a_readable_placeholder(): void
    {
        $redactor = new PiiRedactor;
        $map = [];

        $text = 'Mail ada@example.com, card 4111 1111 1111 1111, IBAN GB82 WEST 1234 5698 7654 32, '
            .'IP 203.0.113.7, key sk-abcdefghijklmnop1234, phone +216 55 123 456.';

        $redacted = $redactor->redactText($text, $map);

        $this->assertStringNotContainsString('ada@example.com', $redacted);
        $this->assertStringNotContainsString('4111 1111 1111 1111', $redacted);
        $this->assertStringNotContainsString('GB82', $redacted);
        $this->assertStringNotContainsString('203.0.113.7', $redacted);
        $this->assertStringNotContainsString('sk-abcdefghijklmnop1234', $redacted);
        $this->assertCount(6, $map);
    }

    public function test_restoring_the_answer_puts_the_original_values_back(): void
    {
        $redactor = new PiiRedactor;
        $map = [];

        $redacted = $redactor->redactText('Write to ada@example.com about it.', $map);

        $this->assertSame(
            'Write to ada@example.com about it.',
            $redactor->restoreText($redacted, $map),
        );
    }

    public function test_a_deep_walk_replaces_inside_message_structures(): void
    {
        $redactor = new PiiRedactor;
        $map = [];

        $messages = [
            ['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => 'Contact 10.0.0.5 today'],
            ]],
        ];

        $result = $redactor->redactDeep($messages, $map);

        $this->assertStringContainsString('[[PII:ipv4:1]]', (string) $result[0]['content'][0]['text']);
        $this->assertSame($messages, $redactor->restoreDeep($result, $map));
    }

    public function test_a_placeholder_cannot_be_forged_into_the_map(): void
    {
        $redactor = new PiiRedactor;
        $map = [];

        $redactor->redactText('Reach me at ada@example.com', $map);
        $restored = $redactor->restoreText('I could not find [[PII:email:99]].', $map);

        $this->assertSame('I could not find [[PII:email:99]].', $restored);
    }
}
