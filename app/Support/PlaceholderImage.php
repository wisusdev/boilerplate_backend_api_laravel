<?php

namespace App\Support;

/**
 * Genera imágenes de relleno con GD, sin salir a la red.
 *
 * Un seeder que descarga de un servicio externo es frágil: si el proveedor se
 * cae, cada imagen agota su timeout y sembrar pasa de segundos a horas. Aquí la
 * imagen se dibuja en memoria en milisegundos, es determinista (el mismo texto
 * produce siempre la misma imagen) y funciona sin conexión y en CI.
 */
class PlaceholderImage
{
    /** Paletas cálidas y frías; se elige una de forma determinista por semilla. */
    private const PALETAS = [
        [[24, 76, 160], [56, 189, 248]],    // azul
        [[15, 118, 110], [110, 231, 183]],  // verde
        [[180, 83, 9], [253, 186, 116]],    // ámbar
        [[136, 19, 55], [251, 113, 133]],   // granate
        [[67, 56, 202], [165, 180, 252]],   // índigo
        [[15, 23, 42], [100, 116, 139]],    // pizarra
    ];

    /**
     * Devuelve el JPEG binario de una imagen con degradado y rótulo.
     */
    public static function jpeg(string $semilla, int $ancho = 1200, int $alto = 800, ?string $rotulo = null): string
    {
        $hash = crc32($semilla);
        [$desde, $hasta] = self::PALETAS[$hash % count(self::PALETAS)];

        $img = imagecreatetruecolor($ancho, $alto);

        // Degradado diagonal: se pinta por filas interpolando el color.
        for ($y = 0; $y < $alto; $y++) {
            $t = $y / max($alto - 1, 1);
            $color = imagecolorallocate(
                $img,
                (int) ($desde[0] + ($hasta[0] - $desde[0]) * $t),
                (int) ($desde[1] + ($hasta[1] - $desde[1]) * $t),
                (int) ($desde[2] + ($hasta[2] - $desde[2]) * $t),
            );
            imageline($img, 0, $y, $ancho, $y, $color);
        }

        // Formas suaves para que no sea un degradado plano. La posición depende
        // de la semilla, así que dos imágenes distintas no se ven iguales.
        $velo = imagecolorallocatealpha($img, 255, 255, 255, 108);
        mt_srand($hash);
        for ($i = 0; $i < 3; $i++) {
            $r = (int) ($alto * (0.35 + mt_rand(0, 40) / 100));
            imagefilledellipse(
                $img,
                mt_rand(0, $ancho),
                mt_rand(0, $alto),
                $r, $r,
                $velo,
            );
        }
        mt_srand();

        if ($rotulo !== null && trim($rotulo) !== '') {
            self::escribirRotulo($img, $ancho, $alto, trim($rotulo));
        }

        ob_start();
        imagejpeg($img, null, 82);
        $binario = (string) ob_get_clean();
        imagedestroy($img);

        return $binario;
    }

    /**
     * Guarda la imagen en un fichero temporal y devuelve su ruta.
     * El llamador es responsable de borrarla.
     */
    public static function tempFile(string $semilla, int $ancho, int $alto, ?string $rotulo = null): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'ph_').'.jpg';
        file_put_contents($ruta, self::jpeg($semilla, $ancho, $alto, $rotulo));

        return $ruta;
    }

    /**
     * Rótulo centrado, con sombra para que se lea sobre cualquier degradado.
     */
    private static function escribirRotulo(\GdImage $img, int $ancho, int $alto, string $texto): void
    {
        $fuente = self::fuente();
        $tam = max(16, (int) ($ancho / 26));
        $texto = mb_strimwidth($texto, 0, 42, '…');

        $blanco = imagecolorallocate($img, 255, 255, 255);
        $sombra = imagecolorallocatealpha($img, 0, 0, 0, 70);

        if ($fuente) {
            $caja = imagettfbbox($tam, 0, $fuente, $texto);
            $x = (int) (($ancho - ($caja[2] - $caja[0])) / 2);
            $y = (int) (($alto + ($caja[1] - $caja[7])) / 2);
            imagettftext($img, $tam, 0, $x + 2, $y + 2, $sombra, $fuente, $texto);
            imagettftext($img, $tam, 0, $x, $y, $blanco, $fuente, $texto);

            return;
        }

        // Sin FreeType: fuente de mapa de bits, suficiente para desarrollo.
        $x = (int) (($ancho - imagefontwidth(5) * mb_strlen($texto)) / 2);
        $y = (int) (($alto - imagefontheight(5)) / 2);
        imagestring($img, 5, $x + 1, $y + 1, $texto, $sombra);
        imagestring($img, 5, $x, $y, $texto, $blanco);
    }

    /** Fuente TTF del propio proyecto (DejaVu, la que usa DomPDF). */
    private static function fuente(): ?string
    {
        if (! function_exists('imagettftext')) {
            return null;
        }

        foreach ([
            base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf'),
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ] as $ruta) {
            if (is_file($ruta)) {
                return $ruta;
            }
        }

        return null;
    }
}
