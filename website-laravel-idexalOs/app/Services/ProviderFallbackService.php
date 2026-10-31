<?php

namespace App\Services;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\BackupProvider;

/**
 * Orders gateway candidates so an operator-defined backup chain is tried
 * before the rest of the same-model providers.
 *
 * The gateway already offers every provider that serves the requested model,
 * highest priority first. On top of that auto order, this service pulls each
 * provider's admin-defined backup providers directly behind it, so a chain
 * such as OpenAI -> Azure -> OpenRouter is honoured before unrelated
 * providers that happen to serve the same model.
 *
 * Ordered last, a target the cooldown service says just failed twice is moved
 * behind its healthy peers. The move is a stable partition, so the operator's
 * chain order survives inside both halves and no candidate is ever dropped.
 */
class ProviderFallbackService
{
    public function __construct(private readonly ProviderCooldownService $cooldown) {}

    /**
     * @param  list<array{model: AiModel, provider: AiProvider, url: string, admission: array{allowed: bool, overage: bool, reason: string|null}}>  $contexts
     * @return list<array{model: AiModel, provider: AiProvider, url: string, admission: array{allowed: bool, overage: bool, reason: string|null}}>
     */
    public function order(array $contexts): array
    {
        if (count($contexts) < 2) {
            return $contexts;
        }

        $queue = $contexts;
        $ordered = [];
        $emitted = [];

        while ($queue !== []) {
            $context = array_shift($queue);
            $providerId = (string) $context['provider']->getKey();

            if (isset($emitted[$providerId])) {
                continue;
            }

            $ordered[] = $context;
            $emitted[$providerId] = true;

            $promoted = [];

            foreach ($this->orderedBackupIds($providerId) as $backupId) {
                if (isset($emitted[$backupId])) {
                    continue;
                }

                foreach ($queue as $candidate) {
                    if ((string) $candidate['provider']->getKey() === $backupId) {
                        $promoted[] = $candidate;

                        break;
                    }
                }
            }

            if ($promoted !== []) {
                $queue = array_merge($promoted, $queue);
            }
        }

        return $this->coolingLast($ordered);
    }

    /**
     * Stable partition: cooling targets keep their relative order but give way
     * to every candidate that is not cooling. When all of them are cooling the
     * list is returned untouched, so a request is never starved of a provider.
     *
     * @param  list<array{model: AiModel, provider: AiProvider, url: string, admission: array{allowed: bool, overage: bool, reason: string|null}}>  $contexts
     * @return list<array{model: AiModel, provider: AiProvider, url: string, admission: array{allowed: bool, overage: bool, reason: string|null}}>
     */
    private function coolingLast(array $contexts): array
    {
        $warm = [];
        $cooling = [];

        foreach ($contexts as $context) {
            if ($this->cooldown->isCoolingDown($context['provider'], $context['model'])) {
                $cooling[] = $context;

                continue;
            }

            $warm[] = $context;
        }

        if ($warm === [] || $cooling === []) {
            return $contexts;
        }

        return [...$warm, ...$cooling];
    }

    /**
     * Backup provider ids for a main provider, in the order they should be
     * tried.
     *
     * @return list<string>
     */
    public function orderedBackupIds(string $providerId): array
    {
        return BackupProvider::query()
            ->where('main_provider_id', $providerId)
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->pluck('backup_provider_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    /**
     * Replace a provider's backup chain with the given, ordered provider ids.
     *
     * @param  list<string>  $backupProviderIds
     */
    public function syncChain(AiProvider $provider, array $backupProviderIds): void
    {
        $provider->backupLinks()->delete();

        $priority = 1;

        foreach ($backupProviderIds as $backupProviderId) {
            if ($backupProviderId === (string) $provider->getKey()) {
                continue;
            }

            BackupProvider::query()->create([
                'main_provider_id' => $provider->getKey(),
                'backup_provider_id' => $backupProviderId,
                'priority' => $priority,
                'is_active' => true,
            ]);

            $priority++;
        }
    }
}
