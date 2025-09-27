<?php
// Exibir todos os erros para diagnóstico
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'backend/config/db.php';

// Buscar todos os pedidos pendentes
$stmtPedidos = $conn->prepare("SELECT p.*, c.nome as cliente_nome FROM pedidos p JOIN clientes c ON p.cliente_id = c.id WHERE p.status = 'pendente'");
$stmtPedidos->execute();
$pedidos = $stmtPedidos->fetchAll(PDO::FETCH_ASSOC);

// Atualizar o pedido se o formulário for submetido
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pedido_id = $_POST['pedido_id'];
    $quantidade_caixas = $_POST['quantidade_caixas'];
    $status = $_POST['status'];
    $observacoes = $_POST['observacoes'];

    // Atualizar o pedido no banco de dados
    $stmtUpdate = $conn->prepare("UPDATE pedidos SET quantidade_caixas = :quantidade_caixas, status = :status, observacoes = :observacoes WHERE id = :pedido_id");
    $stmtUpdate->bindParam(':quantidade_caixas', $quantidade_caixas);
    $stmtUpdate->bindParam(':status', $status);
    $stmtUpdate->bindParam(':observacoes', $observacoes);
    $stmtUpdate->bindParam(':pedido_id', $pedido_id);

    if ($stmtUpdate->execute()) {
        echo "Pedido atualizado com sucesso!";
        header('Location: gerenciar_pedidos.php');
        exit;
    } else {
        echo "Erro ao atualizar o pedido.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Pedidos</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>Gerenciar Pedidos</h1>

        <table border="1">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Data do Pedido</th>
                    <th>Data de Retirada</th>
                    <th>Status</th>
                    <th>Quantidade de Caixas</th>
                    <th>Observações</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pedidos as $pedido): ?>
                <tr>
                    <form method="POST" action="gerenciar_pedidos.php">
                        <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
                        <td><?= $pedido['id'] ?></td>
                        <td><?= htmlspecialchars($pedido['cliente_nome']) ?></td>
                        <td><?= $pedido['data_pedido'] ?></td>
                        <td><?= $pedido['data_retirada'] ?></td>
                        <td>
                            <select name="status">
                                <option value="pendente" <?= $pedido['status'] == 'pendente' ? 'selected' : '' ?>>Pendente</option>
                                <option value="em_separacao" <?= $pedido['status'] == 'em_separacao' ? 'selected' : '' ?>>Em Separação</option>
                                <option value="finalizado" <?= $pedido['status'] == 'finalizado' ? 'selected' : '' ?>>Finalizado</option>
                            </select>
                        </td>
                        <td><input type="number" name="quantidade_caixas" min="0" value="<?= $pedido['quantidade_caixas'] ?>"></td>
                        <td><textarea name="observacoes"><?= htmlspecialchars($pedido['observacoes']) ?></textarea></td>
                        <td><button type="submit">Salvar</button></td>
                    </form>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>