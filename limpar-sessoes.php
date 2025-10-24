<?php
/**
 * limpar-sessoes.php
 * EXECUTE ESTE ARQUIVO UMA VEZ para limpar todas as sessões
 * Acesse: aogosto.com.br/atacado/limpar-sessoes.php
 * DELETE após usar!
 */

echo "<h2>🧹 Limpando Sessões do Sistema</h2>";
echo "<hr>";

// 1. Destruir sessão atual
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
    echo "✅ Sessão atual destruída<br>";
} else {
    session_start();
    session_destroy();
    echo "✅ Sessão iniciada e destruída<br>";
}

// 2. Limpar cookies de sessão
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
    echo "✅ Cookie de sessão removido<br>";
}

// 3. Limpar TODOS os cookies do domínio
foreach ($_COOKIE as $cookie_name => $cookie_value) {
    setcookie($cookie_name, '', time() - 3600, '/');
    echo "✅ Cookie '$cookie_name' removido<br>";
}

// 4. Tentar limpar arquivos de sessão (se tiver permissão)
$session_path = session_save_path();
if (empty($session_path)) {
    $session_path = sys_get_temp_dir();
}
echo "<br>Pasta de sessões: $session_path<br>";

// 5. Criar nova sessão LIMPA
session_start();
echo "<br>✅ Nova sessão criada: " . session_id() . "<br>";
echo "Conteúdo da sessão: ";
print_r($_SESSION);

echo "<hr>";
echo "<div style='background:#D1FAE5;padding:20px;border-radius:8px;border:2px solid #10B981;'>";
echo "<h3 style='color:#065F46;'>✅ Limpeza Concluída!</h3>";
echo "<p style='color:#047857;'>Agora faça o seguinte:</p>";
echo "<ol style='color:#047857;'>";
echo "<li><strong>Feche TODAS as abas do navegador</strong></li>";
echo "<li><strong>Abra uma nova aba</strong></li>";
echo "<li><strong>Acesse: <a href='index.php'>aogosto.com.br/atacado/</a></strong></li>";
echo "<li><strong>Faça login normalmente</strong></li>";
echo "</ol>";
echo "<p style='color:#DC2626;'><strong>⚠️ DELETE este arquivo após usar!</strong></p>";
echo "</div>";
?>