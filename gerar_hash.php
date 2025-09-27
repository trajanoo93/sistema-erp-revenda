<?php
$novaSenha = '32131028'; // Senha temporária que você fornecerá ao usuário
$hash = password_hash($novaSenha, PASSWORD_BCRYPT, ['cost' => 10]);
echo "Novo hash para a senha '$novaSenha': " . $hash;
?>