<?php

namespace App\Support;

/**
 * The one catalogue of gateway features: the names the platform records on a
 * request, the operator-facing label, and the flags the Features panel and the
 * provider form need. Everything that lists features — the panel cards, the
 * per-provider and per-model overrides, the log filter, the usage export — reads
 * from here, so adding or renaming a feature is a single-line change.
 *
 * Case order is the canonical pipeline order; it is what the panels render and
 * what the usage export iterates, so two runs of a page agree.
 */
enum GatewayFeature: string
{
    case WebSearch = 'web_search';

    case Vision = 'vision';

    case ParamSanitizer = 'param_sanitizer';

    case ModelSubstitute = 'model_substitute';

    case PiiSanitizer = 'pii_sanitizer';

    case PatientRetry = 'patient_retry';

    case JsonSchema = 'json_schema';

    case RepetitionGuard = 'repetition_guard';

    /**
     * Every feature key, in canonical pipeline order.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_map(fn (self $feature): string => $feature->value, self::cases());
    }

    /**
     * Feature key => operator-facing label, in canonical order.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::cases() as $feature) {
            $labels[$feature->value] = __($feature->labelKey());
        }

        return $labels;
    }

    /** The name of the settings row the operator toggle writes. */
    public function settingKey(): string
    {
        return 'feature_'.$this->value.'_enabled';
    }

    /** The name of the checkbox the Features panel submits. */
    public function fieldKey(): string
    {
        return $this->value.'_enabled';
    }

    /** The operator-facing name shown in the panel, the form, and the log. */
    public function labelKey(): string
    {
        return 'ui.admin.features.'.$this->value.'_name';
    }

    /** The one-line explanation on the Features panel card. */
    public function bodyKey(): string
    {
        return 'ui.admin.features.'.$this->value.'_body';
    }

    /** The short badge shown on a gateway log row. */
    public function badgeKey(): string
    {
        return 'ui.admin.gateway.feature_badges.'.$this->value;
    }

    /** Features labelled as beta while their behaviour is still settling. */
    public function isBeta(): bool
    {
        return match ($this) {
            self::ParamSanitizer, self::JsonSchema, self::RepetitionGuard => true,
            default => false,
        };
    }

    /** The extra note under the card, or null when the card needs none. */
    public function noteKey(): ?string
    {
        return match ($this) {
            self::WebSearch => 'ui.admin.features.billed',
            default => null,
        };
    }
}
