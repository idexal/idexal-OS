<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\ContactMessage;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use App\Services\SupportTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_can_open_a_ticket_from_the_dashboard(): void
    {
        [$user, $organization] = $this->workspace();

        $this->actingAs($user)
            ->get('/dashboard/support')
            ->assertOk()
            ->assertSee(__('ui.support.title'));

        $response = $this->actingAs($user)
            ->post('/dashboard/support', [
                'subject' => 'Gateway returns 500',
                'body' => 'Every request to the gateway fails today.',
                'category' => SupportTicket::CATEGORY_TECHNICAL,
                'priority' => SupportTicket::PRIORITY_HIGH,
            ]);

        $ticket = SupportTicket::query()->firstOrFail();

        $response->assertRedirect('/dashboard/support/'.$ticket->getKey());

        $this->assertSame($user->getKey(), $ticket->user_id);
        $this->assertSame($organization->getKey(), $ticket->organization_id);
        $this->assertSame(SupportTicket::STATUS_OPEN, $ticket->status);
        $this->assertSame(SupportTicket::CATEGORY_TECHNICAL, $ticket->category);
        $this->assertSame($user->name, $ticket->requester_name);
        $this->assertNotNull($ticket->reference);
    }

    public function test_opening_a_ticket_notifies_every_admin(): void
    {
        $admin = User::factory()->admin()->create();
        [$user] = $this->workspace();

        $this->actingAs($user)->post('/dashboard/support', [
            'subject' => 'Cannot sign in',
            'body' => 'My link stopped working.',
            'category' => SupportTicket::CATEGORY_ACCOUNT,
            'priority' => SupportTicket::PRIORITY_NORMAL,
        ]);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $admin->getKey(),
            'type' => 'support.ticket',
            'title' => __('ui.notifications.support_opened_title'),
        ]);
    }

    public function test_a_staff_reply_is_visible_to_the_customer_and_notifies_them(): void
    {
        $admin = User::factory()->admin()->create();
        [$user] = $this->workspace();

        $ticket = app(SupportTicketService::class)->open([
            'subject' => 'Invoice question',
            'body' => 'Was I charged twice?',
        ], $user, $this->organizationOf($user));

        $this->actingAs($admin)
            ->post('/admin/support/'.$ticket->getKey().'/reply', [
                'body' => 'We refunded the duplicate charge.',
            ])
            ->assertRedirect('/admin/support/'.$ticket->getKey());

        $ticket->refresh();

        $this->assertNotNull($ticket->first_response_at);
        $this->assertSame(SupportTicket::STATUS_PENDING, $ticket->status);

        $this->assertDatabaseHas('support_ticket_replies', [
            'support_ticket_id' => $ticket->getKey(),
            'author_type' => SupportTicketReply::AUTHOR_STAFF,
            'is_internal' => false,
        ]);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $user->getKey(),
            'type' => 'support.reply',
            'title' => __('ui.notifications.support_reply_title'),
        ]);

        $this->actingAs($user)
            ->get('/dashboard/support/'.$ticket->getKey())
            ->assertOk()
            ->assertSee('We refunded the duplicate charge.');
    }

    public function test_an_internal_note_is_never_shown_to_the_customer(): void
    {
        $admin = User::factory()->admin()->create();
        [$user] = $this->workspace();

        $ticket = app(SupportTicketService::class)->open([
            'subject' => 'Payment failed',
            'body' => 'My card was declined.',
        ], $user, $this->organizationOf($user));

        $this->actingAs($admin)
            ->post('/admin/support/'.$ticket->getKey().'/reply', [
                'body' => 'Check the fraud flags before answering.',
                'internal' => '1',
            ])
            ->assertRedirect('/admin/support/'.$ticket->getKey());

        $this->assertDatabaseHas('support_ticket_replies', [
            'support_ticket_id' => $ticket->getKey(),
            'is_internal' => true,
        ]);

        $this->assertNull($ticket->fresh()->first_response_at);

        $this->actingAs($user)
            ->get('/dashboard/support/'.$ticket->getKey())
            ->assertOk()
            ->assertDontSee('Check the fraud flags before answering.');
    }

    public function test_the_admin_can_see_an_internal_note(): void
    {
        $admin = User::factory()->admin()->create();
        [$user] = $this->workspace();

        $ticket = app(SupportTicketService::class)->open([
            'subject' => 'Refund please',
            'body' => 'I want a refund.',
        ], $user, $this->organizationOf($user));

        app(SupportTicketService::class)->reply($ticket, $admin, [
            'body' => 'Internal only note.',
            'internal' => true,
        ], SupportTicketReply::AUTHOR_STAFF);

        $this->actingAs($admin)
            ->get('/admin/support/'.$ticket->getKey())
            ->assertOk()
            ->assertSee('Internal only note.');
    }

    public function test_a_customer_reply_reopens_the_ticket_and_notifies_staff(): void
    {
        $admin = User::factory()->admin()->create();
        [$user] = $this->workspace();

        $ticket = app(SupportTicketService::class)->open([
            'subject' => 'Slow responses',
            'body' => 'The gateway is slow.',
        ], $user, $this->organizationOf($user));

        app(SupportTicketService::class)->changeStatus($ticket, SupportTicket::STATUS_RESOLVED);

        $this->actingAs($user)
            ->post('/dashboard/support/'.$ticket->getKey().'/reply', [
                'body' => 'It is happening again.',
            ])
            ->assertRedirect('/dashboard/support/'.$ticket->getKey());

        $ticket->refresh();

        $this->assertSame(SupportTicket::STATUS_OPEN, $ticket->status);
        $this->assertNotNull($ticket->last_reply_at);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $admin->getKey(),
            'type' => 'support.ticket',
            'title' => __('ui.notifications.support_customer_reply_title'),
        ]);
    }

    public function test_a_customer_cannot_read_another_users_ticket(): void
    {
        [$owner] = $this->workspace();
        [$stranger] = $this->workspace();

        $ticket = app(SupportTicketService::class)->open([
            'subject' => 'Private matter',
            'body' => 'Only for me.',
        ], $owner, $this->organizationOf($owner));

        $this->actingAs($stranger)
            ->get('/dashboard/support/'.$ticket->getKey())
            ->assertNotFound();

        $this->actingAs($stranger)
            ->post('/dashboard/support/'.$ticket->getKey().'/reply', [
                'body' => 'Sneaky reply.',
            ])
            ->assertNotFound();
    }

    public function test_a_contact_message_always_becomes_a_ticket(): void
    {
        $this->post('/contact', [
            'name' => 'Visitor',
            'email' => 'visitor@example.com',
            'subject' => 'Pre-sales question',
            'message' => 'Do you support teams?',
        ])->assertRedirect();

        $message = ContactMessage::query()->firstOrFail();

        $this->assertDatabaseHas('support_tickets', [
            'contact_message_id' => $message->getKey(),
            'requester_email' => 'visitor@example.com',
            'source' => SupportTicket::SOURCE_CONTACT,
        ]);
    }

    public function test_a_contact_message_becomes_only_one_ticket(): void
    {
        $message = ContactMessage::query()->create([
            'name' => 'Visitor',
            'email' => 'visitor@example.com',
            'subject' => 'Hello',
            'message' => 'Just once please.',
            'status' => ContactMessage::STATUS_NEW,
            'ip' => '127.0.0.1',
        ]);

        $service = app(SupportTicketService::class);

        $first = $service->openFromContact($message);
        $second = $service->openFromContact($message);

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertDatabaseCount('support_tickets', 1);
    }

    public function test_the_ai_assistant_keeps_its_draft_internal_until_sent(): void
    {
        $admin = User::factory()->admin()->create();
        [$user] = $this->workspace();

        $this->enableAi();
        Http::fake([
            'https://api.example.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Thanks for reaching out, we are on it.']],
                ],
            ], 200),
        ]);

        $ticket = app(SupportTicketService::class)->open([
            'subject' => 'Feature request',
            'body' => 'Please add dark mode.',
        ], $user, $this->organizationOf($user));

        $draft = SupportTicketReply::query()
            ->where('support_ticket_id', $ticket->getKey())
            ->firstOrFail();

        $this->assertTrue($draft->is_internal);
        $this->assertTrue($draft->ai_generated);
        $this->assertSame(SupportTicketReply::AUTHOR_AI, $draft->author_type);
        $this->assertNull($ticket->fresh()->first_response_at);

        $this->actingAs($user)
            ->get('/dashboard/support/'.$ticket->getKey())
            ->assertOk()
            ->assertDontSee('Thanks for reaching out');

        $this->actingAs($admin)
            ->post('/admin/support/'.$ticket->getKey().'/replies/'.$draft->getKey().'/send')
            ->assertRedirect('/admin/support/'.$ticket->getKey());

        $draft->refresh();

        $this->assertFalse($draft->is_internal);
        $this->assertNotNull($ticket->fresh()->first_response_at);

        $this->actingAs($user)
            ->get('/dashboard/support/'.$ticket->getKey())
            ->assertOk()
            ->assertSee('Thanks for reaching out');
    }

    public function test_the_first_ai_reply_is_sent_automatically_when_enabled(): void
    {
        [$user] = $this->workspace();

        $this->enableAi(autoReply: true);
        Http::fake([
            'https://api.example.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Hello, here is your answer.']],
                ],
            ], 200),
        ]);

        $ticket = app(SupportTicketService::class)->open([
            'subject' => 'Quick question',
            'body' => 'How do I rotate a key?',
        ], $user, $this->organizationOf($user));

        $reply = SupportTicketReply::query()
            ->where('support_ticket_id', $ticket->getKey())
            ->firstOrFail();

        $this->assertFalse($reply->is_internal);
        $this->assertTrue($reply->ai_generated);
        $this->assertNotNull($ticket->fresh()->first_response_at);
    }

    public function test_a_failing_provider_leaves_the_ticket_without_a_draft(): void
    {
        [$user] = $this->workspace();

        $this->enableAi();
        Http::fake([
            'https://api.example.com/v1/chat/completions' => Http::response(['error' => 'boom'], 500),
        ]);

        $ticket = app(SupportTicketService::class)->open([
            'subject' => 'Still needs a human',
            'body' => 'The assistant cannot help here.',
        ], $user, $this->organizationOf($user));

        $this->assertSame(0, SupportTicketReply::query()->where('support_ticket_id', $ticket->getKey())->count());
    }

    public function test_an_admin_can_configure_the_desk_and_the_assistant(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = AiProvider::factory()->create();
        $model = AiModel::factory()->create(['ai_provider_id' => $provider->getKey()]);

        $this->actingAs($admin)
            ->get('/admin/support')
            ->assertOk();

        $this->actingAs($admin)
            ->patch('/admin/support', [
                'support_enabled' => '1',
                'support_auto_close_days' => '21',
                'support_ai_enabled' => '1',
                'support_ai_auto_reply' => '1',
                'support_ai_provider_id' => $provider->getKey(),
                'support_ai_model_id' => $model->getKey(),
                'support_ai_instructions' => 'Always be concise.',
            ])
            ->assertRedirect('/admin/support');

        $this->assertSame('1', (string) Setting::get('support_enabled'));
        $this->assertSame(21, (int) Setting::get('support_auto_close_days'));
        $this->assertSame($provider->getKey(), Setting::get('support_ai_provider_id'));
        $this->assertSame('Always be concise.', Setting::get('support_ai_instructions'));
    }

    public function test_the_admin_can_change_a_ticket_status_and_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        [$user] = $this->workspace();

        $ticket = app(SupportTicketService::class)->open([
            'subject' => 'Needs routing',
            'body' => 'Route me please.',
        ], $user, $this->organizationOf($user));

        $this->actingAs($admin)
            ->patch('/admin/support/'.$ticket->getKey(), [
                'status' => SupportTicket::STATUS_RESOLVED,
                'priority' => SupportTicket::PRIORITY_URGENT,
                'category' => SupportTicket::CATEGORY_COMPLAINT,
                'assigned_to' => $admin->getKey(),
            ])
            ->assertRedirect('/admin/support/'.$ticket->getKey());

        $ticket->refresh();

        $this->assertSame(SupportTicket::STATUS_RESOLVED, $ticket->status);
        $this->assertSame(SupportTicket::PRIORITY_URGENT, $ticket->priority);
        $this->assertSame(SupportTicket::CATEGORY_COMPLAINT, $ticket->category);
        $this->assertSame($admin->getKey(), $ticket->assigned_to);
        $this->assertNotNull($ticket->resolved_at);
    }

    public function test_the_admin_support_page_renders_settings_and_ai(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/support')
            ->assertOk()
            ->assertSee(__('ui.admin.support.title'))
            ->assertSee(__('ui.admin.support.ai_title'));
    }

    public function test_dashboard_support_can_be_switched_off_without_losing_the_contact_form(): void
    {
        Setting::put('support_enabled', false, false, 'support');

        [$user] = $this->workspace();

        $this->actingAs($user)
            ->post('/dashboard/support', [
                'subject' => 'Blocked',
                'body' => 'Should not be allowed.',
                'category' => SupportTicket::CATEGORY_GENERAL,
                'priority' => SupportTicket::PRIORITY_NORMAL,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('support_tickets', 0);

        $this->post('/contact', [
            'name' => 'Visitor',
            'email' => 'visitor@example.com',
            'subject' => 'Still works',
            'message' => 'The contact form must always reach support.',
        ])->assertRedirect();

        $this->assertDatabaseCount('support_tickets', 1);
    }

    private function enableAi(bool $autoReply = false): void
    {
        $provider = AiProvider::factory()->create([
            'driver' => AiProvider::DRIVER_OPENAI,
            'base_url' => 'https://api.example.com/v1',
            'api_key' => 'test-key',
        ]);

        $model = AiModel::factory()->create(['ai_provider_id' => $provider->getKey()]);

        Setting::put('support_ai_enabled', true, false, 'support');
        Setting::put('support_ai_auto_reply', $autoReply, false, 'support');
        Setting::put('support_ai_provider_id', $provider->getKey(), false, 'support');
        Setting::put('support_ai_model_id', $model->getKey(), false, 'support');
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function workspace(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['owner_id' => $user->getKey()]);

        Membership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        return [$user, $organization];
    }

    private function organizationOf(User $user): Organization
    {
        return Organization::query()
            ->where('owner_id', $user->getKey())
            ->firstOrFail();
    }
}
