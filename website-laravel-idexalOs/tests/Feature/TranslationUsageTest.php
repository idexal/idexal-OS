<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A `ui.` key that the English catalogue does not define renders as the literal
 * key in the UI, so one typo — or one renamed string — silently ships broken
 * copy to operators and customers. This scans every literal translation key the
 * app uses and asserts the catalogue defines it.
 *
 * Dynamic keys (`__('ui.admin.features.'.$feature.'_name')`) and prefix keys
 * ending in `.` are skipped: their resolved value cannot be read statically.
 */
class TranslationUsageTest extends TestCase
{
    public function test_every_literal_ui_key_used_by_the_website_exists(): void
    {
        $known = $this->englishKeys();

        $missing = [];
        $checked = 0;

        foreach ($this->sourceFiles() as $file) {
            $source = (string) file_get_contents($file);

            preg_match_all(
                '/(?:__|trans|trans_choice|@lang|Lang::get)\(\s*\'([^\']+)\'(\s*.)/',
                $source,
                $matches,
                PREG_SET_ORDER,
            );

            foreach ($matches as $match) {
                $key = $match[1];

                // `'ui.x.'.$suffix` resolves to a key we cannot read statically.
                if (str_contains($match[2], '.')) {
                    continue;
                }

                if (! str_starts_with($key, 'ui.') || str_ends_with($key, '.')) {
                    continue;
                }

                if (! isset($known[$key])) {
                    $missing[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file).' :: '.$key;
                }

                $checked++;
            }
        }

        $missing = array_values(array_unique($missing));
        sort($missing);

        // A collapse here would mean the scan silently stopped finding call sites.
        $this->assertGreaterThan(1000, $checked);
        $this->assertSame([], $missing, 'Translation keys used but not defined in lang/en/ui.php:');
    }

    /**
     * Every English key, including the array-valued ones (`__(...)` may return a
     * list, e.g. a page's section copy).
     *
     * @return array<string, true>
     */
    private function englishKeys(): array
    {
        $known = [];

        foreach (array_keys(Arr::dot(require lang_path('en/ui.php'))) as $key) {
            $parts = explode('.', $key);

            for ($depth = 1; $depth <= count($parts); $depth++) {
                $known['ui.'.implode('.', array_slice($parts, 0, $depth))] = true;
            }
        }

        return $known;
    }

    /**
     * @return list<string>
     */
    private function sourceFiles(): array
    {
        $directories = [app_path(), resource_path('views'), base_path('routes')];

        $files = [];

        foreach ($directories as $directory) {
            foreach (File::allFiles($directory) as $file) {
                if ($file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
