<?php
declare(strict_types=1);

/**
 * Configuração ÚNICA do projeto.
 *
 * Objetivo:
 * - Centralizar TODAS as variáveis de configuração (Mapa + /desconexoes)
 * - Manter compatibilidade com o código legado (variáveis soltas como $url, $token, etc.)
 */

// Função simples para carregar variáveis do arquivo .env
function loadEnv($path) {
    if (!file_exists($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $env[trim($name)] = trim($value);
        }
    }
    return $env;
}

// Carregar variáveis de ambiente
$env = loadEnv(__DIR__ . '/../.env');

$CONFIG = [
    'mapa' => [
        // Coordenadas da localização fixa
        'central_latitude'  => $env['MAPA_CENTRAL_LATITUDE'] ?? '-12.1289',
        'central_longitude' => $env['MAPA_CENTRAL_LONGITUDE'] ?? '-38.4066',

        // Google Maps (se aplicável ao seu front)
        'google_maps_api_key' => $env['GOOGLE_MAPS_API_KEY'] ?? 'API GOOGLE MAPS',

        // SGP
        'sgp' => [
            'base_url' => $env['SGP_BASE_URL'] ?? 'https://URLSGP.sgp.tsmx.com.br',
            'token'    => $env['SGP_TOKEN'] ?? 'TOKEN',
            'app'      => $env['SGP_APP'] ?? 'APP',
        ],

        // Filtros/pesquisa
        'pesquisa' => [
            'status' => 'ATIVO',
            'uf'     => 'BA',
        ],

        // Porta de acesso aos roteadores
        'port' => '9090',

        // Se trabalha somente com FTTH use 0; FTTH + UTP use 1
        'tecnologia' => '1',

        // Ativa a consulta detalhada da ONU no InfoWindow (1 ativa, 0 desativa)
        'consulta_onu_ativa' => '1',
    ],

    // Módulo: /desconexoes
    'desconexoes' => [
        // Endpoint relativo de logs do RADIUS no SGP
        'endpoint_path' => '/api/radius/log/',

        // Regras de exibição
        'threshold'   => 10,
        'max_results' => 200,

        // Cache local para reduzir chamadas na API
        'cache_ttl_seconds'    => 60,
        'cache_dir'            => __DIR__ . '/../desconexoes/cache',

        // HTTP
        'http_timeout_seconds' => 10,
    ],
];

// -----------------------------------------------------------------------------
// Compatibilidade legada (variáveis soltas usadas no projeto)
// -----------------------------------------------------------------------------

$centralLatitude  = (string)$CONFIG['mapa']['central_latitude'];
$centralLongitude = (string)$CONFIG['mapa']['central_longitude'];
$googleMapsApiKey = (string)$CONFIG['mapa']['google_maps_api_key'];

$url   = rtrim((string)$CONFIG['mapa']['sgp']['base_url'], '/');
$token = (string)$CONFIG['mapa']['sgp']['token'];
$app   = (string)$CONFIG['mapa']['sgp']['app'];

$status = (string)$CONFIG['mapa']['pesquisa']['status'];
$uf     = (string)$CONFIG['mapa']['pesquisa']['uf'];

$port = (string)$CONFIG['mapa']['port'];

$tecnologia = (string)$CONFIG['mapa']['tecnologia'];

$ConsutaOnuAtiva = (string)$CONFIG['mapa']['consulta_onu_ativa'];
