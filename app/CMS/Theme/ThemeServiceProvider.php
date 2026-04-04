<?php

namespace App\CMS\Theme;

use App\CMS\Theme\Contracts\ThemeManagerInterface;
use App\CMS\Theme\Middleware\ThemeMiddleware;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;

class ThemeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../../config/theme.php', 'theme');

        $this->app->singleton(ThemeManagerInterface::class, function ($app) {
            return new ThemeManager(
                $app->make(Filesystem::class),
                $app->make(ViewFactory::class)
            );
        });

        $this->app->alias(ThemeManagerInterface::class, 'theme');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerMiddleware();
        $this->registerCommands();
        $this->registerBladeDirectives();
        $this->setDefaultTheme();
    }

    /**
     * Register the theme middleware.
     */
    protected function registerMiddleware(): void
    {
        $router = $this->app->make('router');
        $router->aliasMiddleware('theme', ThemeMiddleware::class);
    }

    /**
     * Register artisan commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\MakeThemeCommand::class,
                Commands\ThemeListCommand::class,
                Commands\ThemePublishCommand::class,
                Commands\ThemeCacheCommand::class,
            ]);
        }
    }

    /**
     * Register Blade directives.
     */
    protected function registerBladeDirectives(): void
    {
        $blade = $this->app->make('blade.compiler');

        // @theme('theme-name')
        $blade->directive('theme', function ($expression) {
            return "<?php app('theme')->set({$expression}); ?>";
        });

        // @themeAsset('path/to/asset.css')
        $blade->directive('themeAsset', function ($expression) {
            return "<?php echo app('theme')->asset({$expression}); ?>";
        });

        // @themePath('views/partials')
        $blade->directive('themePath', function ($expression) {
            return "<?php echo app('theme')->path({$expression}); ?>";
        });

        // @ifTheme('theme-name')
        $blade->directive('ifTheme', function ($expression) {
            return "<?php if(app('theme')->getActive() === {$expression}): ?>";
        });

        $blade->directive('endIfTheme', function () {
            return "<?php endif; ?>";
        });

        // @themeSupports('feature')
        $blade->directive('themeSupports', function ($expression) {
            return "<?php if(app('theme')->active()?->supports({$expression})): ?>";
        });

        $blade->directive('endThemeSupports', function () {
            return "<?php endif; ?>";
        });
    }

    /**
     * Set the default theme on boot.
     */
    protected function setDefaultTheme(): void
    {
        if (!$this->app->runningInConsole()) {
            $defaultTheme = config('theme.default');
            $themeManager = $this->app->make(ThemeManagerInterface::class);

            if ($defaultTheme && $themeManager->exists($defaultTheme)) {
                $themeManager->set($defaultTheme);
            }
        }
    }
}
