<?php
require_once("config/conf.php");
session_start();

// Verificar se o captcha foi validado
if (!isset($_SESSION['validated']) || $_SESSION['validated'] !== true) {
    header('Location: validar.php');
    exit;
}
// Verificar se todos os parâmetros obrigatórios foram fornecidos
$olt_id = $_POST['olt_id'] ?? null;
$splitter = $_POST['cto'] ?? null;
$splitter_ports = $_POST['ports'] ?? null;
$occupied_ports = explode(',', $_POST['occupied_ports'] ?? '');
$ctopon = $_POST['ctopon'] ?? null;
$ctoident = $_POST['ctoident'];

if (!$olt_id || !$splitter || !$splitter_ports || !$ctopon) {
    die('Erro: Parâmetros obrigatórios não fornecidos.');
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
    <link rel="stylesheet" type="text/css" href="css/style.css">
    <style>
        /* Estilo para o indicador de carregamento */
        #loading, #loadingcl {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body class="provisionamento-onu">
    <div class="container">
        <h1>ONUs não autorizadas na OLT ID <?php echo htmlspecialchars($olt_id); ?></h1>
        <!--<h1>CTO <?php echo htmlspecialchars($ctoident); ?></h1>-->
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
                        <label>Porta do Splitter:
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
        // Função para mostrar o indicador de carregamento correto
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
                        'contrato': contrato
                    })
                })
                .then(response => response.json().catch(() => { throw new Error('Resposta inválida'); }))
                .then(data => {
                    if (data.msg === "Contrato(s) Localizado(s)" && data.contratos.length > 0) {
                        clienteNomeElement.textContent = data.contratos[0].razaoSocial;
                    } else {
                        clienteNomeElement.textContent = "Contrato não encontrado.";
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