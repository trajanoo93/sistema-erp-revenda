<?php
require_once 'backend/config/db.php';

if (!isset($_GET['id'])) {
    echo "ID do cliente não fornecido.";
    exit;
}

$id = $_GET['id'];

// Buscar cliente no banco de dados
$stmt = $conn->prepare("SELECT * FROM clientes WHERE id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    echo "Cliente não encontrado.";
    exit;
}
?>

<h2>Editar Cliente #<?= $cliente['id'] ?></h2>

<form id="formEditarCliente" method="POST">
    <input type="hidden" name="id" value="<?= $cliente['id'] ?>">

    <label for="nome">Nome:</label>
    <input type="text" name="nome" id="nome" value="<?= $cliente['nome'] ?>" required><br>

    <label for="telefone">Telefone:</label>
    <input type="text" name="telefone" id="telefone" value="<?= $cliente['telefone'] ?>" required><br>

    <label for="cidade">Cidade:</label>
    <input type="text" name="cidade" id="cidade" value="<?= $cliente['cidade'] ?>" required><br>

    <label for="cnpj">CNPJ:</label>
    <input type="text" name="cnpj" id="cnpj" value="<?= $cliente['cnpj'] ?>" required><br>

    <button type="submit">Salvar Alterações</button>
</form>

<script>
    // Enviar o formulário de edição de cliente via AJAX
   // Enviar o formulário de edição de cliente via AJAX
$("#formEditarCliente").submit(function (e) {
    e.preventDefault(); // Impede o comportamento padrão do formulário

    var dadosForm = $(this).serialize(); // Serializar os dados do formulário

    $.ajax({
        url: "salvar_cliente.php", // Atualiza o cliente via AJAX
        method: "POST",
        data: dadosForm,
        success: function (response) {
            console.log("Resposta do servidor:", response); // Adiciona log detalhado da resposta
            try {
                var result = JSON.parse(response);
                if (result.status === "success") {
                    alert("Cliente atualizado com sucesso!");
                    $('#editClienteContainer').removeClass('active'); // Fecha o painel após salvar
                    location.reload(); // Recarrega a página de clientes
                } else {
                    alert("Erro ao atualizar cliente: " + result.message);
                }
            } catch (error) {
                console.error("Erro ao analisar a resposta JSON:", error);
                alert("Erro inesperado ao atualizar cliente.");
            }
        },
        error: function (xhr, status, error) {
            console.error("Erro na requisição AJAX:", status, error);
            alert("Erro na comunicação com o servidor.");
        }
    });
});
</script>