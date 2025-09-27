<?php
require_once 'backend/config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id']) && isset($_POST['status'])) {
    $id = $_POST['id'];
    $status = $_POST['status'];

    // Atualizar o status do pedido no banco de dados
    $stmt = $conn->prepare("UPDATE pedidos SET status = :status WHERE id = :id");
    $stmt->bindParam(':status', $status, PDO::PARAM_STR);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        echo 'success';
    } else {
        echo 'error';
    }
}
?>