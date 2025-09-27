<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Fiscal - Pedido <?= htmlspecialchars($pedido['id']) ?></title>
    <style>
        /* Estilo geral */
        body {
            font-family: 'Helvetica', Arial, sans-serif;
            color: #333;
            margin: 0;
            padding: 30px;
            background-color: #fff;
            line-height: 1.6;
        }

        /* Estilo do cabeçalho */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 20px;
        }

        .header img {
            max-width: 200px;
        }

        .pedido-info {
            text-align: right;
            font-size: 14px;
        }

        .pedido-info p {
            margin: 5px 0;
            font-weight: 500;
        }

        /* Título */
        h1 {
            text-align: center;
            font-size: 26px;
            margin: 20px 0;
            color: #FC4813;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Informações do cliente */
        .cliente-info {
            margin-top: 20px;
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        .cliente-info h3 {
            font-size: 18px;
            margin-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 5px;
            color: #555;
        }

        .cliente-info p {
            font-size: 14px;
            margin: 5px 0;
        }

        /* Estilo dos itens */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
        }

        table th, table td {
            padding: 12px 15px;
            border: 1px solid #ddd;
            text-align: left;
        }

        table th {
            background-color: #FC4813;
            color: #fff;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 13px;
        }

        table td {
            background-color: #fff;
        }

        /* Highlight para divergências */
        tr.divergencia {
            background-color: #FFF3CD !important;
            border: 2px solid #FD7E14 !important;
        }

        /* Disclaimer de divergência */
        .divergencia-disclaimer {
            margin-top: 15px;
            padding: 15px;
            background-color: #f9f9f9;
            border-left: 4px solid #FD7E14;
            font-size: 14px;
            color: #555;
            border-radius: 4px;
        }

        .divergencia-disclaimer p {
            margin: 0;
            font-style: italic;
        }

        /* Informações de pagamento e total */
        .total-info {
            margin-top: 20px;
            font-size: 16px;
            text-align: right;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        .total-info p {
            margin: 5px 0;
            font-weight: bold;
        }

        /* Observações */
        .observacoes {
            background-color: #f9f9f9;
            padding: 15px;
            margin-top: 20px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        .observacoes h3 {
            font-size: 16px;
            margin-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 5px;
            color: #555;
        }

        .observacoes p {
            font-size: 14px;
            margin: 0;
        }

        /* Rodapé */
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
            color: #777;
            border-top: 1px solid #e0e0e0;
            padding-top: 10px;
        }
    </style>
</head>
<body>

<div class="nota-fiscal">
    <div class="header">
        <img src="https://aogosto.com.br/atacado/uploads/logo-laranja.png" alt="Ao Gosto Carnes | Atacado" style="width: 150px; height: auto;">
        <div class="pedido-info">
            <p><strong>Pedido Nº:</strong> <?= htmlspecialchars($pedido['id']) ?></p>
            <p><strong>Data do Pedido:</strong> <?= date('d/m/Y', strtotime($pedido['data_pedido'])) ?></p>
            <p><strong>Data de Retirada:</strong> <?= date('d/m/Y', strtotime($pedido['data_retirada'])) ?></p>
            <?php if ($pedido['quantidade_caixas'] > 0): ?>
                <p><strong>Quantidade de Caixas:</strong> <?= (int)$pedido['quantidade_caixas'] ?></p>
            <?php endif; ?>
        </div>
    </div>

    <h1>Detalhes do Pedido</h1>

    <div class="cliente-info">
        <h3>Dados do Cliente</h3>
        <p><strong>Nome:</strong> <?= htmlspecialchars($pedido['cliente_nome']) ?></p>
        <p><strong>CNPJ/CPF:</strong> <?= htmlspecialchars($pedido['cliente_documento']) ?></p>
        <p><strong>Cidade:</strong> <?= htmlspecialchars($pedido['cliente_cidade']) ?></p>
        <p><strong>Telefone:</strong> <?= htmlspecialchars($pedido['cliente_telefone']) ?></p>
    </div>

    <h3>Itens do Pedido</h3>
    <?php
    $valorTotalRecalculado = 0;
    $hasDivergence = false; // Flag para verificar se há divergências
    ?>
    <table>
        <thead>
            <tr>
                <th>Produto</th>
                <th>Quantidade Solicitada</th>
                <th>Qtd. Disponível</th>
                <th>Valor Unitário</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($itens as $item):
                // Usar quantidade_separada se disponível (após "Em Separação"), caso contrário usar quantidade
                $quantidadeUsada = ($pedido['status'] !== 'Novo Pedido' && !is_null($item['quantidade_separada'])) ? $item['quantidade_separada'] : $item['quantidade'];
                $totalItem = $quantidadeUsada * $item['valor_unitario'];
                $valorTotalRecalculado += $totalItem;

                // Normalizar os valores para comparação
                $quantidade = floatval(str_replace(',', '.', $item['quantidade']));
                $quantidadeSeparada = !is_null($item['quantidade_separada']) ? floatval(str_replace(',', '.', $item['quantidade_separada'])) : null;

                // Verificar se há divergência com uma tolerância pequena
                $tolerancia = 0.001;
                $temDivergencia = !is_null($quantidadeSeparada) && (abs($quantidade - $quantidadeSeparada) > $tolerancia);

                // Atualizar a flag de divergência
                if ($temDivergencia) {
                    $hasDivergence = true;
                }
            ?>
                <tr class="<?= $temDivergencia ? 'divergencia' : '' ?>">
                    <td><?= htmlspecialchars($item['produto_nome']) ?> (<?= htmlspecialchars($item['produto_tipo']) ?>)</td>
                    <td>
                        <?php
                        if ($item['produto_tipo'] === 'UND') {
                            echo (int)$item['quantidade'];
                        } else {
                            echo number_format($item['quantidade'], 3, ',', '.');
                        }
                        ?>
                    </td>
                    <td>
                        <?php
                        if (!is_null($item['quantidade_separada'])) {
                            if ($item['produto_tipo'] === 'UND') {
                                echo (int)$item['quantidade_separada'];
                            } else {
                                echo number_format($item['quantidade_separada'], 3, ',', '.');
                            }
                        } else {
                            echo "N/A";
                        }
                        ?>
                    </td>
                    <td>R$ <?= number_format($item['valor_unitario'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($totalItem, 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($hasDivergence): ?>
        <div class="divergencia-disclaimer">
            <p><strong>Aviso:</strong> Este pedido apresenta divergências nos produtos, podendo faltar ou exceder em pequena quantidade, conforme indicado nas linhas destacadas da tabela. Segue o valor atualizado abaixo.</p>
        </div>
    <?php endif; ?>

    <div class="total-info">
        <p><strong>Valor Total:</strong> R$ <?= number_format($valorTotalRecalculado, 2, ',', '.') ?></p>
        <p><strong>Forma de Pagamento:</strong> <?= htmlspecialchars($pedido['forma_pagamento']) ?></p>
    </div>

    <?php if (!empty($pedido['observacoes'])): ?>
        <div class="observacoes">
            <h3>Observações</h3>
            <p><?= nl2br(htmlspecialchars($pedido['observacoes'])) ?></p>
        </div>
    <?php endif; ?>


</div>

</body>
</html>