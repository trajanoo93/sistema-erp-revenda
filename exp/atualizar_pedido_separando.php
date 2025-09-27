<?php
require_once '../backend/config/db.php';
require '../vendor/autoload.php';

use Google\Auth\ApplicationDefaultCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

header('Content-Type: application/json');

// Configuração de log de erros
ini_set("log_errors", 1);
ini_set("error_log", __DIR__ . "/../php-error.log");

$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim(explode(';', $_SERVER["CONTENT_TYPE"])[0]) : '';  
$rawInput = file_get_contents("php://input");

error_log("Conteúdo bruto recebido: $rawInput");

// Decodifica JSON, dependendo do Content-Type
if ($contentType === "application/json") {
    $inputData = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Erro ao decodificar JSON: " . json_last_error_msg());
        echo json_encode(['error' => 'Erro ao decodificar JSON.']);
        exit;
    }
} else {
    $inputData = $_POST;
}

error_log("Array decodificado: " . print_r($inputData, true));

// Extração de dados
$id = $inputData['id'] ?? null;
$status = $inputData['status'] ?? null;
$quantidadeCaixas = $inputData['quantidade_caixas'] ?? null;
$observacoes = $inputData['observacoes'] ?? null;
$itensQuantidades = $inputData['itens_quantidades'] ?? null;

error_log("Dados processados - ID: $id, Status: $status, Quantidade Caixas: $quantidadeCaixas, Observações: $observacoes");

if (empty($id) || empty($status)) {
    echo json_encode([
        'error' => 'Dados incompletos. ID ou status ausente.',
        'received_data' => [
            'id' => $id,
            'status' => $status,
            'quantidade_caixas' => $quantidadeCaixas,
            'observacoes' => $observacoes,
            'itens_quantidades' => $itensQuantidades,
            'rawInput' => $rawInput,
            'inputData' => $inputData,
            'contentType' => $contentType,
        ]
    ]);
    exit;
}

try {
    $conn->beginTransaction();

    // Atualizar o pedido
    $stmt = $conn->prepare("
        UPDATE pedidos 
        SET status = :status, quantidade_caixas = :quantidade_caixas, observacoes = :observacoes
        WHERE id = :id
    ");
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':quantidade_caixas', $quantidadeCaixas);
    $stmt->bindParam(':observacoes', $observacoes);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        error_log("Pedido #$id atualizado com status '$status' no banco de dados.");

        if ($status == 'Pedido Separado' && isset($itensQuantidades) && is_array($itensQuantidades)) {
            salvarQuantidadesSeparadas($id, $conn, $itensQuantidades, $quantidadeCaixas);
        }

        $conn->commit();
        error_log("Transação para atualizar pedido #$id confirmada.");

        // Enviar notificação
        $tokens = buscarTodosTokens($conn);
        $title = "Pedido #$id Atualizado!";
        $body = "Atualizado para: $status.";

        $sucesso = true;
        $errosNotificacao = []; // Array para capturar erros de notificação
        foreach ($tokens as $token) {
            $resultadoEnvio = enviarNotificacaoFCM($token['fcm_token'], $title, $body);

            if ($resultadoEnvio) {
                error_log("Notificação enviada com sucesso para o token: " . $token['fcm_token']);
            } else {
                $sucesso = false;
                $errosNotificacao[] = "Falha ao enviar notificação para o token: " . $token['fcm_token'];
            }
        }

        // Construir uma única resposta JSON para evitar múltiplos json_encode
        $response = [
            'success' => $sucesso,
            'mensagem' => $sucesso ? 'Pedido atualizado com sucesso e notificações enviadas' : 'Falha ao enviar algumas notificações',
            'erros_notificacao' => $errosNotificacao
        ];
        echo json_encode($response); // Enviar uma única resposta
        exit;
    } else {
        error_log("Erro ao atualizar o pedido no banco de dados.");
        $conn->rollBack();
        echo json_encode(['error' => 'Erro ao atualizar o pedido.']);
        exit;
    }
} catch (Exception $e) {
    $conn->rollBack();
    error_log("Erro na transação: " . $e->getMessage());
    echo json_encode(['error' => 'Erro: ' . $e->getMessage()]);
    exit;
}

// Função para buscar todos os tokens FCM do banco de dados
function buscarTodosTokens($conn) {
    $stmt = $conn->prepare("SELECT fcm_token FROM fcm_tokens");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Função para enviar notificações FCM com logs detalhados
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
        error_log("Status da resposta do FCM para o token $token: " . $response->getStatusCode());
        error_log("Corpo da resposta do FCM para o token $token: " . $response->getBody()->getContents());
        return $response->getStatusCode() === 200;
    } catch (Exception $e) {
        error_log("Erro ao enviar notificação para o token $token: " . $e->getMessage());
        return false;
    }
}
?>