<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AdminPaymentController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $gateway = (string) $request->query('gateway', '');
        $search = trim((string) $request->query('q', ''));

        $payments = Payment::query()
            ->with(['organization:id,name,slug', 'invoice:id,number', 'user:id,name'])
            ->when(in_array($status, [
                Payment::STATUS_SUCCEEDED,
                Payment::STATUS_PENDING,
                Payment::STATUS_FAILED,
                Payment::STATUS_REFUNDED,
            ], true), fn ($query) => $query->where('status', $status))
            ->when(in_array($gateway, Payment::gateways(), true), fn ($query) => $query->where('gateway', $gateway))
            ->when($search !== '', fn ($query) => $query->where('gateway_reference', 'like', '%'.$search.'%'))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.payments.index', [
            'payments' => $payments,
            'status' => $status,
            'gateway' => $gateway,
            'search' => $search,
            'stats' => [
                'succeeded' => Payment::query()->where('status', Payment::STATUS_SUCCEEDED)->sum('amount'),
                'pending' => Payment::query()->where('status', Payment::STATUS_PENDING)->sum('amount'),
                'refunded' => Payment::query()->where('status', Payment::STATUS_REFUNDED)->sum('amount'),
            ],
        ]);
    }
}
