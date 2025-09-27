<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'backend/config/db.php';

// Verificar se a requisição é do tipo POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Capturar e sanitizar os dados do POST
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    $telefone = isset($_POST['telefone']) ? trim($_POST['telefone']) : '';
    $cidade = isset($_POST['cidade']) ? trim($_POST['cidade']) : '';
    $documento = isset($_POST['documento']) ? trim($_POST['documento']) : '';

    // Verificar se todos os campos estão preenchidos
    if (empty($nome) || empty($telefone) || empty($cidade) || empty($documento)) {
        echo json_encode(['status' => 'error', 'message' => 'Todos os campos são obrigatórios.']);
        exit;
    }

    // Verificar se já existe um cliente com o mesmo telefone
    $stmt = $conn->prepare("SELECT COUNT(*) FROM clientes WHERE telefone = :telefone");
    $stmt->bindParam(':telefone', $telefone);
    $stmt->execute();
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Já existe um cliente com este telefone.']);
        exit;
    }

    // Inserir cliente no banco de dados
    $stmt = $conn->prepare("INSERT INTO clientes (nome, telefone, cidade, documento) VALUES (:nome, :telefone, :cidade, :documento)");
    $stmt->bindParam(':nome', $nome);
    $stmt->bindParam(':telefone', $telefone);
    $stmt->bindParam(':cidade', $cidade);
    $stmt->bindParam(':documento', $documento);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Cliente criado com sucesso!']);
    } else {
        // Capturar erro do PDO
        $erroInfo = $stmt->errorInfo();
        error_log("Erro ao inserir no banco de dados: " . $erroInfo[2]);
        echo json_encode(['status' => 'error', 'message' => 'Erro ao criar o cliente.']);
    }
    exit;
} else {
    // Se a requisição não for do tipo POST
    echo json_encode(['status' => 'error', 'message' => 'Método de requisição inválido.']);
    exit;
}
?>