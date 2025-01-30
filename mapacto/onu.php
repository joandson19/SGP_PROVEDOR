<?php
require_once "config/conf.php";
session_start();

// Verificar se o captcha foi validado
if (!isset($_SESSION['validated']) || $_SESSION['validated'] !== true) {
    header('Location: validar.php');
    exit;
}

$idcto = ($_GET['cto']);
$apiUrl = "$url/api/fttx/splitter/$idcto/onu/all/?token=$token&app=$app";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (!$data || !is_array($data)) {
    die("Erro ao obter dados da API.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <title>Informações de RX e Clientes</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <h1>Informações de RX e Clientes</h1>
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
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <script>
        $(document).ready(function () {
            $(".update-signal").click(function () {
                var phyAddr = $(this).data("phy");
                var button = $(this);
                var apiUrl = "<?php echo $url; ?>/api/fttx/onu/" + phyAddr + "/info/?token=<?php echo $token; ?>&app=<?php echo $app; ?>";

                button.text("Atualizando...").prop("disabled", true);

                $.get(apiUrl, function () {
                    setTimeout(function () {
                        location.reload(); // Recarrega a página para atualizar os dados
                    }, 3000); // Aguarda 3 segundos antes de recarregar
                }).fail(function () {
                    alert("Erro ao atualizar o sinal.");
                }).always(function () {
                    button.text("Atualizar Sinal").prop("disabled", false);
                });
            });
        });
    </script>
</body>
</html>
