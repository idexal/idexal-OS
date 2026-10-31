<?php

namespace App\Services;

use Illuminate\Support\Facades\Lang;

/**
 * The release record, in the visitor's language.
 *
 * The order and the dates live in `config/releases.php` so both languages share
 * one list; the prose lives in `lang/{locale}/changelog.php`. The changelog page
 * and the resources page read the same list here rather than keeping two copies
 * in step.
 */
class ReleaseNotesService
{
    /**
     * Every release, newest first, with only the ones that have copy in this
     * language (a version whose notes are not translated yet is not shown).
     *
     * @return list<array{version: string, date: string, title: string, summary: string, changes: list<string>}>
     */
    public function all(): array
    {
        return collect(config('releases.history', []))
            ->filter(fn (array $release): bool => is_string($release['version'] ?? null)
                && Lang::has($this->key($release['version'], 'title')))
            ->map(fn (array $release): array => [
                'version' => (string) $release['version'],
                'date' => (string) ($release['date'] ?? ''),
                'title' => (string) __($this->key($release['version'], 'title')),
                'summary' => (string) __($this->key($release['version'], 'summary')),
                'changes' => array_values((array) __($this->key($release['version'], 'changes'))),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{version: string, date: string, title: string, summary: string, changes: list<string>}>
     */
    public function recent(int $limit = 5): array
    {
        return array_slice($this->all(), 0, max(0, $limit));
    }

    /**
     * Translation keys cannot contain dots, because the translator reads dots as
     * nesting. Version numbers do, so they are stored with underscores.
     */
    private function key(string $version, string $field): string
    {
        return 'changelog.releases.'.str_replace('.', '_', $version).'.'.$field;
    }
}
