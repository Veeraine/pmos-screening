<?php

namespace App\Providers;

use App\Emr\EmrAdapter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The EMR adapter chosen in config/emr.php, shared for the whole request.
        $this->app->singleton(EmrAdapter::class, fn ($app) => $app->make(
            config('emr.adapters')[config('emr.adapter')],
        ));
    }

    // Bootstrap any application services.

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        // Staff logins are checked by the EMR, through its adapter.
        Auth::provider('emr', fn ($app) => $app->make(EmrAdapter::class)->loginProvider());
    }
}
