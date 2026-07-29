<?php

namespace Tests\Feature\Order;

use App\Enums\TenantRole;
use App\Models\EditingTask;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class EditingTaskTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_moves_through_start_in_review_request_revision_then_completes(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $order = Order::factory()->create(['tenant_id' => $tenant->id]);
        $task = EditingTask::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/editing-tasks/{$task->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->actingAsUser($owner)
            ->postJson("/api/v1/editing-tasks/{$task->id}/in-review")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review');

        $this->actingAsUser($owner)
            ->postJson("/api/v1/editing-tasks/{$task->id}/request-revision")
            ->assertOk()
            ->assertJsonPath('data.status', 'revision_requested');

        // Revision requested -> can resume via start again
        $this->actingAsUser($owner)
            ->postJson("/api/v1/editing-tasks/{$task->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->actingAsUser($owner)
            ->postJson("/api/v1/editing-tasks/{$task->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_cannot_mark_in_review_before_starting(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $order = Order::factory()->create(['tenant_id' => $tenant->id]);
        $task = EditingTask::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/editing-tasks/{$task->id}/in-review")
            ->assertStatus(422);
    }

    public function test_manager_can_reassign_a_task_but_editor_cannot(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $editorA = $this->addUserToTenant($tenant, TenantRole::Editor);
        $editorB = $this->addUserToTenant($tenant, TenantRole::Editor);
        $order = Order::factory()->create(['tenant_id' => $tenant->id]);
        $task = EditingTask::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id, 'assigned_user_id' => $editorA->id]);

        $this->actingAsUser($editorA)
            ->postJson("/api/v1/editing-tasks/{$task->id}/assign", ['assigned_user_id' => $editorB->id])
            ->assertForbidden();

        $this->actingAsUser($owner)
            ->postJson("/api/v1/editing-tasks/{$task->id}/assign", ['assigned_user_id' => $editorB->id])
            ->assertOk()
            ->assertJsonPath('data.assigned_user.id', $editorB->id);
    }

    public function test_an_editor_can_only_update_their_own_assigned_task(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $editorA = $this->addUserToTenant($tenant, TenantRole::Editor);
        $editorB = $this->addUserToTenant($tenant, TenantRole::Editor);
        $order = Order::factory()->create(['tenant_id' => $tenant->id]);
        $task = EditingTask::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id, 'assigned_user_id' => $editorA->id]);

        $this->actingAsUser($editorB)
            ->postJson("/api/v1/editing-tasks/{$task->id}/start")
            ->assertForbidden();

        $this->actingAsUser($editorA)
            ->postJson("/api/v1/editing-tasks/{$task->id}/start")
            ->assertOk();
    }
}
