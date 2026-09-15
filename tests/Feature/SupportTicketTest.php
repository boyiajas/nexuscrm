<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Department;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;
    private Department $dept;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bank = Bank::query()->create([
            'name' => 'African Bank',
            'code' => 'absa',
            'status' => 'Active',
        ]);

        $this->dept = Department::query()->create([
            'name' => 'Customer Care',
            'code' => 'cc',
            'status' => 'Active',
            'bank_id' => $this->bank->id,
        ]);
    }

    public function test_authenticated_user_can_create_support_ticket(): void
    {
        $user = $this->createUser('Agent', User::ROLE_AGENT);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/support/tickets', [
            'subject' => 'Cannot view assigned campaign template',
            'category' => 'Campaigns & Dispatch',
            'priority' => 'high',
            'description' => 'When opening campaign #4, the template preview throws an error.',
            'bank_id' => $this->bank->id,
            'department_id' => $this->dept->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('subject', 'Cannot view assigned campaign template')
            ->assertJsonPath('status', 'open')
            ->assertJsonPath('priority', 'high')
            ->assertJsonPath('user_id', $user->id);

        $this->assertDatabaseHas('support_tickets', [
            'subject' => 'Cannot view assigned campaign template',
            'user_id' => $user->id,
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('support_ticket_messages', [
            'user_id' => $user->id,
            'message' => 'When opening campaign #4, the template preview throws an error.',
        ]);
    }

    public function test_user_can_list_own_tickets(): void
    {
        $user = $this->createUser('Agent 1', User::ROLE_AGENT);
        $otherUser = $this->createUser('Agent 2', User::ROLE_AGENT);

        SupportTicket::create([
            'user_id' => $user->id,
            'bank_id' => $this->bank->id,
            'subject' => 'My issue',
            'category' => 'Other',
            'description' => 'Help needed for my problem.',
        ]);

        SupportTicket::create([
            'user_id' => $otherUser->id,
            'bank_id' => $this->bank->id,
            'subject' => 'Other user issue',
            'category' => 'Other',
            'description' => 'Not my problem.',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/support/tickets');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', 'My issue');
    }

    public function test_user_and_staff_can_reply_to_ticket(): void
    {
        $user = $this->createUser('Agent', User::ROLE_AGENT);
        $admin = $this->createUser('Manager', User::ROLE_ADMIN);

        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'bank_id' => $this->bank->id,
            'subject' => 'Question on WABA',
            'category' => 'Live Chat & WhatsApp',
            'description' => 'How do I register a new number?',
        ]);

        // Staff replies
        Sanctum::actingAs($admin);
        $replyResponse = $this->postJson("/api/support/tickets/{$ticket->id}/reply", [
            'message' => 'Please navigate to Settings -> WABA Profile.',
        ]);

        $replyResponse->assertCreated()
            ->assertJsonPath('is_staff_reply', true);

        $this->assertEquals('in_progress', $ticket->fresh()->status);

        // User replies back
        Sanctum::actingAs($user);
        $userReplyResponse = $this->postJson("/api/support/tickets/{$ticket->id}/reply", [
            'message' => 'Thank you, that solved it!',
        ]);

        $userReplyResponse->assertCreated()
            ->assertJsonPath('is_staff_reply', false);
    }

    public function test_admin_can_update_status_and_resolve_ticket(): void
    {
        $user = $this->createUser('Agent', User::ROLE_AGENT);
        $admin = $this->createUser('Admin', User::ROLE_ADMIN);

        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'bank_id' => $this->bank->id,
            'subject' => 'Fix permissions',
            'category' => 'Access & Permissions',
            'description' => 'I cannot see client import button.',
        ]);

        Sanctum::actingAs($admin);
        $response = $this->patchJson("/api/support/tickets/{$ticket->id}/status", [
            'status' => 'resolved',
            'note' => 'Permission assigned to role.',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'resolved')
            ->assertJsonPath('resolved_by_user_id', $admin->id);

        $this->assertNotNull($ticket->fresh()->resolved_at);
    }

    public function test_validation_fails_for_invalid_ticket_creation(): void
    {
        $user = $this->createUser('Agent', User::ROLE_AGENT);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/support/tickets', [
            'subject' => '',
            'description' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['subject', 'category', 'description']);
    }

    public function test_user_cannot_view_unauthorized_tickets(): void
    {
        $user = $this->createUser('Agent A', User::ROLE_AGENT);
        $otherBank = Bank::query()->create(['name' => 'Nedbank', 'code' => 'ned', 'status' => 'Active']);
        $otherUser = User::query()->create([
            'name' => 'Agent B',
            'email' => 'agent-b@example.test',
            'password' => Hash::make('Password123!'),
            'password_changed_at' => now(),
            'password_reset_required' => false,
            'role' => User::ROLE_AGENT,
            'status' => 'Active',
            'bank_id' => $otherBank->id,
        ]);

        $ticket = SupportTicket::create([
            'user_id' => $otherUser->id,
            'bank_id' => $otherBank->id,
            'subject' => 'Secret Bank Issue',
            'category' => 'Other',
            'description' => 'Should not be seen.',
        ]);

        Sanctum::actingAs($user);
        $this->getJson("/api/support/tickets/{$ticket->id}")->assertForbidden();
    }

    private function createUser(string $name, string $roleCode): User
    {
        $role = Role::query()->where('code', $roleCode)->first()
            ?: Role::query()->create(['name' => $name, 'code' => $roleCode]);

        $user = User::query()->create([
            'name' => $name,
            'email' => strtolower(Str::slug($name)) . '-' . Str::uuid() . '@example.test',
            'password' => Hash::make('Password123!'),
            'password_changed_at' => now(),
            'password_reset_required' => false,
            'role' => $roleCode,
            'status' => 'Active',
            'bank_id' => $this->bank->id,
            'department_id' => $this->dept->id,
        ]);

        $user->roles()->sync([$role->id]);
        $user->banks()->sync([$this->bank->id]);
        $user->departments()->sync([$this->dept->id]);

        return $user->fresh(['bank', 'banks', 'roles', 'departments']);
    }
}
