<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /atacado/login.php');
    exit;
}
require_once 'backend/config/db.php';

// Captura os filtros
$status = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;
$dataInicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : null;
$dataFim = isset($_GET['data_fim']) ? $_GET['data_fim'] : null;
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : null;

// Definir a data inicial e final para o mês atual se não houver intervalo selecionado
if (!$dataInicio && !$dataFim) {
    $dataInicio = date('Y-m-01'); // Primeiro dia do mês atual
    $dataFim = date('Y-m-d'); // Hoje
}

// Consulta principal para buscar os pedidos
$query = "
    SELECT p.id, c.nome AS cliente, c.cidade, p.status, p.status_pagamento, p.valor_total, p.observacoes, p.data_pedido
    FROM pedidos p
    JOIN clientes c ON p.cliente_id = c.id
    WHERE DATE(p.data_pedido) BETWEEN :data_inicio AND :data_fim
";
$params = [
    ':data_inicio' => $dataInicio,
    ':data_fim' => $dataFim
];
if ($status) {
    $query .= " AND p.status = :status";
    $params[':status'] = $status;
}
if ($busca) {
    if (is_numeric($busca)) {
        $query .= " AND p.id = :id_busca";
        $params[':id_busca'] = $busca;
    } else {
        $query .= " AND c.nome LIKE :busca";
        $params[':busca'] = '%' . $busca . '%';
    }
}

$stmtPedidos = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmtPedidos->bindValue($key, $value);
}
try {
    $stmtPedidos->execute();
    $pedidos = $stmtPedidos->fetchAll(PDO::FETCH_ASSOC);

    // Para cada pedido, verificar se há divergência e ajustar o valor_total
    foreach ($pedidos as &$pedido) {
        $pedidoId = $pedido['id'];
        $pedidoStatus = $pedido['status'];
        // Consulta para buscar os itens do pedido
        $queryItens = "
            SELECT i.quantidade, i.quantidade_separada, i.valor_unitario
            FROM itens_pedido i
            WHERE i.pedido_id = :pedido_id
        ";
        $stmtItens = $conn->prepare($queryItens);
        $stmtItens->bindParam(':pedido_id', $pedidoId);
        $stmtItens->execute();
        $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

        // Verificar se há divergência
        $temDivergencia = false;
        foreach ($itens as $item) {
            $quantidade = floatval($item['quantidade']);
            $quantidadeSeparada = floatval($item['quantidade_separada']);
            if ($quantidade != $quantidadeSeparada) {
                $temDivergencia = true;
                break;
            }
        }
        // O ícone de alerta só aparece se houver divergência e o status não for "pendente" ou "Em Separação"
        $statusSemDivergencia = ['pendente', 'Em Separação'];
        $pedido['tem_divergencia'] = $temDivergencia && !in_array($pedidoStatus, $statusSemDivergencia);

        // Forçar o valor_total a ser 0 para status "pendente" e "Em Separação"
        if (in_array($pedidoStatus, ['pendente', 'Em Separação'])) {
            $pedido['valor_total'] = 0;
        }
        // Recalcular o valor_total para pedidos no status "Pedido Separado"
        elseif ($pedidoStatus === 'Pedido Separado') {
            $valorTotalCalculado = 0;
            foreach ($itens as $item) {
                $quantidadeUsada = !is_null($item['quantidade_separada']) ? floatval($item['quantidade_separada']) : floatval($item['quantidade']);
                $valorUnitario = floatval($item['valor_unitario']);
                $valorTotalCalculado += $quantidadeUsada * $valorUnitario;
            }
            if (round($valorTotalCalculado, 2) != round($pedido['valor_total'], 2)) {
                $updateQuery = "
                    UPDATE pedidos
                    SET valor_total = :valor_total
                    WHERE id = :pedido_id
                ";
                $stmtUpdate = $conn->prepare($updateQuery);
                $stmtUpdate->bindParam(':valor_total', $valorTotalCalculado);
                $stmtUpdate->bindParam(':pedido_id', $pedidoId);
                $stmtUpdate->execute();
                $pedido['valor_total'] = $valorTotalCalculado;
            }
        }
    }
    unset($pedido); // Desfazer a referência para evitar problemas
} catch (PDOException $e) {
    echo "Erro ao carregar pedidos: " . $e->getMessage();
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    include 'pedidos_table.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
   <style>
    /* Estilizar os filtros para ficarem em uma única linha */
    .filter-row {
        display: flex;
        flex-direction: row;
        flex-wrap: wrap;
        align-items: center;
        gap: 20px;
        margin-bottom: 20px;
        width: 100%;
    }
    .filter-item {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 10px;
        flex: 1;
        min-width: 150px;
    }
    .filter-item label {
        font-weight: 500;
        white-space: nowrap;
    }
    .filter-item select,
    .filter-item input {
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 14px;
        width: 100%;
        box-sizing: border-box;
    }
    .filter-item:nth-child(1) {
        flex: 1.5;
        min-width: 180px;
    }
    .filter-item:nth-child(2) {
        flex: 1.5;
        min-width: 180px;
    }
    .filter-item:nth-child(3),
    .filter-item:nth-child(4) {
        flex: 1;
        min-width: 150px;
    }
    .floating-button {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background-color: #007bff;
        color: white;
        border: none;
        border-radius: 50%;
        width: 60px;
        height: 60px;
        font-size: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        cursor: pointer;
    }
    .floating-button:hover {
        background-color: #0056b3;
    }
    .custom-modal {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background-color: white;
        padding: 20px;
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 1050;
        max-width: 500px;
        width: 100%;
    }
    .custom-modal .modal-content {
        position: relative;
    }
    .close-modal-observacoes {
        position: absolute;
        top: 10px;
        right: 10px;
        font-size: 20px;
        cursor: pointer;
    }
    #notificacao {
        position: fixed;
        top: -50px;
        left: 50%;
        transform: translateX(-50%);
        padding: 10px 20px;
        border-radius: 5px;
        color: white;
        z-index: 1060;
        transition: all 0.3s ease;
    }
    #notificacao.success {
        background-color: #28a745;
    }
    #notificacao.error {
        background-color: #dc3545;
    }
    /* Correção para o datepicker */
    .ui-datepicker {
        z-index: 1070 !important; /* Acima do modal (1050) e backdrop (1060) */
    }
</style>
</head>
<body>
    <div id="notificacao"></div>
    <h1>Pedidos</h1>
    <!-- Filtro de pedidos -->
    <div class="filter-row">
        <!-- Filtro por Busca (Cliente ou ID) -->
        <div class="filter-item">
            <label for="busca">Buscar por Cliente ou ID:</label>
            <input type="text" id="busca" name="busca" value="<?= htmlspecialchars($busca ?? '') ?>" placeholder="Nome do cliente ou ID">
        </div>
        <!-- Filtro por Status -->
        <div class="filter-item">
            <label for="statusFilter">Filtrar por Status:</label>
            <select id="statusFilter" name="status">
                <option value="">Todos</option>
                <option value="pendente" <?= $status == 'pendente' ? 'selected' : '' ?>>Pendente</option>
                <option value="Em Separação" <?= $status == 'Em Separação' ? 'selected' : '' ?>>Em Separação</option>
                <option value="Pedido Separado" <?= $status == 'Pedido Separado' ? 'selected' : '' ?>>Pedido Separado</option>
                <option value="Aguardando Cliente" <?= $status == 'Aguardando Cliente' ? 'selected' : '' ?>>Aguardando Cliente</option>
                <option value="Aguardando Retirada" <?= $status == 'Aguardando Retirada' ? 'selected' : '' ?>>Aguardando Retirada</option>
                <option value="Pagamento na Retirada" <?= $status == 'Pagamento na Retirada' ? 'selected' : '' ?>>Pagamento na Retirada</option>
                <option value="Concluído" <?= $status == 'Concluído' ? 'selected' : '' ?>>Concluído</option>
            </select>
        </div>
        <!-- Filtros de Intervalo de Datas -->
        <div class="filter-item">
            <label for="data_inicio">Data Início:</label>
            <input type="date" id="data_inicio" value="<?= htmlspecialchars($dataInicio) ?>">
        </div>
        <div class="filter-item">
            <label for="data_fim">Data Fim:</label>
            <input type="date" id="data_fim" value="<?= htmlspecialchars($dataFim) ?>">
        </div>
    </div>
    <!-- Contêiner para a tabela de pedidos -->
    <div id="pedidosContainer">
        <?php include 'pedidos_table.php'; ?>
    </div>
    <!-- Modal para criar pedido -->
    <div class="modal fade" id="modalPedido" tabindex="-1" role="dialog" aria-labelledby="modalPedidoLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header custom-modal-header">
                    <h5 class="modal-title" id="modalPedidoLabel">Criar Pedido</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="formPedido" method="POST">
                        <!-- Seção Selecionar Cliente -->
                        <div class="form-group">
                            <label for="buscarCliente">Cliente <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                </div>
                                <input type="text" id="buscarCliente" class="form-control" placeholder="Buscar clientes...">
                                <div class="invalid-feedback">Por favor, selecione um cliente.</div>
                                <input type="hidden" name="cliente" id="cliente_id">
                            </div>
                            <div id="listaClientes" class="list-group"></div>
                        </div>
                        <!-- Seção Adicionar Produtos -->
                        <div class="form-group">
                            <label for="buscarProduto">Produtos</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-box"></i></span>
                                </div>
                                <input type="text" id="buscarProduto" class="form-control" placeholder="Buscar produtos...">
                            </div>
                            <div id="listaProdutos" class="list-group"></div>
                        </div>
                        <!-- Lista de Produtos Selecionados -->
                        <div id="produtosSelecionados" class="mb-3"></div>
                        <!-- Data de Retirada -->
                        <div class="form-group">
                            <label for="data_retirada">Data de Retirada <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                </div>
                                <input type="text" name="data_retirada" id="data_retirada" class="form-control datepicker" placeholder="DD/MM/YYYY" required>
                            </div>
                        </div>
                        <!-- Observações -->
                        <div class="form-group">
                            <label for="observacoes">Observações</label>
                            <textarea name="observacoes" id="observacoes" class="form-control" placeholder="Adicionar observações..."></textarea>
                        </div>
                        <!-- Botão de Salvar Pedido -->
                  <div class="text-right">
    <button type="button" id="btnSalvarPedido" class="btn btn-primary">Salvar Pedido</button>
</div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal de Visualização de Pedido -->
    <div class="modal fade" id="verPedidoModal" tabindex="-1" role="dialog" aria-labelledby="modalPedidoLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalPedidoLabel">Visualizar Pedido</h5>
                    <button type="button" class="close pedido-close" data-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="verPedidoContent">
                        <!-- O conteúdo do pedido será carregado aqui via AJAX -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal de Observações -->
    <div id="observacoesModal" class="custom-modal">
        <div class="modal-content">
            <span class="close-modal-observacoes">&times;</span>
            <h2>Observações</h2>
            <div id="observacoesContent">
                <!-- As observações serão carregadas aqui via JavaScript -->
            </div>
        </div>
    </div>
    <!-- Botão para abrir o modal de criação de pedido -->
    <button id="btnNovoPedido" class="floating-button">
        <i class="fas fa-plus"></i>
    </button>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src="/atacado/public/js/pedidos.js?v=<?= time() ?>"></script>
</body>
</html>