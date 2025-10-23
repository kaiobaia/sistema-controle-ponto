<?php
// Iniciar sessão se ainda não foi iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Limpar todas as variáveis de sessão
$_SESSION = array();

// Se for usado cookie de sessão, destruir o cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir a sessão
session_destroy();

// Headers para prevenir cache do navegador
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirecionar para a página de login
header("Location: index.php");
exit(); 