<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Blade::directive('vite', function ($expression) {
            return "<?php echo app(\\App\\Support\\ViteManifest::class)->tags({$expression}); ?>";
        });

        // Laravel 8 compatibility for conditional attributes used by the views.
        Blade::directive('checked', function ($expression) {
            return "<?php echo (".$expression.") ? 'checked' : ''; ?>";
        });

        Blade::directive('selected', function ($expression) {
            return "<?php echo (".$expression.") ? 'selected' : ''; ?>";
        });
    }
}
