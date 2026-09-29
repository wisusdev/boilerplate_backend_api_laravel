<?php

namespace App\Http\Controllers\Api\Base;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Manual del administrador (sección Ayuda del panel). El texto vive en
 * resources/docs/manual-admin.md, versionado con el código: al cambiar una
 * función, se actualiza su explicación en el mismo commit.
 */
class HelpController extends Controller
{
    /** GET /help/admin: la introducción y una entrada por sección (## …). */
    public function admin(): JsonResponse
    {
        $markdown = (string) file_get_contents(resource_path('docs/manual-admin.md'));

        // Se trocea por las secciones de nivel 2 para que el panel arme el
        // índice y el buscador. Los ``` no se usan en el manual, así que un
        // "## " a principio de línea siempre es un título.
        $partes = preg_split('/^## /m', str_replace("\r\n", "\n", $markdown)) ?: [];
        $intro = trim((string) array_shift($partes));

        $secciones = [];
        foreach ($partes as $parte) {
            [$titulo, $cuerpo] = array_pad(explode("\n", $parte, 2), 2, '');
            $titulo = trim($titulo);
            $secciones[] = [
                'id' => Str::slug($titulo),
                'title' => $titulo,
                'html' => $this->html($cuerpo),
            ];
        }

        return response()->json([
            'data' => [
                'type' => 'help',
                'id' => 'admin',
                'attributes' => [
                    'intro' => $this->html($intro),
                    'sections' => $secciones,
                    'updated_at' => date(DATE_ATOM, (int) filemtime(resource_path('docs/manual-admin.md'))),
                ],
            ],
        ]);
    }

    private function html(string $markdown): string
    {
        return Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
