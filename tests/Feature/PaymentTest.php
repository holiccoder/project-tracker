<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Payment;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_creation_increments_paid_amount_via_observer(): void
    {
        $admin = Admin::factory()->create();
        $project = Project::factory()->create([
            'amount' => '1000.00',
            'paid_amount' => '0.00',
            'created_by' => $admin->id,
        ]);

        $this->assertEquals('0.00', $project->paid_amount);

        // Create payment
        $payment = Payment::create([
            'project_id' => $project->id,
            'amount' => '250.50',
            'date' => now()->toDateString(),
            'remark' => '第一笔付款',
            'created_by' => $admin->id,
        ]);

        $project->refresh();
        $this->assertEquals('250.50', $project->paid_amount);

        // Update payment
        $payment->update(['amount' => '300.00']);
        $project->refresh();
        $this->assertEquals('300.00', $project->paid_amount);

        // Delete payment
        $payment->delete();
        $project->refresh();
        $this->assertEquals('0.00', $project->paid_amount);
    }
}
