<?php if (!empty($pedidos)): ?>
    <!-- Tabela Desktop/Tablet -->
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
                    <td data-label="ID"><?= $pedido['id'] ?></td>
                    <td data-label="Cliente"><?= htmlspecialchars($pedido['cliente']) ?></td>
                    <td data-label="Cidade"><?= htmlspecialchars($pedido['cidade']) ?></td>
                    <td data-label="Status">
                        <?php
                        // Normalizar o status para adicionar uma classe CSS específica
                        $statusNormalizado = strtolower(preg_replace('/[^\w]/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $pedido['status'])));
                        ?>
                        <span class="status-label <?= $statusNormalizado ?>">
                            <?= htmlspecialchars($pedido['status']) ?>
                        </span>
                    </td>
                    <td data-label="Pago?">
                        <?php if ($pedido['status_pagamento'] === 'Sim'): ?>
                            <span class="payment-status payment-yes">
                                <i class="fas fa-check"></i>
                            </span>
                        <?php else: ?>
                            <span class="payment-status payment-no">
                                <i class="fas fa-times"></i>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Valor Total">R$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?></td>
                    <td data-label="Ações">
                        <!-- Botão Ver Mais -->
                        <button class="btn btn-view detalhesPedido" data-id="<?= $pedido['id'] ?>" title="Visualizar pedido">
                            <i class="fas fa-eye"></i>
                        </button>

                        <!-- Botão de Alerta (Divergência) -->
                        <?php if (isset($pedido['tem_divergencia']) && $pedido['tem_divergencia']): ?>
                            <button class="btn btn-alerta" data-id="<?= $pedido['id'] ?>" data-observacoes="Divergência nas quantidades separadas. Verifique os itens do pedido." title="Divergência detectada">
                                <i class="fas fa-exclamation-triangle"></i>
                            </button>
                        <?php endif; ?>

                        <!-- Botão de Observação -->
                        <?php if (!empty($pedido['observacoes'])): ?>
                            <button class="btn btn-observacao" data-id="<?= $pedido['id'] ?>" data-observacoes="<?= nl2br(htmlspecialchars($pedido['observacoes'])) ?>" title="Ver observações">
                                <i class="fas fa-comment"></i>
                            </button>
                        <?php endif; ?>

                        <!-- Botão Lixeira -->
                        <button class="btn btn-delete excluirPedido" data-id="<?= $pedido['id'] ?>" title="Excluir pedido">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Cards Mobile -->
    <div class="pedidos-cards-mobile">
        <?php foreach ($pedidos as $pedido): ?>
            <?php
            $statusNormalizado = strtolower(preg_replace('/[^\w]/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $pedido['status'])));
            ?>
            <div class="pedido-card" data-id="<?= $pedido['id'] ?>">
                <!-- Header do Card -->
                <div class="pedido-card-header">
                    <div>
                        <div class="pedido-id">
                            #<?= str_pad($pedido['id'], 5, '0', STR_PAD_LEFT) ?>
                        </div>
                        <div class="pedido-cliente">
                            <?= htmlspecialchars($pedido['cliente']) ?>
                        </div>
                        <div class="pedido-cidade">
                            <i class="fas fa-map-marker-alt"></i> 
                            <?= htmlspecialchars($pedido['cidade']) ?>
                        </div>
                    </div>
                    <div class="pedido-badges">
                        <span class="status-label <?= $statusNormalizado ?>">
                            <?= htmlspecialchars($pedido['status']) ?>
                        </span>
                        <?php if (isset($pedido['tem_divergencia']) && $pedido['tem_divergencia']): ?>
                            <span class="status-label" style="background-color: rgba(245, 158, 11, 0.15); color: #F59E0B;">
                                <i class="fas fa-exclamation-triangle"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Body do Card -->
                <div class="pedido-card-body">
                    <!-- Data do Pedido -->
                    <div class="pedido-info-row">
                        <span class="pedido-info-label">
                            <i class="fas fa-calendar"></i> Data
                        </span>
                        <span class="pedido-info-value">
                            <?= date('d/m/Y', strtotime($pedido['data_pedido'])) ?>
                        </span>
                    </div>

                    <!-- Status de Pagamento -->
                    <div class="pedido-info-row">
                        <span class="pedido-info-label">
                            <i class="fas fa-credit-card"></i> Pagamento
                        </span>
                        <span class="pedido-info-value">
                            <?php if ($pedido['status_pagamento'] === 'Sim'): ?>
                                <span class="payment-status payment-yes">
                                    <i class="fas fa-check"></i> Pago
                                </span>
                            <?php else: ?>
                                <span class="payment-status payment-no">
                                    <i class="fas fa-times"></i> Pendente
                                </span>
                            <?php endif; ?>
                        </span>
                    </div>

                    <!-- Valor Total -->
                    <div class="pedido-info-row" style="margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--border-color);">
                        <span class="pedido-info-label" style="font-size: 16px;">
                            <i class="fas fa-money-bill-wave"></i> Valor Total
                        </span>
                        <span class="pedido-valor">
                            R$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?>
                        </span>
                    </div>

                    <!-- Observações (se existir) -->
                    <?php if (!empty($pedido['observacoes'])): ?>
                    <div class="pedido-info-row" style="margin-top: 10px;">
                        <span class="pedido-info-label">
                            <i class="fas fa-comment"></i> Observações
                        </span>
                        <button class="btn btn-observacao" data-id="<?= $pedido['id'] ?>" data-observacoes="<?= nl2br(htmlspecialchars($pedido['observacoes'])) ?>" style="position: static; width: auto; height: auto; padding: 4px 12px;">
                            Ver
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Actions do Card -->
                <div class="pedido-card-actions">
                    <button class="btn btn-view detalhesPedido" data-id="<?= $pedido['id'] ?>">
                        <i class="fas fa-eye"></i>
                        <span>Ver Detalhes</span>
                    </button>
                    
                    <button class="btn btn-delete excluirPedido" data-id="<?= $pedido['id'] ?>">
                        <i class="fas fa-trash-alt"></i>
                        <span>Excluir</span>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<?php else: ?>
    <div style="text-align: center; padding: 60px 20px;">
        <i class="fas fa-inbox" style="font-size: 64px; color: #D1D5DB; margin-bottom: 20px;"></i>
        <p style="font-size: 18px; color: #6B7280; font-weight: 500;">Nenhum pedido encontrado</p>
        <p style="font-size: 14px; color: #9CA3AF;">Ajuste os filtros ou crie um novo pedido</p>
    </div>
<?php endif; ?>