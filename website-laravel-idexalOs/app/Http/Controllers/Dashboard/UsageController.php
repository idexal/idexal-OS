<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\OrganizationContext;
use App\Services\UsageService;
use App\Support\GatewayFeature;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UsageController extends Controller
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly UsageService $usage,
    ) {}

    public function index(Request $request): View
    {
        $organization = $this->context->requireOrganization();
        $period = $this->period($request);
        $summary = $this->usage->summary($organization, $period);
        $from = now()->subDays(29)->startOfDay();
        $to = now();

        return view('dashboard.usage', [
            'organization' => $organization,
            'summary' => $summary,
            'series' => $this->usage->dailySeries($organization, $from, $to),
            'featureUsage' => $this->usage->featureTotals($organization, $from, $to),
            'requests' => $this->usage->recentRequests($organization, 20),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $organization = $this->context->requireOrganization();
        $from = now()->subDays(29)->startOfDay();
        $to = now();
        $series = $this->usage->dailySeries($organization, $from, $to);
        $featureUsage = $this->usage->dailyFeatureUsage($organization, $from, $to);
        $filename = 'idexal-usage-'.$organization->slug.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($series, $featureUsage): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['date', 'requests', 'tokens', 'cost', 'features']);

            foreach ($series as $row) {
                fputcsv($handle, [
                    $row['date'],
                    $row['requests'],
                    $row['tokens'],
                    $row['cost'],
                    $this->featureCell($featureUsage[$row['date']] ?? []),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * The feature counts for one CSV cell, in the pipeline's canonical order so
     * two exports of the same day are identical. Raw feature names keep the
     * export locale-independent for spreadsheets and API consumers.
     *
     * @param  array<string, int>  $usage
     */
    private function featureCell(array $usage): string
    {
        $parts = [];

        foreach (GatewayFeature::keys() as $feature) {
            if (($usage[$feature] ?? 0) > 0) {
                $parts[] = $feature.'='.$usage[$feature];
            }
        }

        return implode('; ', $parts);
    }

    private function period(Request $request): ?string
    {
        $period = (string) $request->query('period', '');

        return preg_match('/^\d{4}-\d{2}$/', $period) === 1 ? $period : null;
    }
}
