<?php
require_once("config/conf.php");
session_start();

// Verificar se o captcha foi validado
if (!isset($_SESSION['validated']) || $_SESSION['validated'] !== true) {
    header('Location: validar.php');
    exit;
}

// Função para chamar API
function callAPI($url, $method = 'GET', $data = []) {
    global $token, $app;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . "?token=$token&app=$app");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new Exception("Erro ao conectar à API: " . curl_error($ch));
    }
    curl_close($ch);
    return json_decode($result, true);
}

// Receber os parâmetros via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Certifique-se de que os parâmetros estão sendo recebidos corretamente
        $olt_id = $_POST['olt_id'] ?? null;
        $splitter = $_POST['splitter'] ?? null;

        if (!$olt_id || !$splitter) {
            throw new Exception("Parâmetros obrigatórios não fornecidos.");
        }

        $data = [
            'slot' => $_POST['slot'] ?? '',
            'pon' => $_POST['pon'] ?? '',
            'id' => $_POST['id'] ?? '',
            'onutype' => $_POST['onutype'] ?? '',
            'mode' => $_POST['onumode'] ?? '',
            'onutemplate' => $_POST['onutemplate'] ?? '',
            'splitter' => $splitter,
            'splitter_port' => $_POST['splitter_port'] ?? '',
            'contrato' => $_POST['contrato'] ?? ''
        ];

        // Chamar API para autorizar ONU
        $response = callAPI("$url/api/fttx/olt/$olt_id/auth/", 'POST', $data);

        // Exibir mensagem de sucesso ou erro e redirecionar via POST
        echo '<script>
            alert("' . htmlspecialchars($response['msg'] ?? 'Erro desconhecido', ENT_QUOTES, 'UTF-8') . '");

            console.log("Criando formulário para redirecionamento...");

            setTimeout(() => {
                var form = document.createElement("form");
                form.method = "POST";
                form.action = "onu.php";

                var input = document.createElement("input");
                input.type = "hidden";
                input.name = "cto";
                input.value = "' . htmlspecialchars($splitter, ENT_QUOTES, 'UTF-8') . '";

                form.appendChild(input);
                document.body.appendChild(form);

                console.log("Enviando formulário...");
                form.submit();
            }, 500); // Pequeno atraso para garantir que o alerta seja mostrado
        </script>';
        exit;
    } catch (Exception $e) {
        echo '<script>alert("Erro ao autorizar ONU: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '");</script>';
    }
    exit;
}
?>
