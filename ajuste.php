<?php
// Headers para prevenir cache do navegador
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Iniciar sessão para armazenar mensagens
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Ativar exibição de erros para depuração
/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);*/
require_once 'conn.php';

// require 'menu.php';

// Usar o usuário original para logs (quem realmente fez o ajuste)
$user_original = isset($_SESSION['user_original']) ? $_SESSION['user_original'] : $_SESSION['user'];
$user = htmlspecialchars(strstr($user_original, '@', true));
$dataAtual = date('d/m/Y');

$obsjus = 'Ajustado por ' . $user_original . ' em  ' . $dataAtual;

// PROCESSAMENTO DE POST (antes de qualquer saída HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'abater_atraso') {
    require_once 'conn.php'; // Garante conexão
    $conn = conectar_db();
    $numemp = $_POST['numemp'];
    $tipcol = $_POST['tipcol'];
    $numcad = $_POST['numcad'];
    $datapu = $_POST['datapu'];
    // Buscar o atraso (QTDHOR) do colaborador para a data
    $qtdhor = 0;
    $sql_qtdhor = "SELECT QTDHOR FROM VETORH.R066SIT WHERE NUMEMP = :numemp AND TIPCOL = :tipcol AND NUMCAD = :numcad AND DATAPU = TO_DATE(:datapu, 'DD/MM/YYYY') AND (CODSIT = 911 OR CODSIT = 103)";
    $stmt_qtdhor = oci_parse($conn, $sql_qtdhor);
    oci_bind_by_name($stmt_qtdhor, ':numemp', $numemp);
    oci_bind_by_name($stmt_qtdhor, ':tipcol', $tipcol);
    oci_bind_by_name($stmt_qtdhor, ':numcad', $numcad);
    oci_bind_by_name($stmt_qtdhor, ':datapu', $datapu);
    if (oci_execute($stmt_qtdhor)) {
        $row_qtdhor = oci_fetch_array($stmt_qtdhor, OCI_ASSOC + OCI_RETURN_NULLS);
        if ($row_qtdhor && isset($row_qtdhor['QTDHOR'])) {
            $qtdhor = (int)$row_qtdhor['QTDHOR'];
        }
    }
    // Definir regras de empresa
    if ($numemp == 10) {
        $codbhr = 13;
        $codsit = 802;
    } else {
        $codbhr = 15;
        $codsit = 911;
    }
    // Primeiro: Insert em R011LAN
    $sql_insert = "INSERT INTO VETORH.R011LAN (
        NUMEMP, TIPCOL, NUMCAD, CODBHR, DATLAN, CODSIT, ORILAN, SINLAN, QTDHOR, QTDPAG, DATCMP, HORCMP, PRRLAN, PERREF, CMPLAN
    ) VALUES (
        :numemp, :tipcol, :numcad, :codbhr, TO_DATE(:datapu, 'DD/MM/YYYY'), :codsit, 'A', '-', :qtdhor, 0, TO_DATE('04/06/2080', 'DD/MM/YYYY'), 0, 0, TO_DATE('31/12/1900', 'DD/MM/YYYY'), TO_DATE('31/12/1900', 'DD/MM/YYYY')
    )";
    $stmt_insert = oci_parse($conn, $sql_insert);
    oci_bind_by_name($stmt_insert, ':numemp', $numemp);
    oci_bind_by_name($stmt_insert, ':tipcol', $tipcol);
    oci_bind_by_name($stmt_insert, ':numcad', $numcad);
    oci_bind_by_name($stmt_insert, ':codbhr', $codbhr);
    oci_bind_by_name($stmt_insert, ':datapu', $datapu);
    oci_bind_by_name($stmt_insert, ':codsit', $codsit);
    oci_bind_by_name($stmt_insert, ':qtdhor', $qtdhor);
    if (!oci_execute($stmt_insert)) {
        $e = oci_error($stmt_insert);
        $_SESSION['mensagem'] = exibir_mensagem('Erro ao inserir em R011LAN: ' . $e['message'], 'danger');
    } else {
        // Segundo: Update em R066SIT
        $sql_update = "UPDATE VETORH.R066SIT
                       SET CODSIT = 911, CODUSU = 824
                       WHERE NUMEMP = :numemp
                         AND TIPCOL = :tipcol
                         AND NUMCAD = :numcad
                         AND DATAPU = TO_DATE(:datapu, 'DD/MM/YYYY')
                         AND CODSIT = 103";
        $stmt_update = oci_parse($conn, $sql_update);
        oci_bind_by_name($stmt_update, ':numemp', $numemp);
        oci_bind_by_name($stmt_update, ':tipcol', $tipcol);
        oci_bind_by_name($stmt_update, ':numcad', $numcad);
        oci_bind_by_name($stmt_update, ':datapu', $datapu);
        if (!oci_execute($stmt_update)) {
            $e = oci_error($stmt_update);
            $_SESSION['mensagem'] = exibir_mensagem('Erro ao abater atraso: ' . $e['message'], 'danger');
        } else {
            // Após o update, inserir em R070JUS apenas se existir registro na R070ACC
            $sql_check_acc = "SELECT COUNT(*) AS TOTAL FROM VETORH.R070ACC 
                              WHERE NUMCRA = :numcra 
                              AND DATACC = TO_DATE(:datacc, 'DD/MM/YYYY')";
            $stmt_check_acc = oci_parse($conn, $sql_check_acc);
            oci_bind_by_name($stmt_check_acc, ':numcra', $numcad);
            oci_bind_by_name($stmt_check_acc, ':datacc', $datapu);

            if (oci_execute($stmt_check_acc)) {
                $row_check_acc = oci_fetch_array($stmt_check_acc, OCI_ASSOC + OCI_RETURN_NULLS);
                if ($row_check_acc && $row_check_acc['TOTAL'] > 0) {
                    // Só insere em R070JUS se existir registro na R070ACC
                    $sql_insert_jus = "INSERT INTO VETORH.R070JUS (NUMCRA, DATACC, HORACC, SEQACC, CODJMA, OBSJMA)
                        VALUES (:numcra, TO_DATE(:datacc, 'DD/MM/YYYY'), :horacc, 1, 71, 'Autorizado via ajuste')";
                    $stmt_insert_jus = oci_parse($conn, $sql_insert_jus);
                    oci_bind_by_name($stmt_insert_jus, ':numcra', $numcad); // Usando $numcad como $numcra
                    oci_bind_by_name($stmt_insert_jus, ':datacc', $datapu); // Usando $datapu como data de ajuste
                    oci_bind_by_name($stmt_insert_jus, ':horacc', $horacc);
                    if (!oci_execute($stmt_insert_jus)) {
                        $e = oci_error($stmt_insert_jus);
                        $_SESSION['mensagem'] = exibir_mensagem('Erro ao inserir em R070JUS: ' . $e['message'], 'danger');
                    } else {
                        $_SESSION['mensagem'] = exibir_mensagem('Atraso abatido com sucesso!', 'success');
                    }
                } else {
                    $_SESSION['mensagem'] = exibir_mensagem('Atraso abatido com sucesso!', 'success'); //(Sem registro em R070ACC para inserir em R070JUS)
                }
            } else {
                $e = oci_error($stmt_check_acc);
                $_SESSION['mensagem'] = exibir_mensagem('Erro ao verificar R070ACC: ' . $e['message'], 'danger');
            }
        }
    }
    // Redireciona mantendo o filtro de data
    $redirectUrl = $_SERVER['PHP_SELF'];
    if (isset($_GET['datacc'])) {
        $redirectUrl .= '?datacc=' . urlencode($_GET['datacc']);
    }
    error_log('Mensagem de sessão: ' . (isset($_SESSION['mensagem']) ? $_SESSION['mensagem'] : 'N/A'));
    header('Location: ' . $redirectUrl);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'autorizar_inconsistencia') {
    $numcra = $_POST['numcra'];
    $datacc = $_POST['datacc'];
    $conn = conectar_db();

    // Verifica se existe registro em R070JUS
    $sql_check = "
        SELECT MAX(HORACC) AS MAX_HORACC
        FROM VETORH.R070JUS
        WHERE NUMCRA = :numcra
          AND DATACC = TO_DATE(:datacc, 'DD/MM/YYYY')";
    $stmt_check = oci_parse($conn, $sql_check);
    oci_bind_by_name($stmt_check, ':numcra', $numcra);
    oci_bind_by_name($stmt_check, ':datacc', $datacc);
    oci_execute($stmt_check);
    $row_check = oci_fetch_array($stmt_check, OCI_ASSOC + OCI_RETURN_NULLS);

    if ($row_check && $row_check['MAX_HORACC'] !== null) {
        // Se existe, faz o update no maior HORACC
        $sql_update = "
            UPDATE VETORH.R070JUS
            SET CODJMA = 70
            WHERE NUMCRA = :numcra
              AND DATACC = TO_DATE(:datacc, 'DD/MM/YYYY')
              AND HORACC = :horacc
        ";
        $stmt = oci_parse($conn, $sql_update);
        oci_bind_by_name($stmt, ':numcra', $numcra);
        oci_bind_by_name($stmt, ':datacc', $datacc);
        oci_bind_by_name($stmt, ':horacc', $row_check['MAX_HORACC']);
        if (oci_execute($stmt)) {
            $_SESSION['mensagem'] = exibir_mensagem('Inconsistência autorizada com sucesso!', 'success');
        } else {
            $e = oci_error($stmt);
            $_SESSION['mensagem'] = exibir_mensagem('Erro ao autorizar inconsistência: ' . $e['message'], 'danger');
        }
    } else {
        // Se não existe, busca o maior HORACC em R070ACC e insere em R070JUS
        $sql_acc = "
            SELECT NUMCRA, DATACC, HORACC
            FROM VETORH.R070ACC
            WHERE NUMCRA = :numcra
              AND DATACC = TO_DATE(:datacc, 'DD/MM/YYYY')
              AND DATAPU <> '31/12/1900'
            ORDER BY HORACC DESC
            FETCH FIRST 1 ROWS ONLY
        ";
        $stmt_acc = oci_parse($conn, $sql_acc);
        oci_bind_by_name($stmt_acc, ':numcra', $numcra);
        oci_bind_by_name($stmt_acc, ':datacc', $datacc);
        oci_execute($stmt_acc);
        $row_acc = oci_fetch_array($stmt_acc, OCI_ASSOC + OCI_RETURN_NULLS);

        if ($row_acc && $row_acc['HORACC'] !== null) {
            $sql_insert = "
                INSERT INTO VETORH.R070JUS (NUMCRA, DATACC, HORACC, SEQACC, CODJMA, OBSJMA)
                VALUES (:numcra, TO_DATE(:datacc, 'DD/MM/YYYY'), :horacc, 1, 70, 'Autorizado via ajuste')
            ";
            $stmt_insert = oci_parse($conn, $sql_insert);
            oci_bind_by_name($stmt_insert, ':numcra', $numcra);
            oci_bind_by_name($stmt_insert, ':datacc', $datacc);
            oci_bind_by_name($stmt_insert, ':horacc', $row_acc['HORACC']);
            if (oci_execute($stmt_insert)) {
                $_SESSION['mensagem'] = exibir_mensagem('Inconsistência autorizada e inserida com sucesso!', 'success');
            } else {
                $e = oci_error($stmt_insert);
                $_SESSION['mensagem'] = exibir_mensagem('Erro ao inserir inconsistência: ' . $e['message'], 'danger');
            }
        } else {
            $_SESSION['mensagem'] = exibir_mensagem('Não foi possível encontrar marcação para autorizar inconsistência.', 'danger');
        }
    }

    $redirectUrl = $_SERVER['PHP_SELF'];
    if (isset($_GET['datacc'])) {
        $redirectUrl .= '?datacc=' . urlencode($_GET['datacc']);
    }
    header('Location: ' . $redirectUrl);
    exit;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Ponto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>

<body style="background: #f5f6fa;">
    <!-- Navbar fixa com logo e nome -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top" style="z-index: 1040;">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="https://app.grupofarias.com.br/_next/image?url=%2Fassets%2Fimages%2Flogos%2Flogo.png&w=96&q=75&dpl=dpl_2zZSoeRAkK891Wnr1b564YVbKpdi" alt="Logo Grupo Farias" style="height:38px; margin-right:12px;">
                <span class="fw-bold" style="font-size:1.25rem;">Ajuste de Pontos - GF</span>
            </a>
            <div class="d-flex ms-auto align-items-center">
                <?php
                // Verificar se o usuário está visualizando dados de outro usuário
                if (isset($_SESSION['user_original']) && isset($_SESSION['user_display']) && $_SESSION['user_original'] !== $_SESSION['user_display']) {
                    $usuario_original = strstr($_SESSION['user_original'], '@', true);
                    $usuario_exibicao = strstr($_SESSION['user_display'], '@', true);
                    echo '<span class="badge bg-warning text-dark me-2" title="Visualizando dados de ' . htmlspecialchars($usuario_exibicao) . '">';
                    echo '<i class="bi bi-eye"></i> ' . htmlspecialchars($usuario_exibicao);
                    echo '</span>';
                }
                ?>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">Sair</a>
            </div>
        </div>
    </nav>
    <div class="container-fluid" style="margin-top: 0; margin-bottom: 1rem; max-width: 1400px;">
        <div class="row g-2">
            <!-- Filtro e Formulário -->
            <div class="col-md-3">
                <div class="card shadow-sm p-3 mb-2">
                    <h6 class="fw-semibold mb-2"><i class="bi bi-funnel"></i> Filtro</h6>
                    <form method="get">
                        <div class="mb-2">
                            <label for="datacc" class="form-label form-label-sm">Data:</label>
                            <?php
                            // Para o filtro de data
                            $filtro_data_value = '';
                            if (isset($_GET['datacc']) && $_GET['datacc']) {
                                $filtro_data_value = $_GET['datacc'];
                            } else {
                                $filtro_data_value = date('Y-m-d');
                            }
                            ?>
                            <input type="date" name="datacc" id="datacc" class="form-control form-control-sm" value="<?= htmlspecialchars($filtro_data_value) ?>">
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">Filtrar</button>
                            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary btn-sm w-100">Limpar</a>
                        </div>
                    </form>
                </div>
                <div class="card shadow-sm p-3">
                    <h6 class="fw-semibold mb-2"><i class="bi bi-plus-circle"></i> Novo Registro</h6>
                    <?php
                    if (!isset($conn) || !$conn) {
                        $conn = conectar_db();
                    }

                    $datacc = isset($_GET['datacc']) ? formatar_data_para_ddmmyyyy($_GET['datacc']) : date('d/m/Y');
                    $numcad = buscarNumcadPorLogin($conn);

                    $sql = "SELECT *
                                FROM (SELECT DISTINCT R034FUN.NUMEMP,
                                                        R034FUN.TIPCOL,
                                                        R034FUN.NUMCAD,
                                                        R034FUN.NUMCRA,
                                                        TO_CHAR(R070ACC.DATACC, 'DD/MM/YYYY') AS DATACC,
                                                        R070ACC.HORACC,
                                                        R034FUN.NOMFUN,
                                                        R024CAR.TITCAR,
                                                        (SELECT R066.QTDHOR
                                                        FROM VETORH.R066SIT R066
                                                        WHERE R066.NUMEMP = R034FUN.NUMEMP
                                                            AND R066.TIPCOL = R034FUN.TIPCOL
                                                            AND R066.NUMCAD = R034FUN.NUMCAD
                                                            AND R066.DATAPU = R070ACC.DATAPU
                                                            AND R066.CODSIT IN (103, 15) FETCH FIRST 1 ROWS ONLY) AS QTDHOR,
                                                        (SELECT R066.CODSIT
                                                        FROM VETORH.R066SIT R066
                                                        WHERE R066.NUMEMP = R034FUN.NUMEMP
                                                            AND R066.TIPCOL = R034FUN.TIPCOL
                                                            AND R066.NUMCAD = R034FUN.NUMCAD
                                                            AND R066.DATAPU = R070ACC.DATAPU
                                                            AND R066.CODSIT IN (103, 15) FETCH FIRST 1 ROWS ONLY) AS CODSIT
                                        FROM VETORH.R080SUB
                                        INNER JOIN VETORH.R034FUN
                                            ON R080SUB.NUMLOC = R034FUN.NUMLOC
                                        AND R034FUN.NUMEMP = R080SUB.NUMEMP
                                        INNER JOIN VETORH.usu_tlocenc LOC
                                            on LOC.Usu_CodLen = R034FUN.Usu_CodLen
                                        LEFT JOIN VETORH.R070ACC
                                            ON R034FUN.NUMEMP = R070ACC.NUMEMP
                                        AND R034FUN.NUMCRA = R070ACC.NUMCRA
                                        AND R070ACC.DATAPU = TO_DATE(:datacc, 'DD/MM/YYYY')
                                        INNER JOIN VETORH.R024CAR
                                            ON R034FUN.CODCAR = R024CAR.CODCAR
                                        WHERE loc.Usu_NumCad = $numcad
                                        AND EXISTS (SELECT 1
                                                FROM VETORH.R066SIT R066
                                                WHERE R066.NUMEMP = R034FUN.NUMEMP
                                                AND R066.TIPCOL = R034FUN.TIPCOL
                                                AND R066.NUMCAD = R034FUN.NUMCAD
                                                AND R066.DATAPU = R070ACC.DATACC
                                                AND R066.CODSIT IN (103, 15))
                                        AND NOT EXISTS (SELECT 1
                                                FROM VETORH.R070JUS JUS
                                                WHERE JUS.NUMCRA = R034FUN.NUMCRA
                                                AND JUS.DATACC = R070ACC.DATAPU
                                                AND JUS.CODJMA = 70)
                                        union
                                        select sit.numemp,
                                            sit.tipcol,
                                            sit.numcad,
                                            fun.numcra,
                                            TO_CHAR(sit.datapu, 'DD/MM/YYYY') datacc,
                                            0 horacc,
                                            fun.nomfun,
                                            car.titcar,
                                            sit.qtdhor,
                                            sit.codsit
                                        from VETORH.r066sit sit, VETORH.r034fun fun, VETORH.r024car car, VETORH.usu_tlocenc loc
                                        where fun.numemp = sit.numemp
                                        and fun.tipcol = sit.tipcol
                                        and fun.numcad = sit.numcad
                                        and fun.codcar = car.codcar
                                        and car.estcar = 100
                                        and LOC.Usu_CodLen = fun.Usu_CodLen
                                        and loc.Usu_NumCad = $numcad
                                        and sit.codsit = 15
                                        and sit.datapu = TO_DATE(:datacc, 'DD/MM/YYYY')
                                        and not exists (select acc.datacc
                                                from VETORH.r070acc acc
                                                where acc.datapu = sit.datapu
                                                and acc.numemp = sit.numemp
                                                and acc.tipcol = sit.tipcol
                                                and acc.numcad = sit.numcad))
                                ORDER BY NUMCRA, DATACC, HORACC";

                    $stmt = oci_parse($conn, $sql);
                    oci_bind_by_name($stmt, ':datacc', $datacc);
                    oci_execute($stmt);

                    $colaboradores = [];
                    while ($row = oci_fetch_array($stmt, OCI_ASSOC + OCI_RETURN_NULLS)) {
                        $colaboradores[$row['NUMCRA']]['dados'] = [
                            'NUMCRA' => $row['NUMCRA'],
                            'NOMFUN' => $row['NOMFUN'],
                            'TITCAR' => $row['TITCAR'],
                            'NUMEMP' => $row['NUMEMP'],
                            'TIPCOL' => $row['TIPCOL'],
                            'NUMCAD' => $row['NUMCAD'],
                            'QTDHOR' => $row['QTDHOR'], // Adicionado para exibir o atraso
                        ];
                    }
                    ?>
                    <form method="post">
                        <div class="mb-2">
                            <label for="numcra" class="form-label form-label-sm">Crachá:</label>
                            <select name="NUMCRA" id="numcra" class="form-control form-control-sm" required>
                                <option value="">Selecione um colaborador</option>
                                <?php
                                foreach ($colaboradores as $colab) {
                                    $numcra = htmlspecialchars($colab['dados']['NUMCRA']);
                                    $nomfun = htmlspecialchars($colab['dados']['NOMFUN']);
                                    echo "<option value=\"$numcra\">$numcra - $nomfun</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label for="datacc" class="form-label form-label-sm">Data:</label>
                            <?php
                            $datacc_filtro = '';
                            if (isset($_GET['datacc']) && $_GET['datacc']) {
                                // Se vier no formato yyyy-mm-dd, já está pronto
                                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['datacc'])) {
                                    $datacc_filtro = $_GET['datacc'];
                                } else {
                                    // Se vier em outro formato, tenta converter
                                    $data = DateTime::createFromFormat('d/m/Y', $_GET['datacc']);
                                    if ($data) {
                                        $datacc_filtro = $data->format('Y-m-d');
                                    }
                                }
                            }
                            ?>
                            <input type="date" name="DATACC" id="datacc" class="form-control form-control-sm dataAjuste" required value="<?= htmlspecialchars($datacc_filtro) ?>">
                        </div>
                        <div class="mb-2">
                            <label for="horacc" class="form-label form-label-sm">Horário:</label>
                            <input type="time" name="HORACC" id="horacc" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-2">
                            <label for="CODJMA" class="form-label form-label-sm">Justificativa:</label>
                            <select name="CODJMA" id="CODJMA" class="form-control form-control-sm" required>
                                <option value="">Selecione uma justificativa</option>
                                <?php
                                $justificativas = buscar_justificativas($conn);
                                foreach ($justificativas as $justificativa): ?>
                                    <option value="<?= htmlspecialchars($justificativa['CODJMA']) ?>">
                                        <?= htmlspecialchars($justificativa['CODJMA']) . '-' . htmlspecialchars($justificativa['DESJMA']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="hidden" name="acao" value="Adicionar Registro">
                        <button type="submit" class="btn btn-success btn-sm w-100">Adicionar</button>
                    </form>
                </div>
                <?php
                // Executar a query para obter os percentuais
                $datacc = isset($_GET['datacc']) ? formatar_data_para_ddmmyyyy($_GET['datacc']) : date('d/m/Y');
                $numcad = buscarNumcadPorLogin($conn);
                $sql_pizza = "SELECT DADOS.NUMCAD,
                                    DADOS.NOMFUN,
                                    DADOS.TITCAR,
                                    DADOS.TOTAL_MARCACOES,
                                    ROUND((DADOS.TOTAL_MARCACOES / 4) * 100, 2) AS PERCENTUAL_MARCACOES,
                                    ROUND((1 - (DADOS.TOTAL_MARCACOES / 4)) * 100, 2) AS PERCENTUAL_FALTAS
                                FROM (SELECT R034FUN.NUMCAD,
                                            R034FUN.NOMFUN,
                                            R024CAR.TITCAR,
                                            COUNT(R070ACC.HORACC) AS TOTAL_MARCACOES
                                        FROM VETORH.R034FUN
                                        INNER JOIN VETORH.R080SUB
                                            ON R080SUB.NUMLOC = R034FUN.NUMLOC
                                        AND R034FUN.NUMEMP = R080SUB.NUMEMP
                                        INNER JOIN VETORH.R024CAR
                                            ON R034FUN.CODCAR = R024CAR.CODCAR
                                        LEFT JOIN VETORH.R070ACC
                                            ON R034FUN.NUMEMP = R070ACC.NUMEMP
                                        AND R034FUN.NUMCRA = R070ACC.NUMCRA
                                        AND R070ACC.DATACC = TO_DATE(:datacc, 'DD/MM/YYYY')
                                        AND R070ACC.NUMCAD <> 0
                                        AND R070ACC.DATAPU <> TO_DATE('31/12/1900', 'DD/MM/YYYY')
                                        INNER JOIN VETORH.R066SIT
                                            ON R066SIT.NUMEMP = R034FUN.NUMEMP
                                        AND R066SIT.TIPCOL = R034FUN.TIPCOL
                                        AND R066SIT.NUMCAD = R034FUN.NUMCAD
                                        AND R066SIT.DATAPU = TO_DATE(:datacc, 'DD/MM/YYYY')
                                        AND R066SIT.CODSIT IN (1, 15)
                                        WHERE R034FUN.NUMCAD <> R080SUB.CADCHE
                                        AND R034FUN.NUMCAD <> 548
                                        AND R034FUN.ESTCAR = 100
                                        AND R034FUN.TIPCON <> 6
                                        AND (R080SUB.CADCHE = :numcad OR R080SUB.CADSCH = :numcad)
                                        AND R034FUN.SITAFA <> 7
                                        GROUP BY R034FUN.NUMCAD, R034FUN.NOMFUN, R024CAR.TITCAR) DADOS
                                ORDER BY DADOS.NUMCAD";
                $stmt_pizza = oci_parse($conn, $sql_pizza);
                oci_bind_by_name($stmt_pizza, ':datacc', $datacc);
                oci_bind_by_name($stmt_pizza, ':numcad', $numcad);
                oci_execute($stmt_pizza);
                $total_marcacoes = 0;
                $total_percentual_marcacoes = 0;
                $total_percentual_faltas = 0;
                $colaboradores_pizza = 0;
                while ($row = oci_fetch_array($stmt_pizza, OCI_ASSOC + OCI_RETURN_NULLS)) {
                    $total_marcacoes += $row['TOTAL_MARCACOES'];
                    $total_percentual_marcacoes += $row['PERCENTUAL_MARCACOES'];
                    $total_percentual_faltas += $row['PERCENTUAL_FALTAS'];
                    $colaboradores_pizza++;
                }
                if ($colaboradores_pizza > 0) {
                    $media_percentual_marcacoes = round($total_percentual_marcacoes / $colaboradores_pizza, 2);
                    $media_percentual_faltas = round($total_percentual_faltas / $colaboradores_pizza, 2);
                } else {
                    $media_percentual_marcacoes = 0;
                    $media_percentual_faltas = 0;
                }
                ?>
                <div class="mb-2"></div>
                <div class="card shadow-sm p-3 mb-3">
                    <h6 class="fw-semibold mb-2"><i class="bi bi-pie-chart"></i> Percentual de Marcações x Faltas</h6>
                    <canvas id="graficoPizzaMarcacoes" width="300" height="180"></canvas>
                </div>
                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <script>
                    const ctxPizza = document.getElementById('graficoPizzaMarcacoes').getContext('2d');
                    new Chart(ctxPizza, {
                        type: 'pie',
                        data: {
                            labels: ['Marcações', 'Faltas'],
                            datasets: [{
                                data: [<?= $media_percentual_marcacoes ?>, <?= $media_percentual_faltas ?>],
                                backgroundColor: ['#1976d2', '#c62828'],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                },
                                tooltip: {
                                    enabled: true
                                }
                            }
                        }
                    });
                </script>
            </div>
            <!-- Tabela -->
            <div class="col-md-9">
                <div class="card shadow-sm p-2">

                    <?php

                    // Função para exibir mensagens
                    function exibir_mensagem($mensagem, $tipo)
                    {
                        return "<div class='alert alert-$tipo'>$mensagem</div>";
                    }

                    // Função para converter horário HH:MM para numérico
                    function formatar_horario_para_numerico($horario)
                    {
                        if (strpos($horario, ':') !== false) {
                            list($horas, $minutos) = explode(':', $horario);
                            return ($horas * 60) + $minutos;
                        }
                        return null;
                    }

                    // Função para converter horário numérico para HH:MM
                    function formatar_horario_para_hhmm($horario)
                    {
                        if (is_numeric($horario)) {
                            // Se o horário for maior que 1440 minutos (24 horas), ajusta para o dia seguinte
                            if ($horario >= 1440) {
                                $horario_ajustado = $horario - 1440;
                                $horas = floor($horario_ajustado / 60);
                                $minutos = $horario_ajustado % 60;
                            } else {
                                $horas = floor($horario / 60);
                                $minutos = $horario % 60;
                            }
                            return sprintf('%02d:%02d', $horas, $minutos);
                        }
                        return null;
                    }

                    // Função para converter data de YYYY-MM-DD para DD/MM/YYYY
                    function formatar_data_para_ddmmyyyy($data)
                    {
                        $partes = explode('-', $data); // Divide a string no formato YYYY-MM-DD
                        if (count($partes) === 3) {
                            return $partes[2] . '/' . $partes[1] . '/' . $partes[0]; // Retorna DD/MM/YYYY
                        }
                        return null;
                    }

                    // Consulta os dados do colaborador
                    function buscar_colaborador($conn, $numcra)
                    {
                        $sql = "SELECT NUMEMP, TIPCOL, NUMCAD, NUMCRA 
                                        FROM VETORH.R034FUN 
                                        WHERE NUMCRA = :numcra AND SITAFA <> 7";
                        $stmt = oci_parse($conn, $sql);
                        oci_bind_by_name($stmt, ':numcra', $numcra);

                        if (!oci_execute($stmt)) {
                            $e = oci_error();
                            die("Erro ao buscar colaborador: " . $e['message']);
                        }

                        return oci_fetch_array($stmt, OCI_ASSOC + OCI_RETURN_NULLS);
                    }

                    // Verifica se o registro já existe
                    function registro_existe($conn, $numcra, $datacc, $horacc)
                    {
                        $datacc = formatar_data_para_ddmmyyyy($datacc);

                        $sql = "SELECT COUNT(*) AS TOTAL 
                                            FROM VETORH.R070ACC 
                                            WHERE NUMCRA = :numcra AND DATACC = TO_DATE(:datacc, 'DD/MM/YYYY') AND HORACC = :horacc";
                        $stmt = oci_parse($conn, $sql);
                        oci_bind_by_name($stmt, ':numcra', $numcra);
                        oci_bind_by_name($stmt, ':datacc', $datacc);
                        oci_bind_by_name($stmt, ':horacc', $horacc);

                        if (!oci_execute($stmt)) {
                            $e = oci_error($stmt);
                            die("Erro ao verificar duplicidade: " . $e['message']);
                        }

                        $row = oci_fetch_array($stmt, OCI_ASSOC + OCI_RETURN_NULLS);
                        return $row['TOTAL'] > 0;
                    }

                    // Verifica se o registro já existe
                    function banco_horas($conn, $numcra)
                    {
                        $sql = "SELECT CASE
                                                WHEN diferenca_horas < 0 THEN
                                            '-' || LPAD(ABS(TRUNC(diferenca_horas)), 2, '0') || ':' ||
                                            LPAD(ABS(ROUND((diferenca_horas - TRUNC(diferenca_horas)) * 60)),
                                                2,
                                                '0')
                                        ELSE
                                            LPAD(TRUNC(diferenca_horas), 2, '0') || ':' ||
                                            LPAD(ROUND((diferenca_horas - TRUNC(diferenca_horas)) * 60),
                                                2,
                                                '0')
                                        END AS horas_banco
                                                FROM (SELECT COALESCE(SUM(CASE
                                                                            WHEN sinlan = '+' THEN
                                                                            qtdhor
                                                                            ELSE
                                                                            -qtdhor
                                                                        END) / 60,
                                                                    0) AS diferenca_horas
                                                        FROM vetorh.R011LAN, vetorh.r034fun
                                                    WHERE R011LAN.numemp = r034fun.numemp
                                                        AND R011LAN.numcad = r034fun.numcad
                                                        AND R011LAN.tipcol = r034fun.tipcol
                                                        AND r034fun.numcra = :numcra
                                                        AND r034fun.tipcon <> 6
                                                        AND r034fun.sitafa <> 7
                                                        AND R011LAN.codbhr = 15)";
                        $stmt = oci_parse($conn, $sql);
                        oci_bind_by_name($stmt, ':numcra', $numcra);

                        if (!oci_execute($stmt)) {
                            $e = oci_error($stmt);
                            die("Erro ao verificar duplicidade: " . $e['message']);
                        }

                        $row = oci_fetch_array($stmt, OCI_ASSOC + OCI_RETURN_NULLS);
                        return $row["HORAS_BANCO"];
                    }

                    // Recuperar justificativas da tabela VETORH.R076JMA
                    function buscar_justificativas($conn)
                    {
                        $sql = "SELECT CODJMA, DESJMA FROM VETORH.R076JMA ORDER BY CAST(CODJMA AS INT)";
                        $stmt = oci_parse($conn, $sql);

                        if (!oci_execute($stmt)) {
                            $e = oci_error($stmt);
                            die("Erro ao executar a consulta: " . $e['message']);
                        }

                        $justificativas = [];
                        while ($row = oci_fetch_array($stmt, OCI_ASSOC + OCI_RETURN_NULLS)) {
                            $justificativas[] = $row;
                        }

                        oci_free_statement($stmt); // Liberar o recurso do statement
                        return $justificativas;
                    }

                    function buscarNumcadPorLogin($conn)
                    {
                        // Verificar se existe conteúdo após o @
                        if (isset($_SESSION['user_after']) && !empty($_SESSION['user_after'])) {
                            // Se tem conteúdo após @, usa para NUMCAD
                            $user = $_SESSION['user_after'];

                            // Remover o @ inicial se existir
                            if (strpos($user, '@') === 0) {
                                $user = substr($user, 1);
                            }

                            // Remove o domínio se houver (ex: @gf.local)
                            if (strpos($user, '@') !== false) {
                                $user = strstr($user, '@', true);
                            }
                        } else {
                            // Se não tem @, usa o usuário normal
                            $user = isset($_SESSION['user']) ? $_SESSION['user'] : null;
                        }

                        // Verificar se o usuário foi extraído corretamente
                        if (!$user) {
                            return null; // Retorna null caso a sessão não contenha um usuário válido
                        }

                        // Adicionar o padrão de busca com wildcard
                        $userPattern = '%' . $user . '%';

                        // Query para buscar o NUMCAD
                        $sql = "SELECT NUMCAD
                    FROM vetorh.r034cpl
                    WHERE emapar IS NOT NULL
                    AND emapar <> ' '
                    AND emapar LIKE :userPattern";

                        // Preparar a consulta
                        $stmt = oci_parse($conn, $sql);

                        // Vincular o parâmetro com o padrão de busca
                        oci_bind_by_name($stmt, ':userPattern', $userPattern);

                        // Executar a consulta
                        if (oci_execute($stmt)) {
                            $row = oci_fetch_array($stmt, OCI_ASSOC + OCI_RETURN_NULLS);
                            if ($row && isset($row['NUMCAD'])) {
                                return $row['NUMCAD']; // Retorna o NUMCAD se encontrado
                            }
                        }

                        // Retorna null se não encontrar
                        return null;
                    }


                    function obterDataInicial()
                    {
                        $dataAtual = new DateTime();
                        if ((int)$dataAtual->format('d') < 26) {
                            $mesAnterior = (int)$dataAtual->format('m') - 1;
                            $ano = (int)$dataAtual->format('Y');
                            if ($mesAnterior < 1) {
                                $mesAnterior = 12;
                                $ano--;
                            }
                            $dataInicial = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-26', $ano, $mesAnterior));
                        } else {
                            $dataInicial = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-26', (int)$dataAtual->format('Y'), (int)$dataAtual->format('m')));
                        }
                        return $dataInicial->format('d/m/Y');
                    }

                    // Função para converter HH:MM para minutos
                    function bancoHorasParaMinutos($banco_horas)
                    {
                        $negativo = false;
                        if (strpos($banco_horas, '-') === 0) {
                            $negativo = true;
                            $banco_horas = substr($banco_horas, 1);
                        }
                        if (strpos($banco_horas, ':') !== false) {
                            list($h, $m) = explode(':', $banco_horas);
                            $total = ((int)$h) * 60 + (int)$m;
                            return $negativo ? -$total : $total;
                        }
                        return 0;
                    }


                    // Processamento dos dados
                    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                        $conn = conectar_db();

                        $acao = isset($_POST['acao']) ? $_POST['acao'] : null;
                        $numcra = isset($_POST['NUMCRA']) ? $_POST['NUMCRA'] : null;
                        $datacc = $_POST['DATACC'];
                        $horacc = isset($_POST['HORACC']) ? formatar_horario_para_numerico($_POST['HORACC']) : null;
                        $horacc_original = isset($_POST['HORACC_ORIGINAL']) ? $_POST['HORACC_ORIGINAL'] : null;
                        $justificativa = isset($_POST['CODJMA']) ? $_POST['CODJMA'] : null; // Adicionado campo de justificativa

                        if ($acao == 'Adicionar Registro') {
                            $datacc = formatar_data_para_ddmmyyyy($datacc);
                            if (!$numcra || !$datacc || !$horacc) {
                                $_SESSION['mensagem'] = exibir_mensagem("Todos os campos são obrigatórios!", "danger");
                            } else {
                                $colaborador = buscar_colaborador($conn, $numcra);
                                if (!$colaborador) {
                                    $_SESSION['mensagem'] = exibir_mensagem("Colaborador não encontrado!", "danger");
                                } else {
                                    $numemp = $colaborador['NUMEMP'];
                                    $tipcol = $colaborador['TIPCOL'];
                                    $numcad = $colaborador['NUMCAD'];

                                    if (registro_existe($conn, $numcra, $datacc, $horacc)) {
                                        $_SESSION['mensagem'] = exibir_mensagem("Registro já existe!", "warning");
                                    } else {
                                        $sql = "INSERT INTO VETORH.R070ACC (NUMEMP, TIPCOL, NUMCAD, NUMCRA, DATACC, HORACC, SEQACC, ORIACC, USOMAR, DATAPU)
                                         VALUES (:numemp, :tipcol, :numcad, :numcra, TO_DATE(:datacc, 'DD/MM/YYYY'), :horacc, 1, 'D', 4, TO_DATE(:datacc, 'DD/MM/YYYY'))";

                                        $stmt = oci_parse($conn, $sql);
                                        oci_bind_by_name($stmt, ':numemp', $numemp);
                                        oci_bind_by_name($stmt, ':tipcol', $tipcol);
                                        oci_bind_by_name($stmt, ':numcad', $numcad);
                                        oci_bind_by_name($stmt, ':numcra', $numcra);
                                        oci_bind_by_name($stmt, ':datacc', $datacc);
                                        oci_bind_by_name($stmt, ':horacc', $horacc);

                                        $sql_jus = "INSERT INTO VETORH.R070JUS (NUMCRA, DATACC, HORACC, SEQACC, CODJMA, OBSJMA)
                                            VALUES (:numcra, TO_DATE(:datacc, 'DD/MM/YYYY'), :horacc, 1, :justificativa, '$obsjus')";

                                        $stmt2 = oci_parse($conn, $sql_jus);
                                        oci_bind_by_name($stmt2, ':numcra', $numcra);
                                        oci_bind_by_name($stmt2, ':datacc', $datacc);
                                        oci_bind_by_name($stmt2, ':horacc', $horacc);
                                        oci_bind_by_name($stmt2, ':justificativa', $justificativa);

                                        if (oci_execute($stmt)) {
                                            $_SESSION['mensagem'] = exibir_mensagem("Registro adicionado com sucesso!", "success");
                                        } else {
                                            $e = oci_error($stmt);
                                            $_SESSION['mensagem'] = exibir_mensagem("Erro ao adicionar registro: " . $e['message'], "danger");
                                        }
                                        if (oci_execute($stmt2)) {
                                            $_SESSION['mensagem'] = exibir_mensagem("Registro adicionado com sucesso!", "success");
                                        } else {
                                            $e = oci_error($stmt2);
                                            $_SESSION['mensagem'] = exibir_mensagem("Erro ao adicionar registro: " . $e['message'], "danger");
                                        }
                                    }
                                }
                                // Após processar o formulário, redirecione para a mesma página (PRG)
                                //header("Location: " . $_SERVER['PHP_SELF']);
                                //exit();
                            }
                        } elseif ($acao == 'Salvar Alterações') {
                            if (!$numcra || !$datacc || !$horacc || !$horacc_original) {
                                $datacc = formatar_data_para_ddmmyyyy($datacc);
                                $_SESSION['mensagem'] = exibir_mensagem("Todos os campos são obrigatórios para salvar alterações!", "danger");
                            } else {
                                $sql = "UPDATE VETORH.R070ACC
                                    SET HORACC = :horacc
                                    WHERE NUMCRA = :numcra
                                    AND DATACC = TO_DATE(:datacc, 'DD/MM/YYYY')
                                    AND HORACC = :horacc_original";
                                $stmt = oci_parse($conn, $sql);
                                oci_bind_by_name($stmt, ':horacc', $horacc);
                                oci_bind_by_name($stmt, ':numcra', $numcra);
                                oci_bind_by_name($stmt, ':datacc', $datacc);
                                oci_bind_by_name($stmt, ':horacc_original', $horacc_original);

                                if (oci_execute($stmt)) {
                                    $_SESSION['mensagem'] = exibir_mensagem("Registro atualizado com sucesso!", "success");
                                } else {
                                    $e = oci_error($stmt);
                                    $_SESSION['mensagem'] = exibir_mensagem("Erro ao atualizar registro: " . $e['message'], "danger");
                                }
                            }
                        }
                        // Após processar o formulário, redirecione para a mesma página (PRG)
                        //header("Location: " . $_SERVER['PHP_SELF']);
                        //exit();
                    }
                    // Consulta aos dados
                    $conn = conectar_db();

                    $numcad = buscarNumcadPorLogin($conn);

                    $_SESSION['numcad'] = $numcad;

                    $datacc = isset($_GET['datacc']) ? formatar_data_para_ddmmyyyy($_GET['datacc']) : date('d/m/Y');

                    $dataInicial = obterDataInicial();

                    /*    $sql = "SELECT *
                                FROM (SELECT DISTINCT R034FUN.NUMEMP,
                                                        R034FUN.TIPCOL,
                                                        R034FUN.NUMCAD,
                                                        R034FUN.NUMCRA,
                                                        TO_CHAR(R070ACC.DATACC, 'DD/MM/YYYY') AS DATACC,
                                                        R070ACC.HORACC,
                                                        R034FUN.NOMFUN,
                                                        R024CAR.TITCAR,
                                                        (SELECT R066.QTDHOR
                                                        FROM VETORH.R066SIT R066
                                                        WHERE R066.NUMEMP = R034FUN.NUMEMP
                                                            AND R066.TIPCOL = R034FUN.TIPCOL
                                                            AND R066.NUMCAD = R034FUN.NUMCAD
                                                            AND R066.DATAPU = R070ACC.DATACC
                                                            AND R066.CODSIT IN (103, 15) FETCH FIRST 1 ROWS ONLY) AS QTDHOR,
                                                        (SELECT R066.CODSIT
                                                        FROM VETORH.R066SIT R066
                                                        WHERE R066.NUMEMP = R034FUN.NUMEMP
                                                            AND R066.TIPCOL = R034FUN.TIPCOL
                                                            AND R066.NUMCAD = R034FUN.NUMCAD
                                                            AND R066.DATAPU = R070ACC.DATACC
                                                            AND R066.CODSIT IN (103, 15) FETCH FIRST 1 ROWS ONLY) AS CODSIT,
                                                        (SELECT COUNT(*)
                                                        FROM VETORH.R070ACC RA
                                                        WHERE RA.NUMCRA = R034FUN.NUMCRA
                                                            AND RA.DATACC = TO_DATE(:datacc, 'DD/MM/YYYY')
                                                            AND RA.NUMCAD <> 0
                                                            AND RA.DATAPU <>
                                                                TO_DATE('31/12/1900', 'DD/MM/YYYY')) AS TOTAL_REGISTROS
                                        FROM VETORH.R080SUB
                                        INNER JOIN VETORH.R034FUN
                                            ON R080SUB.NUMLOC = R034FUN.NUMLOC
                                        AND R034FUN.NUMEMP = R080SUB.NUMEMP
                                        INNER JOIN VETORH.usu_tlocenc LOC
                                            on LOC.Usu_CodLen = R034FUN.Usu_CodLen
                                        LEFT JOIN VETORH.R070ACC
                                            ON R034FUN.NUMEMP = R070ACC.NUMEMP
                                        AND R034FUN.NUMCRA = R070ACC.NUMCRA
                                        AND R070ACC.DATACC = TO_DATE(:datacc, 'DD/MM/YYYY')
                                        INNER JOIN VETORH.R024CAR
                                            ON R034FUN.CODCAR = R024CAR.CODCAR
                                        WHERE loc.Usu_NumCad = $numcad
                                        AND EXISTS (SELECT 1
                                                FROM VETORH.R066SIT R066
                                                WHERE R066.NUMEMP = R034FUN.NUMEMP
                                                AND R066.TIPCOL = R034FUN.TIPCOL
                                                AND R066.NUMCAD = R034FUN.NUMCAD
                                                AND R066.DATAPU = R070ACC.DATACC
                                                AND R066.CODSIT IN (103, 15))
                                                AND NOT EXISTS ( SELECT 1 FROM VETORH.R070JUS JUS WHERE JUS.NUMCRA = R034FUN.NUMCRA AND JUS.DATACC = R070ACC.DATACC AND JUS.CODJMA=70 ))
                                ORDER BY NUMCRA, DATACC, HORACC";*/

                    $sql = "SELECT *
                                FROM (SELECT DISTINCT R034FUN.NUMEMP,
                                                        R034FUN.TIPCOL,
                                                        R034FUN.NUMCAD,
                                                        R034FUN.NUMCRA,
                                                        TO_CHAR(R070ACC.DATACC, 'DD/MM/YYYY') AS DATACC,
                                                        R070ACC.HORACC,
                                                        R034FUN.NOMFUN,
                                                        R024CAR.TITCAR,
                                                        (SELECT R066.QTDHOR
                                                        FROM VETORH.R066SIT R066
                                                        WHERE R066.NUMEMP = R034FUN.NUMEMP
                                                            AND R066.TIPCOL = R034FUN.TIPCOL
                                                            AND R066.NUMCAD = R034FUN.NUMCAD
                                                            AND R066.DATAPU = R070ACC.DATACC
                                                            AND R066.CODSIT IN (103, 15) FETCH FIRST 1 ROWS ONLY) AS QTDHOR,
                                                        (SELECT R066.CODSIT
                                                        FROM VETORH.R066SIT R066
                                                        WHERE R066.NUMEMP = R034FUN.NUMEMP
                                                            AND R066.TIPCOL = R034FUN.TIPCOL
                                                            AND R066.NUMCAD = R034FUN.NUMCAD
                                                            AND R066.DATAPU = R070ACC.DATACC
                                                            AND R066.CODSIT IN (103, 15) FETCH FIRST 1 ROWS ONLY) AS CODSIT
                                        FROM VETORH.R080SUB
                                        INNER JOIN VETORH.R034FUN
                                            ON R080SUB.NUMLOC = R034FUN.NUMLOC
                                        AND R034FUN.NUMEMP = R080SUB.NUMEMP
                                        INNER JOIN VETORH.usu_tlocenc LOC
                                            on LOC.Usu_CodLen = R034FUN.Usu_CodLen
                                        LEFT JOIN VETORH.R070ACC
                                            ON R034FUN.NUMEMP = R070ACC.NUMEMP
                                        AND R034FUN.NUMCRA = R070ACC.NUMCRA
                                        AND R070ACC.DATAPU = TO_DATE(:datacc, 'DD/MM/YYYY')
                                        INNER JOIN VETORH.R024CAR
                                            ON R034FUN.CODCAR = R024CAR.CODCAR
                                        WHERE loc.Usu_NumCad = $numcad
                                        AND EXISTS (SELECT 1
                                                FROM VETORH.R066SIT R066
                                                WHERE R066.NUMEMP = R034FUN.NUMEMP
                                                AND R066.TIPCOL = R034FUN.TIPCOL
                                                AND R066.NUMCAD = R034FUN.NUMCAD
                                                AND R066.DATAPU = R070ACC.DATAPU
                                                AND R066.CODSIT IN (103, 15))
                                        AND NOT EXISTS (SELECT 1
                                                FROM VETORH.R070JUS JUS
                                                WHERE JUS.NUMCRA = R034FUN.NUMCRA
                                                AND JUS.DATACC = R070ACC.DATACC
                                                AND JUS.CODJMA = 70)
                                        union
                                        select sit.numemp,
                                            sit.tipcol,
                                            sit.numcad,
                                            fun.numcra,
                                            TO_CHAR(sit.datapu, 'DD/MM/YYYY') datacc,
                                            0 horacc,
                                            fun.nomfun,
                                            car.titcar,
                                            sit.qtdhor,
                                            sit.codsit
                                        from VETORH.r066sit sit, VETORH.r034fun fun, VETORH.r024car car, VETORH.usu_tlocenc loc
                                        where fun.numemp = sit.numemp
                                        and fun.tipcol = sit.tipcol
                                        and fun.numcad = sit.numcad
                                        and fun.codcar = car.codcar
                                        and car.estcar = 100
                                        and LOC.Usu_CodLen = fun.Usu_CodLen
                                        and loc.Usu_NumCad = $numcad
                                        and sit.codsit = 15
                                        and sit.datapu = TO_DATE(:datacc, 'DD/MM/YYYY')
                                        and not exists (select acc.datacc
                                                from VETORH.r070acc acc
                                                where acc.DATAPU = sit.datapu
                                                and acc.numemp = sit.numemp
                                                and acc.tipcol = sit.tipcol
                                                and acc.numcad = sit.numcad))
                                ORDER BY NUMCRA, DATACC, HORACC";

                    $stmt = oci_parse($conn, $sql);
                    oci_bind_by_name($stmt, ':datacc', $datacc);
                    oci_execute($stmt);
                    ?>

                    <!DOCTYPE html>
                    <html lang="pt-BR">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <title>Gerenciamento de Ponto</title>
                        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
                    </head>

                    <div>

                        <?php
                        if (isset($_SESSION['mensagem'])) {
                            echo $_SESSION['mensagem'];
                            unset($_SESSION['mensagem']);
                        }
                        ?>

                        <?php
                        // Agrupar registros por colaborador
                        $colaboradores = [];
                        while ($row = oci_fetch_array($stmt, OCI_ASSOC + OCI_RETURN_NULLS)) {
                            $colaboradores[$row['NUMCRA']]['dados'] = [
                                'NUMCRA' => $row['NUMCRA'],
                                'NOMFUN' => $row['NOMFUN'],
                                'TITCAR' => $row['TITCAR'],
                                'NUMEMP' => $row['NUMEMP'],
                                'TIPCOL' => $row['TIPCOL'], // garantir TIPCOL
                                'NUMCAD' => $row['NUMCAD'], // garantir NUMCAD
                                'QTDHOR' => isset($row['QTDHOR']) ? $row['QTDHOR'] : null, // Adicionado para exibir o atraso
                                'CODSIT' => isset($row['CODSIT']) ? $row['CODSIT'] : null, // Adicionado para condicionar o botão
                            ];
                            if (
                                isset($row['DATACC']) && $row['DATACC'] !== null &&
                                isset($row['HORACC']) && $row['HORACC'] !== null &&
                                $row['HORACC'] != 0 && $row['HORACC'] !== '0' && $row['HORACC'] !== ''
                            ) {
                                $colaboradores[$row['NUMCRA']]['marcacoes'][] = [
                                    'NUMEMP' => $row['NUMEMP'],
                                    'DATACC' => $row['DATACC'],
                                    'HORACC' => $row['HORACC'],
                                ];
                            }
                        }
                        foreach ($colaboradores as $colab) {
                            $banco_horas = banco_horas($conn, $colab['dados']['NUMCRA']);
                            $banco_class = (strpos($banco_horas, '-') === 0) ? 'banco-negativo' : 'banco-positivo';
                            echo "<div class='info-colaborador mb-2'>";
                            echo "<span class='fw-semibold'>Crachá:</span> <span>" . htmlspecialchars($colab['dados']['NUMCRA']) . "</span> - ";
                            echo "<span class='colaborador-nome'>" . htmlspecialchars($colab['dados']['NOMFUN']) . "</span> - ";
                            echo "<span>" . htmlspecialchars($colab['dados']['TITCAR']) . "</span> - ";
                            echo "<span class='fw-semibold'>Banco de Horas:</span> <span class='$banco_class'>" . htmlspecialchars($banco_horas) . "</span>";
                            $atraso_minutos = isset($colab['dados']['QTDHOR']) ? (int)$colab['dados']['QTDHOR'] : 0;
                            $saldo_minutos = bancoHorasParaMinutos($banco_horas);
                            if (
                                $atraso_minutos > 0 && isset($colab['dados']['CODSIT']) && (($colab['dados']['CODSIT'] == 103) or ($colab['dados']['CODSIT'] == 15)) && $saldo_minutos > $atraso_minutos
                            ) {
                                $horas = floor($atraso_minutos / 60);
                                $minutos = $atraso_minutos % 60;
                                $atraso_formatado = sprintf('%02d:%02d', $horas, $minutos);
                                echo " <span class='text-danger fw-semibold'>(Atraso: $atraso_formatado)</span>";
                                $datapu = isset($_GET['datacc']) ? formatar_data_para_ddmmyyyy($_GET['datacc']) : date('d/m/Y');
                                echo "<div class='d-inline-flex gap-2 align-items-center' style='vertical-align:middle;'>
                                    <form method='post' style='display:inline' onsubmit=\"return confirm('Deseja realmente abater o atraso nas horas positivas do banco?');\">
                                        <input type='hidden' name='acao' value='abater_atraso'>
                                        <input type='hidden' name='numemp' value='" . htmlspecialchars($colab['dados']['NUMEMP']) . "'>
                                        <input type='hidden' name='tipcol' value='" . htmlspecialchars($colab['dados']['TIPCOL']) . "'>
                                        <input type='hidden' name='numcad' value='" . htmlspecialchars($colab['dados']['NUMCAD']) . "'>
                                        <input type='hidden' name='datapu' value='" . htmlspecialchars($datapu) . "'>
                                        <button type='submit' class='btn btn-warning btn-sm'>Abater atraso</button>
                                    </form>
                                    <form method='post' style='display:inline' onsubmit=\"return confirm('Tem certeza que deseja autorizar a inconsistência para este colaborador nesta data? Esta ação não poderá ser desfeita.');\">
                                        <input type='hidden' name='acao' value='autorizar_inconsistencia'>
                                        <input type='hidden' name='numcra' value='" . htmlspecialchars($colab['dados']['NUMCRA']) . "'>
                                        <input type='hidden' name='datacc' value='" . htmlspecialchars($datapu) . "'>
                                        <button type='submit' class='btn btn-secondary btn-sm'>Autorizar inconsistência</button>
                                    </form>
                                </div>";
                            } elseif ($atraso_minutos > 0) {
                                $horas = floor($atraso_minutos / 60);
                                $minutos = $atraso_minutos % 60;
                                $atraso_formatado = sprintf('%02d:%02d', $horas, $minutos);
                                echo " <span class='text-danger fw-semibold'>(Atraso: $atraso_formatado)</span>";
                            }
                            echo "</div>";
                            echo "<table class='table table-bordered table-sm table-marcacoes'>";
                            echo "<thead><tr><th>Empresa</th><th>Data</th><th>Horário</th></tr></thead>";
                            echo "<tbody>";
                            if (!empty($colab['marcacoes'])) {
                                foreach ($colab['marcacoes'] as $marc) {
                                    echo "<tr>";
                                    echo "<td><input type='text' name='NUMEMP' value='" . htmlspecialchars($marc['NUMEMP']) . "' class='form-control form-control-sm' readonly></td>";
                                    echo "<td><input type='text' name='DATACC' value='" . htmlspecialchars($marc['DATACC']) . "' class='form-control form-control-sm' readonly></td>";
                                    echo "<td><input type='time' name='HORACC' value='" . htmlspecialchars(formatar_horario_para_hhmm($marc['HORACC'])) . "' class='form-control form-control-sm' readonly></td>";
                                    echo "</tr>";
                                }
                            } elseif (isset($colab['dados']['CODSIT']) && $colab['dados']['CODSIT'] == 15) {
                                echo "<tr><td colspan='3' class='text-center text-danger fw-bold'>Sem marcações</td></tr>";
                            }
                            echo "</tbody></table>";
                        }
                        ?>
                    </div>
                    <?php
                    // Após o foreach, exibir mensagem se não houver colaboradores
                    if (empty($colaboradores)) {
                        echo "<div class='alert alert-info text-center mt-3'>Não há ajustes a serem corrigidos para a data selecionada.</div>";
                    }
                    ?>