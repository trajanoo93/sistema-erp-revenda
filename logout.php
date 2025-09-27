<?php
session_start();
session_unset(); // Limpa todas as variáveis de sessão
session_destroy(); // Destrói a sessão atual
header('Location: https://aogosto.com.br/atacado'); // Redireciona para a página de login
exit();
?>