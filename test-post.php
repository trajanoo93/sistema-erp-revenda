<?php
/**
 * test-post.php
 * Coloque na raiz: /atacado/
 * Acesse para ver o que está chegando
 */

echo "<h2>🔍 Teste de POST</h2>";
echo "<hr>";

echo "<h3>Método da Requisição:</h3>";
echo "<pre>" . $_SERVER['REQUEST_METHOD'] . "</pre>";

echo "<h3>Conteúdo de \$_POST:</h3>";
echo "<pre>";
print_r($_POST);
echo "</pre>";

echo "<h3>Conteúdo de \$_GET:</h3>";
echo "<pre>";
print_r($_GET);
echo "</pre>";

echo "<h3>Conteúdo de \$_REQUEST:</h3>";
echo "<pre>";
print_r($_REQUEST);
echo "</pre>";

echo "<h3>Headers da Requisição:</h3>";
echo "<pre>";
print_r(getallheaders());
echo "</pre>";

echo "<hr>";

// Formulário de teste
?>
<h3>Formulário de Teste:</h3>
<form method="POST" action="test-post.php">
    <label>Email:</label><br>
    <input type="email" name="email" value="teste@teste.com" style="padding:8px;width:300px;"><br><br>
    
    <label>Senha:</label><br>
    <input type="password" name="senha" value="123456" style="padding:8px;width:300px;"><br><br>
    
    <button type="submit" name="login" value="1" style="padding:10px 20px;background:#FC4813;color:white;border:none;border-radius:5px;cursor:pointer;">
        Testar POST
    </button>
</form>

<hr>
<p><strong>Instruções:</strong></p>
<ol>
    <li>Clique no botão "Testar POST" acima</li>
    <li>Veja se aparece: <code>login => 1</code> no $_POST</li>
    <li>Se aparecer, o problema está no caminho para authRoutes.php</li>
    <li>Se NÃO aparecer, há alguma configuração do servidor bloqueando</li>
</ol>