<?php
session_start();
require_once("auth.php");

// Se o usuário já está autenticado, redirecionar para a página desejada
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    $redirectURL = $_SESSION['redirect_after_login'] ?? 'index.php'; // Padrão: index.php
    unset($_SESSION['redirect_after_login']); // Remove a variável de sessão
    header("Location: $redirectURL");
    exit;
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $authResult = authenticate($username, $password, $urlauth);

        if ($authResult) {
            $_SESSION['authenticated'] = true;
            $_SESSION['user_info'] = $authResult;
            $_SESSION['welcome_shown'] = false; // Para exibir o pop-up de boas-vindas depois
            
            // Redireciona para a página original que o usuário tentou acessar
            $redirectURL = $_SESSION['redirect_after_login'] ?? 'index.php';
            unset($_SESSION['redirect_after_login']);
            header("Location: $redirectURL");
            exit;
        } else {
            $error = "Usuário ou senha inválidos.";
        }
    } else {
        $error = "Preencha ambos os campos.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
		body {
			font-family: 'Poppins', sans-serif;
			background-color: #f4f4f4;
			display: flex;
			justify-content: center;
			align-items: center;
			height: 100vh;
			margin: 0;
		}

		.login-container {
			background: white;
			padding: 30px;
			box-shadow: 0px 4px 15px rgba(0, 0, 0, 0.1);
			border-radius: 12px;
			text-align: center;
			width: 350px;
		}

		.login-container h2 {
			margin-bottom: 20px;
			font-size: 24px;
			font-weight: 600;
		}

		input {
			width: calc(100% - 24px); /* Reduzindo um pouco a largura para alinhar com o botão */
			padding: 12px;
			margin: 10px 0;
			border: 1px solid #ccc;
			border-radius: 6px;
			font-size: 16px;
			background-color: #f8f9fa;
			transition: 0.3s;
			box-sizing: border-box;
		}

		input:focus {
			outline: none;
			border-color: #28a745;
			background-color: #eaf5ea;
			box-shadow: 0px 0px 8px rgba(40, 167, 69, 0.2);
		}

		button {
			background: #28a745;
			color: white;
			border: none;
			padding: 10px 20px; /* Reduzindo o padding para diminuir a altura */
			width: 100%;
			max-width: 200px; /* Definindo um tamanho máximo para o botão */
			border-radius: 6px;
			font-size: 16px;
			font-weight: 600;
			cursor: pointer;
			transition: 0.3s;
			margin-top: 10px;
			display: block;
			margin-left: auto;
			margin-right: auto; /* Centralizando o botão */
		}

		button:hover {
			background: #218838;
		}

		/* Ajustando para telas menores */
		@media screen and (max-width: 400px) {
			.login-container {
				width: 90%;
			}
		}
    </style>
</head>
<body>

<div class="login-container">
    <h2>Login</h2>
    <?php if (!empty($error)): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <form method="POST" action="">
        <input type="text" name="username" placeholder="Usuário" required>
        <input type="password" name="password" placeholder="Senha" required>
        <button type="submit">Entrar</button>
    </form>
</div>

</body>
</html>
