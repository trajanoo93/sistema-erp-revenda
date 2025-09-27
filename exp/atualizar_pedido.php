<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../backend/config/db.php';
require '../vendor/autoload.php';

use Google\Auth\ApplicationDefaultCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

// Função para gravar logs em um arquivo personalizado
function writeLog($message) {
    $logFile = __DIR__ . '/../api/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] atualizar_pedido.php - $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Lê os dados brutos da requisição
$rawInput = file_get_contents('php://input');
writeLog("Dados brutos recebidos: $rawInput");

// Decodifica os dados JSON
$inputData = json_decode($rawInput, true);

// Verifica erros de decodificação do JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    writeLog("Erro ao decodificar JSON: " . json_last_error_msg());
    echo json_encode(['success' => false, 'error' => 'Erro ao decodificar JSON: ' . json_last_error_msg()]);
    exit;
}

// Log dos dados decodificados
writeLog("Dados decodificados: " . print_r($inputData, true));

// Captura os dados necessários
$id = $inputData['id'] ?? null;
$status = $inputData['status'] ?? null;
$quantidadeCaixas = $inputData['quantidade_caixas'] ?? null;
$observacoes = $inputData['observacoes'] ?? null;
$itensQuantidades = $inputData['itens_quantidades'] ?? null;

// Validação de campos obrigatórios
if (empty($id) || empty($status)) {
    writeLog("Erro: ID ou status ausente");
    echo json_encode(['success' => false, 'error' => 'Dados incompletos. ID ou status ausente.']);
    exit;
}

// Validação de itens_quantidades (opcional, apenas se fornecido)
if (!empty($itensQuantidades) && is_array($itensQuantidades)) {
    foreach ($itensQuantidades as $item) {
        if (empty($item['produto_id']) || !isset($item['quantidade'])) {
            writeLog("Erro: Um dos itens está faltando produto_id ou quantidade.");
            echo json_encode(['success' => false, 'error' => 'Um ou mais itens estão incompletos.']);
            exit;
        }
    }
}

// Inicia a transação
try {
    $conn->beginTransaction();

    // Lista de status onde o valor_total deve ser recalculado com base em quantidade_separada
    $statusRecalcular = ['Em Separação', 'Pedido Separado', 'Aguardando Cliente', 'Aguardando Retirada', 'Pagamento na Retirada', 'Concluído'];

    // Recalcular o valor total com base em quantidade_separada se o status estiver na lista
    $valorTotal = null;
    if (in_array($status, $statusRecalcular)) {
        // Buscar os itens do pedido com quantidade_separada e valor_unitario
        $stmtItens = $conn->prepare("
            SELECT i.quantidade_separada, i.valor_unitario
            FROM itens_pedido i
            WHERE i.pedido_id = :pedido_id
        ");
        $stmtItens->bindParam(':pedido_id', $id, PDO::PARAM_INT);
        $stmtItens->execute();
        $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

        // Calcular o novo valor total
        $valorTotal = 0;
        foreach ($itens as $item) {
            // Se quantidade_separada for null, usar 0
            $quantidadeSeparada = floatval($item['quantidade_separada'] ?? 0);
            $valorUnitario = floatval($item['valor_unitario']);
            $valorTotal += $quantidadeSeparada * $valorUnitario;
        }
        writeLog("Novo valor_total calculado para Pedido ID $id: $valorTotal");
    }

    // Atualiza o pedido
    $sql = "
        UPDATE pedidos 
        SET status = :status, quantidade_caixas = :quantidade_caixas, observacoes = :observacoes
    ";
    if ($valorTotal !== null) {
        $sql .= ", valor_total = :valor_total";
    }
    $sql .= " WHERE id = :id";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':quantidade_caixas', $quantidadeCaixas);
    $stmt->bindParam(':observacoes', $observacoes);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    if ($valorTotal !== null) {
        $stmt->bindParam(':valor_total', $valorTotal);
    }

    if ($stmt->execute()) {
        writeLog("Pedido atualizado com sucesso no banco de dados. Pedido ID: $id, Novo Status: $status");

        // Atualiza os itens somente se itens_quantidades for fornecido
        if (!empty($itensQuantidades) && is_array($itensQuantidades) && ($status === 'Concluído' || $status === 'Pedido Separado')) {
            foreach ($itensQuantidades as $item) {
                $produto_id = $item['produto_id'];
                $quantidade = $item['quantidade'];
                writeLog("Quantidade separada recebida: " . print_r($item, true));

                $stmtItem = $conn->prepare("
                    UPDATE itens_pedido 
                    SET quantidade_separada = :quantidade_separada
                    WHERE pedido_id = :pedido_id AND produto_id = :produto_id
                ");
                $stmtItem->bindParam(':quantidade_separada', $quantidade);
                $stmtItem->bindParam(':pedido_id', $id);
                $stmtItem->bindParam(':produto_id', $produto_id);

                if (!$stmtItem->execute()) {
                    writeLog("Erro ao atualizar quantidade_separada para produto_id $produto_id no pedido_id $id.");
                    throw new Exception("Erro ao atualizar itens_pedido.");
                }
            }
        }

        $conn->commit();

        // Envia notificações push
        $tokens = buscarTodosTokens($conn);
        $title = "Pedido #$id Atualizado!";
        $body = "Atualizado para: $status.";

        $sucesso = true;
        foreach ($tokens as $token) {
            if (!enviarNotificacaoFCM($token['fcm_token'], $title, $body)) {
                $sucesso = false;
                writeLog("Falha ao enviar notificação para o token: {$token['fcm_token']}");
            }
        }

        writeLog("Notificações enviadas. Sucesso: " . ($sucesso ? 'true' : 'false'));
        echo json_encode([
            'success' => $sucesso,
            'mensagem' => $sucesso ? 'Notificações enviadas com sucesso' : 'Falha ao enviar algumas notificações'
        ]);
        exit;
    } else {
        $conn->rollBack();
        writeLog("Erro ao atualizar o pedido no banco de dados. Pedido ID: $id");
        echo json_encode(['success' => false, 'error' => 'Erro ao atualizar o pedido.']);
        exit;
    }
} catch (Exception $e) {
    $conn->rollBack();
    writeLog("Erro na transação: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro: ' . $e->getMessage()]);
    exit;
}

// Função para buscar todos os tokens FCM do banco de dados
function buscarTodosTokens($conn) {
    $stmt = $conn->prepare("SELECT fcm_token FROM fcm_tokens");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Função para enviar notificações FCM
function enviarNotificacaoFCM($token, $title, $body) {
    $serviceAccountFile = __DIR__ . '/ao-gosto-app-2-27a6c6ace1db.json';
    putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $serviceAccountFile);

    $middleware = ApplicationDefaultCredentials::getMiddleware([
        'https://www.googleapis.com/auth/firebase.messaging',
    ]);
    $stack = HandlerStack::create();
    $stack->push($middleware);

    $client = new Client([
        'handler' => $stack,
        'base_uri' => 'https://fcm.googleapis.com',
        'auth' => 'google_auth',
    ]);

    $notification = [
        'message' => [
            'token' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body
            ]
        ]
    ];

    try {
        $response = $client->post('/v1/projects/ao-gosto-app-2/messages:send', [
            'json' => $notification,
        ]);
        writeLog("Notificação FCM enviada com sucesso para token: $token");
        return $response->getStatusCode() === 200;
    } catch (Exception $e) {
        writeLog("Erro ao enviar notificação FCM para token $token: " . $e->getMessage());
        return false;
    }
}
?>