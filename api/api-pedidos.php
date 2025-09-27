<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../backend/config/db.php';

// Função para gravar logs em um arquivo personalizado
function writeLog($message) {
    $logFile = __DIR__ . '/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getPedido($_GET['id']);
        } else {
            getPedidos();
        }
        break;

    case 'POST':
        createPedido();
        break;

    case 'PUT':
        $data = json_decode(file_get_contents("php://input"), true);

        if (isset($data['quantidade_caixas'])) {
            updateQuantidadeCaixas();
        } elseif (isset($_GET['item']) && $_GET['item'] == 1) {
            updateItemPedido();
        } else {
            updatePedidoCompleto();
        }
        break;

    case 'DELETE':
        if (isset($_GET['id'])) {
            deletePedido($_GET['id']);
        } else {
            echo json_encode(['message' => 'ID do pedido não fornecido.']);
        }
        break;

    default:
        echo json_encode(['message' => 'Método não suportado.']);
        break;
}

function getPedidos() {
    global $conn;
    
    $stmt = $conn->query("SELECT pedidos.*, 
                                 clientes.nome AS cliente_nome, 
                                 clientes.cidade AS cliente_cidade, 
                                 clientes.telefone AS cliente_telefone, 
                                 clientes.documento AS cliente_documento 
                          FROM pedidos 
                          LEFT JOIN clientes ON pedidos.cliente_id = clientes.id");
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($pedidos as &$pedido) {
        $pedido_id = $pedido['id'];
        
        $stmt_items = $conn->prepare("SELECT itens_pedido.*, 
                                             produtos.nome AS produto_nome, 
                                             produtos.tipo AS tipo 
                                      FROM itens_pedido 
                                      LEFT JOIN produtos ON itens_pedido.produto_id = produtos.id 
                                      WHERE itens_pedido.pedido_id = :pedido_id");
        $stmt_items->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
        $stmt_items->execute();
        $pedido['itens'] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode($pedidos);
}

function getPedido($id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT pedidos.*, 
                                   clientes.nome AS cliente_nome, 
                                   clientes.cidade AS cliente_cidade, 
                                   clientes.telefone AS cliente_telefone, 
                                   clientes.documento AS cliente_documento 
                            FROM pedidos 
                            LEFT JOIN clientes ON pedidos.cliente_id = clientes.id 
                            WHERE pedidos.id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pedido) {
        $stmt_items = $conn->prepare("SELECT itens_pedido.*, 
                                             produtos.nome AS produto_nome, 
                                             produtos.tipo AS tipo 
                                      FROM itens_pedido 
                                      LEFT JOIN produtos ON itens_pedido.produto_id = produtos.id 
                                      WHERE itens_pedido.pedido_id = :pedido_id");
        $stmt_items->bindParam(':pedido_id', $id, PDO::PARAM_INT);
        $stmt_items->execute();
        $pedido['itens'] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($pedido);
    } else {
        echo json_encode(['message' => 'Pedido não encontrado.']);
    }
}

function createPedido() {
    global $conn;

    $data = json_decode(file_get_contents("php://input"), true);

    if (!empty($data['cliente_id']) && !empty($data['data_pedido'])) {
        $stmt = $conn->prepare("INSERT INTO pedidos (cliente_id, data_pedido, valor_total) VALUES (:cliente_id, :data_pedido, :valor_total)");
        $stmt->bindParam(':cliente_id', $data['cliente_id']);
        $stmt->bindParam(':data_pedido', $data['data_pedido']);
        $stmt->bindParam(':valor_total', $data['valor_total']);

        if ($stmt->execute()) {
            echo json_encode(['message' => 'Pedido criado com sucesso.']);
        } else {
            echo json_encode(['message' => 'Erro ao criar o pedido.']);
        }
    } else {
        echo json_encode(['message' => 'Dados incompletos.']);
    }
}

function updateQuantidadeCaixas() {
    global $conn;

    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!empty($data['id']) && isset($data['quantidade_caixas'])) {
        $stmt = $conn->prepare("UPDATE pedidos 
                                SET quantidade_caixas = :quantidade_caixas 
                                WHERE id = :id");
        $stmt->bindParam(':id', $data['id']);
        $stmt->bindParam(':quantidade_caixas', $data['quantidade_caixas'], PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            echo json_encode(['message' => 'Quantidade de caixas atualizada com sucesso.']);
        } else {
            echo json_encode(['message' => 'Erro ao atualizar a quantidade de caixas.']);
        }
    } else {
        echo json_encode(['message' => 'Dados incompletos.']);
    }
}

function updateItemPedido() {
    global $conn;

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id'], $data['quantidade_separada'])) {
        echo json_encode(['message' => 'Parâmetros incompletos.']);
        return;
    }

    // Converter o valor para float
    $qtdSeparada = floatval($data['quantidade_separada']);
    // Garantir que o valor tenha 3 casas decimais
    $qtdSeparadaFormatted = number_format($qtdSeparada, 3, '.', '');

    // Adicionar logs detalhados
    writeLog("Valor recebido para quantidade_separada: " . json_encode($data['quantidade_separada']));
    writeLog("Valor convertido para float: $qtdSeparada");
    writeLog("Valor formatado: $qtdSeparadaFormatted");

    $stmt = $conn->prepare("UPDATE itens_pedido 
                            SET quantidade_separada = :quantidade_separada 
                            WHERE id = :id");
    $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);
    $stmt->bindParam(':quantidade_separada', $qtdSeparadaFormatted);

    if ($stmt->execute()) {
        writeLog("Atualização bem-sucedida para item ID {$data['id']}");
        echo json_encode(['message' => 'Item atualizado com sucesso.', 'id' => $data['id']]);
    } else {
        $errorInfo = $stmt->errorInfo();
        writeLog("Erro ao atualizar o item ID {$data['id']}: " . json_encode($errorInfo));
        echo json_encode(['message' => 'Erro ao atualizar o item.', 'error' => $errorInfo]);
    }
}

function updatePedidoCompleto() {
    global $conn;

    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!empty($data['id']) && !empty($data['cliente_id']) && !empty($data['data_pedido'])) {
        $stmt = $conn->prepare("UPDATE pedidos 
                                SET cliente_id = :cliente_id, 
                                    data_pedido = :data_pedido, 
                                    valor_total = :valor_total 
                                WHERE id = :id");
        $stmt->bindParam(':id', $data['id']);
        $stmt->bindParam(':cliente_id', $data['cliente_id']);
        $stmt->bindParam(':data_pedido', $data['data_pedido']);
        $stmt->bindParam(':valor_total', $data['valor_total']);
        
        if ($stmt->execute()) {
            echo json_encode(['message' => 'Pedido atualizado com sucesso.']);
        } else {
            echo json_encode(['message' => 'Erro ao atualizar o pedido.']);
        }
    } else {
        echo json_encode(['message' => 'Dados incompletos.']);
    }
}

function deletePedido($id) {
    global $conn;

    $stmt = $conn->prepare("DELETE FROM pedidos WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        echo json_encode(['message' => 'Pedido excluído com sucesso.']);
    } else {
        echo json_encode(['message' => 'Erro ao excluir o pedido.']);
    }
}