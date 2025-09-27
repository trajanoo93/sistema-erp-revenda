<?php
require_once 'backend/config/db.php';
require_once 'dompdf/autoload.inc.php'; // Inclui o Dompdf

use Dompdf\Dompdf;
use Dompdf\Options;

if (isset($_GET['id']) && isset($_GET['status'])) {
    $pedido_id = $_GET['id'];
    $novo_status = $_GET['status'];

    // Inicia uma transação para garantir consistência
    $conn->beginTransaction();

    try {
        // Atualiza o status do pedido
        $stmt = $conn->prepare("
            UPDATE pedidos 
            SET status = :status
            WHERE id = :id
        ");
        $stmt->bindParam(':status', $novo_status);
        $stmt->bindParam(':id', $pedido_id, PDO::PARAM_INT);
        $stmt->execute();

        // Busca os dados do pedido para o PDF
        $stmt = $conn->prepare("
            SELECT p.*, c.nome AS cliente_nome, c.cidade AS cliente_cidade, c.telefone AS cliente_telefone, c.documento AS cliente_documento
            FROM pedidos p
            JOIN clientes c ON p.cliente_id = c.id
            WHERE p.id = :id
        ");
        $stmt->bindParam(':id', $pedido_id, PDO::PARAM_INT);
        $stmt->execute();
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pedido) {
            throw new Exception("Pedido não encontrado.");
        }

        // Busca os itens do pedido, incluindo quantidade_separada e o tipo do produto
        $stmtItens = $conn->prepare("
            SELECT i.quantidade, i.quantidade_separada, i.valor_unitario, pr.nome AS produto_nome, pr.tipo AS produto_tipo
            FROM itens_pedido i
            JOIN produtos pr ON i.produto_id = pr.id
            WHERE i.pedido_id = :pedido_id
        ");
        $stmtItens->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
        $stmtItens->execute();
        $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

        // Recalcular o valor total com base em quantidade_separada
        $valorTotalRecalculado = 0;
        foreach ($itens as $item) {
            $quantidadeUsada = ($pedido['status'] !== 'Novo Pedido' && !is_null($item['quantidade_separada'])) ? $item['quantidade_separada'] : $item['quantidade'];
            $valorTotalRecalculado += $quantidadeUsada * $item['valor_unitario'];
        }

        // Atualizar o pedido com o valor_total recalculado
        $stmtUpdate = $conn->prepare("
            UPDATE pedidos 
            SET valor_total = :valor_total
            WHERE id = :id
        ");
        $stmtUpdate->bindParam(':valor_total', $valorTotalRecalculado);
        $stmtUpdate->bindParam(':id', $pedido_id, PDO::PARAM_INT);
        $stmtUpdate->execute();

        // Atualizar o valor_total no array $pedido
        $pedido['valor_total'] = $valorTotalRecalculado;

        // Configurações do Dompdf
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isPhpEnabled', true); // Permitir PHP no HTML
        $options->set('isHtml5ParserEnabled', true); // Usar parser HTML5
        $options->set('tempDir', '/tmp'); // Diretório temporário (ajuste conforme necessário)
        $options->set('logOutputFile', '/home/u991329655/domains/aogosto.com.br/public_html/php-error.log'); // Log de erros do Dompdf
        $dompdf = new Dompdf($options);

        // Definir o diretório base para resolver caminhos relativos
        $dompdf->setBasePath('/home/u991329655/domains/aogosto.com.br/public_html/atacado/');

        // Verificar se o arquivo da logo existe
        $logoPath = '/home/u991329655/domains/aogosto.com.br/public_html/atacado/uploads/logo-laranja.png';
        if (!file_exists($logoPath)) {
            error_log("Erro: Arquivo da logo não encontrado em $logoPath");
            throw new Exception("Arquivo da logo não encontrado.");
        } else {
            error_log("Logo encontrada em $logoPath");
        }

        // Gera o HTML a partir do template
        ob_start();
        include 'template_pdf.php';
        $html = ob_get_clean();

        // Log do HTML gerado para depuração
        file_put_contents('/home/u991329655/domains/aogosto.com.br/public_html/php-error.log', "HTML gerado para o PDF:\n$html\n", FILE_APPEND);

        // Carrega o HTML no Dompdf
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Confirma a transação
        $conn->commit();

        // Envia o PDF para o navegador
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="Pedido_' . $pedido_id . '.pdf"');
        echo $dompdf->output();
        exit;
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Erro ao gerar PDF: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
        exit;
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
    exit;
}
?>