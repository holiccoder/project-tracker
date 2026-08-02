<?php

namespace App\Providers;

use App\Models\Payment;
use App\Observers\PaymentObserver;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        Payment::observe(PaymentObserver::class);

        // Dynamically switch session cookie to ensure 100% cookie and logout isolation between Admin and User
        $isAdminRequest = request()->is('admin') || 
                          request()->is('admin/*') || 
                          (request()->is('livewire/*') && str_contains(request()->header('referer', ''), '/admin'));

        if ($isAdminRequest) {
            config(['session.cookie' => 'admin_session']);
        }
    }
}
