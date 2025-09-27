<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'backend/config/db.php';

if (!$conn) {
    die("Erro ao conectar com o banco de dados.");
}

// Consultar as cidades no banco de dados
$cidades = [];
$query = $conn->query("SELECT nome FROM cidades_mg ORDER BY nome ASC");
if ($query) {
    $cidades = $query->fetchAll(PDO::FETCH_COLUMN);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : null;
    $telefone = isset($_POST['telefone']) ? trim($_POST['telefone']) : null;
    $cidade = isset($_POST['cidade']) ? trim($_POST['cidade']) : null;
    $documento = isset($_POST['documento']) ? trim($_POST['documento']) : null;

    if (!$nome || !$telefone || !$cidade || !$documento) {
        echo json_encode(['status' => 'error', 'message' => 'Todos os campos são obrigatórios.']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO clientes (nome, telefone, cidade, documento) VALUES (:nome, :telefone, :cidade, :documento)");
    $stmt->bindParam(':nome', $nome);
    $stmt->bindParam(':telefone', $telefone);
    $stmt->bindParam(':cidade', $cidade);
    $stmt->bindParam(':documento', $documento);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Cliente criado com sucesso!']);
    } else {
        $errorInfo = $stmt->errorInfo();
        error_log("Erro ao inserir no banco: " . $errorInfo[2]);
        echo json_encode(['status' => 'error', 'message' => 'Erro ao criar o cliente.']);
    }
}
?>

<!-- HTML e scripts para o formulário -->
<div class="container">
    <h1>Adicionar Novo Cliente</h1>

    <form id="formNovoCliente" method="POST" class="form-cliente">
        <label for="nome">Nome:</label>
        <input type="text" name="nome" id="nome" placeholder="Digite o nome do cliente" required><br>

        <label for="telefone">Telefone:</label>
        <input type="text" name="telefone" id="telefone" placeholder="Digite o telefone" required><br>

        <label for="cidade">Cidade:</label>
        <input type="text" name="cidade" id="cidade" placeholder="Digite a cidade" required><br>

        <label for="tipo_cliente">Tipo de Cliente:</label>
        <select name="tipo_cliente" id="tipo_cliente">
            <option value="cpf">Pessoa Física (CPF)</option>
            <option value="cnpj">Pessoa Jurídica (CNPJ)</option>
        </select><br>

        <label for="documento">CPF/CNPJ:</label>
        <input type="text" name="documento" id="documento" placeholder="Digite o CPF ou CNPJ" required><br>

        <button type="submit" class="btn-submit">Salvar</button>
    </form>

    <div id="mensagem"></div> <!-- Mensagem de sucesso ou erro -->
</div>


<script src="public/js/clientes.js"></script> 

<!-- Script para pesquisa de cidades e máscara de CPF/CNPJ -->
<script>
    $(document).ready(function() {
        // Função para aplicar a máscara correta
        $('#tipo_cliente').change(function() {
            var tipo = $(this).val();
            $('#documento').val(''); // Limpa o campo antes de aplicar a nova máscara
            
            if (tipo === 'cpf') {
                $('#documento').mask('000.000.000-00', {reverse: true});
                $('#documento').attr('placeholder', 'Digite o CPF');
            } else {
                $('#documento').mask('00.000.000/0000-00', {reverse: true});
                $('#documento').attr('placeholder', 'Digite o CNPJ');
            }
        });

        // Inicializa a máscara com CPF como padrão
        $('#tipo_cliente').trigger('change');

        // Função para autocomplete de cidades
        var cidades = <?php echo json_encode($cidades); ?>;
        $('#cidade').autocomplete({
            source: cidades
        });
    });
</script>

<!-- Estilo personalizado para o formulário -->
<style>
    .container h1 {
        font-size: 1.5rem;
        margin-bottom: 15px;
    }

    .form-cliente label {
        font-size: 0.9rem;
        font-weight: 600;
        margin-bottom: 5px;
        display: block;
    }

    .form-cliente input, .form-cliente select {
        width: 100%;
        padding: 8px;
        margin-bottom: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 0.9rem;
    }

    .btn-submit {
        background-color: #FC4813;
        color: white;
        border: none;
        padding: 10px 15px;
        font-size: 0.9rem;
        font-weight: bold;
        border-radius: 4px;
        cursor: pointer;
        width: 100%;
        text-align: center;
        transition: background-color 0.3s ease;
    }

    .btn-submit:hover {
        background-color: #E33D10;
    }

    #mensagem p {
        margin-top: 15px;
        font-size: 0.9rem;
    }
</style>