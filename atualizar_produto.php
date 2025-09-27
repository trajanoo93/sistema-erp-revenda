<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'backend/config/db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$id = $_POST['id'];

$stmt = $conn->prepare("SELECT * FROM produtos WHERE id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$produto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$produto) {
    echo json_encode(['status' => 'error', 'message' => 'Produto não encontrado!']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$stmtUsuario = $conn->prepare("SELECT nome FROM usuarios WHERE id = :usuario_id");
$stmtUsuario->bindParam(':usuario_id', $usuario_id);
$stmtUsuario->execute();
$usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = $_POST['nome'];
    $valor = $_POST['valor'];
    $quantidade_estoque = $_POST['quantidade_estoque'];
    $tipo = $_POST['tipo'];
    $observacao = $_POST['observacao'];

    $alteracoes = [];
    if ($produto['nome'] != $nome) {
        $alteracoes[] = "Nome alterado de '{$produto['nome']}' para '$nome'";
    }
    if ($produto['valor'] != $valor) {
        $alteracoes[] = "Valor alterado de R$ " . number_format($produto['valor'], 2, ',', '.') . " para R$ " . number_format($valor, 2, ',', '.');
    }
    if ($produto['quantidade_estoque'] != $quantidade_estoque) {
        $alteracoes[] = "Quantidade em estoque alterada de {$produto['quantidade_estoque']} para $quantidade_estoque";
    }
    if ($produto['tipo'] != $tipo) {
        $alteracoes[] = "Tipo alterado de '{$produto['tipo']}' para '$tipo'";
    }

    $stmt = $conn->prepare("UPDATE produtos SET nome = :nome, valor = :valor, quantidade_estoque = :quantidade_estoque, tipo = :tipo WHERE id = :id");
    $stmt->bindParam(':nome', $nome);
    $stmt->bindParam(':valor', $valor);
    $stmt->bindParam(':quantidade_estoque', $quantidade_estoque);
    $stmt->bindParam(':tipo', $tipo);
    $stmt->bindParam(':id', $id);

    if ($stmt->execute()) {
        if (!empty($alteracoes)) {
            foreach ($alteracoes as $alteracao) {
                $acao = "Alterou o produto '{$produto['nome']}': {$alteracao}. Motivo: {$observacao}";

                $stmtLog = $conn->prepare("INSERT INTO logs (usuario_id, acao) VALUES (:usuario_id, :acao)");
                $stmtLog->bindParam(':usuario_id', $usuario_id);
                $stmtLog->bindParam(':acao', $acao);

                if (!$stmtLog->execute()) {
                    echo json_encode(['status' => 'error', 'message' => 'Erro ao inserir log.']);
                    exit;
                }
            }
        }
        echo json_encode(['status' => 'success', 'message' => 'Produto atualizado com sucesso!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao atualizar o produto.']);
    }
    exit;
}
?>