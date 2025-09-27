<?php
// Ativar exibição de erros para desenvolvimento
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluir o controlador de usuários (você precisará criar isso)
require_once __DIR__ . '/../controllers/userController.php';

// Verificar se a sessão já foi iniciada antes de iniciar uma nova
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o formulário foi enviado via POST e se o botão "cadastrar" foi acionado
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cadastrar'])) {
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $senha = isset($_POST['senha']) ? $_POST['senha'] : '';

    // Validação simples dos dados
    if (empty($nome) || empty($email) || empty($senha)) {
        echo 'Todos os campos são obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo 'Email inválido.';
    } else {
        // Chama a função de cadastro no controlador
        $senhaHash = password_hash($senha, PASSWORD_BCRYPT); // Criptografa a senha
        if (registrarUsuario($nome, $email, $senhaHash)) {
            // Caso o cadastro seja bem-sucedido, redireciona o usuário
            echo 'Usuário cadastrado com sucesso.';
            header('Location: /atacado/');
            exit;
        } else {
            echo 'Erro ao cadastrar o usuário. Tente novamente.';
        }
    }
}
?>