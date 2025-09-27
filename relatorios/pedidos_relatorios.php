<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: https://aogosto.com.br/atacado'); // Redireciona para o login se não houver sessão
    exit;
}

// Caso contrário, continue com a lógica da página
?>
<!-- Conteúdo HTML ou PHP da página -->
<h1>Relatório de Pedidos</h1>
<p>Em breve, aqui estará o relatório dos pedidos.</p>