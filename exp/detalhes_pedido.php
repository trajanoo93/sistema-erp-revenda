<?php
session_start();
require_once '../backend/config/db.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Arquivo de log
$logFile = 'logs/pedidos_log.txt';
function registrarLog($mensagem, $nivel = 'INFO') {
    global $logFile;
    $dataHora = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $usuarioId = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 'unknown';
    $logEntry = "[$dataHora] [$nivel] [IP: $ip] [Usuário ID: $usuarioId] $mensagem\n";
    
    // Criar diretório logs/ se não existir
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Garantir permissões de escrita
    if (!file_exists($logFile)) {
        touch($logFile);
        chmod($logFile, 0664);
    }
    
    if (is_writable($logFile)) {
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        if ($nivel === 'ERROR') {
            error_log($logEntry);
        }
    } else {
        error_log("Não foi possível escrever no arquivo de log: $logFile");
    }
}

header('Content-Type: application/json');
if (!isset($_GET['id'])) {
    registrarLog("Erro: ID do pedido não fornecido em exp/detalhes_pedido.php", 'ERROR');
    echo json_encode(['error' => 'ID do pedido não fornecido.']);
    exit;
}

$id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
registrarLog("Carregando detalhes do pedido ID: $id em exp/detalhes_pedido.php", 'INFO');

try {
    // Buscar detalhes do pedido
    $stmt = $conn->prepare("
        SELECT p.id, p.status, p.quantidade_caixas, c.nome AS cliente_nome, c.cidade AS cliente_cidade
        FROM pedidos p
        JOIN clientes c ON p.cliente_id = c.id
        WHERE p.id = :id
    ");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        registrarLog("Erro: Pedido não encontrado. ID: $id", 'ERROR');
        echo json_encode(['error' => 'Pedido não encontrado.']);
        exit;
    }

    // Buscar itens do pedido
    $stmtItens = $conn->prepare("
        SELECT ip.produto_id, ip.quantidade, ip.quantidade_separada, ip.valor_unitario, pr.nome AS produto_nome, pr.tipo AS produto_tipo
        FROM itens_pedido ip
        JOIN produtos pr ON ip.produto_id = pr.id
        WHERE ip.pedido_id = :id
    ");
    $stmtItens->bindParam(':id', $id, PDO::PARAM_INT);
    $stmtItens->execute();
    $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

    if (!$itens) {
        registrarLog("Erro: Itens do pedido não encontrados. ID: $id", 'ERROR');
        echo json_encode(['error' => 'Itens do pedido não encontrados.']);
        exit;
    }

    registrarLog("Detalhes do pedido carregados. ID: $id, Itens: " . count($itens), 'INFO');

    // Retornar JSON
    echo json_encode([
        'status' => 'success',
        'pedido' => [
            'id' => htmlspecialchars($pedido['id']),
            'cliente_nome' => htmlspecialchars($pedido['cliente_nome']),
            'cliente_cidade' => htmlspecialchars($pedido['cliente_cidade']),
            'status' => htmlspecialchars($pedido['status']),
            'quantidade_caixas' => htmlspecialchars($pedido['quantidade_caixas'] ?? '0')
        ],
        'itens' => array_map(function($item) {
            return [
                'produto_id' => htmlspecialchars($item['produto_id']),
                'produto_nome' => htmlspecialchars($item['produto_nome']),
                'quantidade' => $item['produto_tipo'] === 'UND' ? number_format($item['quantidade'], 0, ',', '.') : number_format($item['quantidade'], 3, ',', '.'),
                'quantidade_separada' => $item['quantidade_separada'] !== null ? ($item['produto_tipo'] === 'UND' ? number_format($item['quantidade_separada'], 0, ',', '.') : number_format($item['quantidade_separada'], 3, ',', '.')) : null,
                'valor_unitario' => number_format($item['valor_unitario'], 2, '.', '')
            ];
        }, $itens)
    ]);
} catch (PDOException $e) {
    registrarLog("Erro ao carregar detalhes do pedido ID: $id. Mensagem: " . $e->getMessage(), 'ERROR');
    echo json_encode(['error' => 'Erro ao carregar detalhes do pedido: ' . $e->getMessage()]);
}
exit;
?>