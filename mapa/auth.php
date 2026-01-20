<?php
require_once("config/conf.php");
session_start();

// Se a sessão ainda não foi iniciada, inicia
if (!isset($_SESSION['session_started'])) {
    session_regenerate_id(true);
    $_SESSION['session_started'] = true;
}

// URL da API de autenticação
$urlauth = "$url/api/auth/info/";

// Função para autenticar via API
function authenticate($username, $password, $urlauth) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $urlauth);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode("$username:$password")
    ]);
    //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Apenas para teste, remova em produção!

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response !== false) {
        return json_decode($response, true);
    }

    error_log("Falha ao autenticar: HTTP $httpCode, resposta: $response");
    return false;
}


// Função para proteger páginas
function requireAuth() {
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        if (basename($_SERVER['PHP_SELF']) !== 'login.php') {
            // Salva a página atual antes de redirecionar para o login
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header("Location: /login.php");
            exit;
        }
    }
}
// Aplica autenticação automaticamente (exceto na página de login)
requireAuth();
?>
