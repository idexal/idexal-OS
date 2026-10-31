<?php

namespace Tests\Unit;

use App\Services\Gateway\RepetitionGuard;
use PHPUnit\Framework\TestCase;

class RepetitionGuardTest extends TestCase
{
    public function test_ordinary_text_is_never_flagged(): void
    {
        $this->assertNull(RepetitionGuard::detect('The gateway forwards the request and returns the answer.'));
        $this->assertNull(RepetitionGuard::detect(''));
    }

    public function test_a_looping_answer_is_cut_after_the_first_copy(): void
    {
        $unit = 'I will explain this again in a moment. ';
        $text = 'Here is the answer. '.str_repeat($unit, 4);

        $cut = RepetitionGuard::detect($text);
        $kept = substr($text, 0, (int) $cut);

        $this->assertNotNull($cut);
        $this->assertLessThan(strlen($text), strlen($kept));
        $this->assertStringStartsWith('Here is the answer. '.$unit, $kept);
        $this->assertStringEndsWith($unit, $kept);
    }

    public function test_the_guard_accumulates_streamed_deltas(): void
    {
        $unit = 'the same sentence keeps coming back ';
        $guard = new RepetitionGuard;

        $guard->push('Intro. ');
        foreach (range(1, 4) as $ignored) {
            $guard->push($unit);

            if ($guard->looping()) {
                break;
            }
        }

        $this->assertTrue($guard->looping());
        $this->assertNotNull($guard->cut());
    }

    public function test_text_shorter_than_the_minimum_loop_length_is_never_flagged(): void
    {
        $this->assertNull(RepetitionGuard::detect(str_repeat('same ', 10)));
    }
}
