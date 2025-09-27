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

// Função para registrar no log
function registrarLog($mensagem) {
    global $logFile;
    $dataHora = date('Y-m-d H:i:s');
    $logEntry = "[$dataHora] $mensagem\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    ob_start();

    // Log inicial da criação
    registrarLog("Início da criação de pedido. Usuário ID: {$_SESSION['usuario_id']}. Dados recebidos: " . json_encode($_POST));

    function validateInput($data) {
        return trim(htmlspecialchars($data));
    }

    if (isset($_SESSION['ultimo_pedido']) && (time() - $_SESSION['ultimo_pedido']) < 5) {
        registrarLog("Erro: Tentativa de criar pedido muito rápida. Usuário ID: {$_SESSION['usuario_id']}");
        echo json_encode(['status' => 'error', 'message' => 'Aguarde antes de criar outro pedido.']);
        ob_end_flush();
        exit;
    }

    $cliente_id = isset($_POST['cliente']) ? validateInput($_POST['cliente']) : null;
    $data_retirada = isset($_POST['data_retirada']) ? validateInput($_POST['data_retirada']) : null;
    $observacoes = isset($_POST['observacoes']) ? validateInput($_POST['observacoes']) : '';
    $produtos = isset($_POST['produto_id']) ? $_POST['produto_id'] : [];
    $quantidades = isset($_POST['quantidade']) ? $_POST['quantidade'] : [];

    if (empty($cliente_id) || empty($data_retirada) || empty($produtos) || empty($quantidades)) {
        registrarLog("Erro: Campos obrigatórios não preenchidos. Usuário ID: {$_SESSION['usuario_id']}");
        echo json_encode(['status' => 'error', 'message' => 'Por favor, preencha todos os campos obrigatórios (cliente, data de retirada e produtos).']);
        ob_end_flush();
        exit;
    }

    if (count($produtos) !== count($quantidades)) {
        registrarLog("Erro: Inconsistência nos dados de produtos. Número de produtos e quantidades não coincidem. Usuário ID: {$_SESSION['usuario_id']}");
        echo json_encode(['status' => 'error', 'message' => 'Dados dos produtos estão inconsistentes: número de produtos e quantidades não coincidem.']);
        ob_end_flush();
        exit;
    }

    if ($data_retirada) {
        $dataObj = DateTime::createFromFormat('d/m/Y', $data_retirada);
        if ($dataObj === false) {
            registrarLog("Erro: Formato de data inválido. Data: $data_retirada. Usuário ID: {$_SESSION['usuario_id']}");
            echo json_encode(['status' => 'error', 'message' => 'Formato de data inválido. Use o formato DD/MM/YYYY.']);
            ob_end_flush();
            exit;
        }
        $data_retirada = $dataObj->format('Y-m-d');

        $dataAtual = new DateTime();
        $dataRetiradaObj = new DateTime($data_retirada);
        // Permitir data de retirada no mesmo dia ou futura
        if ($dataRetiradaObj < $dataAtual->setTime(0, 0)) {
            registrarLog("Erro: Data de retirada anterior à atual. Data: $data_retirada. Usuário ID: {$_SESSION['usuario_id']}");
            echo json_encode(['status' => 'error', 'message' => 'A data de retirada não pode ser anterior à data atual.']);
            ob_end_flush();
            exit;
        }
    }

    $_SESSION['ultimo_pedido'] = time();

    try {
        $conn->beginTransaction();

        $stmtPedido = $conn->prepare("
            INSERT INTO pedidos (cliente_id, data_retirada, observacoes, data_pedido, status, status_pagamento) 
            VALUES (:cliente_id, :data_retirada, :observacoes, NOW(), 'Novo Pedido', 'Não')
        ");
        $stmtPedido->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
        $stmtPedido->bindParam(':data_retirada', $data_retirada);
        $stmtPedido->bindParam(':observacoes', $observacoes);

        if ($stmtPedido->execute()) {
            $pedido_id = $conn->lastInsertId();
            $valor_total = 0;

            registrarLog("Pedido criado. ID: $pedido_id, Cliente ID: $cliente_id, Data Retirada: $data_retirada");

            $stmtProduto = $conn->prepare("SELECT valor, tipo FROM produtos WHERE id = :produto_id");
            $stmtProdutoPedido = $conn->prepare("
                INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, valor_unitario) 
                VALUES (:pedido_id, :produto_id, :quantidade, :valor_unitario)
            ");

            foreach ($produtos as $index => $produto_id) {
                $quantidade = str_replace(',', '.', validateInput($quantidades[$index]));

                // Buscar o tipo do produto
                $stmtProduto->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
                $stmtProduto->execute();
                $produto = $stmtProduto->fetch(PDO::FETCH_ASSOC);

                if ($produto) {
                    $tipo = $produto['tipo'];
                    $valor_unitario = $produto['valor'];

                    // Validação da quantidade com base no tipo
                    if ($tipo === "UND") {
                        // Para UND: deve ser um número inteiro positivo
                        if (!ctype_digit($quantidade) || (int)$quantidade <= 0) {
                            registrarLog("Erro: Quantidade inválida para produto ID: $produto_id (UND). Quantidade: $quantidade. Pedido ID: $pedido_id");
                            echo json_encode(['status' => 'error', 'message' => 'Quantidade inválida para o produto ID: ' . $produto_id . '. Produtos do tipo UND devem ter quantidade inteira (ex.: 1, 2).']);
                            $conn->rollBack();
                            ob_end_flush();
                            exit;
                        }
                    } else {
                        // Para KG: deve ser no formato X,XXX e maior que 0
                        if (!is_numeric($quantidade) || $quantidade <= 0 || !preg_match('/^\d{1,3}\.\d{1,3}$/', $quantidade)) {
                            registrarLog("Erro: Quantidade inválida para produto ID: $produto_id (KG). Quantidade: $quantidade. Pedido ID: $pedido_id");
                            echo json_encode(['status' => 'error', 'message' => 'Quantidade inválida para o produto ID: ' . $produto_id . '. Produtos do tipo KG devem ser no formato X,YYY (ex.: 0,850).']);
                            $conn->rollBack();
                            ob_end_flush();
                            exit;
                        }
                    }

                    $produto_id = validateInput($produto_id);

                    if ($valor_unitario === null || $valor_unitario <= 0) {
                        registrarLog("Erro: Valor unitário inválido para produto ID: $produto_id. Pedido ID: $pedido_id");
                        echo json_encode(['status' => 'error', 'message' => 'O produto ID ' . $produto_id . ' tem um valor inválido ou não definido.']);
                        $conn->rollBack();
                        ob_end_flush();
                        exit;
                    }

                    $valor_total += $quantidade * $valor_unitario;

                    error_log("Produto ID: $produto_id, Quantidade: $quantidade, Valor Unitário: $valor_unitario, Subtotal: " . ($quantidade * $valor_unitario));

                    registrarLog("Adicionando produto ID: $produto_id ao Pedido ID: $pedido_id. Quantidade: $quantidade, Subtotal: " . ($quantidade * $valor_unitario));

                    $stmtProdutoPedido->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
                    $stmtProdutoPedido->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
                    $stmtProdutoPedido->bindParam(':quantidade', $quantidade, PDO::PARAM_STR);
                    $stmtProdutoPedido->bindParam(':valor_unitario', $valor_unitario);
                    $stmtProdutoPedido->execute();
                } else {
                    registrarLog("Erro: Produto não encontrado ID: $produto_id. Pedido ID: $pedido_id");
                    echo json_encode(['status' => 'error', 'message' => 'Produto não encontrado: ID ' . $produto_id]);
                    $conn->rollBack();
                    ob_end_flush();
                    exit;
                }
            }

            error_log("Valor total do pedido ID $pedido_id: $valor_total");

            $stmtAtualizaTotal = $conn->prepare("UPDATE pedidos SET valor_total = :valor_total WHERE id = :pedido_id");
            $stmtAtualizaTotal->bindParam(':valor_total', $valor_total);
            $stmtAtualizaTotal->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
            $stmtAtualizaTotal->execute();

            registrarLog("Valor total atualizado para Pedido ID: $pedido_id. Valor: $valor_total");

            $conn->commit();
            ob_end_clean();
            registrarLog("Fim da criação de pedido. Sucesso. Pedido ID: $pedido_id");
            echo json_encode(['status' => 'success', 'message' => 'Pedido criado com sucesso!', 'pedido_id' => $pedido_id]);
            exit;
        } else {
            $conn->rollBack();
            ob_end_clean();
            registrarLog("Erro ao inserir pedido no banco. Usuário ID: {$_SESSION['usuario_id']}");
            echo json_encode(['status' => 'error', 'message' => 'Erro ao criar o pedido.']);
            exit;
        }
    } catch (Exception $e) {
        $conn->rollBack();
        ob_end_clean();
        registrarLog("Exceção ao criar pedido: " . $e->getMessage() . ". Usuário ID: {$_SESSION['usuario_id']}");
        error_log('Erro ao criar o pedido: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Erro ao processar o pedido: ' . $e->getMessage()]);
        exit;
    }
} else {
    registrarLog("Requisição inválida para criar pedido. Método: {$_SERVER['REQUEST_METHOD']}");
    header('HTTP/1.1 405 Method Not Allowed');
    header('Allow: POST');
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido. Utilize o método POST.']);
    exit;
}
?>