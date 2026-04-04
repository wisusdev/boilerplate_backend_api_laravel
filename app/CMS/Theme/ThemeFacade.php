<?php

namespace App\CMS\Theme;

use App\CMS\Theme\Contracts\ThemeInterface;
use App\CMS\Theme\Contracts\ThemeManagerInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void set(string $theme)
 * @method static ThemeInterface|null active()
 * @method static string|null getActive()
 * @method static ThemeInterface|null parent()
 * @method static ThemeInterface[] all()
 * @method static ThemeInterface|null find(string $theme)
 * @method static bool exists(string $theme)
 * @method static void clear()
 * @method static string path(string $path = '', ?string $theme = null)
 * @method static array getViewPaths()
 * @method static string asset(string $path, ?string $theme = null)
 * @method static bool publishAssets(?string $theme = null)
 * @method static void clearCache()
 *
 * @see ThemeManager
 */
class ThemeFacade extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return ThemeManagerInterface::class;
    }
}
