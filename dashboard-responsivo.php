<?php
session_start();
// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: https://aogosto.com.br/atacado');
    exit;
}
// Verifica se o papel do usuário é "Expedição" e redireciona para a página de pedidos
if (isset($_SESSION['usuario_role']) && $_SESSION['usuario_role'] == 'Expedição') {
    header('Location: pedidos_exp.php');
    exit;
}
$nome_usuario = isset($_SESSION['usuario_nome']) ? $_SESSION['usuario_nome'] : 'Visitante';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#FC4813">
    <title>Dashboard</title>
    <link rel="icon" href="/atacado/uploads/logo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" onload="console.log('Font Awesome carregado com sucesso via CDN.')" onerror="console.error('Erro ao carregar Font Awesome via CDN.'); this.onerror=null; this.href='/atacado/public/css/fontawesome.min.css';">
    
    <!-- CSS Modular do Dashboard - NOVO -->
    <link rel="stylesheet" href="/atacado/public/css/dashboard.css?v=<?= time() ?>">
    
    <!-- CSS Global (apenas se necessário para outros componentes) -->
    <link rel="stylesheet" href="/atacado/public/css/style.css?v=<?= time() ?>">
    
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" onload="console.log('jQuery UI CSS carregado com sucesso.')" onerror="console.error('Erro ao carregar jQuery UI CSS.');">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" onload="console.log('Bootstrap CSS carregado com sucesso.')" onerror="console.error('Erro ao carregar Bootstrap CSS.');">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" onload="console.log('Toastr CSS carregado com sucesso.')" onerror="console.error('Erro ao carregar Toastr CSS.');">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" onload="console.log('jQuery carregado com sucesso.')" onerror="console.error('Erro ao carregar jQuery.')"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js" onload="console.log('jQuery UI JS carregado com sucesso.')" onerror="console.error('Erro ao carregar jQuery UI JS.')"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js" onload="console.log('Bootstrap JS carregado com sucesso.')" onerror="console.error('Erro ao carregar Bootstrap JS.')"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js" onload="console.log('jQuery Mask Plugin carregado com sucesso.')" onerror="console.error('Erro ao carregar jQuery Mask Plugin.')"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js" onload="console.log('Toastr JS carregado com sucesso.')" onerror="console.error('Erro ao carregar Toastr JS.')"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" onload="console.log('Chart.js carregado com sucesso.')" onerror="console.error('Erro ao carregar Chart.js via CDN.')"></script>
</head>
<body>
<div class="dashboard">
    <!-- Botão Mobile Menu - NOVO -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Abrir menu">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Overlay para fechar menu no mobile - NOVO -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo-container">
            <img src="/atacado/uploads/logo.png" alt="Logo" class="logo">
        </div>
        <ul>
            <li><a href="#" data-page="inicio.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
            <li><a href="#" data-page="clientes.php"><i class="fas fa-users"></i> <span>Clientes</span></a></li>
            <li><a href="#" data-page="produtos.php"><i class="fas fa-box"></i> <span>Produtos</span></a></li>
            <li><a href="#" data-page="pedidos.php"><i class="fas fa-file-alt"></i> <span>Pedidos</span></a></li>
            <li class="menu-relatorios">
                <a href="javascript:void(0)"><i class="fas fa-chart-line"></i> <span>Relatórios</span></a>
                <ul class="submenu">
                    <li><a href="#" data-page="relatorios/pedidos_relatorios.php"><i class="fas fa-file-alt"></i> Pedidos</a></li>
                    <li><a href="#" data-page="relatorios/clientes_relatorios.php"><i class="fas fa-users"></i> Clientes</a></li>
                    <li><a href="#" data-page="relatorios/produtos_relatorios.php"><i class="fas fa-box"></i> Produtos</a></li>
                </ul>
            </li>
            <li><a href="#" data-page="logs.php"><i class="fas fa-file-archive"></i> <span>Logs</span></a></li>
        </ul>
    </div>

    <!-- Containers de Edição -->
    <div id="editContainer" class="edit-container">
        <div class="panel-content">
            <span class="close-btn">×</span>
            <div id="formContent"></div>
        </div>
    </div>
    <div id="novoPedidoContainer" class="edit-container">
        <div class="panel-content">
            <span class="close-btn">×</span>
            <div id="formNovoPedidoContent"></div>
        </div>
    </div>
    <div id="novoProdutoContainer" class="edit-produto-container">
        <div class="panel-content">
            <span class="close-btn">×</span>
            <div id="formProdutoContent"></div>
        </div>
    </div>

    <!-- Conteúdo Principal -->
    <div class="dashboard-page">
        <div class="content" id="conteudo"></div>
    </div>
</div>

<script src="/atacado/public/js/produtos.js?v=<?= time() ?>" onerror="console.error('Erro ao carregar produtos.js')"></script>
<script src="/atacado/public/js/pedidos.js?v=<?= time() ?>" onerror="console.error('Erro ao carregar pedidos.js')"></script>
<script src="/atacado/public/js/clientes.js?v=<?= time() ?>" onerror="console.error('Erro ao carregar clientes.js')"></script>
<script>
    // ================================
    // FUNÇÃO MENU MOBILE - NOVO
    // ================================
    function initMobileMenu() {
        const mobileMenuToggle = $('#mobileMenuToggle');
        const sidebar = $('#sidebar');
        const sidebarOverlay = $('#sidebarOverlay');

        // Toggle do menu mobile
        mobileMenuToggle.on('click', function() {
            sidebar.toggleClass('active');
            sidebarOverlay.toggleClass('active');
            
            // Alternar ícone
            const icon = $(this).find('i');
            if (sidebar.hasClass('active')) {
                icon.removeClass('fa-bars').addClass('fa-times');
            } else {
                icon.removeClass('fa-times').addClass('fa-bars');
            }
        });

        // Fechar menu ao clicar no overlay
        sidebarOverlay.on('click', function() {
            sidebar.removeClass('active');
            sidebarOverlay.removeClass('active');
            mobileMenuToggle.find('i').removeClass('fa-times').addClass('fa-bars');
        });

        // Fechar menu ao clicar em um link (apenas no mobile)
        if (window.innerWidth <= 768) {
            $('.sidebar ul li a').on('click', function() {
                sidebar.removeClass('active');
                sidebarOverlay.removeClass('active');
                mobileMenuToggle.find('i').removeClass('fa-times').addClass('fa-bars');
            });
        }

        // Tratamento especial para submenu no mobile
        if (window.innerWidth <= 768) {
            $('.menu-relatorios > a').off('click').on('click', function(e) {
                e.preventDefault();
                $(this).parent().toggleClass('active');
            });
        }
    }

    // ================================
    // FUNÇÃO CARREGAR CSS
    // ================================
    function carregarCSS(href) {
        if (!$('link[href="' + href + '"]').length) {
            $('<link>')
                .appendTo('head')
                .attr({ type: 'text/css', rel: 'stylesheet' })
                .attr('href', href);
        }
    }

    // ================================
    // INICIALIZAR GRÁFICOS DO DASHBOARD
    // ================================
    function inicializarGraficosDashboard() {
        if (typeof Chart === 'undefined') {
            console.error('Chart.js não está carregado.');
            return;
        }
        var chartDataEl = $('#chartData');
        if (!chartDataEl.length) {
            console.error('Elemento #chartData não encontrado.');
            return;
        }
        var statusLabels = chartDataEl.data('status-labels');
        var statusData = chartDataEl.data('status-data');
        var diasLabels = chartDataEl.data('dias-labels');
        var vendasData = chartDataEl.data('vendas-data');
        console.log('Inicial Status Labels:', statusLabels);
        console.log('Inicial Status Data:', statusData);
        console.log('Inicial Dias Labels:', diasLabels);
        console.log('Inicial Vendas Data:', vendasData);
        
        var ctxStatus = document.getElementById('statusChart')?.getContext('2d');
        if (ctxStatus) {
            var statusChart = new Chart(ctxStatus, {
                type: 'pie',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        label: 'Pedidos por Status',
                        data: statusData,
                        backgroundColor: ['#FC4813', '#F97316', '#EA580C', '#EF4444', '#F87171'],
                        borderColor: ['#fff'],
                        borderWidth: 1,
                        hoverOffset: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { font: { size: 14, family: "'Inter', sans-serif", weight: 500 }, color: '#1F2937', padding: 15, boxWidth: 20 } },
                        tooltip: { backgroundColor: 'rgba(0, 0, 0, 0.8)', titleFont: { size: 14, family: "'Inter', sans-serif" }, bodyFont: { size: 12, family: "'Inter', sans-serif" }, padding: 10, cornerRadius: 8 }
                    },
                    animation: { duration: 1500, easing: 'easeInOutQuart' }
                }
            });
        }
        
        var ctxVendas = document.getElementById('vendasChart')?.getContext('2d');
        if (ctxVendas) {
            var vendasChart = new Chart(ctxVendas, {
                type: 'line',
                data: {
                    labels: diasLabels,
                    datasets: [{
                        label: 'Total de Vendas (R$)',
                        data: vendasData,
                        backgroundColor: 'rgba(252, 72, 19, 0.2)',
                        borderColor: '#FC4813',
                        borderWidth: 3,
                        pointBackgroundColor: '#FC4813',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: '#FC4813',
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                },
                                font: { size: 12, family: "'Inter', sans-serif" },
                                color: '#4B5563'
                            },
                            grid: { color: 'rgba(0, 0, 0, 0.05)' }
                        },
                        x: {
                            ticks: {
                                font: { size: 12, family: "'Inter', sans-serif" },
                                color: '#4B5563'
                            },
                            grid: { display: false }
                        }
                    },
                    plugins: {
                        legend: { position: 'top', labels: { font: { size: 14, family: "'Inter', sans-serif", weight: 500 }, color: '#1F2937', padding: 15, boxWidth: 20 } },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleFont: { size: 14, family: "'Inter', sans-serif" },
                            bodyFont: { size: 12, family: "'Inter', sans-serif" },
                            padding: 10,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    return 'R$ ' + context.parsed.y.toLocaleString('pt-BR');
                                }
                            }
                        }
                    },
                    animation: { duration: 1500, easing: 'easeInOutQuart' }
                }
            });
        }
    }

    // ================================
    // FUNÇÃO CARREGAR CONTEÚDO VIA AJAX
    // ================================
    function carregarConteudo(page) {
        $.ajax({
            url: page,
            method: "GET",
            success: function(data) {
                try {
                    $("#conteudo").html(data);
                    console.log(`Página ${page} carregada com sucesso.`);
                    
                    setTimeout(function() {
                        if (page === 'inicio.php') {
                            if (typeof Chart === 'undefined') {
                                console.log('Chart.js não está carregado. Tentando recarregar...');
                                $.getScript('https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js').done(function() {
                                    console.log('Chart.js recarregado com sucesso.');
                                    inicializarGraficosDashboard();
                                }).fail(function() {
                                    console.error('Erro ao recarregar Chart.js.');
                                });
                            } else {
                                inicializarGraficosDashboard();
                            }
                        } else if (page === 'pedidos.php') {
                            let attempt = 1;
                            const maxAttempts = 50;
                            const retryInterval = setInterval(function() {
                                if (typeof bindNovoPedidoEvent === 'function' && typeof bindFilterEvents === 'function') {
                                    bindNovoPedidoEvent();
                                    bindFilterEvents();
                                    console.log("Eventos de pedidos reaplicados após carregamento AJAX.");
                                    clearInterval(retryInterval);
                                } else {
                                    console.log(`Tentativa ${attempt}/${maxAttempts} de reaplicar eventos para pedidos... Funções ainda não disponíveis.`);
                                    attempt++;
                                    if (attempt >= maxAttempts) {
                                        console.error("Funções bindNovoPedidoEvent ou bindFilterEvents não encontradas após tentativas.");
                                        clearInterval(retryInterval);
                                    }
                                }
                            }, 1000);
                        } else if (page === 'clientes.php') {
                            if (typeof bindEvents === 'function' && typeof reapplyClientButtonEvents === 'function') {
                                bindEvents();
                                reapplyClientButtonEvents();
                                console.log("Eventos de clientes reaplicados após carregamento AJAX.");
                            } else {
                                console.error("Funções bindEvents ou reapplyClientButtonEvents não encontradas.");
                            }
                        } else if (page === 'produtos.php') {
                            if (typeof reapplyButtonEvents === 'function') {
                                let attempt = 1;
                                const maxAttempts = 50;
                                const retryInterval = setInterval(function() {
                                    if ($('#btnNovoProduto').length || attempt >= maxAttempts) {
                                        reapplyButtonEvents();
                                        console.log("Eventos de produtos reaplicados após carregamento AJAX.");
                                        clearInterval(retryInterval);
                                    } else {
                                        console.log(`Tentativa ${attempt}/${maxAttempts} de reaplicar eventos para produtos... Elemento #btnNovoProduto ainda não encontrado.`);
                                        attempt++;
                                    }
                                }, 1000);
                            } else {
                                console.error("Função reapplyButtonEvents não encontrada.");
                            }
                        }
                    }, 5000);
                } catch (e) {
                    console.error("Erro ao inserir conteúdo em #conteudo:", e);
                    $("#conteudo").html('<p>Erro ao carregar conteúdo: ' + e.message + '</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error(`Erro ao carregar ${page}:`, error);
                console.error("Status:", status);
                console.error("Response Text:", xhr.responseText);
                $("#conteudo").html('<p>Erro ao carregar conteúdo: ' + error + '</p>');
            }
        });
    }

    // ================================
    // CONFIGURAR MENU
    // ================================
    function configurarMenu() {
        $(".menu-relatorios > a").click(function(e) {
            e.preventDefault();
        });
        $(".sidebar ul li a").click(function(e) {
            e.preventDefault();
            var page = $(this).data("page");
            if (page) {
                $(".sidebar ul li a").removeClass("active");
                $(this).addClass("active");
                localStorage.setItem('activePage', page);
                carregarConteudo(page);
            }
        });
    }

    // ================================
    // INICIALIZAÇÃO DO DOCUMENTO
    // ================================
    $(document).ready(function() {
        console.log("dashboard.php: Scripts carregados. Verificando funções...");
        
        // Inicializar menu mobile
        initMobileMenu();
        
        if (typeof $.fn.modal === 'function') {
            console.log("Método modal do Bootstrap está disponível.");
        } else {
            console.error("Método modal do Bootstrap NÃO está disponível.");
        }
        
        setTimeout(function() {
            let attempt = 1;
            const maxAttempts = 30;
            const retryInterval = setInterval(function() {
                if (typeof bindNovoPedidoEvent === 'function' && typeof bindFilterEvents === 'function') {
                    bindNovoPedidoEvent();
                    bindFilterEvents();
                    console.log("Funções bindNovoPedidoEvent e bindFilterEvents encontradas.");
                    clearInterval(retryInterval);
                } else {
                    console.log(`Tentativa ${attempt}/${maxAttempts} de encontrar funções bindNovoPedidoEvent e bindFilterEvents...`);
                    attempt++;
                    if (attempt >= maxAttempts) {
                        console.error("Funções bindNovoPedidoEvent ou bindFilterEvents NÃO encontradas após tentativas.");
                        clearInterval(retryInterval);
                    }
                }
            }, 1000);
        }, 10000);
        
        if (typeof reapplyButtonEvents === 'function') {
            let attempt = 1;
            const maxAttempts = 30;
            const retryInterval = setInterval(function() {
                if ($('#btnNovoProduto').length || attempt >= maxAttempts) {
                    reapplyButtonEvents();
                    console.log("Eventos de produtos reaplicados na carga inicial.");
                    clearInterval(retryInterval);
                } else {
                    console.log(`Tentativa ${attempt}/${maxAttempts} de reaplicar eventos na carga inicial... Elemento #btnNovoProduto ainda não encontrado.`);
                    attempt++;
                }
            }, 1000);
        } else {
            console.error("Função reapplyButtonEvents NÃO encontrada após a carga inicial.");
        }
        
        if (typeof bindEvents === 'function' && typeof reapplyClientButtonEvents === 'function') {
            bindEvents();
            reapplyClientButtonEvents();
            console.log("Eventos de clientes reaplicados na carga inicial.");
        } else {
            console.error("Funções bindEvents ou reapplyClientButtonEvents NÃO encontradas após a carga inicial.");
        }
        
        $(".logout").on("click", function() {
            localStorage.clear();
        });
        
        var activePage = localStorage.getItem('activePage') || 'dashboard.php';
        carregarConteudo(activePage);
        
        $(".sidebar ul li a").each(function() {
            if ($(this).data("page") === activePage) {
                $(this).addClass("active");
            }
        });
        
        configurarMenu();

        // Reinicializar menu mobile ao redimensionar janela
        $(window).on('resize', function() {
            if (window.innerWidth > 768) {
                $('#sidebar').removeClass('active');
                $('#sidebarOverlay').removeClass('active');
                $('#mobileMenuToggle i').removeClass('fa-times').addClass('fa-bars');
            }
        });
    });
</script>
</body>
</html>