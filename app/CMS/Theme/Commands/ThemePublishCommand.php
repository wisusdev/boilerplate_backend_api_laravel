<?php

namespace App\CMS\Theme\Commands;

use App\CMS\Theme\Contracts\ThemeManagerInterface;
use Illuminate\Console\Command;

class ThemePublishCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'theme:publish
                            {theme? : The theme to publish assets for}
                            {--all : Publish assets for all themes}';

    /**
     * The console command description.
     */
    protected $description = 'Publish theme assets to the public directory';

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
        if ($this->option('all')) {
            return $this->publishAll();
        }

        $themeName = $this->argument('theme') ?? $this->themeManager->getActive();

        if (!$themeName) {
            $this->error('Please specify a theme or set an active theme.');
            return self::FAILURE;
        }

        return $this->publishTheme($themeName);
    }

    /**
     * Publish assets for a single theme.
     */
    protected function publishTheme(string $themeName): int
    {
        if (!$this->themeManager->exists($themeName)) {
            $this->error("Theme [{$themeName}] not found.");
            return self::FAILURE;
        }

        $this->info("Publishing assets for theme: {$themeName}");

        if ($this->themeManager->publishAssets($themeName)) {
            $this->info("Assets published successfully!");
            return self::SUCCESS;
        }

        $this->error("Failed to publish assets for theme [{$themeName}].");
        return self::FAILURE;
    }

    /**
     * Publish assets for all themes.
     */
    protected function publishAll(): int
    {
        $themes = $this->themeManager->all();

        if (empty($themes)) {
            $this->warn('No themes found.');
            return self::SUCCESS;
        }

        $this->info("Publishing assets for all themes...");
        $this->newLine();

        $failed = 0;

        foreach ($themes as $theme) {
            $themeName = $theme->getSlug();
            $this->line("  - {$themeName}...");

            if (!$this->themeManager->publishAssets($themeName)) {
                $this->error("    Failed to publish {$themeName}");
                $failed++;
            }
        }

        $this->newLine();

        if ($failed > 0) {
            $this->warn("Published with {$failed} failure(s).");
            return self::FAILURE;
        }

        $this->info("All theme assets published successfully!");
        return self::SUCCESS;
    }
}
