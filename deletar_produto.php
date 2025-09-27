<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'backend/config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];

    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'ID do produto não fornecido.']);
        exit;
    }

    // Excluir o produto do banco de dados
    $stmt = $conn->prepare("DELETE FROM produtos WHERE id = :id");
    $stmt->bindParam(':id', $id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Produto excluído com sucesso!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao excluir o produto.']);
    }
    exit;
}
?>