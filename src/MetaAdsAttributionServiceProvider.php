<?php

namespace Antigravity\MetaAdsAttribution;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Antigravity\MetaAdsAttribution\Services\MetaAttributionManager;
use Antigravity\MetaAdsAttribution\Services\MetaConversionService;
use Antigravity\MetaAdsAttribution\Middleware\CaptureMetaAttributionMiddleware;

class MetaAdsAttributionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/meta-attribution.php', 'meta-attribution');

        $this->app->singleton(MetaAttributionManager::class, function ($app) {
            return new MetaAttributionManager();
        });

        $this->app->singleton(MetaConversionService::class, function ($app) {
            return new MetaConversionService();
        });
    }

    public function boot(): void
    {
        // Publish config & migrations
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/meta-attribution.php' => config_path('meta-attribution.php'),
            ], 'meta-attribution-config');

            $this->publishes([
                __DIR__ . '/Database/Migrations' => database_path('migrations'),
            ], 'meta-attribution-migrations');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/meta-attribution'),
            ], 'meta-attribution-views');

            $this->commands([
                Commands\MetaAttributionInstallCommand::class,
            ]);
        }

        // Load migrations & views
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'meta-attribution');

        // Load package routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // Register Blade directive & Component
        Blade::directive('metaPixel', function () {
            return "<?php echo view('meta-attribution::pixel')->render(); ?>";
        });
    }
}
