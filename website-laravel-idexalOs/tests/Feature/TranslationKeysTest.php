<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * Guards the translation tables. A key that a page asks for but neither locale
 * defines renders the raw key to the user, and a key defined in one locale but
 * not the other silently falls back to English; both are release-blocking for a
 * bilingual platform, so they are asserted here instead of found in production.
 */
class TranslationKeysTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const SCANNED_DIRECTORIES = ['app', 'resources/views', 'routes'];

    public function test_every_referenced_ui_key_exists_in_both_locales(): void
    {
        $english = $this->keys('en');
        $arabic = $this->keys('ar');

        $missing = [];
        foreach ($this->referencedKeys($english + $arabic) as $key) {
            if (! isset($english[$key])) {
                $missing[] = "en: {$key}";
            }
            if (! isset($arabic[$key])) {
                $missing[] = "ar: {$key}";
            }
        }

        $this->assertSame([], $missing, 'Translation keys referenced by the app are missing: '.implode(', ', $missing));
    }

    public function test_both_locales_define_the_same_keys(): void
    {
        $english = array_keys($this->keys('en'));
        $arabic = array_keys($this->keys('ar'));
        sort($english);
        sort($arabic);

        $this->assertSame(
            array_values(array_diff($english, $arabic)),
            [],
            'Keys defined in English but missing from Arabic.',
        );
        $this->assertSame(
            array_values(array_diff($arabic, $english)),
            [],
            'Keys defined in Arabic but missing from English.',
        );
    }

    /**
     * `__('Only combos can be edited here.')` looks translated and never is: the
     * lookup misses, and the translator returns the English sentence. Every key
     * must be a dotted name that a locale table can define.
     */
    public function test_no_english_sentence_is_used_as_a_translation_key(): void
    {
        $offenders = [];

        foreach (self::SCANNED_DIRECTORIES as $directory) {
            $path = base_path($directory);
            if (! is_dir($path)) {
                continue;
            }

            /** @var SplFileInfo $file */
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
                if (! $file->isFile() || ! preg_match('/\.php$/', $file->getFilename())) {
                    continue;
                }

                $contents = (string) file_get_contents($file->getPathname());

                if (! preg_match_all("/__\\(\\s*'([^'\\\\]*(?:\\\\.[^'\\\\]*)*)'/", $contents, $matches)) {
                    continue;
                }

                foreach ($matches[1] as $key) {
                    if (str_contains($key, ' ')) {
                        $offenders[] = $key;
                    }
                }
            }
        }

        $this->assertSame([], $offenders, 'A sentence cannot be used as a translation key: '.implode(' | ', $offenders));
    }

    /**
     * @return array<string, true>
     */
    private function keys(string $locale): array
    {
        /** @var array<string, mixed> $table */
        $table = require base_path("lang/{$locale}/ui.php");
        $table = array_key_exists('ui', $table) ? $table['ui'] : $table;

        $flat = [];
        $this->flatten($table, 'ui', $flat);

        return $flat;
    }

    /**
     * @param  array<string, mixed>  $table
     * @param  array<string, true>  $flat
     */
    private function flatten(array $table, string $prefix, array &$flat): void
    {
        foreach ($table as $key => $value) {
            $full = $prefix.'.'.$key;
            if (is_array($value)) {
                $this->flatten($value, $full, $flat);

                continue;
            }
            $flat[$full] = true;
        }
    }

    /**
     * @param  array<string, true>  $known
     * @return list<string>
     */
    private function referencedKeys(array $known): array
    {
        $referenced = [];

        foreach (self::SCANNED_DIRECTORIES as $directory) {
            $path = base_path($directory);
            if (! is_dir($path)) {
                continue;
            }

            /** @var SplFileInfo $file */
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
                if (! $file->isFile() || ! preg_match('/\.php$/', $file->getFilename())) {
                    continue;
                }

                $contents = (string) file_get_contents($file->getPathname());
                if (! preg_match_all('/[\'"](ui\.[A-Za-z0-9_.\-]+)[\'"]/', $contents, $matches)) {
                    continue;
                }

                foreach ($matches[1] as $key) {
                    if ($this->isDynamicPrefix($key, $known)) {
                        continue;
                    }
                    $referenced[$key] = true;
                }
            }
        }

        $keys = array_keys($referenced);
        sort($keys);

        return $keys;
    }

    /**
     * Keys built by concatenation (for example `ui.x.health_` before a status
     * suffix) look partial; skip them when a longer real key starts with them.
     *
     * @param  array<string, true>  $known
     */
    private function isDynamicPrefix(string $key, array $known): bool
    {
        if (str_ends_with($key, '.')) {
            return true;
        }

        foreach ($known as $candidate => $_) {
            if ($candidate !== $key && str_starts_with($candidate, $key)) {
                return true;
            }
        }

        return false;
    }
}
