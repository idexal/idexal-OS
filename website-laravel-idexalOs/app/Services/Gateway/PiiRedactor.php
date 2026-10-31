<?php

namespace App\Services\Gateway;

/**
 * Redacts personally identifiable information from anything the gateway is
 * about to send upstream and restores it in the answer that comes back.
 *
 * The mapping is per request and keyed by an unguessable token, so a model
 * cannot fabricate a placeholder that would restore into data it never saw,
 * and a placeholder that survives into the visible answer is a literal that
 * only this request knows how to read.
 */
final class PiiRedactor
{
    /**
     * Order matters: identifiers that contain other patterns are replaced
     * first (an email before its domain, a key before the digits inside it).
     *
     * @var array<string, string>
     */
    private const PATTERNS = [
        'email' => '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/',
        'apikey' => '/\b(?:sk|pk|rk|ghp|gho|ghu|ghs|ghr|glpat|xox[baprs]|AKIA|ASIA|AIza)[-_][A-Za-z0-9_\-]{12,}\b/',
        'iban' => '/\b[A-Z]{2}\d{2}(?:[ ]?[A-Z0-9]{4}){2,7}[A-Z0-9]{0,2}\b/',
        'card' => '/(?<!\d)(?:\d[ \-]?){13,19}(?!\d)/',
        'ipv4' => '/(?<!\d)(?:\d{1,3}\.){3}\d{1,3}(?!\d)/',
        'ipv6' => '/(?<![0-9A-Za-z:])(?:[0-9a-fA-F]{1,4}:){2,7}[0-9a-fA-F]{1,4}(?![0-9A-Za-z:])/',
        'phone' => '/(?<!\d)(?:\+|00)\d[\d\s().\-]{6,17}\d(?!\d)/',
    ];

    /**
     * @param  array<string, string>  $map  placeholder => original, filled in place
     */
    public function redactText(string $text, array &$map): string
    {
        if ($text === '') {
            return $text;
        }

        foreach (self::PATTERNS as $type => $pattern) {
            $text = (string) preg_replace_callback(
                $pattern,
                function (array $match) use (&$map, $type): string {
                    $placeholder = $this->placeholder($type, count($map) + 1);
                    $map[$placeholder] = $match[0];

                    return $placeholder;
                },
                $text,
            );
        }

        return $text;
    }

    /**
     * @param  array<string, string>  $map
     */
    public function restoreText(string $text, array $map): string
    {
        if ($map === [] || $text === '') {
            return $text;
        }

        return str_replace(array_keys($map), array_values($map), $text);
    }

    /**
     * @param  array<string, string>  $map
     */
    public function redactDeep(mixed $value, array &$map): mixed
    {
        if (is_string($value)) {
            return $this->redactText($value, $map);
        }

        if (! is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            $result[$key] = $this->redactDeep($item, $map);
        }

        return $result;
    }

    /**
     * @param  array<string, string>  $map
     */
    public function restoreDeep(mixed $value, array $map): mixed
    {
        if (is_string($value)) {
            return $this->restoreText($value, $map);
        }

        if (! is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            $result[$key] = $this->restoreDeep($item, $map);
        }

        return $result;
    }

    private function placeholder(string $type, int $index): string
    {
        return '[[PII:'.$type.':'.$index.']]';
    }
}
