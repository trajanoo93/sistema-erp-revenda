<?php
session_start();

// Destruir sessão
session_unset();
session_destroy();

// Remover cookies (se existirem)
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Redirecionar para login
header('Location: /atacado/index.php?sucesso=logout');
exit;
?>