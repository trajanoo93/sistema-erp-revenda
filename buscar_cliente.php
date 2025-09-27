<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclua a configuração do banco de dados
require_once 'backend/config/db.php';

// Verifique se a consulta foi enviada
if (isset($_GET['query'])) {
    $query = $_GET['query'];

    // Verifique se a variável $conn foi definida corretamente
    if ($conn) {
        // Prepare a consulta SQL para buscar clientes únicos
        $stmt = $conn->prepare("
            SELECT DISTINCT id, nome, cidade 
            FROM clientes 
            WHERE nome LIKE :nome OR cidade LIKE :nome 
            ORDER BY nome 
            LIMIT 10
        ");
        $stmt->bindValue(':nome', "%$query%");
        $stmt->execute();

        // Buscar todos os resultados da consulta
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Retornar os resultados como JSON com charset UTF-8
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($clientes, JSON_UNESCAPED_UNICODE);
    } else {
        // Se não houver conexão com o banco de dados, retorne um erro
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Erro na conexão com o banco de dados']);
    }
} else {
    // Se não houver uma consulta, retorne uma mensagem de erro
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Nenhuma consulta fornecida']);
}
?>