<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiProvider;
use App\Models\AuditLog;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\SupportAiService;
use App\Services\SupportTicketService;
use App\Support\SettingsChanges;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The support queue. One page prices and switches the desk on and off, one
 * lists every ticket with the filters an operator needs, and one ticket page
 * carries the whole conversation, the reply box, and the AI assistant button.
 */
class AdminSupportController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly SupportTicketService $support,
        private readonly SupportAiService $ai,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'status' => (string) $request->query('status', ''),
            'priority' => (string) $request->query('priority', ''),
            'category' => (string) $request->query('category', ''),
            'assigned' => (string) $request->query('assigned', ''),
            'q' => trim((string) $request->query('q', '')),
        ];

        $query = SupportTicket::query()->with(['user', 'organization', 'assignee'])->latest('last_reply_at')->latest('created_at');

        if (in_array($filters['status'], SupportTicket::statuses(), true)) {
            $query->where('status', $filters['status']);
        }

        if (in_array($filters['priority'], SupportTicket::priorities(), true)) {
            $query->where('priority', $filters['priority']);
        }

        if (in_array($filters['category'], SupportTicket::categories(), true)) {
            $query->where('category', $filters['category']);
        }

        if ($filters['assigned'] === 'none') {
            $query->whereNull('assigned_to');
        } elseif ($filters['assigned'] !== '') {
            $query->where('assigned_to', $filters['assigned']);
        }

        if ($filters['q'] !== '') {
            $needle = '%'.$filters['q'].'%';
            $query->where(function ($builder) use ($needle): void {
                $builder->where('reference', 'like', $needle)
                    ->orWhere('subject', 'like', $needle)
                    ->orWhere('requester_name', 'like', $needle)
                    ->orWhere('requester_email', 'like', $needle);
            });
        }

        return view('admin.support.index', [
            'values' => [
                'support_enabled' => $this->settings->boolean('support_enabled', true),
                'support_ai_enabled' => $this->settings->boolean('support_ai_enabled', false),
                'support_ai_auto_reply' => $this->settings->boolean('support_ai_auto_reply', false),
                'support_ai_provider_id' => (string) $this->settings->get('support_ai_provider_id', ''),
                'support_ai_model_id' => (string) $this->settings->get('support_ai_model_id', ''),
                'support_ai_instructions' => (string) $this->settings->get('support_ai_instructions', ''),
                'support_auto_close_days' => $this->settings->integer('support_auto_close_days', 14),
            ],
            'tickets' => $query->paginate(25)->withQueryString(),
            'filters' => $filters,
            'statuses' => SupportTicket::statuses(),
            'priorities' => SupportTicket::priorities(),
            'categories' => SupportTicket::categories(),
            'staff' => $this->staff(),
            'providers' => $this->providersWithModels(),
            'stats' => $this->support->stats(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'support_enabled' => ['nullable', 'boolean'],
            'support_ai_enabled' => ['nullable', 'boolean'],
            'support_ai_auto_reply' => ['nullable', 'boolean'],
            'support_ai_provider_id' => ['nullable', 'string', Rule::exists(AiProvider::class, 'id')],
            'support_ai_model_id' => ['nullable', 'string', Rule::exists('ai_models', 'id')],
            'support_ai_instructions' => ['nullable', 'string', 'max:2000'],
            'support_auto_close_days' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        $values = [
            'support_enabled' => $request->boolean('support_enabled'),
            'support_ai_enabled' => $request->boolean('support_ai_enabled'),
            'support_ai_auto_reply' => $request->boolean('support_ai_auto_reply'),
            'support_ai_provider_id' => (string) ($validated['support_ai_provider_id'] ?? ''),
            'support_ai_model_id' => (string) ($validated['support_ai_model_id'] ?? ''),
            'support_ai_instructions' => (string) ($validated['support_ai_instructions'] ?? ''),
            'support_auto_close_days' => (int) $validated['support_auto_close_days'],
        ];

        $before = SettingsChanges::snapshot(array_keys($values));

        $this->settings->setMany($values, 'support');

        $changes = SettingsChanges::between($before, $values);

        if ($changes === []) {
            return redirect()->route('admin.support.index')->with('status', __('ui.common.saved'));
        }

        AuditLog::record('admin.support.updated', null, null, __('ui.admin.support.saved_log'), ['settings' => $changes]);

        return redirect()->route('admin.support.index')->with('status', __('ui.common.saved'));
    }

    public function show(SupportTicket $supportTicket): View
    {
        return view('admin.support.show', [
            'ticket' => $supportTicket,
            'replies' => $supportTicket->replies()->with('user')->get(),
            'staff' => $this->staff(),
            'statuses' => SupportTicket::statuses(),
            'priorities' => SupportTicket::priorities(),
            'categories' => SupportTicket::categories(),
            'aiEnabled' => $this->ai->enabled(),
        ]);
    }

    public function updateTicket(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(SupportTicket::statuses())],
            'priority' => ['required', Rule::in(SupportTicket::priorities())],
            'category' => ['required', Rule::in(SupportTicket::categories())],
            'assigned_to' => ['nullable', 'string', Rule::exists(User::class, 'id')],
        ]);

        if ($supportTicket->status !== $validated['status']) {
            $this->support->changeStatus($supportTicket, $validated['status']);
        }

        if ($supportTicket->priority !== $validated['priority']) {
            $this->support->setPriority($supportTicket, $validated['priority']);
        }

        if ($supportTicket->category !== $validated['category']) {
            $this->support->setCategory($supportTicket, $validated['category']);
        }

        $assigneeId = $validated['assigned_to'] ?? null;

        if ((string) ($supportTicket->assigned_to ?? '') !== (string) ($assigneeId ?? '')) {
            $this->support->assign($supportTicket, $assigneeId === null ? null : User::query()->find($assigneeId));
        }

        return redirect()->route('admin.support.show', $supportTicket)->with('status', __('ui.admin.support.updated'));
    }

    public function reply(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'internal' => ['nullable', 'boolean'],
        ]);

        $this->support->reply($supportTicket, $request->user(), [
            'body' => $validated['body'],
            'internal' => $request->boolean('internal'),
        ], SupportTicketReply::AUTHOR_STAFF);

        return redirect()->route('admin.support.show', $supportTicket)->with('status', __('ui.admin.support.replied'));
    }

    public function aiDraft(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        $reply = $this->support->generateDraft($supportTicket, $request->user());

        if (! $reply instanceof SupportTicketReply) {
            return back()->withErrors(['ai' => __('ui.admin.support.ai_unavailable')]);
        }

        return redirect()->route('admin.support.show', $supportTicket)->with('status', __('ui.admin.support.ai_drafted'));
    }

    public function sendReply(Request $request, SupportTicket $supportTicket, SupportTicketReply $supportTicketReply): RedirectResponse
    {
        abort_unless($supportTicketReply->support_ticket_id === $supportTicket->getKey(), 404);

        $this->support->publishReply($supportTicket, $supportTicketReply, $request->user());

        return redirect()->route('admin.support.show', $supportTicket)->with('status', __('ui.admin.support.replied'));
    }

    /**
     * @return Collection<int, User>
     */
    private function staff(): Collection
    {
        return User::query()
            ->where('role', User::ROLE_ADMIN)
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, AiProvider>
     */
    private function providersWithModels(): Collection
    {
        return AiProvider::query()
            ->where('is_active', true)
            ->with(['models' => fn ($query) => $query->where('is_active', true)->orderBy('display_name')])
            ->orderBy('name')
            ->get();
    }
}
