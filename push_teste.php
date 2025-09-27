<?php

function sendFCMNotification($fcmToken, $orderStatus) {
    // Use uma única chave de API para Android e iOS
    $serverKey = 'AIzaSyClFKjQFd4HVMKdL9JOX6G_0oH9ez-jraY';

    $fcmUrl = 'https://fcm.googleapis.com/fcm/send';

    $notification = [
        'title' => 'Teste de Notificação',
        'body' => "Status do pedido: $orderStatus",
        'sound' => 'default'
    ];

    $fcmNotification = [
        'to' => $fcmToken,
        'notification' => $notification
    ];

    $headers = [
        'Authorization: key=' . $serverKey,
        'Content-Type: application/json'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fcmUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fcmNotification));
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}

// Teste de envio de notificação
$fcmToken = 'AIzaSyClFKjQFd4HVMKdL9JOX6G_0oH9ez-jraY'; // Substitua pelo token FCM do dispositivo
$orderStatus = 'Concluído'; // Status do pedido de exemplo
$response = sendFCMNotification($fcmToken, $orderStatus);
echo "Resposta do FCM: " . $response;

?>