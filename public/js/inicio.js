window.onload = function() {
    // Log de depuração para garantir que o script está sendo carregado
    console.log('Arquivo inicio.js carregado com sucesso!');

    // Pegando os dados de status e quantidade dinamicamente
    var statusLabels = <?php echo json_encode(array_column($statusCounts, 'status')); ?>;
    var statusData = <?php echo json_encode(array_column($statusCounts, 'quantidade')); ?>;
    
    // Gráfico de Pizza para Pedidos por Status
    var ctxStatus = document.getElementById('statusChart').getContext('2d');
    var statusChart = new Chart(ctxStatus, {
        type: 'pie',
        data: {
            labels: statusLabels,
            datasets: [{
                label: 'Pedidos por Status',
                data: statusData,
                backgroundColor: ['#ff7e5f', '#28a745', '#ffc107', '#17a2b8'], // Cores para os diferentes status
                borderColor: ['#fff', '#fff'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // Pegando os dados de vendas diárias dinamicamente
    var diasLabels = <?php echo json_encode(array_column($vendasDia, 'dia')); ?>;
    var vendasData = <?php echo json_encode(array_column($vendasDia, 'total_vendas')); ?>;

    // Gráfico de Linha para Total de Vendas no Mês
    var ctxVendas = document.getElementById('vendasChart').getContext('2d');
    var vendasChart = new Chart(ctxVendas, {
        type: 'line',
        data: {
            labels: diasLabels,
            datasets: [{
                label: 'Total de Vendas (R$)',
                data: vendasData,
                backgroundColor: 'rgba(40, 167, 69, 0.2)',
                borderColor: '#28a745',
                borderWidth: 2,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
};