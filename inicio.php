<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$nome_usuario = isset($_SESSION['usuario_nome']) ? $_SESSION['usuario_nome'] : 'Visitante';

// Mostrar erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluindo a conexão com o banco de dados
require_once 'backend/config/db.php';

// Definir o intervalo de datas padrão (mês atual)
$dataInicio = date('Y-m-01');
$dataFim = date('Y-m-t');

// Verificar se há filtro de datas enviado via GET (para AJAX)
if (isset($_GET['data_inicio']) && isset($_GET['data_fim'])) {
    $dataInicio = $_GET['data_inicio'];
    $dataFim = $_GET['data_fim'];
}

// Consulta SQL para contar pedidos por status
$queryStatus = "
    SELECT p.status, COUNT(*) AS quantidade 
    FROM pedidos p
    WHERE p.data_pedido >= :data_inicio 
    AND p.data_pedido <= :data_fim
    GROUP BY p.status
";
$stmtStatus = $conn->prepare($queryStatus);
$stmtStatus->bindParam(':data_inicio', $dataInicio);
$stmtStatus->bindParam(':data_fim', $dataFim);
$stmtStatus->execute();
$statusCounts = $stmtStatus->fetchAll(PDO::FETCH_ASSOC);

// Consulta SQL para o total de vendas no intervalo
$queryVendas = "
    SELECT SUM(valor_total) AS total_vendas 
    FROM pedidos 
    WHERE data_pedido >= :data_inicio 
    AND data_pedido <= :data_fim
    AND status_pagamento = 'Sim'
";
$stmtVendas = $conn->prepare($queryVendas);
$stmtVendas->bindParam(':data_inicio', $dataInicio);
$stmtVendas->bindParam(':data_fim', $dataFim);
$stmtVendas->execute();
$totalVendas = $stmtVendas->fetch(PDO::FETCH_ASSOC);

// Consulta SQL para obter as vendas diárias no intervalo
$queryVendasDia = "
    SELECT DATE_FORMAT(d.dia, '%Y-%m-%d') as dia, COALESCE(SUM(p.valor_total), 0) as total_vendas
    FROM (
        SELECT DATE_FORMAT(DATE_ADD(:data_inicio, INTERVAL (d - 1) DAY), '%Y-%m-%d') as dia
        FROM (
            SELECT a.N + b.N * 10 + 1 as d
            FROM 
                (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
                (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
            ORDER BY d
        ) dias
        WHERE DATE_ADD(:data_inicio, INTERVAL (d - 1) DAY) <= :data_fim
    ) d
    LEFT JOIN pedidos p ON DATE(p.data_pedido) = d.dia
    WHERE p.status_pagamento = 'Sim' OR p.status_pagamento IS NULL
    GROUP BY d.dia
    ORDER BY d.dia
";
$stmtVendasDia = $conn->prepare($queryVendasDia);
$stmtVendasDia->bindParam(':data_inicio', $dataInicio);
$stmtVendasDia->bindParam(':data_fim', $dataFim);
$stmtVendasDia->execute();
$vendasDia = $stmtVendasDia->fetchAll(PDO::FETCH_ASSOC);

// Log para depuração
error_log("Total de Vendas: " . print_r($totalVendas, true));
error_log("Vendas Diárias: " . print_r($vendasDia, true));
error_log("Status Counts: " . print_r($statusCounts, true));

// Dados para os gráficos (serão usados pelo JavaScript no dashboard.php)
$chartData = [
    'statusLabels' => array_column($statusCounts, 'status'),
    'statusData' => array_map('intval', array_column($statusCounts, 'quantidade')),
    'diasLabels' => array_column($vendasDia, 'dia'),
    'vendasData' => array_map('floatval', array_column($vendasDia, 'total_vendas')),
    'totalVendas' => number_format($totalVendas['total_vendas'] ?? 0, 2, ',', '.')
];
?>

<!-- Exibe a saudação ao usuário -->
<h1 class="dashboard-title">Bem-vindo, <?= htmlspecialchars($nome_usuario) ?>!</h1>

<!-- Filtro de datas -->
<div class="filter-container">
    <label for="data_inicio">Data Início:</label>
    <input type="text" id="data_inicio" value="<?= $dataInicio ?>">
    <label for="data_fim">Data Fim:</label>
    <input type="text" id="data_fim" value="<?= $dataFim ?>">
    <button id="filtrarDatas">Filtrar</button>
    <button id="resetarDatas">Resetar</button>
</div>

<div class="dashboard-container">
    <!-- Card com Gráfico de Pizza para Pedidos por Status -->
    <div class="card">
        <h2><i class="fas fa-chart-pie"></i> Pedidos por Status</h2>
        <canvas id="statusChart"></canvas>
    </div>

    <!-- Card com Gráfico de Linha para Vendas no Período -->
    <div class="card">
        <h2><i class="fas fa-chart-line"></i> Vendas ao Longo do Período</h2>
        <canvas id="vendasChart"></canvas>
    </div>

    <!-- Card Total de Vendas no Período -->
    <div class="card total-vendas-card">
        <h2><i class="fas fa-dollar-sign"></i> Total de Vendas no Período</h2>
        <p class="total-vendas-value">R$ <span id="totalVendas"><?= number_format($totalVendas['total_vendas'] ?? 0, 2, ',', '.'); ?></span></p>
    </div>
</div>

<!-- Dados para os gráficos, encapsulados em um elemento data -->
<div id="chartData" style="display: none;" 
    data-status-labels='<?= json_encode($chartData['statusLabels'], JSON_HEX_QUOT | JSON_HEX_APOS); ?>' 
    data-status-data='<?= json_encode($chartData['statusData'], JSON_HEX_QUOT | JSON_HEX_APOS); ?>' 
    data-dias-labels='<?= json_encode($chartData['diasLabels'], JSON_HEX_QUOT | JSON_HEX_APOS); ?>' 
    data-vendas-data='<?= json_encode($chartData['vendasData'], JSON_HEX_QUOT | JSON_HEX_APOS); ?>' 
    data-total-vendas='<?= htmlspecialchars($chartData['totalVendas']); ?>'>
</div>

<style>
    /* Estilos ajustados para o dashboard */
    :root {
        --primary-color: #FC4813;       /* Laranja vibrante */
        --accent-color: #FFFFFF;        /* Branco para texto e ícones */
        --hover-bg-color: #F97316;      /* Laranja mais claro para hover */
        --gray-bg: #F9FAFB;             /* Fundo cinza claro */
        --text-primary: #1F2937;        /* Texto primário (cinza escuro) */
        --text-secondary: #4B5563;      /* Texto secundário (cinza mais claro) */
        --success-color: #2ECC71;       /* Verde para valores financeiros */
    }

    .dashboard-title {
        font-size: 32px;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 30px;
        padding-left: 20px;
    }

    .filter-container {
        background: var(--accent-color);
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        padding: 20px;
        margin-bottom: 30px;
        display: flex;
        align-items: center;
        gap: 20px;
        margin-left: 20px;
        margin-right: 20px;
    }

    .filter-container label {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .filter-container input {
        padding: 10px;
        border: 1px solid #D1D5DB;
        border-radius: 6px;
        font-size: 14px;
        width: 150px;
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }

    .filter-container input:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 6px rgba(252, 72, 19, 0.3);
        outline: none;
    }

    .filter-container button {
        padding: 10px 20px;
        background-color: var(--primary-color);
        color: var(--accent-color);
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: background-color 0.3s ease, transform 0.2s ease;
    }

    .filter-container button:hover {
        background-color: var(--hover-bg-color);
        transform: scale(1.05);
    }

    .dashboard-container {
        display: flex;
        flex-wrap: wrap;
        gap: 30px;
        justify-content: space-between;
        padding: 0 20px;
    }

    .card {
        background: var(--accent-color);
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        padding: 20px;
        flex: 1;
        min-width: 300px;
        max-width: 32%;
        display: flex;
        flex-direction: column;
        align-items: center;
        transition: transform 0.2s ease;
    }

    .card:hover {
        transform: translateY(-5px);
    }

    .card h2 {
        font-size: 18px;
        font-weight: 600;
        color: var(--primary-color);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .card canvas {
        max-height: 250px;
        width: 100%;
        border-radius: 8px;
    }

    .card.total-vendas-card {
        background: linear-gradient(135deg, #FFFFFF 0%, #F9FAFB 100%);
        color: var(--text-primary);
    }

    .card.total-vendas-card h2 {
        color: var(--text-primary);
    }

    .total-vendas-value {
        font-size: 28px;
        font-weight: 700;
        text-align: center;
        margin: 15px 0 0 0;
        color: var(--success-color);
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    .no-data {
        text-align: center;
        color: var(--text-secondary);
        font-style: italic;
        font-size: 14px;
        margin-top: 10px;
    }

    /* Responsividade */
    @media (max-width: 768px) {
        .dashboard-container {
            flex-direction: column;
            gap: 20px;
        }

        .card {
            max-width: 100%;
        }

        .filter-container {
            flex-direction: column;
            align-items: stretch;
            gap: 15px;
        }

        .filter-container input {
            width: 100%;
        }

        .filter-container button {
            width: 100%;
        }
    }
</style>