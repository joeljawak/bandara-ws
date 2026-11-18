<?php

namespace App\Providers;

use App\Services\FusekiDataset;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // satu salinan data Fuseki per permintaan
        $this->app->scoped(FusekiDataset::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
