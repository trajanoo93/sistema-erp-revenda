<?php if (!empty($pedidos)): ?>
    <div class="tabela-container">
        <table class="tabela-generica" id="tabelaPedidos">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Cidade</th>
                    <th>Status</th>
                    <th>Pago?</th>
                    <th>Valor Total</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pedidos as $pedido): ?>
                <tr data-id="<?= $pedido['id'] ?>">
                    <td><?= $pedido['id'] ?></td>
                    <td><?= htmlspecialchars($pedido['cliente']) ?></td>
                    <td><?= htmlspecialchars($pedido['cidade']) ?></td>
                    <td>
                        <?php
                        // Normalize o status para adicionar uma classe CSS específica
                        $statusNormalizado = strtolower(preg_replace('/[^\w]/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $pedido['status'])));
                        ?>
                        <span class="status-label <?= $statusNormalizado ?>">
                            <?= htmlspecialchars($pedido['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($pedido['status_pagamento'] === 'Sim'): ?>
                            <span class="payment-status payment-yes">
                                <i class="fas fa-check"></i> <!-- Ícone de check -->
                            </span>
                        <?php else: ?>
                            <span class="payment-status payment-no">
                                <i class="fas fa-times"></i> <!-- Ícone de X -->
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>R$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?></td>
                    <td>
                        <!-- Botão Ver Mais -->
                        <button class="btn btn-view detalhesPedido" data-id="<?= $pedido['id'] ?>">
                            <i class="fas fa-eye"></i>
                        </button>

                        <!-- Botão de Alerta (Divergência) -->
                        <?php if (isset($pedido['tem_divergencia']) && $pedido['tem_divergencia']): ?>
                            <button class="btn btn-alerta" data-id="<?= $pedido['id'] ?>" data-observacoes="Divergência nas quantidades separadas. Verifique os itens do pedido.">
                                <i class="fas fa-exclamation-triangle"></i>
                            </button>
                        <?php endif; ?>

                        <!-- Botão de Observação -->
                        <?php if (!empty($pedido['observacoes'])): ?>
                            <button class="btn btn-observacao" data-id="<?= $pedido['id'] ?>" data-observacoes="<?= nl2br(htmlspecialchars($pedido['observacoes'])) ?>">
                                <i class="fas fa-comment"></i>
                            </button>
                        <?php endif; ?>

                        <!-- Botão Lixeira (movido para o final) -->
                        <button class="btn btn-delete excluirPedido" data-id="<?= $pedido['id'] ?>">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <p>Nenhum pedido encontrado.</p>
<?php endif; ?>