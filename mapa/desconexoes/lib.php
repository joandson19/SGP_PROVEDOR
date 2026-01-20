<?php

declare(strict_types=1);

/**
 * Busca JSON via cURL com timeout e validação mínima.
 * @return array{ok:bool,status:int,body:string,data:?array,error:?string}
 */
function http_get_json(string $url, int $timeoutSeconds): array
{
    $ch = curl_init();
    if ($ch === false) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'data' => null, 'error' => 'Falha ao inicializar cURL.'];
    }

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_CONNECTTIMEOUT => min(5, $timeoutSeconds),
        CURLOPT_TIMEOUT        => $timeoutSeconds,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'User-Agent: desconexoes/1.0',
        ],
    ]);

    $body = curl_exec($ch);
    $errNo = curl_errno($ch);
    $errMsg = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($body === false) {
        return ['ok' => false, 'status' => $status, 'body' => '', 'data' => null, 'error' => $errNo ? "cURL {$errNo}: {$errMsg}" : 'Falha ao requisitar a API.'];
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'status' => $status, 'body' => (string)$body, 'data' => null, 'error' => 'Resposta não é JSON válido.'];
    }

    return ['ok' => ($status >= 200 && $status < 300), 'status' => $status, 'body' => (string)$body, 'data' => $decoded, 'error' => null];
}

function ensure_dir(string $dir): void
{
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Não foi possível criar o diretório: ' . $dir);
        }
    }
}

/**
 * Cache simples em arquivo: retorna null se expirado/inexistente.
 */
function cache_get(string $cacheDir, string $key, int $ttlSeconds): ?array
{
    $file = rtrim($cacheDir, '/') . '/' . $key . '.json';
    if (!is_file($file)) {
        return null;
    }
    $age = time() - (int)filemtime($file);
    if ($age > $ttlSeconds) {
        return null;
    }
    $raw = file_get_contents($file);
    if ($raw === false) {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function cache_set(string $cacheDir, string $key, array $data): void
{
    ensure_dir($cacheDir);
    $file = rtrim($cacheDir, '/') . '/' . $key . '.json';
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * Constrói a URL do endpoint, com token/app em querystring.
 */
function build_api_url(array $cfg): string
{
    $base = rtrim((string)$cfg['sgp_base_url'], '/');
    $path = '/' . ltrim((string)$cfg['endpoint_path'], '/');

    $query = http_build_query([
        'token' => (string)$cfg['token'],
        'app'   => (string)$cfg['app'],
    ]);

    return $base . $path . '?' . $query;
}

/**
 * Analisa logs e retorna contagem de "Login OK" por usuário.
 * @param array $logs Array de itens do endpoint.
 * @return array{counts:array<string,int>,total:int,parsed:int}
 */
function analyze_login_ok(array $logs): array
{
    $counts = [];
    $total = count($logs);
    $parsed = 0;

    foreach ($logs as $row) {
        if (!is_array($row) || !isset($row['log'])) {
            continue;
        }
        $line = (string)$row['log'];
        if (strpos($line, 'Login OK') === false) {
            continue;
        }
        if (!preg_match('/Login OK: \[([^\]]+)\]/', $line, $m)) {
            continue;
        }
        $user = trim((string)$m[1]);
        if ($user === '') {
            continue;
        }
        $counts[$user] = ($counts[$user] ?? 0) + 1;
        $parsed++;
    }

    arsort($counts); // contagem desc

    return [
        'counts' => $counts,
        'total'  => $total,
        'parsed' => $parsed,
    ];
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
