<?php
require_once 'backend/config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id']) && isset($_POST['quantidade_caixas'])) {
    $id = $_POST['id'];
    $quantidade_caixas = $_POST['quantidade_caixas'];

    // Atualizar a quantidade de caixas no banco de dados
    $stmt = $conn->prepare("UPDATE pedidos SET quantidade_caixas = :quantidade_caixas WHERE id = :id");
    $stmt->bindParam(':quantidade_caixas', $quantidade_caixas, PDO::PARAM_INT);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        echo 'success';
    } else {
        echo 'error';
    }
}
?>