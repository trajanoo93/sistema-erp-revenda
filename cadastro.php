<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Usuário - ERP Revenda</title>
     <link rel="icon" href="uploads/logo.png" type="image/png">
    <link rel="stylesheet" href="public/css/cadastro.css?v=15.0">
</head>
<body>
    <div class="container">
        <div class="form-container">
            <img src="uploads/logo.png" alt="Logo" class="logo">
            <h2>Cadastrar Usuário</h2>
            <form action="/atacado/backend/routes/userRoutes.php" method="POST">
                <div class="input-group">
                    <label for="nome">Nome</label>
                    <input type="text" name="nome" placeholder="Digite seu nome" required>
                </div>
                <div class="input-group">
                    <label for="email">Email</label>
                    <input type="email" name="email" placeholder="Digite seu email" required>
                </div>
                <div class="input-group">
                    <label for="senha">Senha</label>
                    <input type="password" name="senha" placeholder="Digite sua senha" required>
                </div>
                <button type="submit" name="cadastrar" class="btn-submit">Cadastrar</button>
            </form>
        </div>
    </div>
</body>
</html>