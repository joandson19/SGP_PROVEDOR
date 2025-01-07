<?php
// Iniciar sessão para controlar o estado de validação
session_start();

// Verificar se o CAPTCHA foi validado
if (isset($_SESSION['validated']) && $_SESSION['validated'] === true) {
    // Redirecionar para a página principal após a validação
    header('Location: .');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	    <link rel="stylesheet" href="css/style.css"> <!-- Adiciona o link para o CSS -->
    <title>Captcha Turnstile</title>

    <!-- Inclusão do script do Turnstile -->
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body>

    <div class="captcha-container">
        <h2>Prove que não é um robô:</h2>
        <form method="POST">
            <!-- Coloque sua site key aqui -->
            <div class="cf-turnstile" data-sitekey='<?php require_once("config/conf.php"); echo $site_key?>'></div>
            <br>
            <button id="new-table-btn" type="submit">Não Sou Robô!</button>
        </form>
    </div>

    <?php
	require_once("config/conf.php");
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Pegar a resposta do captcha
        $response = $_POST['cf-turnstile-response'];

        // Verificar a resposta com a API do Cloudflare
        $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        $data = [
            'secret' => $secret_key,
            'response' => $response
        ];

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($data)
            ]
        ];
        $context = stream_context_create($options);
        $verify_response = file_get_contents($url, false, $context);

        $result = json_decode($verify_response);

        // Verificar se a resposta foi válida
        if ($result->success) {
            $_SESSION['validated'] = true;
            header('Location: .');
            exit;
        } else {
            echo "<p style='color: red; text-align: center;'>Captcha inválido. Tente novamente.</p>";
        }
    }
    ?>

</body>
</html>
