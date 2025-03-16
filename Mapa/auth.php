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
    $authHeader = 'Authorization: Basic ' . base64_encode("$username:$password");

    $options = [
        'http' => [
            'header'  => $authHeader,
            'method'  => 'GET',
        ],
    ];

    $context  = stream_context_create($options);
    $result = @file_get_contents($urlauth, false, $context);

    if ($result === FALSE) {
        return false;
    }

    return json_decode($result, true);
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
