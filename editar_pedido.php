
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
    registrarLog("Erro: Dados obrigatórios não fornecidos. Pedido ID: $pedido_id, Data Retirada: " . ($data_retirada ?: 'vazio'), 'ERROR');
    echo json_encode(['success' => false, 'message' => 'Dados obrigatórios não fornecidos (ID do pedido ou data de retirada).']);
    ob_end_flush();
    exit;
}

// Buscar o status atual do pedido
$stmt = $conn->prepare("SELECT status FROM pedidos WHERE id = :id");
$stmt->bindParam(':id', $pedido_id, PDO::PARAM_INT);
if (!$stmt->execute()) {
    registrarLog("Erro: Falha ao executar consulta de status do pedido. ID: $pedido_id", 'ERROR');
    echo json_encode(['success' => false, 'message' => 'Erro ao verificar pedido no banco.']);
    ob_end_flush();
    exit;
}
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
$newStatus = isset($_POST['status']) ? validateInput($_POST['status']) : $pedido['status'];
$statusIndex = array_search($newStatus, $statusOrder);
$pedidoSeparadoIndex = array_search('Pedido Separado', $statusOrder);
$isAfterPedidoSeparado = $statusIndex >= $pedidoSeparadoIndex;
registrarLog("Novo status: $newStatus, Status index: $statusIndex. Is after 'Pedido Separado': " . ($isAfterPedidoSeparado ? 'true' : 'false'), 'INFO');

// Decodificar produtos (se enviados)
$produtos = $produtos_json ? json_decode($produtos_json, true) : null;
if ($produtos_json && (!$produtos || !is_array($produtos))) {
    registrarLog("Erro: Dados dos produtos inválidos. JSON: $produtos_json. Pedido ID: $pedido_id", 'ERROR');
    echo json_encode(['success' => false, 'message' => 'Dados dos produtos inválidos.']);
    ob_end_flush();
    exit;
}
registrarLog("JSON de produtos recebido: " . ($produtos_json ?: 'vazio'), 'INFO');

try {
    $conn->beginTransaction();
    registrarLog("Transação iniciada para Pedido ID: $pedido_id", 'INFO');

    // Atualizar os dados do pedido
    $stmtUpdate = $conn->prepare("
        UPDATE pedidos
        SET data_retirada = :data_retirada, observacoes = :observacoes, status = :status
        WHERE id = :pedido_id
    ");
    $stmtUpdate->bindParam(':data_retirada', $data_retirada_formatted);
    $stmtUpdate->bindParam(':observacoes', $observacoes);
    $stmtUpdate->bindParam(':status', $newStatus);
    $stmtUpdate->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
    if (!$stmtUpdate->execute()) {
        throw new Exception('Falha ao atualizar dados do pedido no banco.');
    }
    registrarLog("Dados do pedido atualizados. Novo Status: $newStatus, Data Retirada: $data_retirada_formatted. Pedido ID: $pedido_id", 'INFO');

    // Processar itens apenas se produtos foram enviados
    $valor_total = 0;
    if ($produtos && is_array($produtos)) {
        registrarLog("Produtos enviados no POST. Deletando e reinserindo itens. Pedido ID: $pedido_id", 'INFO');
        $stmtDeleteItens = $conn->prepare("DELETE FROM itens_pedido WHERE pedido_id = :pedido_id");
        $stmtDeleteItens->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
        if (!$stmtDeleteItens->execute()) {
            throw new Exception('Falha ao deletar itens existentes do pedido.');
        }
        registrarLog("Itens existentes removidos. Pedido ID: $pedido_id", 'INFO');

        $stmtProduto = $conn->prepare("SELECT valor, tipo FROM produtos WHERE id = :produto_id");
        $stmtProdutoPedido = $conn->prepare("
            INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, quantidade_separada, valor_unitario)
            VALUES (:pedido_id, :produto_id, :quantidade, :quantidade_separada, :valor_unitario)
        ");

        // Buscar itens existentes antes da deleção para preservar quantidade_separada
        $stmtItensExistentes = $conn->prepare("SELECT produto_id, quantidade, quantidade_separada FROM itens_pedido WHERE pedido_id = :pedido_id");
        $stmtItensExistentes->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
        if (!$stmtItensExistentes->execute()) {
            registrarLog("Erro ao carregar itens existentes para Pedido ID: $pedido_id", 'ERROR');
            throw new Exception('Falha ao carregar itens existentes.');
        }
        $itensExistentes = $stmtItensExistentes->fetchAll(PDO::FETCH_ASSOC);
        $quantidadesExistentes = [];
        foreach ($itensExistentes as $item) {
            $quantidadesExistentes[$item['produto_id']] = [
                'quantidade' => $item['quantidade'],
                'quantidade_separada' => $item['quantidade_separada']
            ];
        }
        registrarLog("Quantidades existentes carregadas: " . json_encode($quantidadesExistentes), 'INFO');

        foreach ($produtos as $index => $produto) {
            $produto_id = $produto['produto_id'];
            $quantidade = isset($produto['quantidade']) ? $produto['quantidade'] : null;
            $quantidade_separada = isset($produto['quantidade_separada']) && $produto['quantidade_separada'] !== 'N/A' ? $produto['quantidade_separada'] : null;
            registrarLog("Processando produto index $index: ID $produto_id, Quantidade: " . ($quantidade ?: 'vazio') . ", Separada: " . ($quantidade_separada ?? 'N/A'), 'INFO');

            if (!$quantidade) {
                throw new Exception('Quantidade não fornecida para o produto ID: ' . $produto_id);
            }

            $stmtProduto->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
            if (!$stmtProduto->execute()) {
                throw new Exception('Falha ao consultar produto ID: ' . $produto_id);
            }
            $produtoData = $stmtProduto->fetch(PDO::FETCH_ASSOC);
            if (!$produtoData) {
                throw new Exception('Produto não encontrado: ID ' . $produto_id);
            }

            $tipo = $produtoData['tipo'] ?: 'KG';
            $valor_unitario = floatval($produtoData['valor'] ?? 0);
            if ($valor_unitario <= 0) {
                throw new Exception('Produto ID ' . $produto_id . ' tem valor inválido.');
            }

            if ($tipo === "UND") {
                $quantidade_int = intval($quantidade);
                if ($quantidade_int <= 0) {
                    throw new Exception('Quantidade inválida para o produto ID: ' . $produto_id . '. Produtos do tipo UND devem ter quantidade inteira maior que 0.');
                }
                $quantidade = $quantidade_int;
                if ($quantidade_separada !== null) {
                    $quantidade_separada_int = intval($quantidade_separada);
                    if ($quantidade_separada_int < 0) {
                        throw new Exception('Quantidade separada inválida para o produto ID: ' . $produto_id . '. Produtos do tipo UND devem ter quantidade separada inteira maior ou igual a 0.');
                    }
                    $quantidade_separada = $quantidade_separada_int;
                }
            } else {
                if (!preg_match('/^\d+(?:\.\d{1,3})?$/', $quantidade) || floatval($quantidade) <= 0) {
                    throw new Exception('Quantidade inválida para o produto ID: ' . $produto_id . '. Produtos do tipo KG devem ser no formato X.YYY maior que 0.');
                }
                if ($quantidade_separada !== null && (!preg_match('/^\d+(?:\.\d{1,3})?$/', $quantidade_separada) || floatval($quantidade_separada) < 0)) {
                    throw new Exception('Quantidade separada inválida para o produto ID: ' . $produto_id . '. Produtos do tipo KG devem ser no formato X.YYY maior ou igual a 0.');
                }
            }

            // Usar quantidade_separada do formulário se fornecida e status após Pedido Separado, senão usar quantidade
            $quantidade_usada = ($isAfterPedidoSeparado && $quantidade_separada !== null) ? floatval($quantidade_separada) : floatval($quantidade);
            $subtotal = $quantidade_usada * $valor_unitario;
            $valor_total += $subtotal;
            registrarLog("Adicionando produto ID: $produto_id. Quantidade enviada: $quantidade, Quantidade separada: " . ($quantidade_separada ?? 'N/A') . ", Quantidade usada: $quantidade_usada, Valor unitário: $valor_unitario, Subtotal: $subtotal", 'INFO');

            $stmtProdutoPedido->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
            $stmtProdutoPedido->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
            $stmtProdutoPedido->bindParam(':quantidade', $quantidade, PDO::PARAM_STR);
            $stmtProdutoPedido->bindParam(':quantidade_separada', $quantidade_separada, PDO::PARAM_STR);
            $stmtProdutoPedido->bindParam(':valor_unitario', $valor_unitario);
            if (!$stmtProdutoPedido->execute()) {
                throw new Exception('Falha ao inserir item do pedido ID: ' . $produto_id);
            }
        }
    } else {
        registrarLog("Nenhum produto enviado no POST. Mantendo itens existentes. Pedido ID: $pedido_id", 'INFO');
        // Recalcular valor_total com base nos itens existentes
        $stmtRecalc = $conn->prepare("
            SELECT SUM(COALESCE(quantidade_separada, quantidade, 0) * COALESCE(valor_unitario, 0)) AS total
            FROM itens_pedido
            WHERE pedido_id = :pedido_id
        ");
        $stmtRecalc->bindParam(':pedido_id', $pedido_id);
        if (!$stmtRecalc->execute()) {
            throw new Exception('Falha ao recalcular valor total.');
        }
        $valor_total = floatval($stmtRecalc->fetchColumn() ?? 0);
        registrarLog("Valor total recalculado sem alteração de itens: $valor_total. Pedido ID: $pedido_id", 'INFO');
        if ($valor_total == 0) {
            registrarLog("AVISO: Valor total zerado ao editar sem produtos. Verificar itens_pedido. Pedido ID: $pedido_id", 'WARNING');
        }
    }

    // Atualizar o valor total do pedido
    $stmtAtualizaTotal = $conn->prepare("UPDATE pedidos SET valor_total = :valor_total WHERE id = :pedido_id");
    $stmtAtualizaTotal->bindParam(':valor_total', $valor_total, PDO::PARAM_STR);
    $stmtAtualizaTotal->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
    if (!$stmtAtualizaTotal->execute()) {
        throw new Exception('Falha ao atualizar valor total do pedido no banco. Rows affected: ' . $stmtAtualizaTotal->rowCount());
    }
    $rowsAffected = $stmtAtualizaTotal->rowCount();
    registrarLog("Valor total atualizado no banco: $valor_total. Pedido ID: $pedido_id. Linhas afetadas: $rowsAffected", 'INFO');
    if ($rowsAffected === 0) {
        registrarLog("AVISO: Nenhuma linha afetada ao atualizar valor_total para Pedido ID: $pedido_id. Verificar se ID existe.", 'WARNING');
    }

    // Commit da transação principal
    $conn->commit();
    registrarLog("Transação principal commitada com sucesso. Pedido ID: $pedido_id", 'INFO');

    // Processar comprovantes em uma transação separada
   $uploadErrors = [];
if (isset($_FILES['comprovantes']) && !empty($_FILES['comprovantes']['name'][0])) {
    try {
        $conn->beginTransaction();
        $startTime = microtime(true);
        registrarLog("Upload de comprovantes iniciado. Arquivos: " . json_encode($_FILES['comprovantes']['name']), 'INFO');
        $uploadDir = 'Uploads/comprovantes/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception("Falha ao criar diretório de upload: $uploadDir");
            }
            registrarLog("Diretório de upload criado: $uploadDir", 'INFO');
        }
        if (!is_writable($uploadDir)) {
            throw new Exception("Diretório de upload não tem permissão de escrita: $uploadDir");
        }
        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $maxFileSize = 2 * 1024 * 1024; // 2MB
        $stmtComprovante = $conn->prepare("
            INSERT INTO comprovantes (pedido_id, caminho_arquivo, data_upload)
            VALUES (:pedido_id, :caminho_arquivo, NOW())
        ");
        foreach ($_FILES['comprovantes']['name'] as $key => $name) {
            if ($_FILES['comprovantes']['error'][$key] !== UPLOAD_ERR_OK) {
                $uploadErrors[] = "Erro no upload do arquivo '$name': Código de erro PHP {$_FILES['comprovantes']['error'][$key]}";
                registrarLog("Erro no upload do arquivo: $name. Erro PHP: " . $_FILES['comprovantes']['error'][$key], 'ERROR');
                continue;
            }
            if (!in_array($_FILES['comprovantes']['type'][$key], $allowedTypes)) {
                $uploadErrors[] = "Tipo de arquivo inválido para '$name': {$_FILES['comprovantes']['type'][$key]}";
                registrarLog("Erro: Tipo de arquivo inválido: {$_FILES['comprovantes']['type'][$key]}. Arquivo: $name", 'ERROR');
                continue;
            }
            if ($_FILES['comprovantes']['size'][$key] > $maxFileSize) {
                $uploadErrors[] = "Arquivo muito grande: '$name'. Tamanho: {$_FILES['comprovantes']['size'][$key]} bytes, Máximo: $maxFileSize bytes";
                registrarLog("Erro: Arquivo muito grande: $name. Tamanho: {$_FILES['comprovantes']['size'][$key]} bytes, Máximo: $maxFileSize bytes", 'ERROR');
                continue;
            }
            $tmpName = $_FILES['comprovantes']['tmp_name'][$key];
            $fileName = time() . '_' . preg_replace('/[^A-Za-z0-9\.\-_]/', '_', basename($name));
            $filePath = $uploadDir . $fileName;
            $fileStartTime = microtime(true);
            if (!move_uploaded_file($tmpName, $filePath)) {
                $uploadErrors[] = "Falha ao mover arquivo de comprovante: '$name'";
                registrarLog("Erro ao mover arquivo de comprovante: $fileName. Erro PHP: " . $_FILES['comprovantes']['error'][$key], 'ERROR');
                continue;
            }
            $stmtComprovante->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
            $stmtComprovante->bindParam(':caminho_arquivo', $filePath);
            if (!$stmtComprovante->execute()) {
                $uploadErrors[] = "Falha ao inserir comprovante no banco: '$fileName'";
                registrarLog("Erro ao inserir comprovante: $fileName. Pedido ID: $pedido_id", 'ERROR');
            } else {
                $fileEndTime = microtime(true);
                registrarLog("Upload de comprovante concluído: $fileName. Tempo: " . ($fileEndTime - $fileStartTime) . " segundos. Pedido ID: $pedido_id", 'INFO');
            }
        }
        $endTime = microtime(true);
        registrarLog("Upload de comprovantes finalizado. Tempo total: " . ($endTime - $startTime) . " segundos.", 'INFO');
        $conn->commit();
        registrarLog("Transação de comprovantes commitada com sucesso. Pedido ID: $pedido_id", 'INFO');
    } catch (Exception $e) {
        $conn->rollBack();
        $uploadErrors[] = "Erro ao processar comprovantes: " . $e->getMessage();
        registrarLog("Erro ao processar comprovantes. Mensagem: " . $e->getMessage() . ". Pedido ID: $pedido_id", 'ERROR');
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
    if (!$stmtLog->execute()) {
        registrarLog("Erro ao registrar log de alteração para Pedido ID: $pedido_id", 'ERROR');
    }
    registrarLog("Alteração registrada no log do banco. Descrição: $descricao", 'INFO');

    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Pedido atualizado com sucesso!', 'valor_total' => $valor_total]);
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
