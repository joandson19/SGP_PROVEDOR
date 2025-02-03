<?php
require_once "config/conf.php";
session_start();

// Verificar se o captcha foi validado
if (!isset($_SESSION['validated']) || $_SESSION['validated'] !== true) {
    header('Location: validar.php');
    exit;
}

$idcto = $_GET['cto'] ?? null;

// Verificar se o ID foi passado e é válido
if (!$idcto || !is_numeric($idcto)) {
    die("Erro: Nenhuma CTO informada ou valor inválido.");
}

// Construção da URL da API
$apiUrl = "$url/api/fttx/splitter/$idcto/onu/all/?token=$token&app=$app";

// Inicializa cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

// Verificar se a resposta está vazia
if (!$response) {
    die("Erro: A API não retornou nenhum dado.");
}

// Decodifica a resposta JSON
$data = json_decode($response, true);

// Verifica se a decodificação falhou ou se não há dados
if (json_last_error() !== JSON_ERROR_NONE || !$data || !is_array($data)) {
    die("CTO informada não existe.");
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informações de RX e Clientes</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <h1>Informações de RX e Clientes</h1>

    <button id="update-all" class="update-all">Atualizar Todos</button>
    <span id="loading-all" class="loading" style="display:none;">🔄 Atualizando todas as ONUs...</span>

    <table>
        <thead>
            <tr>
                <th>Porta</th>
                <th>Cliente</th>
                <th>Serial</th>
                <th>Info RX</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data as $onu): ?>
                <tr id="row-<?php echo htmlspecialchars($onu['phy_addr']); ?>">
                    <td><?php echo htmlspecialchars($onu['ctoport'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($onu['service_cliente'] ?? 'N/A') . ' → ' . htmlspecialchars($onu['service_contrato'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($onu['phy_addr'] ?? 'N/A'); ?></td>
                    <td id="rx-<?php echo htmlspecialchars($onu['phy_addr']); ?>" style="color: <?php echo ($onu['info_rx'] ?? 'N/A') <= -23 ? 'red' : 'green'; ?>">
                        <?php echo htmlspecialchars($onu['info_rx'] ?? 'N/A'); ?>
                    </td>
                    <td>
                        <button id="new-table-btn" class="update-signal" data-phy="<?php echo htmlspecialchars($onu['phy_addr']); ?>">Atualizar Sinal</button>
                        <span id="loading-<?php echo htmlspecialchars($onu['phy_addr']); ?>" class="loading" style="display:none;">🔄</span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Função para atualizar o sinal de uma ONU
            function updateONU(phyAddr, button, callback = null) {
                const loading = document.getElementById(`loading-${phyAddr}`);
                const rxField = document.getElementById(`rx-${phyAddr}`);
                const apiUrl = `<?php echo $url; ?>/api/fttx/onu/${phyAddr}/info/?token=<?php echo $token; ?>&app=<?php echo $app; ?>`;

                button.disabled = true;
                button.textContent = "Atualizando...";
                loading.style.display = "inline";

                fetch(apiUrl)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error("Erro ao atualizar o sinal da ONU.");
                        }
                        return fetch(`<?php echo $url; ?>/api/fttx/splitter/<?php echo $idcto; ?>/onu/all/?token=<?php echo $token; ?>&app=<?php echo $app; ?>`);
                    })
                    .then(response => response.json())
                    .then(newData => {
                        const updatedRx = newData.find(onu => onu.phy_addr === phyAddr)?.info_rx || "N/A";
                        rxField.textContent = updatedRx;
                        rxField.style.color = updatedRx <= -23 ? "red" : "green";
                    })
                    .catch(error => {
                        alert(`Erro ao atualizar o sinal da ONU ${phyAddr}: ${error.message}`);
                    })
                    .finally(() => {
                        button.disabled = false;
                        button.textContent = "Atualizar Sinal";
                        loading.style.display = "none";
                        if (callback) callback(); // Chama a próxima atualização se for do botão "Atualizar Todos"
                    });
            }

            // Atualizar sinal de uma ONU individualmente
            document.querySelectorAll(".update-signal").forEach(button => {
                button.addEventListener("click", function () {
                    const phyAddr = this.dataset.phy;
                    updateONU(phyAddr, this);
                });
            });

            // Atualizar sinal de todas as ONUs
            document.getElementById("update-all").addEventListener("click", function () {
                const button = this;
                const loadingAll = document.getElementById("loading-all");
                const onus = document.querySelectorAll(".update-signal");

                button.disabled = true;
                button.textContent = "Atualizando...";
                loadingAll.style.display = "inline";

                // Atualiza cada ONU uma por uma, de forma sequencial
                function atualizarProxima(index) {
                    if (index >= onus.length) {
                        button.disabled = false;
                        button.textContent = "Atualizar Todos";
                        loadingAll.style.display = "none";
                        return;
                    }

                    const phyAddr = onus[index].dataset.phy;
                    updateONU(phyAddr, onus[index], () => atualizarProxima(index + 1));
                }

                atualizarProxima(0);
            });
        });
    </script>
</body>
</html>