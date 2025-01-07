<?php
require_once "config/conf.php";
session_start();

// Verificar se o captcha foi validado
if (!isset($_SESSION['validated']) || $_SESSION['validated'] !== true) {
    header('Location: validar.php');
    exit;
}
$idcto = ($_GET['cto']);
// URL da API
$apiUrl = "$url/api/fttx/splitter/$idcto/onu/all/?token=$token&app=$app";

// Substitua $idcto pelo valor da CTO que deseja consultar
//$idcto = 30; // Exemplo de ID
$apiUrl = str_replace("$idcto", $idcto, $apiUrl);

// Inicializa cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Executa a requisição e decodifica a resposta JSON
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

// Verifica se o retorno é válido
if (!$data || !is_array($data)) {
    die("Erro ao obter dados da API.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informações de RX e Clientes</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #007bff;
            color: #fff;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>
    <h1>Informações de RX e Clientes</h1>
    <table>
        <thead>
            <tr>
				<th>Porta</th>
                <th>Cliente</th>
                <th>Info RX</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data as $onu): ?>
                <tr>
                    <td><?php echo htmlspecialchars($onu['ctoport'] ?? 'N/A'); ?></td>
					<td><?php echo htmlspecialchars($onu['service_cliente'] ?? 'N/A') . ' → ' . htmlspecialchars($onu['service_contrato'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($onu['info_rx'] ?? 'N/A'); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>

