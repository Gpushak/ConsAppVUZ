<?php

namespace App\Providers;

use App\View\DesktopViewFinder;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind('view.finder', function ($app) {
            return new DesktopViewFinder($app['files'], $app['config']['view.paths']);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::share('desktopWindow', false);
        View::share('desktopDialog', false);

        if ($this->app->resolved('view')) {
            $this->app->make('view')->setFinder(
                new DesktopViewFinder($this->app['files'], $this->app['config']->get('view.paths')),
            );
        }
    }
}
