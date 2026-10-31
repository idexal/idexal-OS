<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $action = (string) $request->query('action', '');
        $search = trim((string) $request->query('q', ''));

        // An investigation is usually "what happened between these two days", so
        // the period is part of the page rather than something to narrow by hand.
        $from = $this->day($request->query('from'));
        $to = $this->day($request->query('to'));

        $logs = AuditLog::query()
            ->with(['user:id,name,email', 'organization:id,name,slug'])
            ->when($action !== '', fn ($query) => $query->where('action', $action))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $like = '%'.$search.'%';

                $query->where('description', 'like', $like)
                    ->orWhere('subject_id', 'like', $like)
                    // The action itself: "show me every voided invoice" is a
                    // search for invoice.voided, not for a description.
                    ->orWhere('action', 'like', $like)
                    // The actor, because an operator investigating one person
                    // knows their name or email, not a description they wrote.
                    ->orWhereHas('user', fn ($user) => $user
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like));
            }))
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from->startOfDay()))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to->endOfDay()))
            ->latest('created_at')
            // Two entries written in the same second (a settings save that logs
            // twice, bulk actions) would otherwise come back in any order.
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit', [
            'logs' => $logs,
            'action' => $action,
            'search' => $search,
            'from' => $from?->toDateString() ?? '',
            'to' => $to?->toDateString() ?? '',
            // Totals per action, so the filter shows how much each one holds
            // instead of an alphabetical wall of keys.
            'actions' => AuditLog::query()
                ->toBase()
                ->selectRaw('action, count(*) as total')
                ->groupBy('action')
                ->orderByDesc('total')
                ->orderBy('action')
                ->pluck('total', 'action'),
        ]);
    }

    /**
     * A calendar day from the query string, or null when the operator typed
     * something that is not one. Request::date() parses loosely and throws on
     * garbage, which would turn a hand-edited URL into a 500.
     */
    private function day(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return CarbonImmutable::instance($date);
    }
}
