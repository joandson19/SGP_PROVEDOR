<?php
// Iniciar sessão para controlar o estado de validação
session_start();

// Verificar se o CAPTCHA foi validado
if (isset($_SESSION['validated']) && $_SESSION['validated'] === true) {
    // Redirecionar para a página original que o usuário tentou acessar
    $redirectUrl = $_SESSION['redirect_url'] ?? '.'; // Página padrão caso não haja URL salva
    unset($_SESSION['redirect_url']); // Limpar a URL de redirecionamento após o uso
    header('Location: ' . $redirectUrl);
    exit;
}

// Função para validar o CAPTCHA
function validateCaptcha($response, $secretKey) {
    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $data = [
        'secret' => $secretKey,
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
    $verifyResponse = file_get_contents($url, false, $context);

    if ($verifyResponse === false) {
        throw new Exception("Erro ao conectar à API do Cloudflare.");
    }

    $result = json_decode($verifyResponse);
    return $result->success ?? false;
}

// Verificar se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once("config/conf.php");

    try {
        // Pegar a resposta do CAPTCHA
        $response = $_POST['cf-turnstile-response'] ?? '';

        // Validar a resposta com a API do Cloudflare
        if (validateCaptcha($response, $secret_key)) {
            $_SESSION['validated'] = true;

            // Redirecionar para a URL original ou para a página padrão
            $redirectUrl = $_SESSION['redirect_url'] ?? '.';
            unset($_SESSION['redirect_url']); // Limpar a URL de redirecionamento após o uso
            header('Location: ' . $redirectUrl);
            exit;
        } else {
            $errorMessage = "Captcha inválido. Tente novamente.";
        }
    } catch (Exception $e) {
        $errorMessage = "Erro ao validar o CAPTCHA. Tente novamente mais tarde.";
    }
} else {
    // Salvar a URL da página que o usuário tentou acessar antes de ser redirecionado para o CAPTCHA
    $_SESSION['redirect_url'] = $_SERVER['HTTP_REFERER'] ?? '.';
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css?v=<?= filemtime('css/style.css'); ?>">
    <title>Captcha Turnstile</title>

    <!-- Inclusão do script do Turnstile -->
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body class="validar-page">

    <div class="captcha-container">
        <h2>Prove que não é um robô:</h2>
        <form method="POST">
            <!-- Coloque sua site key aqui -->
            <div class="cf-turnstile" data-sitekey='<?php require_once("config/conf.php"); echo $site_key; ?>'></div>
            <br>
            <button id="new-table-btn" type="submit">Não Sou Robô!</button>
        </form>

        <?php if (!empty($errorMessage)): ?>
            <p style="color: red; text-align: center;"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </div>

</body>
</html>