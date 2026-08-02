<?php

namespace App\Observers;

use App\Models\Payment;

class PaymentObserver
{
    /**
     * Handle the Payment "created" event.
     */
    public function created(Payment $payment): void
    {
        $project = $payment->project;
        $project->paid_amount = bcadd($project->paid_amount ?? '0.00', $payment->amount, 2);
        $project->save();
    }

    /**
     * Handle the Payment "updated" event.
     */
    public function updated(Payment $payment): void
    {
        if ($payment->isDirty('amount')) {
            $diff = bcsub($payment->amount, $payment->getOriginal('amount'), 2);
            $project = $payment->project;
            $project->paid_amount = bcadd($project->paid_amount ?? '0.00', $diff, 2);
            $project->save();
        }
    }

    /**
     * Handle the Payment "deleted" event.
     */
    public function deleted(Payment $payment): void
    {
        $project = $payment->project;
        $project->paid_amount = bcsub($project->paid_amount ?? '0.00', $payment->amount, 2);
        $project->save();
    }
}
