<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    echo '<script>window.location.href = "/atacado/index.php?erro=acesso_negado";</script>';
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
    $dataInicio = date('Y-m-01');
    $dataFim = date('Y-m-d');
}

// Consulta principal
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

$query .= " ORDER BY p.data_pedido DESC, p.id DESC";

$stmtPedidos = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmtPedidos->bindValue($key, $value);
}
try {
    $stmtPedidos->execute();
    $pedidos = $stmtPedidos->fetchAll(PDO::FETCH_ASSOC);

    // Processar cada pedido
    foreach ($pedidos as &$pedido) {
        $pedidoId = $pedido['id'];
        $pedidoStatus = $pedido['status'];
        
        $queryItens = "
            SELECT i.quantidade, i.quantidade_separada, i.valor_unitario
            FROM itens_pedido i
            WHERE i.pedido_id = :pedido_id
        ";
        $stmtItens = $conn->prepare($queryItens);
        $stmtItens->bindParam(':pedido_id', $pedidoId);
        $stmtItens->execute();
        $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

        $temDivergencia = false;
        foreach ($itens as $item) {
            $quantidade = floatval($item['quantidade']);
            $quantidadeSeparada = floatval($item['quantidade_separada']);
            if ($quantidade != $quantidadeSeparada) {
                $temDivergencia = true;
                break;
            }
        }
        
        $statusSemDivergencia = ['pendente', 'Em Separação'];
        $pedido['tem_divergencia'] = $temDivergencia && !in_array($pedidoStatus, $statusSemDivergencia);

        if (in_array($pedidoStatus, ['pendente', 'Em Separação'])) {
            $pedido['valor_total'] = 0;
        } elseif ($pedidoStatus === 'Pedido Separado') {
            $valorTotalCalculado = 0;
            foreach ($itens as $item) {
                $quantidadeUsada = !is_null($item['quantidade_separada']) ? floatval($item['quantidade_separada']) : floatval($item['quantidade']);
                $valorUnitario = floatval($item['valor_unitario']);
                $valorTotalCalculado += $quantidadeUsada * $valorUnitario;
            }
            if (round($valorTotalCalculado, 2) != round($pedido['valor_total'], 2)) {
                $updateQuery = "UPDATE pedidos SET valor_total = :valor_total WHERE id = :pedido_id";
                $stmtUpdate = $conn->prepare($updateQuery);
                $stmtUpdate->bindParam(':valor_total', $valorTotalCalculado);
                $stmtUpdate->bindParam(':pedido_id', $pedidoId);
                $stmtUpdate->execute();
                $pedido['valor_total'] = $valorTotalCalculado;
            }
        }
    }
    unset($pedido);
} catch (PDOException $e) {
    echo "Erro ao carregar pedidos: " . $e->getMessage();
    exit;
}

// Se for AJAX, retornar apenas a tabela
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    include 'pedidos_table.php';
    exit;
}
?>

<!-- Container de Pedidos -->
<div class="pedidos-page-wrapper">
    <div class="pedidos-container">
        <div class="pedidos-header">
            <h1><i class="fas fa-file-alt"></i> Pedidos</h1>
        </div>

        <!-- Filtros -->
        <div class="pedidos-filtros-wrapper">
            <div class="filter-row">
                <!-- Busca -->
                <div class="filter-item">
                    <label for="busca">
                        <i class="fas fa-search"></i> Buscar
                    </label>
                    <input 
                        type="text" 
                        id="busca" 
                        name="busca" 
                        value="<?= htmlspecialchars($busca ?? '') ?>" 
                        placeholder="Cliente ou ID do pedido"
                    >
                </div>

                <!-- Status -->
                <div class="filter-item">
                    <label for="statusFilter">
                        <i class="fas fa-filter"></i> Status
                    </label>
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

                <!-- Data Início -->
                <div class="filter-item">
                    <label for="data_inicio">
                        <i class="fas fa-calendar-alt"></i> Data Início
                    </label>
                    <input 
                        type="date" 
                        id="data_inicio" 
                        value="<?= htmlspecialchars($dataInicio) ?>"
                    >
                </div>

                <!-- Data Fim -->
                <div class="filter-item">
                    <label for="data_fim">
                        <i class="fas fa-calendar-check"></i> Data Fim
                    </label>
                    <input 
                        type="date" 
                        id="data_fim" 
                        value="<?= htmlspecialchars($dataFim) ?>"
                    >
                </div>
            </div>
        </div>

        <!-- Container dos Pedidos -->
        <div id="pedidosContainer">
            <?php include 'pedidos_table.php'; ?>
        </div>
    </div>
</div>

<!-- Modal de Visualização de Pedido -->
<div class="modal fade" id="verPedidoModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-eye"></i> Visualizar Pedido
                </h5>
                <button type="button" class="close pedido-close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="verPedidoContent"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Observações -->
<div class="modal-overlay-custom" id="modalOverlay"></div>
<div id="observacoesModal" class="custom-modal-obs">
    <div class="modal-content-obs">
        <span class="close-modal-obs">&times;</span>
        <h2><i class="fas fa-comment"></i> Observações</h2>
        <div id="observacoesContent"></div>
    </div>
</div>

<!-- Botão Flutuante -->
<button id="btnNovoPedido" class="floating-button-pedidos" title="Novo Pedido">
    <i class="fas fa-plus"></i>
</button>

<!-- Notificação -->
<div id="notificacaoPedidos" class="notificacao-pedidos"></div>

<style>

    
/* CSS INLINE CORRIGIDO PARA SPA */
.pedidos-page-wrapper {
    width: 100%;
    max-width: 100%;
    padding: 0;
    margin: 0;
}

.pedidos-container {
    padding: 20px;
    max-width: 100%;
}

.pedidos-header h1 {
    font-size: 28px;
    font-weight: 700;
    color: #1F2937;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 12px;
}

/* FILTROS */
.pedidos-filtros-wrapper {
    margin-bottom: 25px;
}

.filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    background-color: #FFFFFF;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.filter-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex: 1;
    min-width: 180px;
}

.filter-item label {
    font-weight: 600;
    font-size: 13px;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 6px;
}

.filter-item input,
.filter-item select {
    padding: 10px 12px;
    border: 2px solid #E5E7EB;
    border-radius: 8px;
    font-size: 14px;
    color: #1F2937;
    background-color: #F9FAFB;
    transition: all 0.3s ease;
}

.filter-item input:focus,
.filter-item select:focus {
    outline: none;
    border-color: #FC4813;
    background-color: #FFFFFF;
    box-shadow: 0 0 0 3px rgba(252, 72, 19, 0.1);
}

.filter-item:first-child {
    flex: 2;
    min-width: 220px;
}

/* CARDS MOBILE */
.pedidos-cards-mobile {
    display: none;
}

.pedido-card {
    background-color: #FFFFFF;
    border-radius: 12px;
    padding: 18px;
    margin-bottom: 15px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
}

.pedido-card:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
    transform: translateY(-2px);
}

.pedido-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 2px solid #F3F4F6;
}

.pedido-id {
    font-size: 13px;
    font-weight: 700;
    color: #FC4813;
    background-color: rgba(252, 72, 19, 0.1);
    padding: 6px 10px;
    border-radius: 6px;
}

.pedido-cliente {
    font-size: 17px;
    font-weight: 700;
    color: #1F2937;
    margin-top: 8px;
    margin-bottom: 6px;
}

.pedido-cidade {
    font-size: 13px;
    color: #6B7280;
    display: flex;
    align-items: center;
    gap: 6px;
}

.pedido-badges {
    display: flex;
    flex-direction: column;
    gap: 6px;
    align-items: flex-end;
}

.pedido-card-body {
    display: grid;
    gap: 12px;
    margin-bottom: 15px;
}

.pedido-info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}

.pedido-info-label {
    font-size: 13px;
    color: #6B7280;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
}

.pedido-info-value {
    font-size: 14px;
    color: #1F2937;
    font-weight: 600;
    text-align: right;
}

.pedido-valor {
    font-size: 22px;
    font-weight: 700;
    color: #FC4813;
}

.pedido-card-actions {
    display: flex;
    gap: 10px;
    padding-top: 15px;
    border-top: 2px solid #F3F4F6;
}

.pedido-card-actions .btn {
    flex: 1;
    height: 42px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.pedido-card-actions .btn-view {
    background-color: #3B82F6;
    color: white;
}

.pedido-card-actions .btn-view:hover {
    background-color: #2563EB;
    transform: translateY(-2px);
}

.pedido-card-actions .btn-delete {
    background-color: #EF4444;
    color: white;
}

.pedido-card-actions .btn-delete:hover {
    background-color: #DC2626;
    transform: translateY(-2px);
}

/* STATUS BADGES */
.status-label {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 16px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    white-space: nowrap;
}

.status-label.pendente {
    background-color: rgba(245, 158, 11, 0.15);
    color: #F59E0B;
}

.status-label.em-separacao {
    background-color: rgba(59, 130, 246, 0.15);
    color: #3B82F6;
}

.status-label.pedido-separado {
    background-color: rgba(139, 92, 246, 0.15);
    color: #8B5CF6;
}

.status-label.aguardando-cliente {
    background-color: rgba(245, 158, 11, 0.15);
    color: #F59E0B;
}

.status-label.aguardando-retirada {
    background-color: rgba(6, 182, 212, 0.15);
    color: #06B6D4;
}

.status-label.pagamento-na-retirada {
    background-color: rgba(234, 179, 8, 0.15);
    color: #EAB308;
}

.status-label.concluido {
    background-color: rgba(16, 185, 129, 0.15);
    color: #10B981;
}

/* PAYMENT STATUS */
.payment-status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    font-size: 12px;
}

.payment-status.payment-yes {
    background-color: rgba(16, 185, 129, 0.15);
    color: #10B981;
}

.payment-status.payment-no {
    background-color: rgba(239, 68, 68, 0.15);
    color: #EF4444;
}

/* BOTÃO FLUTUANTE */
.floating-button-pedidos {
    position: fixed;
    bottom: 30px;
    right: 30px;
    background-color: #FC4813;
    color: white;
    border: none;
    border-radius: 50%;
    width: 56px;
    height: 56px;
    font-size: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 16px rgba(252, 72, 19, 0.4);
    cursor: pointer;
    z-index: 999;
    transition: all 0.3s ease;
}

.floating-button-pedidos:hover {
    background-color: #EA580C;
    transform: scale(1.1) rotate(90deg);
    box-shadow: 0 6px 20px rgba(252, 72, 19, 0.5);
}

/* MODAL OBSERVAÇÕES */
.modal-overlay-custom {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 2040;
}

.modal-overlay-custom.show {
    display: block;
}

.custom-modal-obs {
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background-color: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    z-index: 2050;
    max-width: 90%;
    width: 500px;
    max-height: 80vh;
    overflow-y: auto;
}

.custom-modal-obs.show {
    display: block;
}

.custom-modal-obs h2 {
    font-size: 20px;
    font-weight: 700;
    color: #1F2937;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.close-modal-obs {
    position: absolute;
    top: 15px;
    right: 20px;
    font-size: 26px;
    font-weight: bold;
    color: #6B7280;
    cursor: pointer;
    transition: color 0.3s ease;
}

.close-modal-obs:hover {
    color: #EF4444;
}

/* NOTIFICAÇÃO */
.notificacao-pedidos {
    position: fixed;
    top: -100px;
    left: 50%;
    transform: translateX(-50%);
    padding: 14px 22px;
    border-radius: 8px;
    color: white;
    font-weight: 600;
    z-index: 9999;
    transition: top 0.3s ease;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
    min-width: 280px;
    text-align: center;
}

.notificacao-pedidos.show {
    top: 30px;
}

.notificacao-pedidos.success {
    background-color: #10B981;
}

.notificacao-pedidos.error {
    background-color: #EF4444;
}

/* LOADING */
.loading-spinner {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 40px;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #E5E7EB;
    border-top-color: #FC4813;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* RESPONSIVIDADE */
@media (max-width: 768px) {
    .pedidos-container {
        padding: 15px;
    }

    .pedidos-header h1 {
        font-size: 22px;
        margin-bottom: 20px;
    }

    .filter-row {
        flex-direction: column;
        padding: 15px;
        gap: 12px;
    }

    .filter-item {
        width: 100%;
        min-width: auto;
    }

    .filter-item:first-child {
        flex: 1;
    }

    .filter-item input,
    .filter-item select {
        padding: 12px;
        font-size: 16px;
    }

    /* Mostrar cards, esconder tabela */
    .tabela-container {
        display: none !important;
    }

    .pedidos-cards-mobile {
        display: block;
    }

    .floating-button-pedidos {
        width: 52px;
        height: 52px;
        bottom: 20px;
        right: 20px;
        font-size: 20px;
    }

    .custom-modal-obs {
        width: 95%;
        padding: 20px;
    }
}

@media (max-width: 480px) {
    .pedidos-header h1 {
        font-size: 20px;
    }

    .pedido-card {
        padding: 15px;
    }

    .pedido-cliente {
        font-size: 16px;
    }

    .pedido-valor {
        font-size: 20px;
    }

    .pedido-card-actions {
        flex-direction: column;
    }

    .pedido-card-actions .btn {
        width: 100%;
    }
}
</style>

<script>
// JavaScript para funcionar dentro do SPA
$(document).ready(function() {
    console.log('Pedidos carregado no SPA');

    // Aplicar filtros
    function aplicarFiltrosPedidos() {
        const busca = $('#busca').val();
        const status = $('#statusFilter').val();
        const dataInicio = $('#data_inicio').val();
        const dataFim = $('#data_fim').val();

        const url = `pedidos.php?ajax=1&busca=${encodeURIComponent(busca)}&status=${encodeURIComponent(status)}&data_inicio=${dataInicio}&data_fim=${dataFim}`;

        $('#pedidosContainer').html('<div class="loading-spinner"><div class="spinner"></div></div>');

        $.ajax({
            url: url,
            method: 'GET',
            success: function(data) {
                $('#pedidosContainer').html(data);
                reaplicarEventosPedidos();
            },
            error: function() {
                $('#pedidosContainer').html('<p style="color: #EF4444; text-align: center;">Erro ao carregar pedidos.</p>');
            }
        });
    }

    // Reaplicar eventos após AJAX
    function reaplicarEventosPedidos() {
        // Botões de observação
        $('.btn-observacao, .btn-alerta').off('click').on('click', function() {
            const obs = $(this).data('observacoes');
            $('#observacoesContent').html(obs);
            $('#observacoesModal').addClass('show');
            $('#modalOverlay').addClass('show');
        });

        // Fechar modal
        $('.close-modal-obs, #modalOverlay').off('click').on('click', function() {
            $('#observacoesModal').removeClass('show');
            $('#modalOverlay').removeClass('show');
        });
    }

    // Eventos dos filtros
    $('#busca, #statusFilter, #data_inicio, #data_fim').on('change keyup', function() {
        clearTimeout(window.filtroTimeoutPedidos);
        window.filtroTimeoutPedidos = setTimeout(aplicarFiltrosPedidos, 500);
    });	

    // Inicializar eventos
    reaplicarEventosPedidos();

    // Fechar modal com ESC
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('#observacoesModal').removeClass('show');
            $('#modalOverlay').removeClass('show');
        }
    });
});
</script>
