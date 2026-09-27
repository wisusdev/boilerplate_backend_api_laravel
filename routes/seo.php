<?php

use App\Http\Controllers\Seo\SeoController;
use Illuminate\Support\Facades\Route;

/*
| SEO del sitio público. El nginx del frontend deriva aquí /robots.txt,
| /sitemap.xml y la carga inicial de cada ruta de la SPA (ver DESPLIEGUE-GCP.md).
| Sin grupo de middleware: no hacen falta sesión ni cookies, y el throttle por
| IP no sirve porque todas las peticiones llegan desde el propio nginx.
*/
Route::get('/seo/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('/seo/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/seo/page/{path?}', [SeoController::class, 'page'])->where('path', '.*')->name('seo.page');
