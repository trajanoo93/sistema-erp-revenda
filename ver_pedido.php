<?php
opcache_reset();
session_start();
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
   
    // Criar diretório logs/ se não existir
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
   
    // Criar arquivo de log se não existir
    if (!file_exists($logFile)) {
        touch($logFile);
        chmod($logFile, 0664);
    }
   
    // Escrever log apenas se o arquivo for gravável
    if (is_writable($logFile)) {
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        if ($nivel === 'ERROR') {
            error_log($logEntry);
        }
    } else {
        error_log("Não foi possível escrever no arquivo de log: $logFile");
    }
}

// Definir cabeçalho JSON para chamadas AJAX
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
}

if (!isset($_GET['id'])) {
    registrarLog("Erro: ID do pedido não fornecido em ver_pedido.php", 'ERROR');
    if (isset($_GET['ajax'])) {
        echo json_encode(['status' => 'error', 'message' => 'ID do pedido não fornecido.']);
    } else {
        echo '<p>ID do pedido não fornecido.</p>';
    }
    exit;
}

$pedido_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
registrarLog("Carregando detalhes do pedido ID: $pedido_id", 'INFO');

try {
    // Consulta para obter os detalhes do pedido
    $stmt = $conn->prepare("
        SELECT p.*, c.nome AS cliente_nome, c.cidade AS cliente_cidade, c.documento AS cliente_documento, c.telefone AS cliente_telefone
        FROM pedidos p
        JOIN clientes c ON p.cliente_id = c.id
        WHERE p.id = :id
    ");
    $stmt->bindParam(':id', $pedido_id, PDO::PARAM_INT);
    $stmt->execute();
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        registrarLog("Erro: Pedido não encontrado. ID: $pedido_id", 'ERROR');
        if (isset($_GET['ajax'])) {
            echo json_encode(['status' => 'error', 'message' => 'Pedido não encontrado.']);
        } else {
            echo '<p>Pedido não encontrado.</p>';
        }
        exit;
    }

    registrarLog("Pedido encontrado. Status: {$pedido['status']}", 'INFO');

    // Consulta para obter os itens do pedido
    $stmtItens = $conn->prepare("
        SELECT i.produto_id, i.quantidade, i.quantidade_separada, i.valor_unitario, p.nome AS produto_nome, p.tipo AS produto_tipo
        FROM itens_pedido i
        JOIN produtos p ON i.produto_id = p.id
        WHERE i.pedido_id = :pedido_id
    ");
    $stmtItens->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
    $stmtItens->execute();
    $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
    registrarLog("Itens do pedido carregados. Total: " . count($itens), 'INFO');

    // Consulta para obter comprovantes
    $stmtComprovantes = $conn->prepare("
        SELECT id, caminho_arquivo, data_upload
        FROM comprovantes
        WHERE pedido_id = :pedido_id
        ORDER BY data_upload DESC
    ");
    $stmtComprovantes->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
    $stmtComprovantes->execute();
    $comprovantes = $stmtComprovantes->fetchAll(PDO::FETCH_ASSOC);
    registrarLog("Comprovantes carregados. Total: " . count($comprovantes), 'INFO');

    // Suporte para requisição AJAX
    if (isset($_GET['ajax'])) {
        echo json_encode([
            'status' => 'success',
            'pedido' => [
                'id' => $pedido['id'],
                'data_pedido' => date('d/m/Y', strtotime($pedido['data_pedido'])),
                'data_retirada' => date('d/m/Y', strtotime($pedido['data_retirada'])),
                'forma_pagamento' => $pedido['forma_pagamento'] ?? 'Não especificada',
                'status_pagamento' => $pedido['status_pagamento'],
                'valor_total' => number_format($pedido['valor_total'], 2, '.', ''),
                'observacoes' => $pedido['observacoes'] ?? '',
                'status' => $pedido['status'],
                'cliente_nome' => $pedido['cliente_nome'],
                'cliente_cidade' => $pedido['cliente_cidade'],
                'cliente_documento' => $pedido['cliente_documento'] ?? 'Não informado',
                'cliente_telefone' => $pedido['cliente_telefone'],
                'quantidade_caixas' => $pedido['quantidade_caixas'] ?? 0
            ],
            'itens' => array_map(function($item) {
                return [
                    'produto_id' => $item['produto_id'],
                    'produto_nome' => $item['produto_nome'],
                    'quantidade' => $item['produto_tipo'] === 'UND' ? number_format($item['quantidade'], 0, ',', '.') : number_format($item['quantidade'], 3, ',', '.'),
                    'quantidade_separada' => $item['quantidade_separada'] !== null ? ($item['produto_tipo'] === 'UND' ? number_format($item['quantidade_separada'], 0, ',', '.') : number_format($item['quantidade_separada'], 3, ',', '.')) : null,
                    'valor_unitario' => number_format($item['valor_unitario'], 2, '.', ''),
                    'produto_tipo' => $item['produto_tipo']
                ];
            }, $itens),
            'comprovantes' => array_map(function($comprovante) {
                return [
                    'id' => $comprovante['id'],
                    'caminho_arquivo' => $comprovante['caminho_arquivo'],
                    'data_upload' => date('d/m/Y H:i', strtotime($comprovante['data_upload']))
                ];
            }, $comprovantes)
        ]);
        exit;
    }

    // Configuração para renderização HTML
    $statusPedido = $pedido['status'];
    $statusExibirCaixas = ['Aguardando Cliente', 'Aguardando Retirada', 'Pagamento na Retirada', 'Concluído'];
    $statusExibirQuantidadeSeparada = ['Pedido Separado', 'Aguardando Cliente', 'Aguardando Pagamento', 'Aguardando Retirada', 'Pagamento na Retirada', 'Concluído'];
    $exibirQuantidadeSeparada = in_array($statusPedido, $statusExibirQuantidadeSeparada);
    $statusOrder = ['pendente', 'Em Separação', 'Pedido Separado', 'Aguardando Cliente', 'Aguardando Pagamento', 'Aguardando Retirada', 'Pagamento na Retirada', 'Concluído'];
    $statusIndex = array_search($statusPedido, $statusOrder);
    $pedidoSeparadoIndex = array_search('Pedido Separado', $statusOrder);
    $isAfterPedidoSeparado = $statusIndex >= $pedidoSeparadoIndex;
    registrarLog("Status: $statusPedido, Exibir Qtd Separada: " . ($exibirQuantidadeSeparada ? 'true' : 'false') . ", Is after Pedido Separado: " . ($isAfterPedidoSeparado ? 'true' : 'false'), 'INFO');
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="public/css/ver_pedido.css?v=<?= time() ?>">
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
        <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    </head>
    <body>
        <div class="nota-fiscal" id="notaFiscal" data-id="<?= htmlspecialchars($pedido['id']) ?>" data-status="<?= htmlspecialchars($statusPedido) ?>">
            <div class="edit-pedido-header">
                <button class="btn btn-primary btn-edit-pedido" onclick="toggleEditMode(true)">
                    <i class="fas fa-edit"></i> Editar Pedido
                </button>
            </div>
            <div id="viewMode">
                <div class="nf-header">
                    <div class="nf-info-empresa">
                        <img src="https://aogosto.com.br/atacado/uploads/logo-laranja.png" alt="Ao Gosto Carnes" class="nf-logo">
                    </div>
                    <div class="nf-info-pedido">
                        <p><strong>Pedido Nº:</strong> <?= htmlspecialchars($pedido['id']) ?></p>
                        <p><strong>Data do Pedido:</strong> <?= date('d/m/Y', strtotime($pedido['data_pedido'])) ?></p>
                        <p><strong>Data de Retirada:</strong> <span id="dataRetiradaView"><?= date('d/m/Y', strtotime($pedido['data_retirada'])) ?></span></p>
                        <?php if (in_array($statusPedido, $statusExibirCaixas) && $pedido['quantidade_caixas'] > 0): ?>
                            <p><strong>Quantidade de Caixas:</strong> <?= (int)$pedido['quantidade_caixas'] ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="nf-info-cliente">
                    <h3>Dados do Cliente</h3>
                    <div class="cliente-dados">
                        <p><strong>Nome:</strong> <?= htmlspecialchars($pedido['cliente_nome']) ?></p>
                        <p><strong>CNPJ/CPF:</strong> <?= htmlspecialchars($pedido['cliente_documento'] ?? 'Não informado') ?></p>
                        <p><strong>Cidade:</strong> <?= htmlspecialchars($pedido['cliente_cidade']) ?></p>
                        <p><strong>Telefone:</strong> <?= htmlspecialchars($pedido['cliente_telefone']) ?></p>
                    </div>
                </div>
                <h3>Itens do Pedido</h3>
                <table class="nf-itens custom-table">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Qtd.</th>
                            <?php if ($exibirQuantidadeSeparada): ?>
                                <th>Qtd Disponível</th>
                            <?php endif; ?>
                            <th>Valor Unitário</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody id="itensView">
                        <?php foreach ($itens as $item): ?>
                            <?php
                            $quantidadeUsada = ($statusPedido !== 'pendente' && !is_null($item['quantidade_separada'])) ? $item['quantidade_separada'] : $item['quantidade'];
                            $totalItem = $quantidadeUsada * $item['valor_unitario'];
                            $quantidade = floatval(str_replace(',', '.', $item['quantidade']));
                            $quantidadeSeparada = !is_null($item['quantidade_separada']) ? floatval(str_replace(',', '.', $item['quantidade_separada'])) : null;
                            if ($item['produto_tipo'] === "UND") {
                                $quantidade = round($quantidade, 0);
                                $quantidadeSeparada = !is_null($quantidadeSeparada) ? round($quantidadeSeparada, 0) : null;
                            } else {
                                $quantidade = round($quantidade, 3);
                                $quantidadeSeparada = !is_null($quantidadeSeparada) ? round($quantidadeSeparada, 3) : null;
                            }
                            $tolerancia = 0.001;
                            $temDivergencia = $exibirQuantidadeSeparada && !is_null($quantidadeSeparada) && (abs($quantidade - $quantidadeSeparada) > $tolerancia);
                            registrarLog("Produto: {$item['produto_nome']}, Quantidade: $quantidade, Separada: " . ($quantidadeSeparada ?? 'N/A') . ", Classe Aplicada: " . ($temDivergencia ? 'highlight-divergencia' : 'nenhuma'), 'INFO');
                            ?>
                            <tr class="<?= $temDivergencia ? 'highlight-divergencia' : '' ?>">
                                <td><?= htmlspecialchars($item['produto_nome']) ?></td>
                                <td><?= $item['produto_tipo'] === "UND" ? number_format($quantidade, 0, ',', '.') : number_format($quantidade, 3, ',', '.') ?></td>
                                <?php if ($exibirQuantidadeSeparada): ?>
                                    <td><?= !is_null($quantidadeSeparada) ? ($item['produto_tipo'] === "UND" ? number_format($quantidadeSeparada, 0, ',', '.') : number_format($quantidadeSeparada, 3, ',', '.')) : "N/A" ?></td>
                                <?php endif; ?>
                                <td>R$ <?= number_format($item['valor_unitario'], 2, ',', '.') ?></td>
                                <td>R$ <?= number_format($totalItem, 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="nf-total">
                    <p><strong>Valor Total:</strong> R$ <span id="valorTotalView"><?= number_format($pedido['valor_total'], 2, ',', '.') ?></span></p>
                    <p><strong>Forma de Pagamento:</strong> <?= htmlspecialchars($pedido['forma_pagamento'] ?? 'Não especificada') ?></p>
                </div>
                <?php if (!empty($comprovantes)): ?>
                    <div class="comprovantes-list">
                        <h3>Comprovantes Enviados</h3>
                        <ul>
                            <?php foreach ($comprovantes as $comprovante): ?>
                                <li>
                                    <a href="<?= htmlspecialchars($comprovante['caminho_arquivo']) ?>" download>
                                        Comprovante - <?= date('d/m/Y H:i', strtotime($comprovante['data_upload'])) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <div class="nf-observacoes">
                    <h3>Observações</h3>
                    <p id="observacoesView"><?= nl2br(htmlspecialchars($pedido['observacoes'] ?? 'Nenhuma observação.')) ?></p>
                </div>
            </div>
            <div id="editMode" style="display: none;">
                <div class="nf-header">
                    <div class="nf-info-empresa">
                        <img src="https://aogosto.com.br/atacado/uploads/logo-laranja.png" alt="Logo da Empresa" class="nf-logo">
                    </div>
                    <div class="nf-info-pedido">
                        <p><strong>Pedido Nº:</strong> <?= htmlspecialchars($pedido['id']) ?></p>
                        <p><strong>Data do Pedido:</strong> <?= date('d/m/Y', strtotime($pedido['data_pedido'])) ?></p>
                        <div class="form-group">
                            <label for="data_retirada_edit">Data de Retirada:</label>
                            <input type="text" id="data_retirada_edit" name="data_retirada" class="form-control" value="<?= date('d/m/Y', strtotime($pedido['data_retirada'])) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="status_edit">Status:</label>
                            <select id="status_edit" name="status" class="form-control" required>
                                <option value="">Selecione um status</option>
                                <?php foreach ($statusOrder as $statusOption): ?>
                                    <option value="<?= $statusOption ?>" <?= $statusPedido === $statusOption ? 'selected' : '' ?>><?= $statusOption ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="nf-info-cliente">
                    <h3>Dados do Cliente</h3>
                    <div class="cliente-dados">
                        <p><strong>Nome:</strong> <?= htmlspecialchars($pedido['cliente_nome']) ?></p>
                        <p><strong>CNPJ/CPF:</strong> <?= htmlspecialchars($pedido['cliente_documento'] ?? 'Não informado') ?></p>
                        <p><strong>Cidade:</strong> <?= htmlspecialchars($pedido['cliente_cidade']) ?></p>
                        <p><strong>Telefone:</strong> <?= htmlspecialchars($pedido['cliente_telefone']) ?></p>
                    </div>
                </div>
                <div class="form-group">
                    <h3>Itens do Pedido</h3>
                    <label for="buscarProdutoEdit">Adicionar Produtos:</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-box"></i></span>
                        </div>
                        <input type="text" id="buscarProdutoEdit" class="form-control" placeholder="Buscar produtos...">
                    </div>
                    <div id="listaProdutosEdit" class="list-group"></div>
                </div>
                <div id="produtosSelecionadosEdit" class="mb-3">
                    <?php foreach ($itens as $item): ?>
                        <div class="produto-item" data-produto-id="<?= $item['produto_id'] ?>">
                            <span><?= htmlspecialchars($item['produto_nome']) ?> (<?= $item['produto_tipo'] ?>)</span>
                            <input type="hidden" name="produto_id[]" value="<?= $item['produto_id'] ?>">
                            <div class="quantidade-wrapper">
                                <label for="quantidade_<?= $item['produto_id'] ?>" class="quantidade-label">Qtd. Solicitada:</label>
                                <input type="text" id="quantidade_<?= $item['produto_id'] ?>" name="quantidade[]" value="<?= $item['produto_tipo'] === 'UND' ? number_format($item['quantidade'], 0, ',', '.') : number_format($item['quantidade'], 3, ',', '.') ?>" class="form-control quantidade-input" placeholder="<?= $item['produto_tipo'] === 'UND' ? 'Qtd. (inteiro)' : 'Qtd. (ex.: 1,500)' ?>">
                            </div>
                            <?php if ($isAfterPedidoSeparado): ?>
                                <div class="quantidade-separada-wrapper">
                                    <label for="quantidade_separada_<?= $item['produto_id'] ?>" class="quantidade-separada-label">Qtd. Disponível:</label>
                                    <input type="text" id="quantidade_separada_<?= $item['produto_id'] ?>" name="quantidade_separada[]" value="<?= !is_null($item['quantidade_separada']) ? ($item['produto_tipo'] === 'UND' ? number_format($item['quantidade_separada'], 0, ',', '.') : number_format($item['quantidade_separada'], 3, ',', '.')) : '0' ?>" class="form-control quantidade-separada-input" placeholder="<?= $item['produto_tipo'] === 'UND' ? 'Qtd. (inteiro)' : 'Qtd. (ex.: 1,500)' ?>">
                                </div>
                            <?php endif; ?>
                            <button type="button" class="btn btn-danger btn-sm remove-produto">Remover</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="form-group">
                    <h3>Observações</h3>
                    <textarea id="observacoesEdit" name="observacoes" class="form-control" placeholder="Adicionar observações..."><?= htmlspecialchars($pedido['observacoes'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <h3>Adicionar Comprovantes de Pagamento</h3>
                    <label for="comprovanteUploadEdit">Upload de Comprovantes:</label>
                    <input type="file" id="comprovanteUploadEdit" name="comprovantes_edit[]" accept=".png,.jpg,.jpeg,.docx,.pdf" multiple>
                    <div id="fileListEdit" class="file-list"></div>
                </div>
                <div class="edit-actions">
                    <button class="btn btn-success" onclick="salvarAlteracoes(<?= $pedido['id'] ?>)">
                        <i class="fas fa-save"></i> Salvar Alterações
                    </button>
                    <button class="btn btn-secondary" onclick="toggleEditMode(false)">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </div>
            </div>
            <div id="actionsView">
                <?php if ($statusPedido === 'Pedido Separado'): ?>
                    <button class="btn btn-primary" id="btnBaixarPedido" data-id="<?= htmlspecialchars($pedido['id']) ?>" onclick="baixarPedido(<?= htmlspecialchars($pedido['id']) ?>)">
                        <i class="fas fa-download"></i> Baixar Pedido
                    </button>
                <?php endif; ?>
                <?php if (in_array($statusPedido, ['Aguardando Cliente', 'Aguardando Pagamento'])): ?>
                    <div>
                        <label for="formaPagamento">Forma de Pagamento:</label>
                        <select id="formaPagamento">
                            <option value="">Selecione</option>
                            <option value="Dinheiro">Dinheiro</option>
                            <option value="Pix">Pix</option>
                        </select>
                    </div>
                    <div id="uploadComprovante" style="display: none;">
                        <label for="comprovanteUpload">Upload de Comprovantes (Pix):</label>
                        <input type="file" id="comprovanteUpload" name="comprovantes[]" accept=".png,.jpg,.jpeg,.docx,.pdf" multiple>
                        <div id="fileList" class="file-list"></div>
                        <button class="btn btn-success" id="btnMarcarComoPago" onclick="marcarComoPago(<?= htmlspecialchars($pedido['id']) ?>)">
                            <i class="fas fa-check"></i> Marcar como Pago
                        </button>
                    </div>
                    <div id="pagamentoEntrega" style="display: none;">
                        <button class="btn btn-success pagamento-btn" onclick="pagamentoNaEntrega(<?= htmlspecialchars($pedido['id']) ?>)">
                            Pagamento na Entrega
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <script>
            console.log("ver_pedido.php: Script inline executado.");
            // Código do ver_pedido.js embutido aqui
            // Array para armazenar os arquivos selecionados (para comprovantes)
            let selectedFiles = [];

            // Função para configurar a busca de produtos
            function configurarBuscaProdutos() {
                const buscarProdutoInput = document.getElementById('buscarProdutoEdit');
                const listaProdutos = document.getElementById('listaProdutosEdit');
                const produtosSelecionados = document.getElementById('produtosSelecionadosEdit');
                if (!buscarProdutoInput || !listaProdutos || !produtosSelecionados) {
                    console.error('Elementos de busca de produtos não encontrados.');
                    return;
                }
                buscarProdutoInput.addEventListener('input', function() {
                    const query = this.value.trim();
                    if (query.length < 2) {
                        listaProdutos.innerHTML = '';
                        return;
                    }
                    console.log('Buscando produtos com query:', query);
                    fetch(`/atacado/buscar_produtos.php?query=${encodeURIComponent(query)}`)
                        .then(response => {
                            if (!response.ok) throw new Error('Erro na requisição: ' + response.statusText);
                            return response.json();
                        })
                        .then(data => {
                            console.log('Produtos retornados:', data);
                            listaProdutos.innerHTML = '';
                            if (data.status === 'error') {
                                listaProdutos.innerHTML = `<div class="list-group-item text-danger">${data.message}</div>`;
                                return;
                            }
                            if (!Array.isArray(data) || data.length === 0) {
                                listaProdutos.innerHTML = '<div class="list-group-item">Nenhum produto encontrado.</div>';
                                return;
                            }
                            data.forEach(produto => {
                                if (!produto.id || !produto.nome || !produto.tipo) {
                                    console.warn('Produto inválido:', produto);
                                    return;
                                }
                                const item = document.createElement('div');
                                item.className = 'list-group-item list-group-item-action';
                                item.textContent = `${produto.nome} (${produto.tipo})`;
                                item.dataset.produtoId = produto.id;
                                item.dataset.produtoNome = produto.nome;
                                item.dataset.produtoTipo = produto.tipo;
                                item.addEventListener('click', function() {
                                    const produtoItem = document.createElement('div');
                                    produtoItem.className = 'produto-item';
                                    produtoItem.dataset.produtoId = produto.id;
                                    produtoItem.innerHTML = `
                                        <span>${produto.nome} (${produto.tipo})</span>
                                        <input type="hidden" name="produto_id[]" value="${produto.id}">
                                        <div class="quantidade-wrapper">
                                            <label for="quantidade_${produto.id}" class="quantidade-label">Qtd. Solicitada:</label>
                                            <input type="text" id="quantidade_${produto.id}" name="quantidade[]" value="${produto.tipo === 'UND' ? '1' : '1,000'}" class="form-control quantidade-input" placeholder="${produto.tipo === 'UND' ? 'Qtd. (inteiro)' : 'Qtd. (ex.: 1,500)'}">
                                        </div>
                                        <button type="button" class="btn btn-danger btn-sm remove-produto">Remover</button>
                                    `;
                                    produtosSelecionados.appendChild(produtoItem);
                                    listaProdutos.innerHTML = '';
                                    buscarProdutoInput.value = '';
                                    const $quantidadeInput = $(produtoItem).find('.quantidade-input');
                                    if (produto.tipo === 'UND') {
                                        $quantidadeInput.mask('000', { placeholder: "Qtd. (inteiro)" });
                                    } else {
                                        $quantidadeInput.mask('000,000', { reverse: true, placeholder: "Qtd. (ex.: 1,500)" });
                                    }
                                });
                                listaProdutos.appendChild(item);
                            });
                        })
                        .catch(error => {
                            console.error('Erro ao buscar produtos:', error);
                            listaProdutos.innerHTML = '<div class="list-group-item text-danger">Erro ao buscar produtos. Tente novamente.</div>';
                        });
                });
                // Remover produtos no modo de edição
                produtosSelecionados.addEventListener('click', function(e) {
                    if (e.target.classList.contains('remove-produto')) {
                        e.target.parentElement.remove();
                    }
                });
            }

            // Função para carregar produtos existentes
            function carregarProdutosExistentes(pedidoId) {
                console.log('Carregando produtos existentes para Pedido ID:', pedidoId);
                fetch(`/atacado/ver_pedido.php?id=${pedidoId}&ajax=1`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success' && data.itens && Array.isArray(data.itens)) {
                            const produtosSelecionados = document.getElementById('produtosSelecionadosEdit');
                            produtosSelecionados.innerHTML = '';
                            data.itens.forEach(item => {
                                const produtoItem = document.createElement('div');
                                produtoItem.className = 'produto-item';
                                produtoItem.dataset.produtoId = item.produto_id;
                                const quantidade = item.produto_tipo === 'UND' ? parseInt(item.quantidade) : parseFloat(item.quantidade).toFixed(3).replace('.', ',');
                                const quantidadeSeparada = item.quantidade_separada ? (item.produto_tipo === 'UND' ? parseInt(item.quantidade_separada) : parseFloat(item.quantidade_separada).toFixed(3).replace('.', ',')) : '0';
                                const isAfterSeparado = data.pedido.status !== 'pendente' && data.pedido.status !== 'Em Separação';
                                produtoItem.innerHTML = `
                                    <span>${item.produto_nome} (${item.produto_tipo})</span>
                                    <input type="hidden" name="produto_id[]" value="${item.produto_id}">
                                    <div class="quantidade-wrapper">
                                        <label for="quantidade_${item.produto_id}" class="quantidade-label">Qtd. Solicitada:</label>
                                        <input type="text" id="quantidade_${item.produto_id}" name="quantidade[]" value="${quantidade}" class="form-control quantidade-input" placeholder="${item.produto_tipo === 'UND' ? 'Qtd. (inteiro)' : 'Qtd. (ex.: 1,500)'}">
                                    </div>
                                    ${isAfterSeparado ? `
                                    <div class="quantidade-separada-wrapper">
                                        <label for="quantidade_separada_${item.produto_id}" class="quantidade-separada-label">Qtd. Disponível:</label>
                                        <input type="text" id="quantidade_separada_${item.produto_id}" name="quantidade_separada[]" value="${quantidadeSeparada}" class="form-control quantidade-separada-input" placeholder="${item.produto_tipo === 'UND' ? 'Qtd. Disponível (inteiro)' : 'Qtd. Disponível (ex.: 1,500)'}">
                                    </div>` : ''}
                                    <button type="button" class="btn btn-danger btn-sm remove-produto">Remover</button>
                                `;
                                produtosSelecionados.appendChild(produtoItem);
                                const $quantidadeInput = $(produtoItem).find('.quantidade-input');
                                const $quantidadeSeparadaInput = $(produtoItem).find('.quantidade-separada-input');
                                if (item.produto_tipo === 'UND') {
                                    $quantidadeInput.mask('000', { placeholder: "Qtd. (inteiro)" });
                                    if ($quantidadeSeparadaInput.length) {
                                        $quantidadeSeparadaInput.mask('000', { placeholder: "Qtd. Disponível (inteiro)" });
                                    }
                                } else {
                                    $quantidadeInput.mask('000,000', { reverse: true, placeholder: "Qtd. (ex.: 1,500)" });
                                    if ($quantidadeSeparadaInput.length) {
                                        $quantidadeSeparadaInput.mask('000,000', { reverse: true, placeholder: "Qtd. Disponível (ex.: 1,500)" });
                                    }
                                }
                            });
                            console.log('Produtos existentes carregados:', data.itens);
                        } else {
                            console.warn('Nenhum item retornado para o pedido:', pedidoId);
                        }
                    })
                    .catch(error => console.error('Erro ao carregar produtos:', error));
            }

            // Função para configurar os eventos de upload de comprovantes
            function configurarUploadComprovantes() {
                const formaPagamentoSelect = document.getElementById('formaPagamento');
                const uploadComprovanteDiv = document.getElementById('uploadComprovante');
                const pagamentoEntregaDiv = document.getElementById('pagamentoEntrega');
                const comprovanteUploadInput = document.getElementById('comprovanteUpload');
                if (!formaPagamentoSelect || !uploadComprovanteDiv || !pagamentoEntregaDiv || !comprovanteUploadInput) {
                    console.error('Elementos de upload de comprovantes não encontrados.');
                    return;
                }
                formaPagamentoSelect.addEventListener('change', function() {
                    var formaPagamento = this.value;
                    uploadComprovanteDiv.style.display = formaPagamento === 'Pix' ? 'block' : 'none';
                    pagamentoEntregaDiv.style.display = formaPagamento === 'Dinheiro' ? 'block' : 'none';
                });
                comprovanteUploadInput.addEventListener('change', function(event) {
                    const files = Array.from(event.target.files);
                    files.forEach(file => {
                        if (!selectedFiles.some(f => f.name === file.name)) {
                            selectedFiles.push(file);
                        }
                    });
                    updateFileList();
                    event.target.value = '';
                });
            }

            function updateFileList(listId = 'fileList') {
                const fileList = document.getElementById(listId);
                if (!fileList) {
                    console.error(`Elemento ${listId} não encontrado.`);
                    return;
                }
                fileList.innerHTML = '';
                if (selectedFiles.length === 0) {
                    return;
                }
                const ul = document.createElement('ul');
                selectedFiles.forEach((file, index) => {
                    const li = document.createElement('li');
                    li.textContent = file.name;
                    const removeBtn = document.createElement('button');
                    removeBtn.textContent = 'Remover';
                    removeBtn.className = 'remove-file';
                    removeBtn.onclick = () => {
                        selectedFiles.splice(index, 1);
                        updateFileList(listId);
                    };
                    li.appendChild(removeBtn);
                    ul.appendChild(li);
                });
                fileList.appendChild(ul);
            }

            function toggleEditMode(edit) {
                const viewMode = document.getElementById('viewMode');
                const editMode = document.getElementById('editMode');
                const actionsView = document.getElementById('actionsView');
                const editButton = document.querySelector('.btn-edit-pedido');
                if (edit) {
                    viewMode.style.display = 'none';
                    editMode.style.display = 'block';
                    actionsView.style.display = 'none';
                    editButton.style.display = 'none';
                    const $dataRetiradaEdit = $("#data_retirada_edit");
                    console.log('Valor inicial de data_retirada_edit:', $dataRetiradaEdit.val());
                    try {
                        $dataRetiradaEdit.datepicker({
                            dateFormat: 'dd/mm/yy',
                            changeMonth: true,
                            changeYear: true
                        });
                        $dataRetiradaEdit.mask('00/00/0000', { placeholder: "DD/MM/YYYY" });
                        console.log('Datepicker inicializado para data_retirada_edit.');
                    } catch (error) {
                        console.error('Erro ao inicializar Datepicker:', error);
                    }
                    const pedidoId = $('#notaFiscal').data('id');
                    if (pedidoId) carregarProdutosExistentes(pedidoId);
                    configurarBuscaProdutos();
                    const $produtosSelecionados = $("#produtosSelecionadosEdit .produto-item");
                    $produtosSelecionados.each(function() {
                        const $item = $(this);
                        const tipoProduto = $item.find('span').text().includes('(UND)') ? 'UND' : 'KG';
                        const $quantidadeInput = $item.find('.quantidade-input');
                        if (tipoProduto === 'UND') {
                            $quantidadeInput.mask('000', { placeholder: "Qtd. (inteiro)" });
                        } else {
                            $quantidadeInput.mask('000,000', { reverse: true, placeholder: "Qtd. (ex.: 1,500)" });
                        }
                        const $quantidadeSeparadaInput = $item.find('.quantidade-separada-input');
                        if ($quantidadeSeparadaInput.length) {
                            if (tipoProduto === 'UND') {
                                $quantidadeSeparadaInput.mask('000', { placeholder: "Qtd. Disponível (inteiro)" });
                            } else {
                                $quantidadeSeparadaInput.mask('000,000', { reverse: true, placeholder: "Qtd. Disponível (ex.: 1,500)" });
                            }
                        }
                    });
                    $("#listaProdutosEdit").on('click', '.list-group-item', function() {
                        const $novoItem = $("#produtosSelecionadosEdit .produto-item").last();
                        const tipoProduto = $novoItem.find('span').text().includes('(UND)') ? 'UND' : 'KG';
                        const $quantidadeInput = $novoItem.find('.quantidade-input');
                        if (tipoProduto === 'UND') {
                            $quantidadeInput.mask('000', { placeholder: "Qtd. (inteiro)" });
                        } else {
                            $quantidadeInput.mask('000,000', { reverse: true, placeholder: "Qtd. (ex.: 1,500)" });
                        }
                        const $quantidadeSeparadaInput = $novoItem.find('.quantidade-separada-input');
                        if ($quantidadeSeparadaInput.length) {
                            if (tipoProduto === 'UND') {
                                $quantidadeSeparadaInput.mask('000', { placeholder: "Qtd. Disponível (inteiro)" });
                            } else {
                                $quantidadeSeparadaInput.mask('000,000', { reverse: true, placeholder: "Qtd. Disponível (ex.: 1,500)" });
                            }
                        }
                    });
                    const comprovanteUploadInputEdit = document.getElementById('comprovanteUploadEdit');
                    if (comprovanteUploadInputEdit) {
                        comprovanteUploadInputEdit.addEventListener('change', function(event) {
                            const files = Array.from(event.target.files);
                            files.forEach(file => {
                                if (!selectedFiles.some(f => f.name === file.name)) {
                                    selectedFiles.push(file);
                                }
                            });
                            updateFileList('fileListEdit');
                            event.target.value = '';
                        });
                    }
                } else {
                    viewMode.style.display = 'block';
                    editMode.style.display = 'none';
                    actionsView.style.display = 'block';
                    editButton.style.display = 'block';
                    configurarUploadComprovantes();
                    const dataRetiradaValue = document.getElementById('data_retirada_edit').value;
                    console.log('Atualizando dataRetiradaView com:', dataRetiradaValue);
                    document.getElementById('dataRetiradaView').textContent = dataRetiradaValue;
                    document.getElementById('observacoesView').textContent = document.getElementById('observacoesEdit').value || 'Nenhuma observação.';
                }
            }

            function salvarAlteracoes(pedidoId) {
                let dataRetirada = document.getElementById('data_retirada_edit').value;
                const observacoes = document.getElementById('observacoesEdit').value;
                const status = document.getElementById('status_edit').value;
                const produtosSelecionados = Array.from(document.querySelectorAll('#produtosSelecionadosEdit .produto-item'));
                dataRetirada = dataRetirada.trim();
                console.log('Data de retirada antes do envio:', dataRetirada);
                const dataRegex = /^\d{2}\/\d{2}\/\d{4}$/;
                if (!dataRegex.test(dataRetirada)) {
                    alert('Por favor, insira a data de retirada no formato DD/MM/YYYY (ex.: 08/05/2025).');
                    return;
                }
                if (!status || status === '') {
                    alert('Por favor, selecione um status.');
                    return;
                }
                const produtos = produtosSelecionados.map(item => {
                    const quantidadeSeparadaInput = item.querySelector('input[name="quantidade_separada[]"]');
                    return {
                        produto_id: item.querySelector('input[name="produto_id[]"]').value,
                        quantidade: item.querySelector('input[name="quantidade[]"]').value.replace(',', '.'),
                        quantidade_separada: quantidadeSeparadaInput ? quantidadeSeparadaInput.value.replace(',', '.') : null
                    };
                });
                console.log('Produtos enviados para salvarAlteracoes:', produtos);
                const statusOrder = [
                    'pendente', 'Em Separação', 'Pedido Separado', 'Aguardando Cliente',
                    'Aguardando Pagamento', 'Aguardando Retirada', 'Pagamento na Retirada', 'Concluído'
                ];
                const statusAtual = document.querySelector('#notaFiscal')?.dataset?.status || 'pendente';
                const statusIndexAtual = statusOrder.indexOf(statusAtual);
                const statusIndexNovo = statusOrder.indexOf(status);
                if (statusIndexNovo !== statusIndexAtual) {
                    const mensagem = statusIndexNovo > statusIndexAtual
                        ? 'Atenção: Você está avançando o status do pedido. Isso pode afetar o fluxo do processo. Deseja continuar?'
                        : 'Atenção: Você está retrocedendo o status do pedido. Isso pode afetar o fluxo do processo. Deseja continuar?';
                    if (!confirm(mensagem)) {
                        return;
                    }
                }
                const formData = new FormData();
                formData.append('id', pedidoId);
                formData.append('data_retirada', dataRetirada);
                formData.append('observacoes', observacoes);
                formData.append('status', status);
                formData.append('produtos', JSON.stringify(produtos));
                selectedFiles.forEach(file => {
                    formData.append('comprovantes[]', file);
                });
                fetch('/atacado/editar_pedido.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        selectedFiles = [];
                        updateFileList('fileListEdit');
                        alert('Pedido atualizado com sucesso!');
                        location.reload();
                    } else {
                        alert('Erro ao atualizar o pedido: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao atualizar o pedido: ' + error.message);
                });
            }

            function baixarPedido(pedidoId) {
                fetch('/atacado/baixar_pedido.php?id=' + pedidoId + '&status=Aguardando+Cliente')
                    .then(response => {
                        if (!response.ok) {
                            return response.json().then(data => {
                                throw new Error(data.message || 'Erro ao processar o pedido');
                            });
                        }
                        return response.blob();
                    })
                    .then(blob => {
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'Pedido_' + pedidoId + '.pdf';
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        window.URL.revokeObjectURL(url);
                        alert('Status atualizado para Aguardando Cliente.');
                        location.reload();
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        alert('Erro ao baixar o pedido: ' + error.message);
                    });
            }

            function marcarComoPago(pedidoId) {
                const formData = new FormData();
                formData.append('id', pedidoId);
                formData.append('status', 'Aguardando Retirada');
                formData.append('status_pagamento', 'Sim');
                selectedFiles.forEach(file => {
                    formData.append('comprovantes[]', file);
                });
                fetch('/atacado/marcar_como_pago.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Pagamento confirmado com sucesso!');
                        location.reload();
                    } else {
                        alert('Erro ao marcar como pago: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao marcar como pago: ' + error.message);
                });
            }

            function pagamentoNaEntrega(pedidoId) {
                const formData = new FormData();
                formData.append('id', pedidoId);
                formData.append('status', 'Pagamento na Retirada');
                formData.append('status_pagamento', 'Não');
                fetch('/atacado/marcar_como_pago.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Pagamento marcado como "na entrega".');
                        location.reload();
                    } else {
                        alert('Erro ao processar pagamento na entrega: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao processar pagamento na entrega: ' + error.message);
                });
            }

            // Funções para fechar o modal
            function configurarFechamentoModal() {
                document.addEventListener('click', function(e) {
                    if (e.target.classList.contains('pedido-close')) {
                        const modal = document.getElementById('verPedidoModal');
                        if (modal) {
                            modal.style.display = 'none';
                        }
                    }
                });
                document.addEventListener('click', function(e) {
                    const modal = document.getElementById('verPedidoModal');
                    if (modal && modal.style.display !== 'none') {
                        const modalContent = modal.querySelector('.modal-content');
                        if (modalContent && !modalContent.contains(e.target)) {
                            modal.style.display = 'none';
                        }
                    }
                });
            }

            // Função pública para inicializar os eventos quando o modal é aberto
            function inicializarVerPedido() {
                console.log("inicializarVerPedido: Inicializando eventos do modal.");
                configurarBuscaProdutos();
                configurarUploadComprovantes();
                configurarFechamentoModal();
            }

            // Expor a função para ser chamada por outros scripts
            window.inicializarVerPedido = inicializarVerPedido;
            console.log("ver_pedido.js: Função inicializarVerPedido definida.");
        </script>
    </body>
    </html>
    <?php
} catch (PDOException $e) {
    registrarLog("Erro ao carregar detalhes do pedido ID: $pedido_id. Mensagem: " . $e->getMessage(), 'ERROR');
    if (isset($_GET['ajax'])) {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao carregar detalhes do pedido: ' . $e->getMessage()]);
    } else {
        echo '<p>Erro ao carregar detalhes do pedido.</p>';
    }
    exit;
}
?>