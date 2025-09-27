<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'backend/config/db.php';

// Desativar exibição de erros em produção
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Função para enviar resposta JSON e encerrar o script
function sendResponse($status, $message) {
    header('Content-Type: application/json');
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

try {
    // Determinar o ID do produto
    $id = 0;
    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
    } elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
    }

    if ($id <= 0) {
        sendResponse('error', 'ID do produto inválido.');
    }

    // Buscar produto no banco de dados (valores originais)
    $stmt = $conn->prepare("SELECT * FROM produtos WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verificar se o produto foi encontrado
    if (!$produto) {
        sendResponse('error', 'Produto não encontrado! ID: ' . $id);
    }

    // Buscar o nome do usuário logado
    $usuario_id = $_SESSION['usuario_id'];
    $stmtUsuario = $conn->prepare("SELECT nome FROM usuarios WHERE id = :usuario_id");
    $stmtUsuario->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmtUsuario->execute();
    $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Validação dos dados recebidos
        $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
        $valor = isset($_POST['valor']) ? floatval($_POST['valor']) : 0;
        $quantidade_estoque = isset($_POST['quantidade_estoque']) ? floatval($_POST['quantidade_estoque']) : 0;
        $tipo = isset($_POST['tipo']) ? trim($_POST['tipo']) : '';
        $observacao = isset($_POST['observacao']) ? trim($_POST['observacao']) : '';

        // Validações
        if (empty($nome)) {
            sendResponse('error', 'O nome do produto é obrigatório.');
        }
        if ($valor < 0) {
            sendResponse('error', 'O valor do produto não pode ser negativo.');
        }
        if ($quantidade_estoque < 0) {
            sendResponse('error', 'A quantidade em estoque não pode ser negativa.');
        }
        if (!in_array($tipo, ['KG', 'UND'])) {
            sendResponse('error', 'Tipo inválido. Use KG ou UND.');
        }
        if (empty($observacao)) {
            sendResponse('error', 'O motivo da alteração é obrigatório.');
        }

        // Comparar os valores originais com os novos para o log
        $alteracoes = [];
        if ($produto['nome'] != $nome) {
            $alteracoes[] = [
                "campo" => "Nome do Produto",
                "valor_anterior" => $produto['nome'],
                "valor_novo" => $nome
            ];
        }
        if ($produto['valor'] != $valor) {
            $alteracoes[] = [
                "campo" => "Valor do Produto",
                "valor_anterior" => number_format($produto['valor'], 2, ',', '.'),
                "valor_novo" => number_format($valor, 2, ',', '.')
            ];
        }
        if ($produto['quantidade_estoque'] != $quantidade_estoque) {
            $alteracoes[] = [
                "campo" => "Quantidade em Estoque",
                "valor_anterior" => $produto['quantidade_estoque'],
                "valor_novo" => $quantidade_estoque
            ];
        }
        if ($produto['tipo'] != $tipo) {
            $alteracoes[] = [
                "campo" => "Tipo do Produto",
                "valor_anterior" => $produto['tipo'],
                "valor_novo" => $tipo
            ];
        }

        // Atualizar o produto no banco de dados
        $stmt = $conn->prepare("UPDATE produtos SET nome = :nome, valor = :valor, quantidade_estoque = :quantidade_estoque, tipo = :tipo WHERE id = :id");
        $stmt->bindParam(':nome', $nome, PDO::PARAM_STR);
        $stmt->bindParam(':valor', $valor, PDO::PARAM_STR);
        $stmt->bindParam(':quantidade_estoque', $quantidade_estoque, PDO::PARAM_STR);
        $stmt->bindParam(':tipo', $tipo, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            // Se houve alterações, registrar no log
            if (!empty($alteracoes)) {
                foreach ($alteracoes as $alteracao) {
                    $acao = "Produto: " . $produto['nome'] . " - Campo: " . $alteracao['campo'] . " - Alterado de: " . $alteracao['valor_anterior'] . " para: " . $alteracao['valor_novo'] . " - Motivo: " . $observacao;
                    $stmtLog = $conn->prepare("INSERT INTO logs_detalhados (usuario_id, acao) VALUES (:usuario_id, :acao)");
                    $stmtLog->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
                    $stmtLog->bindParam(':acao', $acao, PDO::PARAM_STR);
                    $stmtLog->execute();
                }
            }
            sendResponse('success', 'Produto atualizado com sucesso!');
        } else {
            sendResponse('error', 'Erro ao atualizar o produto: ' . implode(", ", $stmt->errorInfo()));
        }
    }
} catch (Exception $e) {
    sendResponse('error', 'Erro interno no servidor: ' . $e->getMessage());
}
?>

<h2 class="modal-title">Editar Produto</h2>

<form id="formEditarProduto" method="POST" class="form-produto">
    <input type="hidden" name="id" value="<?= htmlspecialchars($produto['id']) ?>">

    <div class="form-group">
        <label for="nome">Nome do Produto:</label>
        <input type="text" name="nome" id="nome" value="<?= htmlspecialchars($produto['nome']) ?>" required>
    </div>

    <div class="form-group">
        <label for="valor">Valor:</label>
        <input type="number" step="0.01" name="valor" id="valor" value="<?= htmlspecialchars($produto['valor']) ?>" required>
    </div>

    <div class="form-group">
        <label for="quantidade_estoque">Quantidade em Estoque:</label>
        <input type="number" step="0.001" name="quantidade_estoque" id="quantidade_estoque" value="<?= htmlspecialchars($produto['quantidade_estoque']) ?>" required>
    </div>

    <div class="form-group">
        <label for="tipo">Tipo:</label>
        <select name="tipo" id="tipo" required>
            <option value="" disabled>Selecione o tipo</option>
            <option value="KG" <?= $produto['tipo'] === 'KG' ? 'selected' : '' ?>>KG</option>
            <option value="UND" <?= $produto['tipo'] === 'UND' ? 'selected' : '' ?>>UND</option>
        </select>
    </div>

    <div class="form-group">
        <label for="observacao">Motivo da Alteração:</label>
        <textarea name="observacao" id="observacao" placeholder="Informe o motivo da alteração" required></textarea>
    </div>

    <button type="submit" class="btn-submit">Salvar Alterações</button>
</form>

<div id="mensagem" class="mensagem"></div>