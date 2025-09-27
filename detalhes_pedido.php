<?php
session_start();
require_once 'backend/config/db.php';
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
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    if ($nivel === 'ERROR') {
        error_log($logEntry);
    }
}

if (!isset($_GET['id'])) {
    registrarLog("Erro: ID do pedido não fornecido em detalhes_pedido.php", 'ERROR');
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'ID do pedido não fornecido.']);
    } else {
        echo '<p>ID do pedido não fornecido.</p>';
    }
    exit;
}

$pedido_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
registrarLog("Carregando detalhes do pedido ID: $pedido_id", 'INFO');

try {
    // Buscar informações do pedido
    $stmtPedido = $conn->prepare("
        SELECT p.id, c.nome AS cliente_nome, c.cidade AS cliente_cidade, p.data_retirada, p.forma_pagamento, 
               p.status_pagamento, p.valor_total, p.observacoes, p.status
        FROM pedidos p
        JOIN clientes c ON p.cliente_id = c.id
        WHERE p.id = :pedido_id
    ");
    $stmtPedido->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
    $stmtPedido->execute();
    $pedido = $stmtPedido->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        registrarLog("Erro: Pedido não encontrado. ID: $pedido_id", 'ERROR');
        if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Pedido não encontrado.']);
        } else {
            echo '<p>Pedido não encontrado.</p>';
        }
        exit;
    }

    // Buscar os produtos do pedido
    $stmtProdutos = $conn->prepare("
        SELECT ip.produto_id, ip.quantidade, ip.quantidade_separada, ip.valor_unitario, pr.nome AS produto_nome, pr.tipo AS produto_tipo
        FROM itens_pedido ip
        JOIN produtos pr ON ip.produto_id = pr.id
        WHERE ip.pedido_id = :pedido_id
    ");
    $stmtProdutos->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
    $stmtProdutos->execute();
    $produtos = $stmtProdutos->fetchAll(PDO::FETCH_ASSOC);

    registrarLog("Detalhes do pedido carregados. ID: $pedido_id, Itens: " . count($produtos), 'INFO');

    // Retornar JSON se ajax=1
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'pedido' => [
                'id' => $pedido['id'],
                'cliente_nome' => $pedido['cliente_nome'],
                'cliente_cidade' => $pedido['cliente_cidade'],
                'data_retirada' => date('d/m/Y', strtotime($pedido['data_retirada'])),
                'forma_pagamento' => $pedido['forma_pagamento'] ?? 'Não especificada',
                'status_pagamento' => $pedido['status_pagamento'],
                'valor_total' => number_format($pedido['valor_total'], 2, '.', ''),
                'observacoes' => $pedido['observacoes'] ?? '',
                'status' => $pedido['status']
            ],
            'itens' => array_map(function($item) {
                return [
                    'produto_id' => $item['produto_id'],
                    'produto_nome' => $item['produto_nome'],
                    'quantidade' => $item['produto_tipo'] === 'UND' ? number_format($item['quantidade'], 0, ',', '.') : number_format($item['quantidade'], 3, ',', '.'),
                    'quantidade_separada' => $item['quantidade_separada'] !== null ? ($item['produto_tipo'] === 'UND' ? number_format($item['quantidade_separada'], 0, ',', '.') : number_format($item['quantidade_separada'], 3, ',', '.')) : null,
                    'valor_unitario' => number_format($item['valor_unitario'], 2, '.', '')
                ];
            }, $produtos)
        ]);
        exit;
    }

    // Retornar HTML para renderização tradicional
    ?>
    <div class="pedido-detalhes">
        <h5>Detalhes do Pedido #<?= htmlspecialchars($pedido['id']) ?></h5>
        <p><strong>Cliente:</strong> <?= htmlspecialchars($pedido['cliente_nome']) ?></p>
        <p><strong>Cidade:</strong> <?= htmlspecialchars($pedido['cliente_cidade']) ?></p>
        <p><strong>Data de Retirada:</strong> <?= date('d/m/Y', strtotime($pedido['data_retirada'])) ?></p>
        <p><strong>Forma de Pagamento:</strong> <?= htmlspecialchars($pedido['forma_pagamento'] ?? 'Não especificada') ?></p>
        <p><strong>Pago:</strong> <?= htmlspecialchars($pedido['status_pagamento']) ?></p>
        <p><strong>Valor Total:</strong> R$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?></p>
        <p><strong>Observações:</strong> <?= nl2br(htmlspecialchars($pedido['observacoes'] ?? 'Nenhuma')) ?></p>
        <hr>
        <h6>Produtos:</h6>
        <ul>
            <?php foreach ($produtos as $produto): ?>
                <li>
                    <?= htmlspecialchars($produto['produto_nome']) ?> - 
                    Quantidade: <?= $produto['produto_tipo'] === 'UND' ? number_format($produto['quantidade'], 0, ',', '.') : number_format($produto['quantidade'], 3, ',', '.') ?>
                    <?php if ($produto['quantidade_separada'] !== null): ?>
                        (Disponível: <?= $produto['produto_tipo'] === 'UND' ? number_format($produto['quantidade_separada'], 0, ',', '.') : number_format($produto['quantidade_separada'], 3, ',', '.') ?>)
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary pedido-close" data-dismiss="modal">Fechar</button>
    </div>
    <?php
} catch (PDOException $e) {
    registrarLog("Erro ao carregar detalhes do pedido ID: $pedido_id. Mensagem: " . $e->getMessage(), 'ERROR');
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Erro ao carregar detalhes do pedido: ' . $e->getMessage()]);
    } else {
        echo '<p>Erro ao carregar detalhes do pedido.</p>';
    }
    exit;
}
?>