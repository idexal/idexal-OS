<?php

namespace App\Services\Gateway;

/**
 * Finds the point where a model started looping instead of producing new text.
 *
 * The signal is a run of identical units at the tail of the answer: an open
 * model that gets stuck repeats the same sentence, list item or JSON fragment
 * verbatim, so a suffix that is an exact repetition of a unit at least
 * {@see self::MIN_UNIT} bytes long, {@see self::REPEATS} times over, is a loop
 * and not ordinary prose. Detection is deliberately conservative — a false
 * positive truncates a good answer, which is worse than letting a real loop run
 * to max_tokens once.
 */
final class RepetitionGuard
{
    private const MIN_UNIT = 24;

    private const REPEATS = 3;

    private const TAIL_BYTES = 900;

    private string $text = '';

    public function push(string $delta): void
    {
        if ($delta !== '') {
            $this->text .= $delta;
        }
    }

    public function text(): string
    {
        return $this->text;
    }

    public function looping(): bool
    {
        return self::detect($this->text) !== null;
    }

    /**
     * The byte offset where the answer should end: everything before it is the
     * first, useful copy of the repeated unit.
     */
    public function cut(): ?int
    {
        return self::detect($this->text);
    }

    /**
     * @return int|null Offset of the end of the first useful copy, or null when
     *                  no loop is present.
     */
    public static function detect(string $text): ?int
    {
        $length = strlen($text);
        $minimum = self::MIN_UNIT * self::REPEATS;

        if ($length < $minimum) {
            return null;
        }

        $tailLength = min($length, self::TAIL_BYTES);
        $tailStart = $length - $tailLength;
        $tail = substr($text, $tailStart);
        $tailLength = strlen($tail);

        $maxUnit = intdiv($tailLength, self::REPEATS);

        // Smallest looping unit first: it is the true period of the loop.
        for ($unit = self::MIN_UNIT; $unit <= $maxUnit; $unit++) {
            $blockStart = $tailLength - $unit * self::REPEATS;
            $block = substr($tail, $blockStart);

            if ($block !== str_repeat(substr($block, 0, $unit), self::REPEATS)) {
                continue;
            }

            // Keep the first copy, drop the repetitions that follow it.
            return $tailStart + $blockStart + $unit;
        }

        return null;
    }
}
