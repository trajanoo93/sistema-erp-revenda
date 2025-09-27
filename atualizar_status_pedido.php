<?php
session_start();
require_once 'backend/config/db.php';
ini_set('log_errors', 1);
ini_set('error_log', '/home/u991329655/domains/aogosto.com.br/public_html/php-error.log');
error_reporting(E_ALL);

// Arquivo de log para pedidos
$logFile = 'logs/pedidos_log.txt';

// Função para registrar no log (com níveis)
function registrarLog($mensagem, $nivel = 'INFO') {
    global $logFile;
    $dataHora = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $usuarioId = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 'unknown';
    $logEntry = "[$dataHora] [$nivel] [IP: $ip] [Usuário ID: $usuarioId] $mensagem\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    if ($nivel === 'ERROR') {
        error_log($logEntry);
    }
}

header('Content-Type: application/json');

// Lista de status permitidos (alinhada com pedidos.status e statusOrder)
$statusPermitidos = [
    'pendente', 'Em Separação', 'Pedido Separado', 'Aguardando Cliente',
    'Aguardando Pagamento', 'Aguardando Retirada', 'Pagamento na Retirada', 'Concluído'
];

// Checar método HTTP
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Recebe os dados via GET
    if (!isset($_GET['id']) || !isset($_GET['status'])) {
        registrarLog("Erro: Dados incompletos na requisição GET. ID: {$_GET['id'] ?? 'N/A'}, Status: {$_GET['status'] ?? 'N/A'}", 'ERROR');
        echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
        exit;
    }

    $pedido_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    $novo_status = trim($_GET['status']);

    // Validar status
    if (!in_array($novo_status, $statusPermitidos)) {
        registrarLog("Erro: Status inválido na requisição GET. Status: $novo_status, Pedido ID: $pedido_id", 'ERROR');
        echo json_encode(['success' => false, 'message' => 'Status inválido.']);
        exit;
    }

    try {
        $conn->beginTransaction();
        registrarLog("Iniciando atualização de status via GET. Pedido ID: $pedido_id, Novo Status: $novo_status", 'INFO');

        // Verificar se o pedido existe
        $stmtCheck = $conn->prepare("SELECT id FROM pedidos WHERE id = :id");
        $stmtCheck->bindParam(':id', $pedido_id, PDO::PARAM_INT);
        $stmtCheck->execute();
        if (!$stmtCheck->fetch()) {
            registrarLog("Erro: Pedido não encontrado. ID: $pedido_id", 'ERROR');
            echo json_encode(['success' => false, 'message' => 'Pedido não encontrado.']);
            $conn->rollBack();
            exit;
        }

        // Atualizar o status do pedido
        $stmt = $conn->prepare("UPDATE pedidos SET status = :status WHERE id = :id");
        $stmt->bindParam(':status', $novo_status);
        $stmt->bindParam(':id', $pedido_id, PDO::PARAM_INT);
        $stmt->execute();

        // Registrar alteração no log do banco
        $usuario_id = $_SESSION['usuario_id'] ?? 0;
        $data_alteracao = date('Y-m-d H:i:s');
        $stmtLog = $conn->prepare("
            INSERT INTO log_alteracoes_pedidos (pedido_id, usuario_id, data_alteracao, descricao)
            VALUES (:pedido_id, :usuario_id, :data_alteracao, :descricao)
        ");
        $descricao = "Status do pedido alterado para '$novo_status' pelo usuário ID $usuario_id.";
        $stmtLog->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
        $stmtLog->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmtLog->bindParam(':data_alteracao', $data_alteracao);
        $stmtLog->bindParam(':descricao', $descricao);
        $stmtLog->execute();

        $conn->commit();
        registrarLog("Status atualizado com sucesso via GET. Pedido ID: $pedido_id, Novo Status: $novo_status", 'INFO');
        echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso.']);
        exit;
    } catch (Exception $e) {
        $conn->rollBack();
        $errorMsg = "Erro ao atualizar status via GET. Pedido ID: $pedido_id, Mensagem: " . $e->getMessage() . ", SQLSTATE: " . ($e instanceof PDOException ? $e->getCode() : 'N/A');
        registrarLog($errorMsg, 'ERROR');
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar o status do pedido: ' . $e->getMessage()]);
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recebe os dados via POST
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['id']) || !isset($data['status']) || !isset($data['status_pagamento'])) {
        registrarLog("Erro: Dados incompletos na requisição POST. Dados: " . json_encode($data), 'ERROR');
        echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
        exit;
    }

    $pedido_id = filter_var($data['id'], FILTER_VALIDATE_INT);
    $novo_status = trim($data['status']);
    $status_pagamento = trim($data['status_pagamento']);

    // Validar status e status_pagamento
    if (!in_array($novo_status, $statusPermitidos)) {
        registrarLog("Erro: Status inválido na requisição POST. Status: $novo_status, Pedido ID: $pedido_id", 'ERROR');
        echo json_encode(['success' => false, 'message' => 'Status inválido.']);
        exit;
    }
    if (!in_array($status_pagamento, ['Sim', 'Não'])) {
        registrarLog("Erro: Status de pagamento inválido. Status Pagamento: $status_pagamento, Pedido ID: $pedido_id", 'ERROR');
        echo json_encode(['success' => false, 'message' => 'Status de pagamento inválido.']);
        exit;
    }

    try {
        $conn->beginTransaction();
        registrarLog("Iniciando atualização de status via POST. Pedido ID: $pedido_id, Novo Status: $novo_status, Status Pagamento: $status_pagamento", 'INFO');

        // Verificar se o pedido existe
        $stmtCheck = $conn->prepare("SELECT id FROM pedidos WHERE id = :id");
        $stmtCheck->bindParam(':id', $pedido_id, PDO::PARAM_INT);
        $stmtCheck->execute();
        if (!$stmtCheck->fetch()) {
            registrarLog("Erro: Pedido não encontrado. ID: $pedido_id", 'ERROR');
            echo json_encode(['success' => false, 'message' => 'Pedido não encontrado.']);
            $conn->rollBack();
            exit;
        }

        // Atualizar status e status_pagamento
        $stmt = $conn->prepare("
            UPDATE pedidos
            SET status = :status, status_pagamento = :status_pagamento
            WHERE id = :id
        ");
        $stmt->bindParam(':status', $novo_status);
        $stmt->bindParam(':status_pagamento', $status_pagamento);
        $stmt->bindParam(':id', $pedido_id, PDO::PARAM_INT);
        $stmt->execute();

        // Registrar alteração no log do banco
        $usuario_id = $_SESSION['usuario_id'] ?? 0;
        $data_alteracao = date('Y-m-d H:i:s');
        $stmtLog = $conn->prepare("
            INSERT INTO log_alteracoes_pedidos (pedido_id, usuario_id, data_alteracao, descricao)
            VALUES (:pedido_id, :usuario_id, :data_alteracao, :descricao)
        ");
        $descricao = "Status do pedido alterado para '$novo_status' e status_pagamento para '$status_pagamento' pelo usuário ID $usuario_id.";
        $stmtLog->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
        $stmtLog->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmtLog->bindParam(':data_alteracao', $data_alteracao);
        $stmtLog->bindParam(':descricao', $descricao);
        $stmtLog->execute();

        $conn->commit();
        registrarLog("Status atualizado com sucesso via POST. Pedido ID: $pedido_id, Novo Status: $novo_status, Status Pagamento: $status_pagamento", 'INFO');
        echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso.']);
        exit;
    } catch (Exception $e) {
        $conn->rollBack();
        $errorMsg = "Erro ao atualizar status via POST. Pedido ID: $pedido_id, Mensagem: " . $e->getMessage() . ", SQLSTATE: " . ($e instanceof PDOException ? $e->getCode() : 'N/A');
        registrarLog($errorMsg, 'ERROR');
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar o status do pedido: ' . $e->getMessage()]);
        exit;
    }
} else {
    registrarLog("Erro: Método HTTP não permitido. Método: {$_SERVER['REQUEST_METHOD']}", 'ERROR');
    echo json_encode(['success' => false, 'message' => 'Método HTTP não permitido.']);
    exit;
}
?>