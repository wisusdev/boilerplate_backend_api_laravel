<?php

namespace App\CMS\Theme\Commands;

use App\CMS\Theme\Contracts\ThemeManagerInterface;
use Illuminate\Console\Command;

class ThemeCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'theme:cache
                            {--clear : Clear the theme cache}';

    /**
     * The console command description.
     */
    protected $description = 'Cache or clear theme configurations';

    protected ThemeManagerInterface $themeManager;

    public function __construct(ThemeManagerInterface $themeManager)
    {
        parent::__construct();
        $this->themeManager = $themeManager;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('clear')) {
            return $this->clearCache();
        }

        return $this->cacheThemes();
    }

    /**
     * Cache all themes.
     */
    protected function cacheThemes(): int
    {
        $this->info('Caching theme configurations...');

        // Clear existing cache first
        $this->themeManager->clearCache();

        // Load all themes (this will cache them)
        $themes = $this->themeManager->all();

        $this->info(sprintf('Cached %d theme(s) successfully!', count($themes)));

        return self::SUCCESS;
    }

    /**
     * Clear the theme cache.
     */
    protected function clearCache(): int
    {
        $this->info('Clearing theme cache...');

        $this->themeManager->clearCache();

        $this->info('Theme cache cleared successfully!');

        return self::SUCCESS;
    }
}
