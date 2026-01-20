<?php
declare(strict_types=1);
// Inicio da função auth
require_once("../auth.php");

// Exibir pop-up de boas-vindas apenas no primeiro login
$nomeUsuario = $_SESSION['user_info']['nome'] ?? 'Usuário';

$mostrarPopup = isset($_SESSION['welcome_shown']) && $_SESSION['welcome_shown'] === false;

if ($mostrarPopup) {
    $_SESSION['welcome_shown'] = true; // Evita que o pop-up apareça novamente após o login
}
// Fim a função auth

require_once __DIR__ . '/../config/conf.php';
require_once __DIR__ . '/lib.php';

// Parâmetros de visualização
$threshold = isset($_GET['threshold']) ? max(0, (int)$_GET['threshold']) : null;
$minCount  = isset($_GET['min_count']) ? max(0, (int)$_GET['min_count']) : 0;
$limit     = isset($_GET['limit']) ? max(1, min(500, (int)$_GET['limit'])) : 50;

$error = null;
$rows = [];
$meta = [];

try {
    // Configuração vem do arquivo único: /config/conf.php
    $cfg = [
        'sgp_base_url' => rtrim((string)$url, '/'),
        'token' => (string)$token,
        'app' => (string)$app,
        'endpoint_path' => (string)($CONFIG['desconexoes']['endpoint_path'] ?? '/api/radius/log/'),
        'threshold' => (int)($CONFIG['desconexoes']['threshold'] ?? 10),
        'max_results' => (int)($CONFIG['desconexoes']['max_results'] ?? 200),
        'cache_ttl_seconds' => (int)($CONFIG['desconexoes']['cache_ttl_seconds'] ?? 60),
        'cache_dir' => (string)($CONFIG['desconexoes']['cache_dir'] ?? (__DIR__ . '/cache')),
        'http_timeout_seconds' => (int)($CONFIG['desconexoes']['http_timeout_seconds'] ?? 10),
    ];

    if ($threshold === null) {
        $threshold = (int)$cfg['threshold'];
    }

    if (trim((string)$cfg['token']) === '' || trim((string)$cfg['app']) === '' || trim((string)$cfg['sgp_base_url']) === '') {
        $error = 'Configuração incompleta. Ajuste /config/conf.php (url/token/app).';
    } else {
        $cacheKey = 'radius_logs';
        $logs = cache_get((string)$cfg['cache_dir'], $cacheKey, (int)$cfg['cache_ttl_seconds']);
        $fromCache = true;

        if (!is_array($logs)) {
            $fromCache = false;
            $url = build_api_url($cfg);
            $resp = http_get_json($url, (int)$cfg['http_timeout_seconds']);
            if (!$resp['ok'] || !is_array($resp['data'])) {
                $error = 'Falha ao consultar a API do SGP.';
            } else {
                $logs = $resp['data'];
                cache_set((string)$cfg['cache_dir'], $cacheKey, $logs);
            }
        }

        if (is_array($logs)) {
            $analysis = analyze_login_ok($logs);
            $i = 0;
            foreach ($analysis['counts'] as $user => $count) {
                if ($count < $minCount) {
                    continue;
                }
                $rows[] = [
                    'usuario' => $user,
                    'count' => $count,
                    'alert' => ($count > $threshold),
                ];
                $i++;
                if ($i >= $limit) {
                    break;
                }
            }

            $meta = [
                'threshold' => $threshold,
                'min_count' => $minCount,
                'limit' => $limit,
                'total_itens_api' => $analysis['total'],
                'itens_parseados_login_ok' => $analysis['parsed'],
                'usuarios_unicos' => count($analysis['counts']),
                'cache_used' => $fromCache,
                'cache_ttl_seconds' => (int)$cfg['cache_ttl_seconds'],
            ];
        }
    }
} catch (Throwable $e) {
    $error = 'Erro interno: ' . $e->getMessage();
}

// Segurança básica
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Content-Security-Policy: default-src \'self\'; style-src \'self\' \'unsafe-inline\'; script-src \'self\'; img-src \'self\' data:;');

?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Desconexões — Login OK (RADIUS)</title>
    <style>
        :root { --bg:#0b1220; --card:#101a2f; --muted:#9aa7c7; --text:#e6ecff; --border:#1b2a4a; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial; background: linear-gradient(180deg, #07101f 0%, #050a14 100%); color: var(--text); }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 28px 16px; }
        .top { display:flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; }
        h1 { margin:0; font-size: 22px; letter-spacing: .2px; }
        .sub { margin-top:6px; color: var(--muted); font-size: 13px; }
        .card { background: rgba(16, 26, 47, .92); border: 1px solid var(--border); border-radius: 14px; padding: 16px; box-shadow: 0 10px 24px rgba(0,0,0,.25); }
        .grid { display:grid; gap: 12px; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-top: 14px; }
        .kpi .label { color: var(--muted); font-size: 12px; }
        .kpi .value { margin-top:6px; font-size: 20px; font-weight: 650; }
        .kpi .hint { margin-top: 4px; font-size: 12px; color: var(--muted); }
        .actions { display:flex; gap:10px; align-items:center; flex-wrap: wrap; }
        .btn { background:#1a2d57; color: var(--text); border:1px solid var(--border); border-radius: 12px; padding: 10px 12px; cursor:pointer; text-decoration:none; font-size: 13px; }
        .btn:hover { filter: brightness(1.05); }
        form { display:flex; gap:10px; flex-wrap: wrap; align-items: flex-end; }
        label { display:block; font-size: 12px; color: var(--muted); margin-bottom: 6px; }
        input { width: 100%; background:#0b1430; border:1px solid var(--border); color: var(--text); border-radius: 12px; padding: 10px 12px; }
        .field { min-width: 160px; }
        table { width:100%; border-collapse: collapse; margin-top: 12px; }
        th, td { text-align:left; padding: 10px 10px; border-bottom: 1px solid rgba(27, 42, 74, .8); font-size: 13px; }
        th { color: var(--muted); font-weight: 600; }
        .badge { display:inline-flex; padding: 2px 10px; border-radius: 999px; font-size: 12px; border:1px solid var(--border); }
        .badge.ok { background: rgba(70, 255, 16); }
        .badge.warn { background: rgba(255, 0, 47); }
        .footer { margin-top: 14px; color: var(--muted); font-size: 12px; }
        @media (max-width: 900px) { .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 520px) { .grid { grid-template-columns: 1fr; } .field { min-width: 100%; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1>Desconexões — Login OK (RADIUS)</h1>
            <div class="sub">Resumo de logins bem-sucedidos por usuário (baseado no endpoint de logs configurado).</div>
        </div>
        <div class="actions">
            <!--a class="btn" href="<?= h('api.php?threshold=' . $threshold . '&min_count=' . $minCount . '&limit=' . min(2000, $limit)) ?>" target="_blank" rel="noreferrer">Abrir API (JSON)</a>
            <a class="btn" href="<?= h('index2.php?threshold=' . $threshold . '&limit=' . min(2000, $limit)) ?>" target="_blank" rel="noreferrer">Abrir legado</a-->
            <a class="btn" href="<?= h($_SERVER['PHP_SELF']) ?>">Atualizar</a>
        </div>
    </div>

    <div class="card" style="margin-top: 14px;">
        <form method="get" action="<?= h($_SERVER['PHP_SELF']) ?>">
            <div class="field">
                <label for="threshold">Threshold (alerta quando &gt;)</label>
                <input id="threshold" name="threshold" type="number" min="0" value="<?= h((string)$threshold) ?>" />
            </div>
            <div class="field">
                <label for="min_count">Filtrar contagem mínima</label>
                <input id="min_count" name="min_count" type="number" min="0" value="<?= h((string)$minCount) ?>" />
            </div>
            <div class="field">
                <label for="limit">Limite (top N)</label>
                <input id="limit" name="limit" type="number" min="1" max="500" value="<?= h((string)$limit) ?>" />
            </div>
            <button class="btn" type="submit">Aplicar</button>
        </form>
    </div>

    <?php if ($error !== null): ?>
        <div class="card" style="margin-top: 14px; border-color: rgba(204, 92, 57, .45);">
            <strong>Erro</strong>
            <div class="sub" style="margin-top: 6px;"><?= h($error) ?></div>
        </div>
    <?php else: ?>
        <div class="grid">
            <div class="card kpi">
                <div class="label">Usuários únicos</div>
                <div class="value"><?= h((string)($meta['usuarios_unicos'] ?? 0)) ?></div>
                <div class="hint">Ordenado por maior contagem</div>
            </div>
            <div class="card kpi">
                <div class="label">Itens retornados pela API</div>
                <div class="value"><?= h((string)($meta['total_itens_api'] ?? 0)) ?></div>
                <div class="hint">Antes do filtro</div>
            </div>
            <div class="card kpi">
                <div class="label">Login OK parseados</div>
                <div class="value"><?= h((string)($meta['itens_parseados_login_ok'] ?? 0)) ?></div>
                <div class="hint">Registros que casaram com o padrão</div>
            </div>
            <div class="card kpi">
                <div class="label">Cache</div>
                <div class="value"><?= ($meta['cache_used'] ?? false) ? 'Sim' : 'Não' ?></div>
                <div class="hint">TTL: <?= h((string)($meta['cache_ttl_seconds'] ?? 0)) ?>s</div>
            </div>
        </div>

        <div class="card" style="margin-top: 14px;">
            <table>
                <thead>
                <tr>
                    <th>Usuário</th>
                    <th style="width: 160px;">Login OK</th>
                    <th style="width: 160px;">Status</th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($rows) === 0): ?>
                    <tr>
                        <td colspan="3" class="sub">Nenhum resultado para os filtros atuais.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= h((string)$r['usuario']) ?></td>
                            <td><strong><?= h((string)$r['count']) ?></strong></td>
                            <td>
                                <?php if ($r['alert']): ?>
                                    <span class="badge warn">ALERTA</span>
                                <?php else: ?>
                                    <span class="badge ok">Normal</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <!--div class="footer">
                Dica: use <code>api.php</code> para consumo por sistemas e <code>index2.php</code> apenas se precisar do formato antigo.
            </div-->
        </div>
    <?php endif; ?>

</div>
<script>
	// Função logout.
	document.getElementById('logoutButton').addEventListener('click', function() {
		fetch('../logout.php') // Chama o script PHP que destrói a sessão
			.then(response => {
				if (response.ok) {
					// Redireciona o usuário após o logout
					alert('Logout realizado com sucesso. Redirecionando para a página de login...');
					window.location.href = '../login.php'; // Página de login ou outra página
				} else {
					alert('Erro ao tentar sair. Tente novamente.');
				}
			})
			.catch(error => {
				console.error('Erro:', error);
			});
	});
</script>
</body>
</html>
