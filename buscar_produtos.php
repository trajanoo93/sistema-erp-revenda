<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'backend/config/db.php';

// Verifique se a consulta foi enviada
if (isset($_GET['query'])) {
    $query = $_GET['query'];

    if ($conn) {
        // Prepare a consulta SQL para buscar produtos únicos, incluindo o campo tipo
        $stmt = $conn->prepare("
            SELECT DISTINCT id, nome, valor, tipo 
            FROM produtos 
            WHERE nome LIKE :nome 
            ORDER BY nome 
            LIMIT 10
        ");
        $stmt->bindValue(':nome', "%$query%"); // Alterado para buscar em qualquer parte do nome
        $stmt->execute();

        $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Retornar a resposta em JSON
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($produtos, JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Erro na conexão com o banco de dados']);
    }
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Nenhuma consulta fornecida']);
}
?>