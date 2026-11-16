<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            \App\Contracts\InventoryServiceInterface::class,
            \App\Services\StandaloneInventoryService::class
        );
    }

    public function boot(): void
    {
        //
    }
}