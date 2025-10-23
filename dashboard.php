<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$user = htmlspecialchars(strstr($_SESSION['user'], '@', true));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Controle de Ponto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .dashboard-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 15px;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .card-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .welcome-section {
            background: rgba(255,255,255,0.95);
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="https://app.grupofarias.com.br/_next/image?url=%2Fassets%2Fimages%2Flogos%2Flogo.png&w=96&q=75&dpl=dpl_2zZSoeRAkK891Wnr1b564YVbKpdi" alt="Logo Grupo Farias" style="height:38px; margin-right:12px;">
                <span class="fw-bold">Controle de Ponto - GF</span>
            </a>
            <div class="d-flex align-items-center">
                <span class="me-3">Bem-vindo, <strong><?= htmlspecialchars($user) ?></strong></span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <!-- Seção de Boas-vindas -->
        <div class="welcome-section p-4 mb-5 text-center">
            <h1 class="display-4 fw-bold text-primary mb-3">
                <i class="bi bi-speedometer2"></i> Dashboard
            </h1>
            <p class="lead text-muted">Sistema de Controle de Ponto - Grupo Farias</p>
            <p class="text-muted">Selecione uma das opções abaixo para gerenciar o controle de ponto</p>
        </div>

        <!-- Cards de Navegação -->
        <div class="row g-4">
            <!-- Inconsistências -->
            <div class="col-md-4">
                <div class="card dashboard-card h-100 text-center p-4">
                    <div class="card-body d-flex flex-column justify-content-center">
                        <i class="bi bi-exclamation-triangle-fill text-warning card-icon"></i>
                        <h5 class="card-title fw-bold">Inconsistências</h5>
                        <p class="card-text text-muted">Gerencie inconsistências de ponto, atrasos e faltas dos colaboradores.</p>
                        <a href="ajuste.php" class="btn btn-warning btn-lg mt-auto">
                            <i class="bi bi-arrow-right"></i> Acessar
                        </a>
                    </div>
                </div>
            </div>

            <!-- Horas Extras -->
            <div class="col-md-4">
                <div class="card dashboard-card h-100 text-center p-4">
                    <div class="card-body d-flex flex-column justify-content-center">
                        <i class="bi bi-clock-fill text-info card-icon"></i>
                        <h5 class="card-title fw-bold">Horas Extras</h5>
                        <p class="card-text text-muted">Visualize e justifique horas extras dos colaboradores com diferentes percentuais.</p>
                        <a href="extras.php" class="btn btn-info btn-lg mt-auto">
                            <i class="bi bi-arrow-right"></i> Acessar
                        </a>
                    </div>
                </div>
            </div>

            <!-- Visões Gerenciais -->
            <div class="col-md-4">
                <div class="card dashboard-card h-100 text-center p-4">
                    <div class="card-body d-flex flex-column justify-content-center">
                        <i class="bi bi-graph-up-arrow text-success card-icon"></i>
                        <h5 class="card-title fw-bold">Visões Gerenciais</h5>
                        <p class="card-text text-muted">Relatórios, gráficos e análises gerenciais do controle de ponto.</p>
                        <a href="visoes.php" class="btn btn-success btn-lg mt-auto">
                            <i class="bi bi-arrow-right"></i> Acessar
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informações Adicionais -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> Informações do Sistema</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="fw-bold">Funcionalidades Disponíveis:</h6>
                                <ul class="list-unstyled">
                                    <li><i class="bi bi-check-circle text-success"></i> Gerenciamento de Inconsistências</li>
                                    <li><i class="bi bi-check-circle text-success"></i> Controle de Horas Extras</li>
                                    <li><i class="bi bi-check-circle text-success"></i> Relatórios Gerenciais</li>
                                    <li><i class="bi bi-check-circle text-success"></i> Justificativas de Ponto</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold">Tipos de Horas Extras:</h6>
                                <ul class="list-unstyled">
                                    <li><span class="badge bg-secondary">301</span> Horas 50%</li>
                                    <li><span class="badge bg-secondary">313</span> Horas 70%</li>
                                    <li><span class="badge bg-secondary">303</span> Horas 75%</li>
                                    <li><span class="badge bg-secondary">305</span> Horas 100% DSR</li>
                                    <li><span class="badge bg-secondary">311</span> Horas 100% Feriado</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
