<?php
// Configurações de conexão com o banco de dados
$servername = "localhost";
$username = "u991329655_revenda";
$password = "AoGosto!100**";
$dbname = "u991329655_revenda";

// Conectar ao banco de dados
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar a conexão
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Obter dados do POST
$user_id = $_POST['user_id'];
$fcm_token = $_POST['fcm_token'];
$platform = $_POST['platform'];

// Verificar se o token já existe para o usuário e atualizar ou inserir
$sql = "SELECT id FROM fcm_tokens WHERE user_id = ? AND platform = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Erro ao preparar statement: " . $conn->error);
}
$stmt->bind_param("is", $user_id, $platform);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    // Atualizar token existente
    $update_sql = "UPDATE fcm_tokens SET fcm_token = ?, last_updated = CURRENT_TIMESTAMP WHERE user_id = ? AND platform = ?";
    $update_stmt = $conn->prepare($update_sql);
    if ($update_stmt === false) {
        die("Erro ao preparar update statement: " . $conn->error);
    }
    $update_stmt->bind_param("sis", $fcm_token, $user_id, $platform);
    if (!$update_stmt->execute()) {
        die("Erro ao executar update: " . $update_stmt->error);
    }
    $update_stmt->close();
} else {
    // Inserir novo token
    $insert_sql = "INSERT INTO fcm_tokens (user_id, fcm_token, platform) VALUES (?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    if ($insert_stmt === false) {
        die("Erro ao preparar insert statement: " . $conn->error);
    }
    $insert_stmt->bind_param("iss", $user_id, $fcm_token, $platform);
    if (!$insert_stmt->execute()) {
        die("Erro ao executar insert: " . $insert_stmt->error);
    }
    $insert_stmt->close();
}

$stmt->close();
$conn->close();

echo "Token salvo com sucesso!";