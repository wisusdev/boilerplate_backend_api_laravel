<?php

return [
    /*
     * index.html del build del frontend. SeoController lo sirve con las
     * etiquetas de cada página ya puestas, para buscadores y para las vistas
     * previas de WhatsApp o Facebook, que no ejecutan JavaScript.
     */
    'frontend_index' => env('FRONTEND_INDEX_PATH', '/var/www/cusca-web/index.html'),
];
