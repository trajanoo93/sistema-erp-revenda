<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
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
    $usuarioId = $_SESSION['usuario_id'] ?? 'unknown';
    $logEntry = "[$dataHora] [$nivel] [IP: $ip] [Usuário ID: $usuarioId] $mensagem\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    if ($nivel === 'ERROR') {
        error_log($logEntry);
    }
}

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    registrarLog("Requisição inválida para editar pedido. Método: {$_SERVER['REQUEST_METHOD']}", 'ERROR');
    header('HTTP/1.1 405 Method Not Allowed');
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Método não permitido. Utilize o método POST.']);
    exit;
}

ob_start();
registrarLog("Início da edição de pedido. Dados recebidos: " . json_encode($_POST));

function validateInput($data) {
    return trim(htmlspecialchars($data));
}

$pedido_id = isset($_POST['id']) ? validateInput($_POST['id']) : null;
$data_retirada = isset($_POST['data_retirada']) ? validateInput($_POST['data_retirada']) : null;
$observacoes = isset($_POST['observacoes']) ? validateInput($_POST['observacoes']) : '';
$produtos_json = isset($_POST['produtos']) ? $_POST['produtos'] : null;

if (empty($pedido_id) || empty($data_retirada)) {
    registrarLog("Erro: Dados obrigatórios não fornecidos. Pedido ID: $pedido_id", 'ERROR');
    echo json_encode(['success' => false, 'message' => 'Dados obrigatórios não fornecidos.']);
    ob_end_flush();
    exit;
}

// Buscar o status atual do pedido
$stmt = $conn->prepare("SELECT status FROM pedidos WHERE id = :id");
$stmt->bindParam(':id', $pedido_id, PDO::PARAM_INT);
$stmt->execute();
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$pedido) {
    registrarLog("Erro: Pedido não encontrado. ID: $pedido_id", 'ERROR');
    echo json_encode(['success' => false, 'message' => 'Pedido não encontrado.']);
    ob_end_flush();
    exit;
}
registrarLog("Status atual do pedido: {$pedido['status']}. Pedido ID: $pedido_id", 'INFO');

// Validar formato da data de retirada
$data_retirada = trim($data_retirada);
registrarLog("Valor bruto de data_retirada: '$data_retirada'. Comprimento: " . strlen($data_retirada) . ". Hex: " . bin2hex($data_retirada), 'INFO');

$dataRetiradaObj = DateTime::createFromFormat('d/m/Y', $data_retirada, new DateTimeZone('America/Sao_Paulo'));
if ($dataRetiradaObj === false) {
    registrarLog("Erro: Formato de data inválido. Data: $data_retirada. Pedido ID: $pedido_id", 'ERROR');
    echo json_encode(['success' => false, 'message' => 'Formato de data inválido. Use o formato DD/MM/YYYY.']);
    ob_end_flush();
    exit;
}

$data_retirada_formatted = $dataRetiradaObj->format('Y-m-d');
date_default_timezone_set('America/Sao_Paulo');
$dataAtual = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
registrarLog("Data atual: " . $dataAtual->format('Y-m-d H:i:s') . ". Data de retirada: " . $dataRetiradaObj->format('Y-m-d H:i:s') . ". Comparação (dataRetirada < dataAtual): " . ($dataRetiradaObj->format('Y-m-d') < $dataAtual->format('Y-m-d') ? 'true' : 'false'), 'INFO');

// Permitir datas passadas em status finais
if ($dataRetiradaObj->format('Y-m-d') < $dataAtual->format('Y-m-d')) {
    $allowedStatuses = ['Concluído', 'Aguardando Retirada', 'Pagamento na Retirada', 'Aguardando Pagamento'];
    if (!in_array($pedido['status'], $allowedStatuses)) {
        registrarLog("Erro: Data passada não permitida para status '{$pedido['status']}'. Pedido ID: $pedido_id", 'ERROR');
        echo json_encode(['success' => false, 'message' => 'Data passada não permitida nesse status.']);
        ob_end_flush();
        exit;
    }
    registrarLog("Aviso: Data passada permitida para status '{$pedido['status']}'. Pedido ID: $pedido_id", 'WARNING');
}

// Determinar se o status está "após Pedido Separado"
$statusOrder = [
    'pendente', 'Em Separação', 'Pedido Separado', 'Aguardando Cliente',
    'Aguardando Pagamento', 'Aguardando Retirada', 'Pagamento na Retirada', 'Concluído'
];
$statusIndex = array_search($pedido['status'], $statusOrder);
$pedidoSeparadoIndex = array_search('Pedido Separado', $statusOrder);
$isAfterPedidoSeparado = $statusIndex >= $pedidoSeparadoIndex;
registrarLog("Status index: $statusIndex. Is after 'Pedido Separado': " . ($isAfterPedidoSeparado ? 'true' : 'false'), 'INFO');

// Decodificar produtos (se enviados)
$produtos = $produtos_json ? json_decode($produtos_json, true) : null;
if ($produtos_json && (!$produtos || !is_array($produtos))) {
    registrarLog("Erro: Dados dos produtos inválidos. JSON: $produtos_json. Pedido ID: $pedido_id", 'ERROR');
    echo json_encode(['success' => false, 'message' => 'Dados dos produtos inválidos.']);
    ob_end_flush();
    exit;
}
registrarLog("JSON de produtos recebido: $produtos_json", 'INFO');

try {
    $conn->beginTransaction();
    registrarLog("Transação iniciada para Pedido ID: $pedido_id", 'INFO');

    // Atualizar os dados do pedido
    $status = isset($_POST['status']) ? validateInput($_POST['status']) : $pedido['status'];
    $stmtUpdate = $conn->prepare("
        UPDATE pedidos
        SET data_retirada = :data_retirada, observacoes = :observacoes, status = :status
        WHERE id = :pedido_id
    ");
    $stmtUpdate->bindParam(':data_retirada', $data_retirada_formatted);
    $stmtUpdate->bindParam(':observacoes', $observacoes);
    $stmtUpdate->bindParam(':status', $status);
    $stmtUpdate->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
    $stmtUpdate->execute();
    registrarLog("Dados do pedido atualizados. Novo Status: $status, Data Retirada: $data_retirada_formatted. Pedido ID: $pedido_id", 'INFO');

    // Processar itens apenas se produtos foram enviados
    $valor_total = 0;
    if ($produtos && is_array($produtos)) {
        registrarLog("Produtos enviados no POST. Deletando e reinserindo itens. Pedido ID: $pedido_id", 'INFO');
        $stmtDeleteItens = $conn->prepare("DELETE FROM itens_pedido WHERE pedido_id = :pedido_id");
        $stmtDeleteItens->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
        $stmtDeleteItens->execute();
        registrarLog("Itens existentes removidos. Pedido ID: $pedido_id", 'INFO');

        $stmtProduto = $conn->prepare("SELECT valor, tipo FROM produtos WHERE id = :produto_id");
        $stmtProdutoPedido = $conn->prepare("
            INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, quantidade_separada, valor_unitario)
            VALUES (:pedido_id, :produto_id, :quantidade, :quantidade_separada, :valor_unitario)
        ");

        foreach ($produtos as $index => $produto) {
            $produto_id = $produto['produto_id'];
            $quantidade = $produto['quantidade'];
            $quantidade_separada = isset($produto['quantidade_separada']) && $produto['quantidade_separada'] !== 'N/A' ? $produto['quantidade_separada'] : null;
            registrarLog("Processando produto index $index: ID $produto_id, Quantidade: $quantidade, Separada: " . ($quantidade_separada ?? 'N/A'), 'INFO');

            $stmtProduto->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
            $stmtProduto->execute();
            $produtoData = $stmtProduto->fetch(PDO::FETCH_ASSOC);
            if (!$produtoData) {
                throw new Exception('Produto não encontrado: ID ' . $produto_id);
            }

            $tipo = $produtoData['tipo'];
            $valor_unitario = $produtoData['valor'];
            if ($tipo === "UND") {
                if (!ctype_digit($quantidade) || (int)$quantidade <= 0) {
                    throw new Exception('Quantidade inválida para o produto ID: ' . $produto_id . '. Produtos do tipo UND devem ter quantidade inteira (ex.: 1, 2).');
                }
                if ($quantidade_separada !== null && (!ctype_digit($quantidade_separada) || (int)$quantidade_separada < 0)) {
                    throw new Exception('Quantidade separada inválida para o produto ID: ' . $produto_id . '. Produtos do tipo UND devem ter quantidade separada inteira (ex.: 0, 1, 2).');
                }
            } else {
                if (!is_numeric($quantidade) || $quantidade <= 0 || !preg_match('/^\d+(?:\.\d{1,3})?$/', $quantidade)) {
                    throw new Exception('Quantidade inválida para o produto ID: ' . $produto_id . '. Produtos do tipo KG devem ser no formato X.YYY (ex.: 0.850).');
                }
                if ($quantidade_separada !== null && (!is_numeric($quantidade_separada) || $quantidade_separada < 0 || !preg_match('/^\d+(?:\.\d{1,3})?$/', $quantidade_separada))) {
                    throw new Exception('Quantidade separada inválida para o produto ID: ' . $produto_id . '. Produtos do tipo KG devem ser no formato X.YYY (ex.: 0.850).');
                }
            }
            if ($valor_unitario === null || $valor_unitario <= 0) {
                throw new Exception('O produto ID ' . $produto_id . ' tem um valor inválido ou não definido.');
            }

            $quantidade_usada = ($isAfterPedidoSeparado && $quantidade_separada !== null) ? $quantidade_separada : $quantidade;
            $subtotal = $quantidade_usada * $valor_unitario;
            $valor_total += $subtotal;
            registrarLog("Adicionando produto ID: $produto_id. Quantidade usada: $quantidade_usada, Valor unitário: $valor_unitario, Subtotal: $subtotal", 'INFO');

            $stmtProdutoPedido->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
            $stmtProdutoPedido->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
            $stmtProdutoPedido->bindParam(':quantidade', $quantidade, PDO::PARAM_STR);
            $stmtProdutoPedido->bindParam(':quantidade_separada', $quantidade_separada, PDO::PARAM_STR);
            $stmtProdutoPedido->bindParam(':valor_unitario', $valor_unitario);
            $stmtProdutoPedido->execute();
        }
    } else {
        registrarLog("Nenhum produto enviado no POST. Mantendo itens existentes. Pedido ID: $pedido_id", 'WARNING');
        // Recalcular valor_total se necessário
        if ($isAfterPedidoSeparado) {
            $stmtRecalc = $conn->prepare("SELECT SUM(COALESCE(quantidade_separada, quantidade) * valor_unitario) AS total FROM itens_pedido WHERE pedido_id = :pedido_id");
            $stmtRecalc->bindParam(':pedido_id', $pedido_id);
            $stmtRecalc->execute();
            $valor_total = $stmtRecalc->fetchColumn() ?? 0;
            registrarLog("Valor total recalculado para status após Separado: $valor_total. Pedido ID: $pedido_id", 'INFO');
        } else {
            $stmtRecalc = $conn->prepare("SELECT SUM(quantidade * valor_unitario) AS total FROM itens_pedido WHERE pedido_id = :pedido_id");
            $stmtRecalc->bindParam(':pedido_id', $pedido_id);
            $stmtRecalc->execute();
            $valor_total = $stmtRecalc->fetchColumn() ?? 0;
            registrarLog("Valor total mantido: $valor_total. Pedido ID: $pedido_id", 'INFO');
        }
    }

    // Atualizar o valor total do pedido
    $stmtAtualizaTotal = $conn->prepare("UPDATE pedidos SET valor_total = :valor_total WHERE id = :pedido_id");
    $stmtAtualizaTotal->bindParam(':valor_total', $valor_total);
    $stmtAtualizaTotal->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
    $stmtAtualizaTotal->execute();
    registrarLog("Valor total atualizado: $valor_total. Pedido ID: $pedido_id", 'INFO');

    // Processar comprovantes
    if (isset($_FILES['comprovantes']) && !empty($_FILES['comprovantes']['name'][0])) {
        registrarLog("Upload de comprovantes iniciado. Arquivos: " . json_encode($_FILES['comprovantes']['name']), 'INFO');
        $uploadDir = 'uploads/comprovantes/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
            registrarLog("Diretório de upload criado: $uploadDir", 'INFO');
        }
        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $stmtComprovante = $conn->prepare("
            INSERT INTO comprovantes (pedido_id, caminho_arquivo, data_upload)
            VALUES (:pedido_id, :caminho_arquivo, NOW())
        ");
        foreach ($_FILES['comprovantes']['name'] as $key => $name) {
            if ($_FILES['comprovantes']['error'][$key] === UPLOAD_ERR_OK) {
                if (!in_array($_FILES['comprovantes']['type'][$key], $allowedTypes)) {
                    registrarLog("Erro: Tipo de arquivo inválido: {$_FILES['comprovantes']['type'][$key]}. Arquivo: $name", 'ERROR');
                    continue;
                }
                $tmpName = $_FILES['comprovantes']['tmp_name'][$key];
                $fileName = time() . '_' . basename($name);
                $filePath = $uploadDir . $fileName;
                if (move_uploaded_file($tmpName, $filePath)) {
                    $stmtComprovante->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
                    $stmtComprovante->bindParam(':caminho_arquivo', $filePath);
                    $stmtComprovante->execute();
                    registrarLog("Upload de comprovante concluído: $fileName. Pedido ID: $pedido_id", 'INFO');
                } else {
                    registrarLog("Erro ao mover arquivo de comprovante: $fileName. Erro PHP: " . $_FILES['comprovantes']['error'][$key], 'ERROR');
                }
            }
        }
    }

    // Registrar alteração no log do banco
    $usuario_id = $_SESSION['usuario_id'];
    $data_alteracao = date('Y-m-d H:i:s');
    $stmtLog = $conn->prepare("
        INSERT INTO log_alteracoes_pedidos (pedido_id, usuario_id, data_alteracao, descricao)
        VALUES (:pedido_id, :usuario_id, :data_alteracao, :descricao)
    ");
    $descricao = "Pedido editado pelo usuário ID $usuario_id. Novo valor total: R$ " . number_format($valor_total, 2, ',', '.') . (isset($_FILES['comprovantes']) && !empty($_FILES['comprovantes']['name'][0]) ? " Comprovantes adicionados." : "");
    $stmtLog->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
    $stmtLog->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmtLog->bindParam(':data_alteracao', $data_alteracao);
    $stmtLog->bindParam(':descricao', $descricao);
    $stmtLog->execute();
    registrarLog("Alteração registrada no log do banco. Descrição: $descricao", 'INFO');

    $conn->commit();
    registrarLog("Transação commitada com sucesso. Pedido ID: $pedido_id", 'INFO');
    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Pedido atualizado com sucesso!']);
    exit;
} catch (Exception $e) {
    $conn->rollBack();
    $errorMsg = "Erro ao editar pedido ID: $pedido_id. Mensagem: " . $e->getMessage() . ". SQLSTATE: " . ($e instanceof PDOException ? $e->getCode() : 'N/A') . ". Trace: " . $e->getTraceAsString();
    registrarLog($errorMsg, 'ERROR');
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Erro ao processar o pedido: ' . $e->getMessage()]);
    exit;
}
?>