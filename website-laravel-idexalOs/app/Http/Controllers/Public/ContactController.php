<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\SupportTicketService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The public contact form. Every submission is stored and turned into a support
 * ticket, so replies happen in the same place as every other customer request.
 */
class ContactController extends Controller
{
    public function __construct(private readonly SupportTicketService $support) {}

    public function index(): View
    {
        return view('public.contact');
    }

    public function store(Request $request): RedirectResponse
    {
        if (filled($request->input('website'))) {
            return back()->with('status', __('ui.contact.sent'));
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $message = ContactMessage::query()->create([
            ...$validated,
            'status' => ContactMessage::STATUS_NEW,
            'ip' => $request->ip(),
        ]);

        $this->support->openFromContact($message);

        return back()->with('status', __('ui.contact.sent'));
    }
}
