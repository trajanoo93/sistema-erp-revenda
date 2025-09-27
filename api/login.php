<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../backend/config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method == 'POST') {
    authenticateUser();
} else {
    echo json_encode(['message' => 'Método não suportado.']);
}

function authenticateUser() {
    global $conn;

    $data = json_decode(file_get_contents("php://input"), true);

    if (!empty($data['email']) && !empty($data['senha'])) {
        $email = $data['email'];
        $senha = $data['senha'];

        // Verificar se o usuário existe no banco de dados
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verificar a senha usando password_verify
            if (password_verify($senha, $user['senha'])) {
                echo json_encode([
                    'status' => 'success',
                    'user_id' => $user['id'],
                    'nome' => $user['nome'],
                    'role' => $user['role']
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Senha incorreta']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Usuário não encontrado']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Dados incompletos']);
    }
}
?>