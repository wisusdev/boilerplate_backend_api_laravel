<?php

$publicPath = getcwd();

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

if ($uri !== '/') {
    $file = $publicPath . $uri;

    // Resolve symlinks (PHP built-in server doesn't follow them when serving static files)
    $realFile = realpath($file);

    if ($realFile !== false && is_file($realFile)) {
        $ext = strtolower(pathinfo($realFile, PATHINFO_EXTENSION));

        $mimeTypes = [
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'webp'  => 'image/webp',
            'svg'   => 'image/svg+xml',
            'ico'   => 'image/x-icon',
            'css'   => 'text/css',
            'js'    => 'application/javascript',
            'json'  => 'application/json',
            'pdf'   => 'application/pdf',
            'mp4'   => 'video/mp4',
            'mp3'   => 'audio/mpeg',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'otf'   => 'font/otf',
            'txt'   => 'text/plain',
            'xml'   => 'text/xml',
            'zip'   => 'application/zip',
        ];

        if (isset($mimeTypes[$ext])) {
            header('Content-Type: ' . $mimeTypes[$ext]);
            header('Content-Length: ' . filesize($realFile));
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($realFile)) . ' GMT');
            readfile($realFile);
            exit;
        }

        // Unknown extension – let PHP serve it directly (non-symlinked files work fine)
        if (strpos($realFile, realpath($publicPath)) === 0) {
            return false;
        }
    }
}

require_once $publicPath . '/index.php';
