<?php
declare(strict_types=1);

/**
 * Configuração ÚNICA do projeto.
 *
 * Objetivo:
 * - Centralizar TODAS as variáveis de configuração (Mapa + /desconexoes)
 * - Manter compatibilidade com o código legado (variáveis soltas como $url, $token, etc.)
 */

$CONFIG = [
    'mapa' => [
        // Coordenadas da localização fixa
        'central_latitude'  => '-12.1289',
        'central_longitude' => '-38.4066',

        // Google Maps (se aplicável ao seu front)
        'google_maps_api_key' => 'API GOOGLE MAPS',

        // SGP
        'sgp' => [
            'base_url' => 'https://URLSGP.sgp.tsmx.com.br',
            'token'    => 'TOKEN',
            'app'      => 'APP',
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
