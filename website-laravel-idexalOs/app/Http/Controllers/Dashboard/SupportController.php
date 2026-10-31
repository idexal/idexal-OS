<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\OrganizationContext;
use App\Services\SupportTicketService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The customer's side of the support desk: open a ticket, watch the thread, and
 * reply. Tickets are scoped to the signed-in person and their workspaces.
 */
class SupportController extends Controller
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly SupportTicketService $support,
    ) {}

    public function index(Request $request): View
    {
        $organization = $this->context->requireOrganization();

        return view('dashboard.support.index', [
            'enabled' => $this->support->enabled(),
            'tickets' => $this->support->forUser($request->user()),
            'stats' => $this->support->stats($organization),
        ]);
    }

    public function create(): View
    {
        $organization = $this->context->requireOrganization();

        return view('dashboard.support.create', [
            'enabled' => $this->support->enabled(),
            'organization' => $organization,
            'categories' => SupportTicket::categories(),
            'priorities' => [SupportTicket::PRIORITY_LOW, SupportTicket::PRIORITY_NORMAL, SupportTicket::PRIORITY_HIGH],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->support->enabled(), 404);

        $organization = $this->context->requireOrganization();

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'category' => ['required', Rule::in(SupportTicket::categories())],
            'priority' => ['required', Rule::in(SupportTicket::priorities())],
        ]);

        $ticket = $this->support->open($validated, $request->user(), $organization);

        return redirect()
            ->route('dashboard.support.show', $ticket)
            ->with('status', __('ui.support.created', ['reference' => $ticket->reference]));
    }

    public function show(Request $request, SupportTicket $supportTicket): View
    {
        abort_unless($this->support->visibleTo($request->user(), $supportTicket), 404);

        return view('dashboard.support.show', [
            'enabled' => $this->support->enabled(),
            'ticket' => $supportTicket,
            'replies' => $supportTicket->publicReplies()->with('user')->get(),
        ]);
    }

    public function reply(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        abort_unless($this->support->visibleTo($request->user(), $supportTicket), 404);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $this->support->reply($supportTicket, $request->user(), $validated, 'customer');

        return redirect()
            ->route('dashboard.support.show', $supportTicket)
            ->with('status', __('ui.support.replied'));
    }
}
