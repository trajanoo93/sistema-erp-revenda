<?php
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

// Buscar os pedidos
$stmt = $conn->prepare("
    SELECT p.id, p.status, p.quantidade_caixas, p.status_pagamento, c.nome AS cliente_nome, c.cidade AS cliente_cidade
    FROM pedidos p
    JOIN clientes c ON p.cliente_id = c.id
    WHERE p.status NOT IN ('Concluído', 'Pedido Separado', 'Aguardando Cliente')
");
$stmt->execute();
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Função para buscar os itens do pedido
function buscarItensPedido($pedido_id, $conn) {
    $stmt = $conn->prepare("
        SELECT ip.produto_id, ip.quantidade, ip.quantidade_separada, ip.valor_unitario, pr.nome AS produto_nome, pr.tipo AS produto_tipo
        FROM itens_pedido ip
        JOIN produtos pr ON ip.produto_id = pr.id
        WHERE ip.pedido_id = :pedido_id
    ");
    $stmt->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos - Expedição</title>
    <link rel="stylesheet" href="public/css/modal.css?v=8.0">
    <link rel="stylesheet" href="public/css/style.css?v=118.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .btn-check {
            background-color: green;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-size: 1em;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
        }
        .btn-check i {
            color: white;
        }
        .modal {
            display: none;
        }
        .modal-content {
            /* Estilos do modal */
        }
    </style>
</head>
<body>
    <div class="exp-pedidos-container">
        <h1>Pedidos - Expedição</h1>
        <table border="1">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Cidade</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pedidos as $pedido): ?>
                <tr>
                    <td><?= htmlspecialchars($pedido['id']) ?></td>
                    <td><?= htmlspecialchars($pedido['cliente_nome']) ?></td>
                    <td><?= htmlspecialchars($pedido['cliente_cidade']) ?></td>
                    <td>
                        <span class="status-label <?= strtolower(str_replace([' ', 'ç', 'ã', 'é', 'í', 'ó'], ['-', 'c', 'a', 'e', 'i', 'o'], $pedido['status'])) ?>">
                            <?= htmlspecialchars($pedido['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($pedido['status'] == 'pendente'): ?>
                            <button class="btn-check" onclick="mudarStatusParaSeparacao(<?= $pedido['id'] ?>)">
                                <i class="fas fa-check"></i>
                            </button>
                        <?php else: ?>
                            <button onclick="abrirDetalhes(<?= $pedido['id'] ?>)">
                                <i class="fas fa-eye"></i>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="modal" id="detalhesModal" style="display:none;">
        <div class="modal-content">
            <h2>Detalhes do Pedido</h2>
            <table class="detalhes-tabela">
                <tr>
                    <th>ID DO PEDIDO:</th>
                    <td><span id="pedidoId"></span></td>
                </tr>
                <tr>
                    <th>CLIENTE:</th>
                    <td><span id="clienteNome"></span></td>
                </tr>
                <tr>
                    <th>CIDADE:</th>
                    <td><span id="clienteCidade"></span></td>
                </tr>
                <tr>
                    <th>STATUS:</th>
                    <td><span id="statusPedido"></span></td>
                </tr>
            </table>
            <h3>Itens do Pedido</h3>
            <table class="itens-tabela">
                <thead>
                    <tr>
                        <th>PRODUTO</th>
                        <th>QUANTIDADE</th>
                        <th>QTD. DISPONÍVEL</th>
                    </tr>
                </thead>
                <tbody id="itensPedido">
                </tbody>
            </table>
            <div class="input-section">
                <label for="quantidadeCaixas">Quantidade de Caixas:</label>
                <input type="number" id="quantidadeCaixas" value="0">
            </div>
            <div class="botoes-acoes">
                <button id="marcarRetiradoBtn" class="btn-acao" onclick="verificarQuantidade()">Marcar como Retirado</button>
            </div>
        </div>
    </div>
    <div class="modal" id="observacaoDivergenciaModal" style="display:none;">
        <div class="modal-content">
            <h2>Motivo da Divergência</h2>
            <textarea id="observacao" placeholder="Insira o motivo da divergência"></textarea>
            <button onclick="salvarObservacao()">Salvar</button>
        </div>
    </div>
    <script>
    $(document).ready(function() {
        var pedidoAtualId;
        var quantidadePedida = {};
        function mudarStatusParaSeparacao(id) {
            $.ajax({
                url: 'exp/atualizar_pedido.php',
                method: 'POST',
                dataType: 'json',
                contentType: 'application/json',
                data: JSON.stringify({ id: id, status: 'Em Separação' }),
                success: function(response) {
                    console.log(response);
                    if (response.success) {
                        alert('Status alterado para "Em Separação".');
                        location.reload();
                    } else {
                        alert('Erro ao atualizar o pedido: ' + (response.error || 'Desconhecido'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Erro ao atualizar status:", error);
                    alert('Erro ao tentar atualizar o pedido.');
                }
            });
        }
        function abrirDetalhes(id) {
            $.ajax({
                url: 'exp/detalhes_pedido.php',
                method: 'GET',
                data: { id: id, ajax: 1 },
                dataType: "json",
                success: function(response) {
                    console.log("Resposta recebida de detalhes_pedido.php:", response);
                    if (response.error) {
                        alert('Erro: ' + response.error);
                        return;
                    }
                    pedidoAtualId = response.pedido.id;
                    $('#pedidoId').text(response.pedido.id);
                    $('#clienteNome').text(response.pedido.cliente_nome);
                    $('#clienteCidade').text(response.pedido.cliente_cidade);
                    $('#statusPedido').text(response.pedido.status);
                    $('#quantidadeCaixas').val(response.pedido.quantidade_caixas);
                    var itensHtml = '';
                    response.itens.forEach(function(item) {
                        var quantidade = parseFloat(String(item.quantidade).replace(',', '.')) || 0;
                        quantidadePedida[item.produto_id] = quantidade;
                        itensHtml += `
                            <tr>
                                <td>${item.produto_nome}</td>
                                <td>${item.quantidade}</td>
                                <td><input type="number" class="qtd-disponivel" data-produto-id="${item.produto_id}" value="${item.quantidade_separada || 0}" step="0.001"></td>
                            </tr>`;
                    });
                    $('#itensPedido').html(itensHtml);
                    if (response.pedido.status === 'Em Separação') {
                        $('.botoes-acoes').html('<button id="marcarSeparadoBtn" class="btn-acao">Marcar como Separado</button>');
                        $('#marcarSeparadoBtn').off('click').on('click', marcarComoSeparado);
                    } else {
                        $('.botoes-acoes').html('<button id="marcarRetiradoBtn" class="btn-acao">Marcar como Retirado</button>');
                        $('#marcarRetiradoBtn').off('click').on('click', verificarQuantidade);
                    }
                    $('#detalhesModal').show();
                },
                error: function(xhr, status, error) {
                    console.error("Erro ao carregar detalhes do pedido:", error);
                    console.error("Status:", status);
                    console.error("Response Text:", xhr.responseText);
                    alert('Erro ao buscar os detalhes do pedido.');
                }
            });
        }
        function marcarComoSeparado() {
            var totalCaixas = $('#quantidadeCaixas').val();
            var itensQuantidades = {};
            $('.qtd-disponivel').each(function() {
                var produtoId = $(this).data('produto-id');
                var qtdDisponivel = parseFloat($(this).val().replace(',', '.')) || 0;
                itensQuantidades[produtoId] = qtdDisponivel;
            });
            var observacoes = '';
            $.ajax({
                url: 'exp/atualizar_pedido.php',
                method: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                data: JSON.stringify({
                    id: pedidoAtualId,
                    status: "Pedido Separado",
                    quantidade_caixas: totalCaixas,
                    observacoes: observacoes,
                    itens_quantidades: itensQuantidades
                }),
                success: function(response) {
                    console.log(response);
                    if (response.success) {
                        alert("Pedido atualizado para 'Pedido Separado' com sucesso!");
                        location.reload();
                    } else if (response.error) {
                        alert("Erro: " + response.error);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Erro ao atualizar pedido:", error);
                    alert("Erro ao tentar atualizar o pedido.");
                }
            });
        }
        function verificarQuantidade() {
            var divergenciaEncontrada = false;
            var totalCaixas = $('#quantidadeCaixas').val();
            var itensQuantidades = {};
            $('.qtd-disponivel').each(function() {
                var produtoId = $(this).data('produto-id');
                var qtdDisponivel = parseFloat($(this).val().replace(',', '.')) || 0;
                var qtdPedido = parseFloat(quantidadePedida[produtoId]) || 0;
                console.log("Produto ID:", produtoId, "Quantidade Informada:", qtdDisponivel, "Quantidade Pedido:", qtdPedido);
                if (qtdDisponivel !== qtdPedido) {
                    divergenciaEncontrada = true;
                }
                itensQuantidades[produtoId] = qtdDisponivel;
            });
            if (divergenciaEncontrada) {
                $('#observacaoDivergenciaModal').show();
                window.itensQuantidades = itensQuantidades;
                window.totalCaixas = totalCaixas;
            } else {
                marcarComoRetirado(totalCaixas, "", itensQuantidades);
            }
        }
        function salvarObservacao() {
            var observacao = $('#observacao').val();
            $('#observacaoDivergenciaModal').hide();
            marcarComoRetirado(window.totalCaixas, observacao, window.itensQuantidades);
        }
        function marcarComoRetirado(totalCaixas, observacoes, itensQuantidades) {
            var pedidoId = pedidoAtualId;
            totalCaixas = parseFloat(String(totalCaixas).replace(',', '.')) || 0;
            $.ajax({
                url: 'exp/atualizar_pedido.php',
                method: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                data: JSON.stringify({
                    id: pedidoId,
                    status: "Concluído",
                    quantidade_caixas: totalCaixas,
                    observacoes: observacoes,
                    itens_quantidades: itensQuantidades
                }),
                success: function(response) {
                    console.log(response);
                    if (response.success) {
                        alert("Pedido atualizado com sucesso!");
                        location.reload();
                    } else if (response.error) {
                        alert("Erro: " + response.error);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Erro ao atualizar pedido:", error);
                    alert("Erro ao tentar atualizar o pedido.");
                }
            });
        }
        $(document).on('click', function(event) {
            if ($(event.target).closest('.modal-content').length === 0 && $(event.target).closest('.modal').length > 0) {
                $('#detalhesModal').hide();
                $('#observacaoDivergenciaModal').hide();
            }
        });
        window.abrirDetalhes = abrirDetalhes;
        window.mudarStatusParaSeparacao = mudarStatusParaSeparacao;
        window.verificarQuantidade = verificarQuantidade;
        window.salvarObservacao = salvarObservacao;
    });
    </script>
</body>
</html>