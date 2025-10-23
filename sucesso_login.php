<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: teste_simulado.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Bem-sucedido - GF Pontos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body {
            background: #f5f6fa;
            padding: 20px;
        }
        .success-card {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07);
            padding: 32px;
        }
        .header-section {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            width: 90px;
            margin-bottom: 12px;
        }
        .title {
            font-size: 2rem;
            font-weight: 700;
            color: #222;
        }
        .info-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .badge-custom {
            background: #f2c029;
            color: #222;
            font-size: 0.9rem;
            padding: 8px 12px;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <div class="success-card">
        <div class="header-section">
            <img src="https://app.grupofarias.com.br/_next/image?url=%2Fassets%2Fimages%2Flogos%2Flogo.png&w=256&q=75&dpl=dpl_2zZSoeRAkK891Wnr1b564YVbKpdi" alt="Logo Grupo Farias" class="logo">
            <div class="title">GRUPO <span style="color: #1a1a1a; font-weight: 900;">FARIAS</span></div>
            <div class="text-muted">Login realizado com sucesso!</div>
        </div>

        <div class="info-section">
            <h5><i class="bi bi-info-circle"></i> Informações da Sessão</h5>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Usuário (antes do @):</strong> <?= htmlspecialchars(isset($_SESSION['user']) ? $_SESSION['user'] : 'N/A') ?></p>
                    <p><strong>Usuário (após @):</strong> <?= htmlspecialchars(isset($_SESSION['user_after']) ? $_SESSION['user_after'] : 'N/A') ?></p>
                    <p><strong>Usuário Original:</strong> <?= htmlspecialchars(isset($_SESSION['user_original']) ? $_SESSION['user_original'] : 'N/A') ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Status:</strong> 
                        <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
                            <span class="badge bg-success">Logado</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Não logado</span>
                        <?php endif; ?>
                    </p>
                    
                    <?php if (isset($_SESSION['user_after']) && !empty($_SESSION['user_after'])): ?>
                        <p><strong>Mapeamento Ativo:</strong> 
                            <span class="badge-custom">
                                <i class="bi bi-eye"></i> Visualizando dados de outro usuário
                            </span>
                        </p>
                        <?php
                        $usuario_autenticacao = $_SESSION['user'];
                        $usuario_exibicao = $_SESSION['user_after'];
                        // Remove domínio se houver
                        if (strpos($usuario_exibicao, '@') !== false) {
                            $usuario_exibicao = strstr($usuario_exibicao, '@', true);
                        }
                        ?>
                        <p><strong>Usuário Autenticado:</strong> <?= htmlspecialchars($usuario_autenticacao) ?></p>
                        <p><strong>Dados Exibidos de:</strong> <?= htmlspecialchars($usuario_exibicao) ?></p>
                    <?php else: ?>
                        <p><strong>Mapeamento:</strong> 
                            <span class="badge bg-secondary">Sem mapeamento</span>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="info-section">
            <h5><i class="bi bi-gear"></i> Como Funcionou</h5>
            <?php if (isset($_SESSION['user_after']) && !empty($_SESSION['user_after'])): ?>
                <div class="alert alert-info">
                    <h6>✅ Mapeamento Funcionou Corretamente!</h6>
                    <ul class="mb-0">
                        <li>Você digitou um email no formato de mapeamento</li>
                        <li>O sistema autenticou com a primeira parte do email (antes do @)</li>
                        <li>Os dados exibidos são da segunda parte do email (após o @)</li>
                        <li>Os logs registrarão o usuário que realmente fez login</li>
                    </ul>
                </div>
            <?php else: ?>
                <div class="alert alert-secondary">
                    <h6>ℹ️ Login Normal</h6>
                    <ul class="mb-0">
                        <li>Você fez login com um usuário sem mapeamento</li>
                        <li>O sistema exibe seus próprios dados</li>
                        <li>Funcionamento padrão do sistema</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <div class="d-flex gap-2 justify-content-center">
            <a href="teste_simulado.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Voltar ao Login
            </a>
            <a href="logout.php" class="btn btn-outline-danger">
                <i class="bi bi-box-arrow-right"></i> Sair
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 