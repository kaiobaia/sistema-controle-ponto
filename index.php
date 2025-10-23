<?php
// Headers para prevenir cache do navegador
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Configuração do servidor LDAP
$ldap_host = 'ldap://192.168.50.13';
$ldap_port = 389;
$ldap_dn = 'dc=gf,dc=local'; // Altere para o DN base do seu ambiente LDAP

// Processamento de login para gestor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'login_gestor') {
    // Sanitização dos inputs
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // Verifica se o usuário digitou um email completo ou apenas o nome
    $username_original = htmlentities($username);
    
    // Criar variáveis para antes e após o @
    if (strpos($username_original, '@') !== false) {
        $username = strstr($username_original, '@', true); // Conteúdo antes do @
        $username_after = strstr($username_original, '@', false); // Conteúdo após o @
    } else {
        $username = $username_original; // Conteúdo antes do @ (não tem @)
        $username_after = ''; // Não tem conteúdo após o @
    }
    
    // Para LDAP, sempre usar apenas o usuário antes do @ + domínio
    if (strpos($username_original, '@') !== false) {
        // Se tem @, pega apenas a parte antes do @
        $username_ldap = strstr($username_original, '@', true) . '@gf.local';
    } else {
        // Se não tem @, adiciona o domínio
        $username_ldap = $username_original . '@gf.local';
    }

    // Conexão ao servidor LDAP
    $ldap_conn = ldap_connect($ldap_host, $ldap_port);

    if (!$ldap_conn) {
        $error_message = "Erro ao conectar ao servidor LDAP.";
    } else {
        // Configurações do protocolo LDAP
        ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

        if (ldap_bind($ldap_conn, $username_ldap, $password)) {

            // Autenticação bem-sucedida, cria sessão para o usuário
            session_start();
            
            // Armazenar as variáveis separadas na sessão
            $_SESSION['user'] = $username; // Conteúdo antes do @
            $_SESSION['user_after'] = $username_after; // Conteúdo após o @
            $_SESSION['user_original'] = $username_original; // Email original completo
            
            $_SESSION['logged_in'] = true;

            // Redireciona para a página inicial ou dashboard
            header("Location: dashboard.php");
            exit;
        } else {
            // Mensagem de erro de autenticação
            $error_message = "Usuário ou senha inválidos.";
        }

        // Fecha a conexão com o servidor LDAP
        ldap_close($ldap_conn);
    }
}

// Processamento de login para colaborador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'login_colaborador') {
    require_once 'conn.php';
    
    $cpf = filter_input(INPUT_POST, 'cpf', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $matricula = filter_input(INPUT_POST, 'matricula', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    
    // Limpar CPF (remover pontos, traços, etc.)
    $cpf_limpo = preg_replace('/[^0-9]/', '', $cpf);
    
    // Preencher com zeros à esquerda se necessário (para CPFs que começam com zero)
    if (strlen($cpf_limpo) < 11) {
        $cpf_limpo = str_pad($cpf_limpo, 11, '0', STR_PAD_LEFT);
    }
    
    if (strlen($cpf_limpo) >= 10 && !empty($matricula)) {
        $conn = conectar_db();
        
        // Buscar colaborador por CPF e matrícula (NUMCRA)
        $sql = "SELECT NUMEMP, TIPCOL, NUMCAD, NUMCRA, NOMFUN 
                FROM VETORH.R034FUN 
                WHERE NUMCPF = :cpf AND NUMCRA = :matricula AND SITAFA <> 7";
        
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':cpf', $cpf_limpo);
        oci_bind_by_name($stmt, ':matricula', $matricula);
        
        if (oci_execute($stmt)) {
            $row = oci_fetch_array($stmt, OCI_ASSOC + OCI_RETURN_NULLS);
            
            if ($row) {
                // Colaborador encontrado, criar sessão
                session_start();
                $_SESSION['user_type'] = 'colaborador';
                $_SESSION['numcad'] = $row['NUMCAD'];
                $_SESSION['numcra'] = $row['NUMCRA'];
                $_SESSION['nomfun'] = $row['NOMFUN'];
                $_SESSION['numemp'] = $row['NUMEMP'];
                $_SESSION['tipcol'] = $row['TIPCOL'];
                $_SESSION['logged_in'] = true;
                
                // Redirecionar para tela de inconsistências do colaborador
                header("Location: inconsistencias_colaborador.php");
                exit;
            } else {
                $error_message = "CPF ou matrícula inválidos.";
            }
        } else {
            $error_message = "Erro ao consultar dados do colaborador.";
        }
        
        oci_close($conn);
    } else {
        $error_message = "CPF deve ter no mínimo 10 dígitos e matrícula é obrigatória.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GF - Pontos</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <style>
    body {
      background: #fff;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .login-container {
      max-width: 400px;
      margin: 0 auto;
      padding: 32px 28px 24px 28px;
      border-radius: 16px;
      box-shadow: 0 2px 16px rgba(0,0,0,0.07);
      background: #fff;
      text-align: center;
    }
    .login-logo {
      width: 90px;
      margin-bottom: 12px;
    }
    .login-title {
      font-size: 2rem;
      font-weight: 700;
      letter-spacing: 1px;
      margin-bottom: 28px;
      color: #222;
    }
    .login-title span {
      color: #1a1a1a;
      font-weight: 900;
    }
    .form-label {
      text-align: left;
      font-weight: 500;
      color: #555;
    }
    .form-control {
      background: #f4f8ff;
      border-radius: 8px;
      border: 1px solid #e0e0e0;
      font-size: 1.1rem;
    }
    .btn-login {
      background: #f2c029;
      color: #222;
      font-weight: 600;
      border: none;
      border-radius: 8px;
      font-size: 1.2rem;
      padding: 10px 0;
      margin-top: 10px;
      transition: background 0.2s;
    }
    .btn-login:hover {
      background: #e6b200;
      color: #111;
    }
    .forgot-link {
      display: block;
      text-align: right;
      color: #e6b200;
      font-size: 0.98rem;
      margin-top: 2px;
      margin-bottom: 10px;
      text-decoration: none;
    }
    .forgot-link:hover {
      text-decoration: underline;
      color: #b38a00;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <img src="https://app.grupofarias.com.br/_next/image?url=%2Fassets%2Fimages%2Flogos%2Flogo.png&w=256&q=75&dpl=dpl_2zZSoeRAkK891Wnr1b564YVbKpdi" alt="Logo Grupo Farias" class="login-logo">
    <div class="login-title">GRUPO <span>FARIAS</span></div>
    <div class="mb-3">
      <i class="bi bi-clock-history" style="font-size: 2.5rem; color: #f2c029;"></i>
      <div class="fw-semibold" style="font-size: 1.15rem; color: #444;">Controle de Ponto</div>
    </div>
    
    <?php if (isset($error_message)): ?>
      <div class="alert alert-danger py-2">
        <?= htmlspecialchars($error_message) ?>
      </div>
    <?php endif; ?>
    
    <!-- Seleção de tipo de usuário -->
    <div class="mb-4">
      <div class="btn-group w-100" role="group" aria-label="Tipo de usuário">
        <input type="radio" class="btn-check" name="user_type" id="colaborador" value="colaborador" checked>
        <label class="btn btn-outline-primary" for="colaborador">
          <i class="bi bi-person"></i> Colaborador
        </label>

        <input type="radio" class="btn-check" name="user_type" id="gestor" value="gestor">
        <label class="btn btn-outline-primary" for="gestor">
          <i class="bi bi-shield-check"></i> Gestor
        </label>
      </div>
    </div>

    <!-- Formulário para Colaborador -->
    <form method="POST" id="form-colaborador" style="display: block;">
      <div class="mb-3 text-start">
        <label for="cpf" class="form-label">CPF</label>
        <input type="text" id="cpf" name="cpf" class="form-control" placeholder="000.000.000-00" required>
      </div>
      <div class="mb-3 text-start">
        <label for="matricula" class="form-label">Matrícula</label>
        <input type="text" id="matricula" name="matricula" class="form-control" placeholder="Sua matrícula" required>
      </div>
      <input type="hidden" name="acao" value="login_colaborador">
      <button type="submit" class="btn btn-login w-100">Entrar</button>
    </form>

    <!-- Formulário para Gestor -->
    <form method="POST" id="form-gestor" style="display: none;">
      <div class="mb-3 text-start">
        <label for="username" class="form-label">Usuário</label>
        <input type="text" id="username" name="username" class="form-control" required value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
      </div>
      <div class="mb-2 text-start">
        <label for="password" class="form-label">Senha</label>
        <div class="input-group">
          <input type="password" id="password" name="password" class="form-control" required>
          <button type="button" class="btn btn-outline-secondary" style="border-radius: 0 8px 8px 0;" onclick="togglePassword()">
            <span id="eye-icon" class="bi bi-eye"></span>
          </button>
        </div>
      </div>
      <a href="#" class="forgot-link">Esqueci minha senha</a>
      <input type="hidden" name="acao" value="login_gestor">
      <button type="submit" class="btn btn-login w-100">Entrar</button>
    </form>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <script>
    function togglePassword() {
      var pwd = document.getElementById('password');
      var icon = document.getElementById('eye-icon');
      if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.className = 'bi bi-eye-slash';
      } else {
        pwd.type = 'password';
        icon.className = 'bi bi-eye';
      }
    }

    // Função para alternar entre formulários
    document.addEventListener('DOMContentLoaded', function() {
      const colaboradorRadio = document.getElementById('colaborador');
      const gestorRadio = document.getElementById('gestor');
      const formColaborador = document.getElementById('form-colaborador');
      const formGestor = document.getElementById('form-gestor');

      colaboradorRadio.addEventListener('change', function() {
        if (this.checked) {
          formColaborador.style.display = 'block';
          formGestor.style.display = 'none';
        }
      });

      gestorRadio.addEventListener('change', function() {
        if (this.checked) {
          formColaborador.style.display = 'none';
          formGestor.style.display = 'block';
        }
      });

      // Formatar CPF
      const cpfInput = document.getElementById('cpf');
      cpfInput.addEventListener('input', function() {
        let value = this.value.replace(/\D/g, '');
        // Permitir até 11 dígitos, mas formatar conforme o usuário digita
        if (value.length <= 11) {
          value = value.replace(/(\d{3})(\d)/, '$1.$2');
          value = value.replace(/(\d{3})(\d)/, '$1.$2');
          value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
          this.value = value;
        }
      });
    });
  </script>
</body>
</html>