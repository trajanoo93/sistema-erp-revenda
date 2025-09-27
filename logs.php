<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'backend/config/db.php';

// Buscar todos os logs e os nomes dos usuários associados
$stmtLogs = $conn->prepare("
    SELECT logs.id, usuarios.nome AS usuario, logs.acao, logs.data
    FROM logs
    JOIN usuarios ON logs.usuario_id = usuarios.id
    ORDER BY logs.data DESC
");
$stmtLogs->execute();
$logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
?>

<h1>Logs do Sistema</h1>

<!-- Contêiner da tabela -->
<div class="tabela-container">
    <table class="tabela-generica" id="tabelaLogs">
        <thead>
            <tr>
                <th>Usuário</th>
                <th>Ação</th>
                <th>Data</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= htmlspecialchars($log['usuario']) ?></td>
                <td><?= htmlspecialchars($log['acao']) ?></td>
                <td>
                    <?php 
                    // Convertendo o horário de UTC para 'America/Sao_Paulo'
                    $dataUtc = new DateTime($log['data'], new DateTimeZone('UTC'));
                    $dataUtc->setTimezone(new DateTimeZone('America/Sao_Paulo'));
                    echo $dataUtc->format('Y-m-d H:i:s');
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>