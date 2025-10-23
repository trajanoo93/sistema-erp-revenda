<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /atacado/login.php');
    exit;
}
require_once 'backend/config/db.php';
ini_set('log_errors', 1);
ini_set('error_log', '/home/u991329655/domains/aogosto.com.br/public_html/php-error.log');
error_reporting(E_ALL);

// Arquivo de log para pedidos
$logFile = 'logs/pedidos_log.txt';

// Função para registrar no log
function registrarLog($mensagem, $nivel = 'INFO') {
    global $logFile;
    $dataHora = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $usuarioId = $_SESSION['usuario_id'] ?? 'unknown';
    $logEntry = "[$dataHora] [$nivel] [IP: $ip] [Usuário ID: $usuarioId] $mensagem\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    if ($nivel === 'ERROR') {
        error_log($logEntry);
    }
}

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    registrarLog("Requisição inválida para criar pedido. Método: {$_SERVER['REQUEST_METHOD']}", 'ERROR');
    header('HTTP/1.1 405 Method Not Allowed');
    header('Allow: POST');
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido. Utilize o método POST.']);
    exit;
}

ob_start();
registrarLog("Requisição POST recebida em criar_pedido.php. Usuário ID: {$_SESSION['usuario_id']}. Dados brutos: " . json_encode($_POST));

function validateInput($data) {
    return trim(htmlspecialchars($data));
}

$cliente_id = isset($_POST['cliente']) ? validateInput($_POST['cliente']) : null;
$data_retirada = isset($_POST['data_retirada']) ? validateInput($_POST['data_retirada']) : null;
$observacoes = isset($_POST['observacoes']) ? validateInput($_POST['observacoes']) : '';
$produtos = isset($_POST['produto_id']) && is_array($_POST['produto_id']) ? array_map('validateInput', $_POST['produto_id']) : [];
$quantidades = isset($_POST['quantidade']) && is_array($_POST['quantidade']) ? array_map('validateInput', $_POST['quantidade']) : [];

registrarLog("Campos recebidos: cliente_id=" . ($cliente_id ?: 'vazio') . ", data_retirada=" . ($data_retirada ?: 'vazio') . ", produtos=" . json_encode($produtos) . ", quantidades=" . json_encode($quantidades));

if (empty($cliente_id) || empty($data_retirada) || empty($produtos) || empty($quantidades)) {
    registrarLog("Erro: Campos obrigatórios não preenchidos. cliente_id=" . ($cliente_id ?: 'vazio') . ", data_retirada=" . ($data_retirada ?: 'vazio') . ", produtos=" . (empty($produtos) ? 'vazio' : count($produtos)) . ", quantidades=" . (empty($quantidades) ? 'vazio' : count($quantidades)), 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Por favor, preencha todos os campos obrigatórios (cliente, data de retirada e produtos).']);
    ob_end_flush();
    exit;
}

if (count($produtos) !== count($quantidades)) {
    registrarLog("Erro: Inconsistência nos dados de produtos. Número de produtos (" . count($produtos) . ") e quantidades (" . count($quantidades) . ") não coincidem.", 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Dados dos produtos estão inconsistentes: número de produtos e quantidades não coincidem.']);
    ob_end_flush();
    exit;
}

if ($data_retirada) {
    $dataObj = DateTime::createFromFormat('d/m/Y', $data_retirada, new DateTimeZone('America/Sao_Paulo'));
    if ($dataObj === false) {
        registrarLog("Erro: Formato de data inválido. Data: $data_retirada.", 'ERROR');
        echo json_encode(['status' => 'error', 'message' => 'Formato de data inválido. Use o formato DD/MM/YYYY.']);
        ob_end_flush();
        exit;
    }
    $data_retirada = $dataObj->format('Y-m-d');
    $dataAtual = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
    if ($dataObj < $dataAtual->setTime(0, 0)) {
        registrarLog("Erro: Data de retirada anterior à atual. Data: $data_retirada.", 'ERROR');
        echo json_encode(['status' => 'error', 'message' => 'A data de retirada não pode ser anterior à data atual.']);
        ob_end_flush();
        exit;
    }
}

try {
    $conn->beginTransaction();
    registrarLog("Transação iniciada para criar pedido.");

    // Inserir pedido
    $stmtPedido = $conn->prepare("
        INSERT INTO pedidos (cliente_id, data_retirada, observacoes, data_pedido, status, status_pagamento)
        VALUES (:cliente_id, :data_retirada, :observacoes, NOW(), 'Novo Pedido', 'Não')
    ");
    $stmtPedido->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
    $stmtPedido->bindParam(':data_retirada', $data_retirada);
    $stmtPedido->bindParam(':observacoes', $observacoes);
    if (!$stmtPedido->execute()) {
        throw new Exception('Erro ao inserir pedido no banco.');
    }

    $pedido_id = $conn->lastInsertId();
    $valor_total = 0;
    registrarLog("Pedido criado. ID: $pedido_id, Cliente ID: $cliente_id, Data Retirada: $data_retirada");

    $stmtProduto = $conn->prepare("SELECT valor, tipo FROM produtos WHERE id = :produto_id");
    $stmtProdutoPedido = $conn->prepare("
        INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, valor_unitario)
        VALUES (:pedido_id, :produto_id, :quantidade, :valor_unitario)
    ");

   foreach ($produtos as $index => $produto_id) {
    $quantidade = $quantidades[$index];
    registrarLog("Processando produto ID: $produto_id, Quantidade: $quantidade");

    $stmtProduto->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
    $stmtProduto->execute();
    $produto = $stmtProduto->fetch(PDO::FETCH_ASSOC);
    if ($produto) {
        $tipo = $produto['tipo'] ?: 'KG';
        $valor_unitario = floatval($produto['valor'] ?? 0);
        if ($valor_unitario <= 0) {
            throw new Exception('Produto ID ' . $produto_id . ' tem valor inválido.');
        }
        if ($tipo === "UND") {
            $quantidade_int = intval($quantidade);
            if ($quantidade_int <= 0) {
                registrarLog("Erro: Quantidade inválida para produto ID: $produto_id (UND). Quantidade: $quantidade. Pedido ID: $pedido_id", 'ERROR');
                echo json_encode(['status' => 'error', 'message' => 'Quantidade inválida para o produto ID: ' . $produto_id . '. Produtos do tipo UND devem ter quantidade inteira (ex.: 1, 2).']);
                $conn->rollBack();
                ob_end_flush();
                exit;
            }
            $quantidade = $quantidade_int;
        } else {
            if (!preg_match('/^\d+(?:\.\d{1,3})?$/', $quantidade) || floatval($quantidade) <= 0) {
                registrarLog("Erro: Quantidade inválida para produto ID: $produto_id (KG). Quantidade: $quantidade. Pedido ID: $pedido_id", 'ERROR');
                echo json_encode(['status' => 'error', 'message' => 'Quantidade inválida para o produto ID: ' . $produto_id . '. Produtos do tipo KG devem ser no formato X.YYY (ex.: 0.850).']);
                $conn->rollBack();
                ob_end_flush();
                exit;
            }
        }
        $valor_total += floatval($quantidade) * $valor_unitario;
        registrarLog("Adicionando produto ID: $produto_id ao Pedido ID: $pedido_id. Quantidade: $quantidade, Subtotal: " . (floatval($quantidade) * $valor_unitario));
        $stmtProdutoPedido->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
        $stmtProdutoPedido->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
        $stmtProdutoPedido->bindParam(':quantidade', $quantidade, PDO::PARAM_STR);
        $stmtProdutoPedido->bindParam(':valor_unitario', $valor_unitario);
        $stmtProdutoPedido->execute();
    } else {
        registrarLog("Erro: Produto não encontrado ID: $produto_id. Pedido ID: $pedido_id", 'ERROR');
        echo json_encode(['status' => 'error', 'message' => 'Produto não encontrado: ID ' . $produto_id]);
        $conn->rollBack();
        ob_end_flush();
        exit;
    }
}

    $stmtAtualizaTotal = $conn->prepare("UPDATE pedidos SET valor_total = :valor_total WHERE id = :pedido_id");
    $stmtAtualizaTotal->bindParam(':valor_total', $valor_total);
    $stmtAtualizaTotal->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
    $stmtAtualizaTotal->execute();
    registrarLog("Valor total atualizado para Pedido ID: $pedido_id. Valor: $valor_total");

    $conn->commit();
    registrarLog("Fim da criação de pedido. Sucesso. Pedido ID: $pedido_id");
    ob_end_clean();
    echo json_encode(['status' => 'success', 'message' => 'Pedido criado com sucesso!', 'pedido_id' => $pedido_id]);
    exit;
} catch (Exception $e) {
    $conn->rollBack();
    $errorMsg = "Exceção ao criar pedido: " . $e->getMessage() . ". Trace: " . $e->getTraceAsString();
    registrarLog($errorMsg, 'ERROR');
    error_log($errorMsg);
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Erro ao processar o pedido: ' . $e->getMessage()]);
    exit;
}
?>