<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AdminSubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $search = trim((string) $request->query('q', ''));

        $subscriptions = Subscription::query()
            ->with(['organization:id,name,slug,currency', 'plan:id,name,slug,price_monthly,price_yearly,currency'])
            ->when(in_array($status, [
                Subscription::STATUS_ACTIVE,
                Subscription::STATUS_TRIALING,
                Subscription::STATUS_PAST_DUE,
                Subscription::STATUS_CANCELED,
                Subscription::STATUS_INCOMPLETE,
                Subscription::STATUS_UNPAID,
            ], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->whereHas('organization', fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('slug', 'like', '%'.$search.'%')))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'status' => $status,
            'search' => $search,
            'stats' => [
                'active' => Subscription::query()->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])->count(),
                'pastDue' => Subscription::query()->where('status', Subscription::STATUS_PAST_DUE)->count(),
                'canceled' => Subscription::query()->where('status', Subscription::STATUS_CANCELED)->count(),
            ],
        ]);
    }
}
