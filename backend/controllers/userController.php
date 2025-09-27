<?php
require_once __DIR__ . '/../config/db.php'; // Conectar ao banco de dados

// Função de login
function login($email, $senha) {
    global $conn;

    // Verificar se a sessão já foi iniciada antes de chamar session_start()
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Preparar e executar a consulta ao banco de dados
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verificar se a senha é válida e armazenar dados na sessão
    if ($usuario && password_verify($senha, $usuario['senha'])) {
        // Armazenar os dados do usuário na sessão
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_role'] = $usuario['role'];
        return true;
    }

    // Se o login falhar, retorna false
    return false;
}

// Função para registrar um novo usuário
function registrarUsuario($nome, $email, $senhaHash) {
    global $conn;

    // Verificar se o email já está registrado
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        // Email já cadastrado
        return false;
    }

    // Inserir o novo usuário no banco de dados
    $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha) VALUES (:nome, :email, :senha)");
    $stmt->bindParam(':nome', $nome);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':senha', $senhaHash);

    return $stmt->execute(); // Retorna true se o cadastro foi bem-sucedido
}

// Função para verificar se o usuário está logado
function verificarLogin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Verificar se o ID do usuário está na sessão
    if (isset($_SESSION['usuario_id'])) {
        return true;
    }

    return false;
}

// Função para fazer logout
function logout() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Destruir a sessão
    session_destroy();
    header('Location: /atacado/login.php');
    exit;
}