<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MarketplaceCatalogService;
use App\Services\PaymentGatewayService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The operator's answer to "is this deployment actually complete?".
 *
 * Every check here has been a real failure at least once: mail left on the
 * `log` transport (nothing is delivered), no payment gateway (paid plans fall
 * back to invoices), a scheduler that never ran, a marketplace that resolved
 * to zero items, and a missing storage link. The page turns those silent
 * states into one list with a fix for each.
 */
class AdminReadinessController extends Controller
{
    /** How stale the scheduler heartbeat may be before it counts as stopped. */
    private const HEARTBEAT_MINUTES = 5;

    public function index(): View
    {
        $checks = [
            $this->runtimeCheck(),
            $this->httpsCheck(),
            $this->mailCheck(),
            $this->paymentCheck(),
            $this->queueCheck(),
            $this->schedulerCheck(),
            $this->backupCheck(),
            $this->storageCheck(),
            $this->catalogueCheck(),
            $this->desktopGateCheck(),
        ];

        $counts = [
            'ok' => collect($checks)->where('status', 'ok')->count(),
            'warn' => collect($checks)->where('status', 'warn')->count(),
            'fail' => collect($checks)->where('status', 'fail')->count(),
        ];

        return view('admin.readiness', [
            'checks' => $checks,
            'counts' => $counts,
            'checkedAt' => now(),
        ]);
    }

    /** @return array{id: string, label: string, status: string, value: string, hint: string} */
    private function runtimeCheck(): array
    {
        $debug = (bool) config('app.debug');
        $environment = (string) config('app.env');

        return [
            'id' => 'runtime',
            'label' => __('ui.admin.readiness.runtime'),
            'status' => $debug || $environment !== 'production' ? 'fail' : 'ok',
            'value' => 'PHP '.PHP_VERSION.' · Laravel '.app()->version().' · '.$environment,
            'hint' => $debug ? __('ui.admin.readiness.runtime_debug') : __('ui.admin.readiness.runtime_ok'),
        ];
    }

    /** @return array{id: string, label: string, status: string, value: string, hint: string} */
    private function httpsCheck(): array
    {
        $url = (string) config('app.url');
        $https = str_starts_with($url, 'https://');

        return [
            'id' => 'https',
            'label' => __('ui.admin.readiness.https'),
            'status' => $https ? 'ok' : 'fail',
            'value' => $url !== '' ? $url : __('ui.admin.readiness.missing'),
            'hint' => $https ? __('ui.admin.readiness.https_ok') : __('ui.admin.readiness.https_hint'),
        ];
    }

    /** @return array{id: string, label: string, status: string, value: string, hint: string} */
    private function mailCheck(): array
    {
        $mailer = (string) config('mail.default');
        $sends = ! in_array($mailer, ['log', 'array', 'null'], true);

        return [
            'id' => 'mail',
            'label' => __('ui.admin.readiness.mail'),
            'status' => $sends ? 'ok' : 'fail',
            'value' => $mailer,
            'hint' => $sends ? __('ui.admin.readiness.mail_ok') : __('ui.admin.readiness.mail_hint'),
        ];
    }

    /** @return array{id: string, label: string, status: string, value: string, hint: string} */
    private function paymentCheck(): array
    {
        $gateways = app(PaymentGatewayService::class)->availableGateways();

        return [
            'id' => 'payments',
            'label' => __('ui.admin.readiness.payments'),
            'status' => $gateways === [] ? 'warn' : 'ok',
            'value' => $gateways === [] ? __('ui.admin.readiness.payments_none') : implode(', ', $gateways),
            'hint' => $gateways === []
                ? __('ui.admin.readiness.payments_hint')
                : __('ui.admin.readiness.payments_ok'),
        ];
    }

    /** @return array{id: string, label: string, status: string, value: string, hint: string} */
    private function queueCheck(): array
    {
        $pending = Schema::hasTable('jobs') ? (int) DB::table('jobs')->count() : 0;
        $failed = Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0;

        return [
            'id' => 'queue',
            'label' => __('ui.admin.readiness.queue'),
            'status' => $failed > 0 ? 'fail' : 'ok',
            'value' => $pending.' / '.$failed,
            'hint' => $failed > 0
                ? __('ui.admin.readiness.queue_failed')
                : __('ui.admin.readiness.queue_ok'),
        ];
    }

    /** @return array{id: string, label: string, status: string, value: string, hint: string} */
    private function schedulerCheck(): array
    {
        $raw = Cache::get('scheduler_heartbeat_at');
        $at = is_string($raw) ? Carbon::parse($raw) : null;
        $fresh = $at !== null && $at->diffInMinutes(now()) <= self::HEARTBEAT_MINUTES;

        return [
            'id' => 'scheduler',
            'label' => __('ui.admin.readiness.scheduler'),
            'status' => $fresh ? 'ok' : 'fail',
            'value' => $at?->diffForHumans() ?? __('ui.admin.readiness.never'),
            'hint' => $fresh
                ? __('ui.admin.readiness.scheduler_ok')
                : __('ui.admin.readiness.scheduler_hint'),
        ];
    }

    /** @return array{id: string, label: string, status: string, value: string, hint: string} */
    private function backupCheck(): array
    {
        $backups = app(\App\Services\BackupService::class);
        $latest = $backups->latest();

        if ($latest === null) {
            return [
                'id' => 'backup',
                'label' => __('ui.admin.readiness.backup'),
                'status' => 'fail',
                'value' => __('ui.admin.readiness.never'),
                'hint' => __('ui.admin.readiness.backup_none'),
            ];
        }

        $stale = $backups->isStale();

        return [
            'id' => 'backup',
            'label' => __('ui.admin.readiness.backup'),
            'status' => $stale ? 'warn' : 'ok',
            'value' => $latest['createdAt']->diffForHumans().' · '.number_format($latest['size'] / 1024 / 1024, 1).' MB',
            'hint' => $stale ? __('ui.admin.readiness.backup_stale') : __('ui.admin.readiness.backup_ok'),
        ];
    }

    /** @return array{id: string, label: string, status: string, value: string, hint: string} */
    private function storageCheck(): array
    {
        $linked = is_link(public_path('storage')) || is_dir(public_path('storage'));

        return [
            'id' => 'storage',
            'label' => __('ui.admin.readiness.storage'),
            'status' => $linked ? 'ok' : 'warn',
            'value' => $linked ? __('ui.admin.readiness.storage_linked') : __('ui.admin.readiness.missing'),
            'hint' => $linked ? __('ui.admin.readiness.storage_ok') : __('ui.admin.readiness.storage_hint'),
        ];
    }

    /** @return array{id: string, label: string, status: string, value: string, hint: string} */
    private function catalogueCheck(): array
    {
        $count = count(app(MarketplaceCatalogService::class)->catalog()['items'] ?? []);

        return [
            'id' => 'catalogue',
            'label' => __('ui.admin.readiness.catalogue'),
            'status' => $count > 0 ? 'ok' : 'fail',
            'value' => (string) $count,
            'hint' => $count > 0 ? __('ui.admin.readiness.catalogue_ok') : __('ui.admin.readiness.catalogue_hint'),
        ];
    }

    /** @return array{id: string, label: string, status: string, value: string, hint: string} */
    private function desktopGateCheck(): array
    {
        $required = app(\App\Services\SettingsService::class)
            ->boolean('feature_require_plan_selection', true);

        return [
            'id' => 'desktop_gate',
            'label' => __('ui.admin.readiness.desktop_gate'),
            'status' => $required ? 'ok' : 'warn',
            'value' => $required ? __('ui.admin.readiness.enabled') : __('ui.admin.readiness.disabled'),
            'hint' => $required
                ? __('ui.admin.readiness.desktop_gate_ok')
                : __('ui.admin.readiness.desktop_gate_hint'),
        ];
    }
}
