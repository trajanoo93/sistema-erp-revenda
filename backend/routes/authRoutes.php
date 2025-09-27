<?php
// Ativar exibição de erros para ajudar no desenvolvimento
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluir o controlador de usuários para acessar a função login
require_once __DIR__ . '/../controllers/userController.php';

// Verificar se a sessão já foi iniciada antes de iniciar uma nova sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $senha = isset($_POST['senha']) ? $_POST['senha'] : '';

    // Verificar se o login foi bem-sucedido
    if (login($email, $senha)) {
        // Armazenar as informações do usuário na sessão
        $_SESSION['usuario_email'] = $email;  // Caso queira armazenar o email também
        
        // Redirecionar para o dashboard após o login bem-sucedido
        header('Location: /atacado/dashboard.php');
        exit;
    } else {
        echo 'Falha no login. Verifique suas credenciais.';
    }
}

// Verificar se o usuário deseja fazer logout
if (isset($_GET['logout'])) {
    // Destruir a sessão e redirecionar para a página de login
    session_destroy();
    header('Location: /atacado/login.php');
    exit;
}
?>