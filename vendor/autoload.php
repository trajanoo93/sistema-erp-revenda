<?php

// Função de autoload para carregar as classes das bibliotecas
spl_autoload_register(function ($class) {
    // Diretórios das bibliotecas
    $libraries = [
        'GuzzleHttp\\' => __DIR__ . '/guzzlehttp-guzzle/src/',
        'Google\\Auth\\' => __DIR__ . '/google-auth/src/',
        'Psr\\Http\\Client\\' => __DIR__ . '/psr/http-client/src/',
        'GuzzleHttp\\Psr7\\' => __DIR__ . '/guzzlehttp-psr7/src/',
        'Psr\\Http\\Message\\' => __DIR__ . '/psr/http-message/src/',
        'GuzzleHttp\\Promise\\' => __DIR__ . '/guzzlehttp-promises/src/',
        'Firebase\\JWT\\' => __DIR__ . '/firebase/php-jwt/src/',// Adicionando o guzzlehttp/promises
    ];

    foreach ($libraries as $prefix => $baseDir) {
        // Verifica se a classe começa com o prefixo esperado
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        // Caminho relativo do arquivo da classe
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        // Inclui o arquivo da classe, se existir
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});