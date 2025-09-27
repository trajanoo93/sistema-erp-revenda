<?php
// Inclua a biblioteca Dompdf e o arquivo de configuração do banco de dados
require_once 'dompdf/autoload.inc.php';
require_once 'backend/config/db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


use Dompdf\Dompdf;
use Dompdf\Options;

// Configurações do Dompdf
$options = new Options();
$options->set('isRemoteEnabled', true); // Permitir o carregamento de URLs remotas
$dompdf = new Dompdf($options);

// Verificar se o ID do pedido foi passado na URL
if (isset($_GET['id'])) {
    $pedido_id = $_GET['id'];

    // Consulta para obter os detalhes do pedido
    $stmt = $conn->prepare("
        SELECT p.*, c.nome AS cliente_nome, c.cidade AS cliente_cidade, c.telefone AS cliente_telefone
        FROM pedidos p
        JOIN clientes c ON p.cliente_id = c.id
        WHERE p.id = :id
    ");
    $stmt->bindParam(':id', $pedido_id, PDO::PARAM_INT);
    $stmt->execute();
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verificar se o pedido existe
    if ($pedido) {
        // Consulta para obter os itens do pedido
        $stmtItens = $conn->prepare("
            SELECT i.quantidade, i.valor_unitario, pr.nome AS produto_nome
            FROM itens_pedido i
            JOIN produtos pr ON i.produto_id = pr.id
            WHERE i.pedido_id = :pedido_id
        ");
        $stmtItens->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
        $stmtItens->execute();
        $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

        // Iniciar buffer de saída para capturar o HTML do template
        ob_start();
        include 'template_pdf.php';
        $html = ob_get_clean();

        // Carregar o conteúdo HTML no Dompdf
        $dompdf->loadHtml($html);

        // Configurar tamanho e orientação do papel
        $dompdf->setPaper('A4', 'portrait');

        // Renderizar o PDF
        $dompdf->render();

        // Enviar o PDF para o navegador
        $dompdf->stream('Pedido_'.$pedido_id.'.pdf', array('Attachment' => 1));
    } else {
        // Mensagem de erro se o pedido não foi encontrado
        echo "Pedido não encontrado.";
    }
} else {
    // Mensagem de erro se o ID do pedido não foi fornecido
    echo "ID do pedido não fornecido.";
}
?>