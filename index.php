<?php
// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Limpar sessões antigas
if (isset($_SESSION['cod_vendedor']) || isset($_SESSION['nome_vendedor'])) {
    session_unset();
    session_destroy();
    session_start();
}

// Se já estiver logado, redireciona
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Mensagens
$erro = isset($_GET['erro']) ? $_GET['erro'] : '';
$sucesso = isset($_GET['sucesso']) ? $_GET['sucesso'] : '';

$mensagens_erro = [
    'credenciais' => 'Email ou senha incorretos.',
    'campos_vazios' => 'Por favor, preencha todos os campos.',
    'sessao_expirada' => 'Sua sessão expirou. Faça login novamente.',
    'acesso_negado' => 'Você precisa estar logado para acessar esta página.',
    'erro_servidor' => 'Erro no servidor. Tente novamente.',
];

$mensagens_sucesso = [
    'logout' => 'Você saiu com sucesso!',
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ERP Atacado</title>
    <link rel="icon" href="uploads/logo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="public/css/login.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="logo-container">
            <img src="uploads/logo.png" alt="Logo">
        </div>

        <div class="login-form">
            <h2>ERP Atacado</h2>
            <p>Faça login para acessar o sistema</p>

            <?php if ($erro && isset($mensagens_erro[$erro])): ?>
            <div class="alert alert-error" style="background:#FEE2E2;color:#991B1B;padding:12px;border-radius:8px;margin-bottom:15px;border:1px solid #FCA5A5;">
                <i class="fas fa-exclamation-circle"></i>
                <?= $mensagens_erro[$erro] ?>
            </div>
            <?php endif; ?>

            <?php if ($sucesso && isset($mensagens_sucesso[$sucesso])): ?>
            <div class="alert alert-success" style="background:#D1FAE5;color:#065F46;padding:12px;border-radius:8px;margin-bottom:15px;border:1px solid #6EE7B7;">
                <i class="fas fa-check-circle"></i>
                <?= $mensagens_sucesso[$sucesso] ?>
            </div>
            <?php endif; ?>

            <!-- FORMULÁRIO COM INPUT HIDDEN -->
            <form method="POST" action="backend/routes/authRoutes.php" id="loginForm">
                
                <!-- INPUT HIDDEN para garantir que "login" seja enviado -->
                <input type="hidden" name="login" value="1">
                
                <div class="form-group with-icon">
                    <label for="email">Email</label>
                    <input 
                        type="email" 
                        name="email" 
                        id="email"
                        placeholder="Digite seu email" 
                        required
                        autocomplete="email"
                    >
                    <i class="fas fa-envelope"></i>
                </div>

                <div class="form-group with-icon">
                    <label for="senha">Senha</label>
                    <input 
                        type="password" 
                        name="senha" 
                        id="senha"
                        placeholder="Digite sua senha" 
                        required
                        autocomplete="current-password"
                    >
                    <i class="fas fa-lock"></i>
                </div>

                <!-- BOTÃO SEM name -->
                <button type="submit" class="btn-login" id="btnLogin">
                    Entrar
                </button>
            </form>
        </div>

        <div class="login-footer">
            <p>&copy; <?= date('Y') ?> ERP Atacado. Todos os direitos reservados.</p>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var form = document.getElementById('loginForm');
        var btn = document.getElementById('btnLogin');
        
        form.addEventListener('submit', function(e) {
            // Mostrar loading
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Entrando...';
            btn.disabled = true;
            
            // Debug
            console.log('Formulário enviando...');
            console.log('Action:', form.action);
        });
    });
    </script>
</body>
</html>