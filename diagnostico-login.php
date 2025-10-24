<?php
/**
 * diagnostico-login.php
 * Coloque este arquivo na raiz /atacado/
 * Acesse: aogosto.com.br/atacado/diagnostico-login.php
 */

echo "<h2>🔍 Diagnóstico do Sistema de Login</h2>";
echo "<hr>";

// 1. Verificar conexão com banco
echo "<h3>1. Conexão com Banco de Dados</h3>";
try {
    require_once 'backend/config/db.php';
    echo "✅ Conexão estabelecida com sucesso<br>";
    echo "Banco: u991329655_revenda<br>";
} catch (Exception $e) {
    echo "❌ Erro na conexão: " . $e->getMessage() . "<br>";
}
echo "<hr>";

// 2. Verificar se tabela usuarios existe
echo "<h3>2. Tabela 'usuarios'</h3>";
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'usuarios'");
    $existe = $stmt->fetch();
    
    if ($existe) {
        echo "✅ Tabela 'usuarios' existe<br>";
        
        // Contar usuários
        $stmt = $conn->query("SELECT COUNT(*) as total FROM usuarios");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Total de usuários: " . $result['total'] . "<br>";
        
    } else {
        echo "❌ Tabela 'usuarios' NÃO existe<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
}
echo "<hr>";

// 3. Verificar estrutura da tabela
echo "<h3>3. Estrutura da Tabela 'usuarios'</h3>";
try {
    $stmt = $conn->query("DESCRIBE usuarios");
    $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse:collapse;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Chave</th></tr>";
    foreach ($colunas as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
}
echo "<hr>";

// 4. Verificar se userController.php existe
echo "<h3>4. Arquivo userController.php</h3>";
$userControllerPath = 'backend/controllers/userController.php';
if (file_exists($userControllerPath)) {
    echo "✅ Arquivo existe<br>";
    
    // Verificar se função login existe
    require_once $userControllerPath;
    if (function_exists('login')) {
        echo "✅ Função login() existe<br>";
    } else {
        echo "❌ Função login() NÃO existe<br>";
    }
} else {
    echo "❌ Arquivo NÃO existe em: $userControllerPath<br>";
}
echo "<hr>";

// 5. Verificar authRoutes.php
echo "<h3>5. Arquivo authRoutes.php</h3>";
$authRoutesPath = 'backend/routes/authRoutes.php';
if (file_exists($authRoutesPath)) {
    echo "✅ Arquivo existe<br>";
    echo "Localização: $authRoutesPath<br>";
} else {
    echo "❌ Arquivo NÃO existe em: $authRoutesPath<br>";
}
echo "<hr>";

// 6. Testar login com usuário real
echo "<h3>6. Teste de Login</h3>";
echo "<form method='POST' style='background:#f0f0f0;padding:20px;border-radius:8px;'>";
echo "<p><strong>⚠️ Digite as credenciais de UM usuário que você sabe que existe:</strong></p>";
echo "<label>Email:</label><br>";
echo "<input type='email' name='test_email' required style='width:300px;padding:8px;margin:5px 0;'><br>";
echo "<label>Senha:</label><br>";
echo "<input type='password' name='test_senha' required style='width:300px;padding:8px;margin:5px 0;'><br>";
echo "<button type='submit' name='testar_login' style='padding:10px 20px;margin-top:10px;background:#FC4813;color:white;border:none;border-radius:5px;cursor:pointer;'>Testar Login</button>";
echo "</form>";

if (isset($_POST['testar_login'])) {
    echo "<div style='background:#FFF3CD;padding:15px;margin-top:15px;border-radius:8px;'>";
    echo "<h4>Resultado do Teste:</h4>";
    
    $test_email = $_POST['test_email'];
    $test_senha = $_POST['test_senha'];
    
    echo "Email fornecido: $test_email<br>";
    echo "Senha fornecida: " . str_repeat('*', strlen($test_senha)) . "<br><br>";
    
    // Buscar usuário
    try {
        $stmt = $conn->prepare("SELECT id, nome, email, senha, role FROM usuarios WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $test_email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario) {
            echo "✅ Usuário encontrado no banco<br>";
            echo "ID: {$usuario['id']}<br>";
            echo "Nome: {$usuario['nome']}<br>";
            echo "Email: {$usuario['email']}<br>";
            echo "Role: {$usuario['role']}<br>";
            echo "Hash da senha no banco: " . substr($usuario['senha'], 0, 20) . "...<br><br>";
            
            // Verificar senha
            if (password_verify($test_senha, $usuario['senha'])) {
                echo "✅ <strong style='color:green;'>SENHA CORRETA!</strong><br>";
                echo "🎉 Login deveria funcionar com estas credenciais!<br>";
            } else {
                echo "❌ <strong style='color:red;'>SENHA INCORRETA!</strong><br>";
                echo "A senha digitada não corresponde ao hash no banco.<br>";
            }
        } else {
            echo "❌ Usuário NÃO encontrado com este email<br>";
            echo "Verifique se o email está correto no banco de dados.<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Erro ao buscar usuário: " . $e->getMessage() . "<br>";
    }
    
    echo "</div>";
}
echo "<hr>";

// 7. Verificar sessão
echo "<h3>7. Configuração de Sessão</h3>";
session_start();
echo "Session ID: " . session_id() . "<br>";
echo "Session Status: " . session_status() . "<br>";
echo "Dados da sessão: ";
print_r($_SESSION);
echo "<hr>";

// 8. Informações do servidor
echo "<h3>8. Informações do Servidor</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "<br>";

echo "<hr>";
echo "<p><strong>📋 Copie TODOS os resultados acima e me envie!</strong></p>";
echo "<p style='color:red;'><strong>⚠️ DELETE este arquivo após o diagnóstico!</strong></p>";
?>