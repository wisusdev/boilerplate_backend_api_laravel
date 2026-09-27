<?php

namespace App\Support\Seo;

/**
 * Etiquetas <head> de una página de la SPA: título, descripción, canónico,
 * Open Graph, Twitter y JSON-LD. inject() las coloca en el index.html del build
 * sustituyendo las genéricas.
 */
class PageMeta
{
    public ?string $title = null;

    public ?string $description = null;

    public ?string $image = null;

    public string $type = 'website';

    public bool $noindex = false;

    /** @var list<array<string,mixed>> */
    public array $jsonLd = [];

    public function __construct(public ?string $canonical, public string $siteName) {}

    /** Primeras frases de un texto, sin HTML, para meta description. */
    public static function summary(string $texto, int $max = 160): string
    {
        $limpio = trim(preg_replace('/\s+/u', ' ', strip_tags($texto)) ?? '');

        return mb_strlen($limpio) > $max ? rtrim(mb_substr($limpio, 0, $max - 1)).'…' : $limpio;
    }

    public function inject(string $html): string
    {
        $titulo = $this->title === null
            ? null
            : (str_contains($this->title, $this->siteName) ? $this->title : "{$this->title} · {$this->siteName}");

        // Fuera las genéricas que se van a reemplazar.
        $patrones = [
            '#\s*<meta\s+property="og:[^"]*"[^>]*>#i',
            '#\s*<meta\s+name="twitter:[^"]*"[^>]*>#i',
            '#\s*<link\s+rel="canonical"[^>]*>#i',
            '#\s*<meta\s+name="robots"[^>]*>#i',
        ];
        if ($titulo !== null) {
            $patrones[] = '#<title>.*?</title>#is';
        }
        if ($this->description !== null) {
            $patrones[] = '#\s*<meta\s+name="description"[^>]*>#is';
        }
        $html = preg_replace($patrones, '', $html) ?? $html;

        // Lo que no se reemplaza (portada) conserva el texto del index.html.
        $titulo ??= preg_match('#<title>(.*?)</title>#is', $html, $m) ? html_entity_decode($m[1]) : $this->siteName;
        $descripcion = $this->description
            ?? (preg_match('#<meta\s+name="description"\s+content="([^"]*)"#is', $html, $m) ? html_entity_decode(trim($m[1])) : null);

        $etiquetas = [];
        if ($this->title !== null) {
            $etiquetas[] = '<title>'.e($titulo).'</title>';
        }
        if ($this->description !== null) {
            $etiquetas[] = '<meta name="description" content="'.e($this->description).'" />';
        }
        if ($this->noindex) {
            $etiquetas[] = '<meta name="robots" content="noindex, nofollow" />';
        }
        if ($this->canonical !== null) {
            $etiquetas[] = '<link rel="canonical" href="'.e($this->canonical).'" />';
        }

        $og = [
            'og:site_name' => $this->siteName,
            'og:type' => $this->type,
            'og:title' => $titulo,
            'og:description' => $descripcion,
            'og:url' => $this->canonical,
            'og:image' => $this->image,
            'og:locale' => 'es_SV',
        ];
        foreach (array_filter($og) as $propiedad => $valor) {
            $etiquetas[] = '<meta property="'.$propiedad.'" content="'.e($valor).'" />';
        }
        $etiquetas[] = '<meta name="twitter:card" content="'.($this->image ? 'summary_large_image' : 'summary').'" />';

        foreach ($this->jsonLd as $datos) {
            // JSON_HEX_TAG evita que un "</script>" en un título cierre la etiqueta.
            $etiquetas[] = '<script type="application/ld+json">'
                .json_encode($datos, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)
                .'</script>';
        }

        $bloque = "\n    ".implode("\n    ", $etiquetas)."\n  ";

        return preg_replace('#</head>#i', $bloque.'</head>', $html, 1) ?? $html;
    }
}
