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
    <title>Menu Principal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        .navbar-brand {
            font-size: 2rem;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .navbar-nav .nav-link {
            font-size: 1.1rem;
            margin-right: 1.2rem;
            transition: color 0.2s;
        }
        .navbar-nav .nav-link:last-child {
            margin-right: 0;
        }
        .navbar-nav .nav-link:hover, .navbar-nav .nav-link:focus {
            color: #1976d2;
        }
        .navbar {
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }
        @media (max-width: 991px) {
            .navbar-brand {
                font-size: 1.3rem;
            }
            .navbar-nav .nav-link {
                margin-right: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-3">
        <div class="container">
            <a class="navbar-brand mx-auto d-lg-none" href="dashboard.php">Controle de Ponto</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-between" id="navbarNav">
                <a class="navbar-brand mx-auto d-none d-lg-block" href="dashboard.php">Controle de Ponto</a>
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Gestão
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="ajuste.php"><i class="bi bi-exclamation-triangle"></i> Inconsistências</a></li>
                            <li><a class="dropdown-item" href="extras.php"><i class="bi bi-clock"></i> Extras</a></li>
                            <li><a class="dropdown-item" href="visoes.php"><i class="bi bi-graph-up"></i> Visões</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <form method="post" action="logout.php" style="display:inline;">
                            <button type="submit" class="btn btn-link nav-link" style="display:inline; padding:0; border:none;">Sair</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>