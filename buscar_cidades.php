<?php
require_once 'backend/config/db.php';

if (isset($_GET['term'])) {
    $term = $_GET['term'] . '%';

    // Buscar cidades que começam com o termo inserido
    $stmt = $conn->prepare("SELECT nome FROM cidades_mg WHERE nome LIKE :term LIMIT 10");
    $stmt->bindParam(':term', $term);
    $stmt->execute();
    $cidades = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Retorna o resultado como JSON
    echo json_encode($cidades);
}