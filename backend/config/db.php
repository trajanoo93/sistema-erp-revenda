<?php
/**
 * db.php - Configuração de Conexão com Banco de Dados
 * Sistema: ERP Web
 */

// Configurações do banco de dados
$host = 'localhost';
$db = 'u991329655_revenda';
$user = 'u991329655_revenda';
$pass = 'AoGosto!100**';
$logFile = __DIR__ . '/../../logs/db_errors.log';

// Função para registrar erros de banco
function registrarLogDB($mensagem, $nivel = 'ERROR') {
    global $logFile;
    
    // Tentar criar pasta de logs se não existir
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    // Registrar log apenas se possível
    if (is_dir($logDir) && is_writable($logDir)) {
        $dataHora = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $logEntry = "[$dataHora] [$nivel] [IP: $ip] $mensagem\n";
        @file_put_contents($logFile, $logEntry, FILE_APPEND);
        error_log($logEntry);
    }
}

// Definir o fuso horário no PHP
date_default_timezone_set('America/Sao_Paulo');

try {
    // Criar a conexão com MySQL utilizando utf8mb4
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    
    // Configurar PDO para lançar exceções em caso de erro
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Sincronizar timezone do MySQL com o PHP
    $conn->exec("SET time_zone = '-03:00'");
    
    registrarLogDB("Conexão com banco de dados estabelecida com sucesso.", 'INFO');
    
} catch (PDOException $e) {
    $errorMsg = "Erro na conexão com o banco: " . $e->getMessage();
    registrarLogDB($errorMsg, 'ERROR');
    
    // Retornar erro apropriado
    if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Erro interno no servidor.']);
    } else {
        echo 'Erro na conexão com o banco de dados. Por favor, contate o administrador.';
    }
    exit;
}
?>