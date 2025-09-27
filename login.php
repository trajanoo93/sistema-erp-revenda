<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ERP Atacado</title>

    <!-- Link para o estilo personalizado -->
    <link rel="stylesheet" href="css/login.css?v=2.0">
    
    <!-- Fonte Roboto para modernidade -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <!-- Logo no topo -->
        <div class="logo-container">
            <img src="uploads/logo.png" alt="Logo ERP">
        </div>

        <!-- Formulário de Login -->
        <form action="/atacado/backend/routes/authRoutes.php" method="POST" class="login-form">
            <h2>Acessar o Sistema</h2>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" placeholder="Digite seu email" required>
            </div>
            
            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" name="senha" placeholder="Digite sua senha" required>
            </div>
            
            <button type="submit" name="login" class="btn-login">Entrar</button>
        </form>
    </div>
</body>
</html>