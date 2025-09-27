<?php
require_once 'backend/config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];

    // Excluir cliente do banco de dados
    $stmt = $conn->prepare("DELETE FROM clientes WHERE id = :id");
    $stmt->bindParam(':id', $id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Cliente excluído com sucesso!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao excluir o cliente.']);
    }
}
?>