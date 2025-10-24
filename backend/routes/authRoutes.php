<?php
/**
 * authRoutes.php - COM DEBUG
 * Este arquivo mostra exatamente o que está acontecendo
 */

// ATIVAR ERROS PARA DEBUG
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Log de debug
$debug_log = [];
$debug_log[] = "=== INÍCIO DO PROCESSAMENTO ===";
$debug_log[] = "Data/Hora: " . date('Y-m-d H:i:s');
$debug_log[] = "Método: " . $_SERVER['REQUEST_METHOD'];
$debug_log[] = "POST login existe? " . (isset($_POST['login']) ? 'SIM' : 'NÃO');

// Incluir configuração do banco
require_once __DIR__ . '/../config/db.php';
$debug_log[] = "✅ db.php incluído";

// Incluir controlador de usuários
require_once __DIR__ . '/../controllers/userController.php';
$debug_log[] = "✅ userController.php incluído";

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    $debug_log[] = "✅ Sessão iniciada";
} else {
    $debug_log[] = "ℹ️ Sessão já estava ativa";
}

$debug_log[] = "Session ID ANTES: " . session_id();
$debug_log[] = "Conteúdo da sessão ANTES: " . print_r($_SESSION, true);

// ================================
// PROCESSAR LOGIN
// ================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    
    $debug_log[] = "=== PROCESSANDO LOGIN ===";
    
    // Limpar sessão antiga
    $debug_log[] = "Limpando sessão antiga...";
    session_unset();
    session_destroy();
    session_start();
    $debug_log[] = "✅ Sessão limpa e reiniciada";
    $debug_log[] = "Session ID DEPOIS: " . session_id();
    
    // Capturar dados
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $senha = isset($_POST['senha']) ? $_POST['senha'] : '';
    
    $debug_log[] = "Email recebido: $email";
    $debug_log[] = "Senha recebida: " . (empty($senha) ? 'VAZIA' : strlen($senha) . ' caracteres');
    
    // Validação
    if (empty($email) || empty($senha)) {
        $debug_log[] = "❌ ERRO: Campos vazios";
        file_put_contents(__DIR__ . '/../../logs/auth_debug.log', implode("\n", $debug_log) . "\n\n", FILE_APPEND);
        header('Location: /atacado/index.php?erro=campos_vazios');
        exit;
    }
    
    $debug_log[] = "✅ Validação OK";
    
    // Tentar login
    try {
        $debug_log[] = "Chamando função login()...";
        $resultado = login($email, $senha);
        $debug_log[] = "Resultado do login: " . print_r($resultado, true);
        
        if ($resultado && isset($resultado['id'])) {
            $debug_log[] = "✅ LOGIN BEM-SUCEDIDO!";
            
            // Armazenar na sessão
            $_SESSION['usuario_id'] = $resultado['id'];
            $_SESSION['usuario_nome'] = $resultado['nome'] ?? 'Usuário';
            $_SESSION['usuario_email'] = $email;
            $_SESSION['usuario_role'] = $resultado['role'] ?? 'user';
            $_SESSION['ultimo_acesso'] = time();
            
            $debug_log[] = "Dados salvos na sessão:";
            $debug_log[] = print_r($_SESSION, true);
            
            // Regenerar ID
            session_regenerate_id(true);
            $debug_log[] = "✅ Session ID regenerado: " . session_id();
            
            // Salvar log
            file_put_contents(__DIR__ . '/../../logs/auth_debug.log', implode("\n", $debug_log) . "\n\n", FILE_APPEND);
            
            $debug_log[] = "Redirecionando para dashboard...";
            
            // REDIRECIONAR
            header('Location: /atacado/dashboard.php');
            exit;
            
        } else {
            $debug_log[] = "❌ LOGIN FALHOU - Credenciais incorretas";
            file_put_contents(__DIR__ . '/../../logs/auth_debug.log', implode("\n", $debug_log) . "\n\n", FILE_APPEND);
            header('Location: /atacado/index.php?erro=credenciais');
            exit;
        }
        
    } catch (Exception $e) {
        $debug_log[] = "❌ EXCEÇÃO: " . $e->getMessage();
        $debug_log[] = "Stack trace: " . $e->getTraceAsString();
        file_put_contents(__DIR__ . '/../../logs/auth_debug.log', implode("\n", $debug_log) . "\n\n", FILE_APPEND);
        header('Location: /atacado/index.php?erro=erro_servidor');
        exit;
    }
}

// ================================
// PROCESSAR LOGOUT
// ================================
if (isset($_GET['logout'])) {
    $debug_log[] = "=== PROCESSANDO LOGOUT ===";
    session_unset();
    session_destroy();
    $debug_log[] = "✅ Sessão destruída";
    file_put_contents(__DIR__ . '/../../logs/auth_debug.log', implode("\n", $debug_log) . "\n\n", FILE_APPEND);
    header('Location: /atacado/index.php?sucesso=logout');
    exit;
}

// ================================
// ACESSO DIRETO
// ================================
$debug_log[] = "=== ACESSO DIRETO (sem POST/GET) ===";
$debug_log[] = "Redirecionando para index.php...";
file_put_contents(__DIR__ . '/../../logs/auth_debug.log', implode("\n", $debug_log) . "\n\n", FILE_APPEND);

header('Location: /atacado/index.php');
exit;
?>