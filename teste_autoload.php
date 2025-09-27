
<?php
// Defina o caminho para o arquivo de credenciais JSON
putenv('GOOGLE_APPLICATION_CREDENTIALS=/home/u991329655/domains/aogosto.com.br/public_html/atacado/exp/ao-gosto-app-2-27a6c6ace1db.json');

require_once 'vendor/autoload.php';

use Google\Auth\ApplicationDefaultCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Teste Google Auth: Tenta criar credenciais padrão do Google
try {
    $scopes = ['https://www.googleapis.com/auth/cloud-platform'];
    $credentials = ApplicationDefaultCredentials::getCredentials($scopes);
    echo "Google Auth: Biblioteca carregada com sucesso!<br>";
} catch (Exception $e) {
    echo "Erro ao carregar Google Auth: " . $e->getMessage() . "<br>";
}

// Teste GuzzleHttp: Envia uma requisição de exemplo
try {
    $client = new Client();
    $response = $client->get('https://jsonplaceholder.typicode.com/todos/1');
    echo "GuzzleHttp: Requisição bem-sucedida! Resposta:<br>";
    echo $response->getBody() . "<br>";
} catch (RequestException $e) {
    echo "Erro ao enviar requisição com Guzzle: " . $e->getMessage() . "<br>";
}