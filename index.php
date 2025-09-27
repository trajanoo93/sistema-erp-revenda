<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ERP Revenda</title>
    <link rel="stylesheet" href="public/css/login.css?v=19.0">
    <!-- Fonte Google Roboto -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="icon" href="uploads/logo.png" type="image/png">
</head>
<body class="login-page">
<div class="login-container">
    <!-- Logo -->
    <div class="logo-container">
        <img src="uploads/logo.png" alt="Logo">
    </div>

    <!-- Formulário de Login -->
    <div class="login-form">
        <h2>ERP Atacado</h2>
        <form action="/atacado/backend/routes/authRoutes.php" method="POST">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" placeholder="Digite seu email" required>
            </div>
            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" name="senha" placeholder="Digite sua senha" required>
            </div>
            <button type="submit" class="btn-login" name="login">Entrar</button>
        </form>
    </div>
</div>

</body>
</html>