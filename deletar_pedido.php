<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['status' => 'error', 'message' => 'Usuário não autenticado.']);
    exit;
}

require_once 'backend/config/db.php';

ini_set('log_errors', 1);
ini_set('error_log', '/home/u991329655/domains/aogosto.com.br/public_html/php-error.log');
error_reporting(E_ALL);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    header('Allow: POST');
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido. Utilize o método POST.']);
    exit;
}

$pedido_id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : null;

if (!$pedido_id || $pedido_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID do pedido inválido.']);
    exit;
}

try {
    $conn->beginTransaction();

    // Verificar se o pedido existe
    $stmtCheck = $conn->prepare("SELECT id FROM pedidos WHERE id = :id");
    $stmtCheck->bindParam(':id', $pedido_id, PDO::PARAM_INT);
    $stmtCheck->execute();
    if (!$stmtCheck->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('Pedido não encontrado.');
    }

    // Registrar a exclusão no log ANTES de excluir o pedido
    $usuario_id = $_SESSION['usuario_id'];
    $data_alteracao = date('Y-m-d H:i:s');
    $stmtLog = $conn->prepare("
        INSERT INTO log_alteracoes_pedidos (pedido_id, usuario_id, data_alteracao, descricao)
        VALUES (:pedido_id, :usuario_id, :data_alteracao, :descricao)
    ");
    $descricao = "Pedido ID $pedido_id excluído pelo usuário ID $usuario_id.";
    $stmtLog->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
    $stmtLog->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmtLog->bindParam(':data_alteracao', $data_alteracao);
    $stmtLog->bindParam(':descricao', $descricao);
    $stmtLog->execute();

    // Excluir registros relacionados na tabela itens_pedido
    $stmtDeleteItens = $conn->prepare("DELETE FROM itens_pedido WHERE pedido_id = :pedido_id");
    $stmtDeleteItens->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
    $stmtDeleteItens->execute();

    // Excluir registros relacionados na tabela comprovantes
    $stmtDeleteComprovantes = $conn->prepare("DELETE FROM comprovantes WHERE pedido_id = :pedido_id");
    $stmtDeleteComprovantes->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
    $stmtDeleteComprovantes->execute();

    // Excluir registros relacionados na tabela log_alteracoes_pedidos
    $stmtDeleteLogs = $conn->prepare("DELETE FROM log_alteracoes_pedidos WHERE pedido_id = :pedido_id");
    $stmtDeleteLogs->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
    $stmtDeleteLogs->execute();

    // Excluir o pedido
    $stmtDeletePedido = $conn->prepare("DELETE FROM pedidos WHERE id = :id");
    $stmtDeletePedido->bindParam(':id', $pedido_id, PDO::PARAM_INT);
    $stmtDeletePedido->execute();

    $conn->commit();

    echo json_encode(['status' => 'success', 'message' => 'Pedido excluído com sucesso!']);
    exit;
} catch (Exception $e) {
    $conn->rollBack();
    error_log("Erro ao excluir pedido ID $pedido_id: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Erro ao excluir pedido: ' . $e->getMessage()]);
    exit;
}
?>