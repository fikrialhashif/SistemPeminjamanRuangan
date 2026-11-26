<?php

namespace App\Providers;

use App\Models\AssetLoan;
use App\Policies\LoanPolicy;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(AssetLoan::class, LoanPolicy::class);

        Carbon::macro('wib', function (): CarbonInterface {
            return $this->copy()->setTimezone(config('app.display_timezone'));
        });

        Paginator::defaultView('pagination::plate');
        Paginator::defaultSimpleView('pagination::plate');
    }
}
