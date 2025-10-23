<?php
// Headers para prevenir cache do navegador
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Iniciar sessão para armazenar mensagens
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'conn.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

// Usar o usuário original para logs
$user_original = isset($_SESSION['user_original']) ? $_SESSION['user_original'] : $_SESSION['user'];
$user = htmlspecialchars(strstr($user_original, '@', true));

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

// Função para converter data de YYYY-MM-DD para DD/MM/YYYY
function formatar_data_para_ddmmyyyy($data)
{
    $partes = explode('-', $data); // Divide a string no formato YYYY-MM-DD
    if (count($partes) === 3) {
        return $partes[2] . '/' . $partes[1] . '/' . $partes[0]; // Retorna DD/MM/YYYY
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

$conn = conectar_db();
$numcad = buscarNumcadPorLogin($conn);

// Definir período padrão (últimos 30 dias)
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-d', strtotime('-30 days'));

$data_inicio_formatada = formatar_data_para_ddmmyyyy($data_inicio);
$data_fim_formatada = formatar_data_para_ddmmyyyy($data_fim);

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visões Gerenciais - Controle de Ponto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body style="background: #f5f6fa;">
    <!-- Navbar fixa com logo e nome -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top" style="z-index: 1040;">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="https://app.grupofarias.com.br/_next/image?url=%2Fassets%2Fimages%2Flogos%2Flogo.png&w=96&q=75&dpl=dpl_2zZSoeRAkK891Wnr1b564YVbKpdi" alt="Logo Grupo Farias" style="height:38px; margin-right:12px;">
                <span class="fw-bold" style="font-size:1.25rem;">Visões Gerenciais - GF</span>
            </a>
            <div class="d-flex ms-auto align-items-center">
                <a href="logout.php" class="btn btn-outline-danger btn-sm">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid" style="margin-top: 80px; margin-bottom: 1rem; max-width: 1400px;">
        <!-- Filtros -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h6 class="fw-semibold mb-3"><i class="bi bi-funnel"></i> Filtros</h6>
                        <form method="get" class="row g-3">
                            <div class="col-md-4">
                                <label for="data_inicio" class="form-label">Data Início:</label>
                                <input type="date" name="data_inicio" id="data_inicio" class="form-control" value="<?= htmlspecialchars($data_inicio) ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="data_fim" class="form-label">Data Fim:</label>
                                <input type="date" name="data_fim" id="data_fim" class="form-control" value="<?= htmlspecialchars($data_fim) ?>">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">Filtrar</button>
                                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary">Limpar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cards de Resumo -->
        <div class="row mb-4">
            <?php
            // Consulta para resumo geral
            $sql_resumo = "SELECT 
                            COUNT(DISTINCT R034FUN.NUMCRA) AS TOTAL_COLABORADORES,
                            COUNT(CASE WHEN R066SIT.CODSIT IN (103, 15) THEN 1 END) AS TOTAL_INCONSISTENCIAS,
                            COUNT(CASE WHEN R066SIT.CODSIT IN (301, 313, 303, 305, 311) THEN 1 END) AS TOTAL_EXTRAS,
                            COUNT(CASE WHEN R070ACC.HORACC IS NOT NULL AND R070ACC.HORACC > 0 THEN 1 END) AS TOTAL_MARCACOES
                          FROM VETORH.R080SUB
                          INNER JOIN VETORH.R034FUN
                              ON R080SUB.NUMLOC = R034FUN.NUMLOC
                          AND R034FUN.NUMEMP = R080SUB.NUMEMP
                          INNER JOIN VETORH.usu_tlocenc LOC
                              ON LOC.Usu_CodLen = R034FUN.Usu_CodLen
                          LEFT JOIN VETORH.R070ACC
                              ON R034FUN.NUMEMP = R070ACC.NUMEMP
                          AND R034FUN.NUMCRA = R070ACC.NUMCRA
                          AND R070ACC.DATACC BETWEEN TO_DATE(:data_inicio, 'DD/MM/YYYY') AND TO_DATE(:data_fim, 'DD/MM/YYYY')
                          LEFT JOIN VETORH.R066SIT
                              ON R066SIT.NUMEMP = R034FUN.NUMEMP
                          AND R066SIT.TIPCOL = R034FUN.TIPCOL
                          AND R066SIT.NUMCAD = R034FUN.NUMCAD
                          AND R066SIT.DATAPU = R070ACC.DATACC
                          WHERE LOC.Usu_NumCad = $numcad
                          AND R034FUN.SITAFA <> 7
                          AND R034FUN.TIPCON <> 6";

            $stmt_resumo = oci_parse($conn, $sql_resumo);
            oci_bind_by_name($stmt_resumo, ':data_inicio', $data_inicio_formatada);
            oci_bind_by_name($stmt_resumo, ':data_fim', $data_fim_formatada);
            oci_execute($stmt_resumo);
            $resumo = oci_fetch_array($stmt_resumo, OCI_ASSOC + OCI_RETURN_NULLS);
            ?>

            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?= $resumo['TOTAL_COLABORADORES'] ?></h4>
                                <p class="card-text">Colaboradores</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-people" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?= $resumo['TOTAL_INCONSISTENCIAS'] ?></h4>
                                <p class="card-text">Inconsistências</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-exclamation-triangle" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?= $resumo['TOTAL_EXTRAS'] ?></h4>
                                <p class="card-text">Horas Extras</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-clock" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?= $resumo['TOTAL_MARCACOES'] ?></h4>
                                <p class="card-text">Marcações</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-check-circle" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h6 class="fw-semibold mb-0"><i class="bi bi-pie-chart"></i> Distribuição de Situações</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="graficoPizza" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h6 class="fw-semibold mb-0"><i class="bi bi-bar-chart"></i> Inconsistências por Dia</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="graficoBarras" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabela de Detalhes -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h6 class="fw-semibold mb-0"><i class="bi bi-table"></i> Detalhamento por Colaborador</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Crachá</th>
                                        <th>Nome</th>
                                        <th>Cargo</th>
                                        <th>Inconsistências</th>
                                        <th>Horas Extras</th>
                                        <th>Marcações</th>
                                        <th>Última Marcação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Consulta detalhada por colaborador
                                    $sql_detalhes = "SELECT 
                                                        R034FUN.NUMCRA,
                                                        R034FUN.NOMFUN,
                                                        R024CAR.TITCAR,
                                                        COUNT(CASE WHEN R066SIT.CODSIT IN (103, 15) THEN 1 END) AS INCONSISTENCIAS,
                                                        COUNT(CASE WHEN R066SIT.CODSIT IN (301, 313, 303, 305, 311) THEN 1 END) AS EXTRAS,
                                                        COUNT(CASE WHEN R070ACC.HORACC IS NOT NULL AND R070ACC.HORACC > 0 THEN 1 END) AS MARCACOES,
                                                        MAX(TO_CHAR(R070ACC.DATACC, 'DD/MM/YYYY')) AS ULTIMA_MARCACAO
                                                    FROM VETORH.R080SUB
                                                    INNER JOIN VETORH.R034FUN
                                                        ON R080SUB.NUMLOC = R034FUN.NUMLOC
                                                    AND R034FUN.NUMEMP = R080SUB.NUMEMP
                                                    INNER JOIN VETORH.usu_tlocenc LOC
                                                        ON LOC.Usu_CodLen = R034FUN.Usu_CodLen
                                                    INNER JOIN VETORH.R024CAR
                                                        ON R034FUN.CODCAR = R024CAR.CODCAR
                                                    LEFT JOIN VETORH.R070ACC
                                                        ON R034FUN.NUMEMP = R070ACC.NUMEMP
                                                    AND R034FUN.NUMCRA = R070ACC.NUMCRA
                                                    AND R070ACC.DATACC BETWEEN TO_DATE(:data_inicio, 'DD/MM/YYYY') AND TO_DATE(:data_fim, 'DD/MM/YYYY')
                                                    LEFT JOIN VETORH.R066SIT
                                                        ON R066SIT.NUMEMP = R034FUN.NUMEMP
                                                    AND R066SIT.TIPCOL = R034FUN.TIPCOL
                                                    AND R066SIT.NUMCAD = R034FUN.NUMCAD
                                                    AND R066SIT.DATAPU = R070ACC.DATACC
                                                    WHERE LOC.Usu_NumCad = $numcad
                                                    AND R034FUN.SITAFA <> 7
                                                    AND R034FUN.TIPCON <> 6
                                                    GROUP BY R034FUN.NUMCRA, R034FUN.NOMFUN, R024CAR.TITCAR
                                                    ORDER BY R034FUN.NUMCRA";

                                    $stmt_detalhes = oci_parse($conn, $sql_detalhes);
                                    oci_bind_by_name($stmt_detalhes, ':data_inicio', $data_inicio_formatada);
                                    oci_bind_by_name($stmt_detalhes, ':data_fim', $data_fim_formatada);
                                    oci_execute($stmt_detalhes);

                                    while ($row = oci_fetch_array($stmt_detalhes, OCI_ASSOC + OCI_RETURN_NULLS)) {
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($row['NUMCRA']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['NOMFUN']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['TITCAR']) . "</td>";
                                        echo "<td><span class='badge bg-warning'>" . htmlspecialchars($row['INCONSISTENCIAS']) . "</span></td>";
                                        echo "<td><span class='badge bg-info'>" . htmlspecialchars($row['EXTRAS']) . "</span></td>";
                                        echo "<td><span class='badge bg-success'>" . htmlspecialchars($row['MARCACOES']) . "</span></td>";
                                        echo "<td>" . htmlspecialchars($row['ULTIMA_MARCACAO'] ?? 'N/A') . "</td>";
                                        echo "</tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Dados para os gráficos (serão preenchidos via PHP)
        <?php
        // Consulta para gráfico de pizza
        $sql_pizza = "SELECT 
                        CASE 
                            WHEN R066SIT.CODSIT IN (103, 15) THEN 'Inconsistências'
                            WHEN R066SIT.CODSIT IN (301, 313, 303, 305, 311) THEN 'Horas Extras'
                            WHEN R070ACC.HORACC IS NOT NULL AND R070ACC.HORACC > 0 THEN 'Marcações Normais'
                            ELSE 'Outros'
                        END AS TIPO,
                        COUNT(*) AS QUANTIDADE
                      FROM VETORH.R080SUB
                      INNER JOIN VETORH.R034FUN
                          ON R080SUB.NUMLOC = R034FUN.NUMLOC
                      AND R034FUN.NUMEMP = R080SUB.NUMEMP
                      INNER JOIN VETORH.usu_tlocenc LOC
                          ON LOC.Usu_CodLen = R034FUN.Usu_CodLen
                      LEFT JOIN VETORH.R070ACC
                          ON R034FUN.NUMEMP = R070ACC.NUMEMP
                      AND R034FUN.NUMCRA = R070ACC.NUMCRA
                      AND R070ACC.DATACC BETWEEN TO_DATE(:data_inicio, 'DD/MM/YYYY') AND TO_DATE(:data_fim, 'DD/MM/YYYY')
                      LEFT JOIN VETORH.R066SIT
                          ON R066SIT.NUMEMP = R034FUN.NUMEMP
                      AND R066SIT.TIPCOL = R034FUN.TIPCOL
                      AND R066SIT.NUMCAD = R034FUN.NUMCAD
                      AND R066SIT.DATAPU = R070ACC.DATACC
                      WHERE LOC.Usu_NumCad = $numcad
                      AND R034FUN.SITAFA <> 7
                      AND R034FUN.TIPCON <> 6
                      AND (R066SIT.CODSIT IS NOT NULL OR R070ACC.HORACC IS NOT NULL)
                      GROUP BY CASE 
                          WHEN R066SIT.CODSIT IN (103, 15) THEN 'Inconsistências'
                          WHEN R066SIT.CODSIT IN (301, 313, 303, 305, 311) THEN 'Horas Extras'
                          WHEN R070ACC.HORACC IS NOT NULL AND R070ACC.HORACC > 0 THEN 'Marcações Normais'
                          ELSE 'Outros'
                      END";

        $stmt_pizza = oci_parse($conn, $sql_pizza);
        oci_bind_by_name($stmt_pizza, ':data_inicio', $data_inicio_formatada);
        oci_bind_by_name($stmt_pizza, ':data_fim', $data_fim_formatada);
        oci_execute($stmt_pizza);

        $dados_pizza = [];
        $labels_pizza = [];
        while ($row = oci_fetch_array($stmt_pizza, OCI_ASSOC + OCI_RETURN_NULLS)) {
            $labels_pizza[] = $row['TIPO'];
            $dados_pizza[] = $row['QUANTIDADE'];
        }
        ?>

        // Gráfico de Pizza
        const ctxPizza = document.getElementById('graficoPizza').getContext('2d');
        new Chart(ctxPizza, {
            type: 'pie',
            data: {
                labels: <?= json_encode($labels_pizza) ?>,
                datasets: [{
                    data: <?= json_encode($dados_pizza) ?>,
                    backgroundColor: [
                        '#ffc107',
                        '#17a2b8',
                        '#28a745',
                        '#6c757d'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Gráfico de Barras (dados de exemplo - pode ser implementado com dados reais)
        const ctxBarras = document.getElementById('graficoBarras').getContext('2d');
        new Chart(ctxBarras, {
            type: 'bar',
            data: {
                labels: ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'],
                datasets: [{
                    label: 'Inconsistências',
                    data: [12, 19, 3, 5, 2, 3, 1],
                    backgroundColor: '#ffc107',
                    borderColor: '#ffc107',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
