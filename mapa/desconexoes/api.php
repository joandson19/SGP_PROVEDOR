<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/conf.php';
require_once __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    $cfg = [
        'sgp_base_url' => rtrim((string)$url, '/'),
        'token' => (string)$token,
        'app' => (string)$app,
        'endpoint_path' => (string)($CONFIG['desconexoes']['endpoint_path'] ?? '/ws/radius/log/'),
        'threshold' => (int)($CONFIG['desconexoes']['threshold'] ?? 10),
        'max_results' => (int)($CONFIG['desconexoes']['max_results'] ?? 200),
        'cache_ttl_seconds' => (int)($CONFIG['desconexoes']['cache_ttl_seconds'] ?? 60),
        'cache_dir' => (string)($CONFIG['desconexoes']['cache_dir'] ?? (__DIR__ . '/cache')),
        'http_timeout_seconds' => (int)($CONFIG['desconexoes']['http_timeout_seconds'] ?? 10),
    ];

    if (trim((string)$cfg['token']) === '' || trim((string)$cfg['app']) === '' || trim((string)$cfg['sgp_base_url']) === '') {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => 'Configuração incompleta. Ajuste /config/conf.php (url/token/app).',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $threshold = isset($_GET['threshold']) ? max(0, (int)$_GET['threshold']) : (int)$cfg['threshold'];
    $minCount = isset($_GET['min_count']) ? max(0, (int)$_GET['min_count']) : 0;
    $limit = isset($_GET['limit']) ? max(1, min(2000, (int)$_GET['limit'])) : (int)$cfg['max_results'];

    $cacheKey = 'radius_logs';
    $cached = cache_get((string)$cfg['cache_dir'], $cacheKey, (int)$cfg['cache_ttl_seconds']);
    if (is_array($cached)) {
        $logs = $cached;
        $fromCache = true;
    } else {
        $url = build_api_url($cfg);
        $resp = http_get_json($url, (int)$cfg['http_timeout_seconds']);
        if (!$resp['ok'] || !is_array($resp['data'])) {
            http_response_code(502);
            echo json_encode([
                'ok' => false,
                'error' => 'Falha ao consultar a API do SGP.',
                'status' => $resp['status'],
                'detail' => $resp['error'] ?? 'Erro desconhecido',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }
        $logs = $resp['data'];
        cache_set((string)$cfg['cache_dir'], $cacheKey, $logs);
        $fromCache = false;
    }

    $analysis = analyze_login_ok($logs);

    $result = [];
    $i = 0;
    foreach ($analysis['counts'] as $user => $count) {
        if ($count < $minCount) {
            continue;
        }
        $result[] = [
            'usuario' => $user,
            'contagem_login_ok' => $count,
            'alerta' => ($count > $threshold),
        ];
        $i++;
        if ($i >= $limit) {
            break;
        }
    }

    echo json_encode([
        'ok' => true,
        'meta' => [
            'threshold' => $threshold,
            'min_count' => $minCount,
            'limit' => $limit,
            'total_itens_api' => $analysis['total'],
            'itens_parseados_login_ok' => $analysis['parsed'],
            'usuarios_unicos' => count($analysis['counts']),
            'cache' => [
                'used' => $fromCache,
                'ttl_seconds' => (int)$cfg['cache_ttl_seconds'],
            ],
        ],
        'data' => $result,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Erro interno.',
        'detail' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
