<?php

namespace App\CMS\Theme\Commands;

use App\CMS\Theme\Contracts\ThemeManagerInterface;
use Illuminate\Console\Command;

class ThemeListCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'theme:list';

    /**
     * The console command description.
     */
    protected $description = 'List all available themes';

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
        $themes = $this->themeManager->all();

        if (empty($themes)) {
            $this->warn('No themes found.');
            $this->newLine();
            $this->line('Create a new theme using: php artisan make:theme <name>');
            return self::SUCCESS;
        }

        $activeTheme = $this->themeManager->getActive();

        $rows = [];
        foreach ($themes as $theme) {
            $isActive = $theme->getSlug() === $activeTheme;
            $rows[] = [
                $isActive ? '✓' : '',
                $theme->getName(),
                $theme->getSlug(),
                $theme->getVersion(),
                $theme->getParent() ?: '-',
                $theme->getAuthor(),
            ];
        }

        $this->table(
            ['Active', 'Name', 'Slug', 'Version', 'Parent', 'Author'],
            $rows
        );

        return self::SUCCESS;
    }
}
