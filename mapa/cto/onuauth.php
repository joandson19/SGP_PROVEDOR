<?php
require_once("../config/conf.php");

// Inicio da função auth
require_once("../auth.php");

// Exibir pop-up de boas-vindas apenas no primeiro login
$nomeUsuario = $_SESSION['user_info']['nome'] ?? 'Usuário';

$mostrarPopup = isset($_SESSION['welcome_shown']) && $_SESSION['welcome_shown'] === false;

if ($mostrarPopup) {
    $_SESSION['welcome_shown'] = true; // Evita que o pop-up apareça novamente após o login
}
// Fim a função auth

// Controlar o cache do navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Verificar se todos os parâmetros obrigatórios foram fornecidos
$olt_id = $_POST['olt_id'] ?? null;
$splitter = $_POST['cto'] ?? null;
$splitter_ports = $_POST['ports'] ?? null;
$occupied_ports = explode(',', $_POST['occupied_ports'] ?? '');
$ctopon = $_POST['ctopon'] ?? null;
$ctoident = $_POST['ctoident'];

if (!$olt_id || !$splitter || !$splitter_ports || !$ctopon) {
    echo "<script>
            alert('Erro: Parâmetros obrigatórios não fornecidos.');
            window.location.href = 'index.php';
          </script>";
    exit();
}

// Função para chamar API
function callAPI($url, $method = 'GET', $data = []) {
    global $token, $app;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . "?token=$token&app=$app");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    $headers = ['Content-Type: multipart/form-data'];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new Exception("Erro ao conectar à API: " . curl_error($ch));
    }
    curl_close($ch);
    return json_decode($result, true);
}

// Listar ONUs não autorizadas e filtrar por PON
try {
    $onus = callAPI("$url/api/fttx/olt/$olt_id/unauth/");
    $onus = array_filter($onus, function($onu) use ($ctopon) {
        return $onu['pon'] == $ctopon;
    });
if (empty($onus)) {
        echo "<script>alert('Nenhuma ONU para autorizar na PON-$ctopon.');</script>";
} else {
    // Listar tipos de ONU e templates
    $onutypes = callAPI("$url/api/fttx/onutype/list/");
    $onutemplates = callAPI("$url/api/fttx/onutemplate/list/");
	$onumode = callAPI("$url/api/fttx/onumode/list/");
	}
} catch (Exception $e) {
    die("Erro ao buscar dados da API: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Provisionamento de ONU</title>
    <link rel="stylesheet" href="css/style.css?v=<?= filemtime('css/style.css'); ?>">
</head>
<body class="provisionamento-onu">
    <div class="container">
        <h2>ONUs não autorizadas na <span style="color: green;">PON-<?php echo htmlspecialchars($ctopon); ?></span></h2>
        <h6><?php echo htmlspecialchars($onus[0]['olt_name']); ?></h6>
        <div id="loading">Autorizando ONU...</div> <!-- Indicador de carregamento -->
        <div id="loadingcl">Buscando Cliente...</div> <!-- Indicador de carregamento -->
        <table class="onu-table">
            <tr>
                <th>Slot</th>
                <th>PON</th>
                <th>ONU ID</th>
                <th>Autorizar em <?php echo htmlspecialchars($ctoident); ?></th>
            </tr>
            <?php foreach ($onus as $onu): ?>
            <tr>
                <td><?php echo htmlspecialchars($onu['slot']); ?></td>
                <td><?php echo htmlspecialchars($onu['pon']); ?></td>
                <td><?php echo htmlspecialchars($onu['id']); ?></td>
                <td>
                    <form method="POST" action="provisiona_onu.php" class="onu-form" onsubmit="showLoading('auth')">
                        <input type="hidden" name="slot" value="<?php echo htmlspecialchars($onu['slot']); ?>">
                        <input type="hidden" name="pon" value="<?php echo htmlspecialchars($onu['pon']); ?>">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($onu['id']); ?>">
						<input type="hidden" name="splitter" value="<?php echo htmlspecialchars($splitter); ?>">
						<input type="hidden" name="olt_id" value="<?php echo htmlspecialchars($olt_id); ?>">
                        <label>Tipo ONU:
                            <select name="onutype" required>
                                <?php foreach ($onutypes as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type['id']); ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Modo:
                            <select name="onumode" required>
                                <?php foreach ($onumode as $mode): ?>
                                    <option value="<?php echo htmlspecialchars($mode['id']); ?>"><?php echo htmlspecialchars($mode['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>						
                        <label>Template:
                            <select name="onutemplate" required>
                                <?php foreach ($onutemplates as $template): ?>
                                    <option value="<?php echo htmlspecialchars($template['id']); ?>"><?php echo htmlspecialchars($template['description']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Contrato:
                            <input type="text" name="contrato" id="contrato" required>
                            <div id="cliente-nome" class="cliente-nome"></div> <!-- Exibe o nome do cliente -->
                        </label>
                        <label>Porta da CTO:
                            <select name="splitter_port" required>
                                <?php for ($i = 1; $i <= $splitter_ports; $i++): ?>
                                    <?php if (!in_array($i, $occupied_ports)): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </select>
                        </label>
                        <button type="submit">Autorizar</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <script>
        // Função para mostrar o indicador de carregamento
        function showLoading(loadingType = 'auth') {
            if (loadingType === 'auth') {
                document.getElementById('loading').style.display = 'block'; // Autorizando ONU
            } else if (loadingType === 'cliente') {
                document.getElementById('loadingcl').style.display = 'block'; // Buscando Cliente
            }
        }

        // Função para ocultar o indicador de carregamento
        function hideLoading(loadingType = 'auth') {
            if (loadingType === 'auth') {
                document.getElementById('loading').style.display = 'none'; // Autorizando ONU
            } else if (loadingType === 'cliente') {
                document.getElementById('loadingcl').style.display = 'none'; // Buscando Cliente
            }
        }

        document.getElementById('contrato').addEventListener('input', function (e) {
            const contrato = e.target.value;
            const clienteNomeElement = document.getElementById('cliente-nome');

            if (contrato.length >= 3) { // Só faz a consulta se houver pelo menos 3 dígitos
                showLoading('cliente'); // Exibe "Buscando Cliente..."

                fetch('<?php echo "$url/api/ura/consultacliente/"; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        'app': '<?php echo $app; ?>',
                        'token': '<?php echo $token; ?>',
						'status': '1,4', // Consultando somente cliente ativos ou suspensos
                        'contrato': contrato
                    })
                })
                .then(response => response.json().catch(() => { throw new Error('Resposta inválida'); }))
                .then(data => {
                    if (data.msg === "Contrato(s) Localizado(s)" && data.contratos.length > 0) {
                        clienteNomeElement.textContent = data.contratos[0].razaoSocial;
						clienteNomeElement.classList.remove("texto-vermelho");
                    } else {
                        clienteNomeElement.textContent = "Contrato não encontrado.";
						clienteNomeElement.classList.add("texto-vermelho");
                    }
                })
                .catch(error => {
                    console.error('Erro ao consultar contrato:', error);
                    clienteNomeElement.textContent = "Erro ao consultar contrato.";
                })
                .finally(() => {
                    hideLoading('cliente'); // Oculta "Buscando Cliente..."
                });
            } else {
                clienteNomeElement.textContent = ""; // Limpa o campo se o contrato for muito curto
            }
        });
    </script>
</body>
</html>