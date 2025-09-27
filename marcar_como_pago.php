<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'backend/config/db.php';
header('Content-Type: application/json');

// Função para enviar resposta JSON
function sendResponse($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Método não permitido.');
    }

    $pedidoId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';
    $statusPagamento = isset($_POST['status_pagamento']) ? trim($_POST['status_pagamento']) : '';
    $formaPagamento = isset($_POST['forma_pagamento']) ? trim($_POST['forma_pagamento']) : null;

    if ($pedidoId <= 0 || empty($status) || empty($statusPagamento)) {
        sendResponse(false, 'Dados inválidos fornecidos.');
    }

    // Verificar se o pedido existe
    $stmt = $conn->prepare("SELECT id FROM pedidos WHERE id = :id");
    $stmt->bindParam(':id', $pedidoId, PDO::PARAM_INT);
    $stmt->execute();
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        sendResponse(false, 'Pedido não encontrado.');
    }

    // Atualizar o status do pedido e o status de pagamento
    $stmt = $conn->prepare("UPDATE pedidos SET status = :status, status_pagamento = :status_pagamento, forma_pagamento = :forma_pagamento WHERE id = :id");
    $stmt->bindParam(':status', $status, PDO::PARAM_STR);
    $stmt->bindParam(':status_pagamento', $statusPagamento, PDO::PARAM_STR);
    $stmt->bindParam(':forma_pagamento', $formaPagamento, PDO::PARAM_STR);
    $stmt->bindParam(':id', $pedidoId, PDO::PARAM_INT);

    if (!$stmt->execute()) {
        sendResponse(false, 'Erro ao atualizar o status do pedido.');
    }

    // Processar múltiplos comprovantes, se enviados
    if (isset($_FILES['comprovantes']) && !empty($_FILES['comprovantes']['name'][0])) {
        $uploadDir = 'uploads/comprovantes/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $allowedFileTypes = [
            'image/png',
            'image/jpeg',
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' // .docx
        ];

        $comprovantes = $_FILES['comprovantes'];
        $totalFiles = count($comprovantes['name']);

        for ($i = 0; $i < $totalFiles; $i++) {
            if ($comprovantes['error'][$i] !== UPLOAD_ERR_OK) {
                continue; // Pular arquivos com erro
            }

            $fileType = $comprovantes['type'][$i];
            if (!in_array($fileType, $allowedFileTypes)) {
                continue; // Pular arquivos com tipo não permitido
            }

            $fileName = uniqid('comprovante_') . '_' . basename($comprovantes['name'][$i]);
            $filePath = $uploadDir . $fileName;

            if (move_uploaded_file($comprovantes['tmp_name'][$i], $filePath)) {
                // Registrar o comprovante na tabela comprovantes
                $stmt = $conn->prepare("INSERT INTO comprovantes (pedido_id, caminho_arquivo) VALUES (:pedido_id, :caminho_arquivo)");
                $stmt->bindParam(':pedido_id', $pedidoId, PDO::PARAM_INT);
                $stmt->bindParam(':caminho_arquivo', $filePath, PDO::PARAM_STR);
                $stmt->execute();
            }
        }
    }

    sendResponse(true, 'Pagamento confirmado com sucesso!');
} catch (Exception $e) {
    sendResponse(false, 'Erro interno no servidor: ' . $e->getMessage());
}
?>