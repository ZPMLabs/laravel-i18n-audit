<?php

declare(strict_types=1);

namespace ZPMLabs\LaravelI18nAudit;

use ZPMLabs\LaravelI18nAudit\Commands\TranslationReportCommand;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use ZPMLabs\LaravelI18nAudit\Http\Controllers\AuditDashboardController;

final class TranslationScannerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/config.php', 'i18n-audit');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'i18n-audit');

        $this->publishes([
            __DIR__ . '/Config/config.php' => config_path('i18n-audit.php'),
        ], 'i18n-audit-config');

        $registerDevRoute = (bool) config('i18n-audit.register_dev_route', true);
        $isDevEnvironment = $this->app->environment(['local', 'development', 'dev']);

        if ($registerDevRoute && $isDevEnvironment) {
            /** @var array<int, string> $middleware */
            $middleware = config('i18n-audit.dev_route_middleware', ['web']);
            $routePath = (string) config('i18n-audit.dev_route_path', 'i18n-audit/latest');

            Route::middleware($middleware)
                ->group(function () use ($routePath): void {
                    Route::get($routePath, [AuditDashboardController::class, 'show'])->name('i18n-audit.latest');
                    Route::post($routePath . '/fill-missing', [AuditDashboardController::class, 'fillMissing'])->name('i18n-audit.fill-missing');
                    Route::post($routePath . '/remove-unused', [AuditDashboardController::class, 'removeUnused'])->name('i18n-audit.remove-unused');
                });
        }

        $this->commands([
            TranslationReportCommand::class,
        ]);
    }
}
