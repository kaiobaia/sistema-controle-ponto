<?php
session_start();

// Verificar se o usuário está logado como colaborador
if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'colaborador') {
    header("Location: index.php");
    exit();
}

require_once 'conn.php';

// Headers para prevenir cache do navegador
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$conn = conectar_db();
$numcra = $_SESSION['numcra'];
$nomfun = $_SESSION['nomfun'];

// Função para obter data inicial do período de apuração (mesma lógica do ajuste.php)
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

// Obter data inicial usando a mesma função do ajuste.php
$data_inicial_formatada = obterDataInicial(); // Retorna no formato dd/mm/yyyy

// Calcular data fim do período de apuração
$dataAtual = new DateTime();
$ano_atual = (int)$dataAtual->format('Y');
$mes_atual = (int)$dataAtual->format('m');

// Se estamos em setembro, o período vai até 25/10 (mês seguinte)
// Se estamos em outubro, o período vai até 25/10 (mês atual)
if ($mes_atual == 9) {
    $mes_fim = 10;
} else {
    $mes_fim = $mes_atual;
}

$data_fim_formatada = sprintf('%02d/%02d/%04d', 25, $mes_fim, $ano_atual);

// Converter para formato yyyy-mm-dd para os inputs
$data_inicio_padrao = DateTime::createFromFormat('d/m/Y', $data_inicial_formatada)->format('Y-m-d');
$data_fim_padrao = DateTime::createFromFormat('d/m/Y', $data_fim_formatada)->format('Y-m-d');

// Processar filtros de data
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : $data_inicio_padrao;
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : $data_fim_padrao;

// Validar se as datas estão dentro do período de apuração
$data_inicio_valida = $data_inicio;
$data_fim_valida = $data_fim;

// Se a data início for anterior à data inicial do período, ajustar
if ($data_inicio < $data_inicio_padrao) {
    $data_inicio_valida = $data_inicio_padrao;
}

// Se a data fim for posterior à data atual, ajustar
if ($data_fim > $data_fim_padrao) {
    $data_fim_valida = $data_fim_padrao;
}

// Converter datas para formato DD/MM/YYYY para as consultas
function formatar_data_para_ddmmyyyy($data)
{
    $partes = explode('-', $data);
    if (count($partes) === 3) {
        return $partes[2] . '/' . $partes[1] . '/' . $partes[0];
    }
    return null;
}

$data_inicio_formatada = formatar_data_para_ddmmyyyy($data_inicio_valida);
$data_fim_formatada = formatar_data_para_ddmmyyyy($data_fim_valida);

// Função para formatar horário numérico para HH:MM
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

// Função para formatar minutos para horas
function formatar_minutos_para_horas($minutos)
{
    if (is_numeric($minutos)) {
        $horas = floor($minutos / 60);
        $minutos_restantes = $minutos % 60;
        return sprintf('%02d:%02d', $horas, $minutos_restantes);
    }
    return '00:00';
}

// Consulta para buscar TODOS os pontos do colaborador (com e sem inconsistências)
$sql_inconsistencias = "
    SELECT 
        TO_CHAR(acc.DATACC, 'DD/MM/YYYY') AS DATA,
        CASE 
            WHEN MAX(CASE WHEN sit.CODSIT IN (103, 15, 911) THEN sit.CODSIT END) = 103 THEN 'Atraso'
            WHEN MAX(CASE WHEN sit.CODSIT IN (103, 15, 911) THEN sit.CODSIT END) = 15 THEN 'Falta'
            WHEN MAX(CASE WHEN sit.CODSIT IN (103, 15, 911) THEN sit.CODSIT END) = 911 THEN 'Atraso Abatido'
            WHEN MAX(CASE WHEN sit.CODSIT IN (103, 15, 911) THEN sit.CODSIT END) = 1 THEN 'Normal'
            WHEN MAX(CASE WHEN sit.CODSIT IN (103, 15, 911) THEN sit.CODSIT END) IS NULL THEN 'Sem Situacao'
            ELSE 'Outros'
        END AS TIPO_SITUACAO,
        MAX(CASE WHEN sit.CODSIT IN (103, 15, 911) THEN sit.CODSIT END) AS CODSIT,
        MAX(CASE WHEN sit.CODSIT IN (103, 15, 911) THEN sit.QTDHOR END) AS QTDHOR,
        LISTAGG(DISTINCT TO_CHAR(FLOOR(acc.HORACC/60), 'FM00') || ':' || TO_CHAR(MOD(acc.HORACC, 60), 'FM00'), ', ') 
            WITHIN GROUP (ORDER BY 
                CASE 
                    WHEN acc.HORACC >= 1200 THEN acc.HORACC - 1440 
                    ELSE acc.HORACC 
                END
            ) AS MARCACOES_FORMATADAS,
        COUNT(DISTINCT acc.HORACC) AS TOTAL_MARCACOES,
        TRABALHANDO_HORAS,
        ADICIONAL_NOTURNO_HORAS
    FROM VETORH.R034FUN fun
    INNER JOIN VETORH.R070ACC acc ON 
        fun.NUMCRA = acc.NUMCRA AND
        acc.HORACC IS NOT NULL AND acc.HORACC != 0 AND
        acc.NUMCAD <> 0 AND
        acc.ORIACC <> 'G'
    LEFT JOIN VETORH.R066SIT sit ON 
        fun.NUMEMP = sit.NUMEMP AND 
        fun.TIPCOL = sit.TIPCOL AND 
        fun.NUMCAD = sit.NUMCAD AND
        sit.DATAPU = acc.DATACC
    LEFT JOIN (
        SELECT 
            sit2.DATAPU,
            sit2.NUMEMP,
            sit2.TIPCOL,
            sit2.NUMCAD,
            MAX(CASE WHEN sit2.CODSIT = 1 THEN TO_CHAR(FLOOR(sit2.QTDHOR/60), 'FM00') || ':' || TO_CHAR(MOD(sit2.QTDHOR, 60), 'FM00') END) AS TRABALHANDO_HORAS,
            MAX(CASE WHEN sit2.CODSIT = 300 THEN TO_CHAR(FLOOR(sit2.QTDHOR/60), 'FM00') || ':' || TO_CHAR(MOD(sit2.QTDHOR, 60), 'FM00') END) AS ADICIONAL_NOTURNO_HORAS
        FROM VETORH.R066SIT sit2
        INNER JOIN VETORH.R034FUN fun2 ON 
            sit2.NUMEMP = fun2.NUMEMP AND 
            sit2.TIPCOL = fun2.TIPCOL AND 
            sit2.NUMCAD = fun2.NUMCAD AND
            fun2.NUMCRA = :numcra2
        WHERE sit2.CODSIT IN (1, 300)
        GROUP BY sit2.DATAPU, sit2.NUMEMP, sit2.TIPCOL, sit2.NUMCAD
    ) situacoes ON situacoes.DATAPU = acc.DATACC 
        AND situacoes.NUMEMP = fun.NUMEMP 
        AND situacoes.TIPCOL = fun.TIPCOL 
        AND situacoes.NUMCAD = fun.NUMCAD
    WHERE fun.NUMCRA = :numcra
    AND acc.DATACC BETWEEN TO_DATE(:data_inicio, 'DD/MM/YYYY') AND TO_DATE(:data_fim, 'DD/MM/YYYY')
    GROUP BY acc.DATACC, TRABALHANDO_HORAS, ADICIONAL_NOTURNO_HORAS
    ORDER BY acc.DATACC DESC
";

$stmt = oci_parse($conn, $sql_inconsistencias);
oci_bind_by_name($stmt, ':numcra', $numcra);
oci_bind_by_name($stmt, ':numcra2', $numcra);
oci_bind_by_name($stmt, ':data_inicio', $data_inicio_formatada);
oci_bind_by_name($stmt, ':data_fim', $data_fim_formatada);

$inconsistencias = [];
if (oci_execute($stmt)) {
    while ($row = oci_fetch_array($stmt, OCI_ASSOC + OCI_RETURN_NULLS)) {
        $inconsistencias[] = $row;
    }
}

// Consulta para buscar banco de horas
$sql_banco_horas = "
    SELECT CASE
        WHEN diferenca_horas < 0 THEN
            '-' || LPAD(ABS(TRUNC(diferenca_horas)), 2, '0') || ':' ||
            LPAD(ABS(ROUND((diferenca_horas - TRUNC(diferenca_horas)) * 60)), 2, '0')
        ELSE
            LPAD(TRUNC(diferenca_horas), 2, '0') || ':' ||
            LPAD(ROUND((diferenca_horas - TRUNC(diferenca_horas)) * 60), 2, '0')
    END AS horas_banco
    FROM (
        SELECT COALESCE(SUM(CASE
            WHEN sinlan = '+' THEN qtdhor
            ELSE -qtdhor
        END) / 60, 0) AS diferenca_horas
        FROM VETORH.R011LAN, VETORH.R034FUN
        WHERE R011LAN.numemp = r034fun.numemp
        AND R011LAN.numcad = r034fun.numcad
        AND R011LAN.tipcol = r034fun.tipcol
        AND r034fun.numcra = :numcra
        AND r034fun.tipcon <> 6
        AND r034fun.sitafa <> 7
        AND R011LAN.codbhr = 15
    )
";

$stmt_banco = oci_parse($conn, $sql_banco_horas);
oci_bind_by_name($stmt_banco, ':numcra', $numcra);
$banco_horas = '00:00';
if (oci_execute($stmt_banco)) {
    $row_banco = oci_fetch_array($stmt_banco, OCI_ASSOC + OCI_RETURN_NULLS);
    if ($row_banco) {
        $banco_horas = $row_banco['HORAS_BANCO'];
    }
}

oci_close($conn);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Inconsistências - Grupo Farias</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: #f5f6fa !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .navbar-brand {
            font-size: 1.25rem;
            font-weight: bold;
        }

        .card {
            border: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .banco-positivo {
            color: #28a745;
            font-weight: bold;
        }

        .banco-negativo {
            color: #dc3545;
            font-weight: bold;
        }

        .badge-atraso {
            background-color: #ffc107;
            color: #000;
        }

        .badge-falta {
            background-color: #dc3545;
            color: #fff;
        }

        .badge-abatido {
            background-color: #28a745;
            color: #fff;
        }

        .pulse-animation {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }

            100% {
                transform: scale(1);
            }
        }

        .table-warning {
            background-color: #fff3cd !important;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="https://app.grupofarias.com.br/_next/image?url=%2Fassets%2Fimages%2Flogos%2Flogo.png&w=96&q=75&dpl=dpl_2zZSoeRAkK891Wnr1b564YVbKpdi" alt="Logo Grupo Farias" style="height:38px; margin-right:12px;">
                <span>Controle de Ponto - Colaborador</span>
            </a>
            <div class="d-flex align-items-center">
                <span class="me-3">Olá, <strong><?= htmlspecialchars($nomfun) ?></strong></span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid" style="margin-top: 20px; padding-top: 15px;">
        <div class="row">
            <!-- Filtros -->
            <div class="col-md-3">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-funnel"></i> Filtros</h6>
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> 
                            Período de apuração: <?= $data_inicial_formatada ?> a <?= $data_fim_formatada ?>
                        </small>
                    </div>
                    <div class="card-body">
                        <form method="GET">
                            <div class="mb-3">
                                <label for="data_inicio" class="form-label">Data Início:</label>
                                <input type="date" class="form-control" id="data_inicio" name="data_inicio"
                                    value="<?= htmlspecialchars($data_inicio) ?>"
                                    min="<?= $data_inicio_padrao ?>"
                                    max="<?= $data_fim_padrao ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="data_fim" class="form-label">Data Fim:</label>
                                <input type="date" class="form-control" id="data_fim" name="data_fim"
                                    value="<?= htmlspecialchars($data_fim) ?>"
                                    min="<?= $data_inicio_padrao ?>"
                                    max="<?= $data_fim_padrao ?>" required>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Filtrar</button>
                                <a href="?" class="btn btn-secondary">Limpar Filtros</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Banco de Horas - REMOVIDO -->
            </div>

            <!-- Lista de Inconsistências -->
            <div class="col-md-9">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Meus Pontos</h5>
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> 
                            Todos os pontos do período com destaque para inconsistências
                        </small>
                    </div>
                    <div class="card-body">
                        <?php if (empty($inconsistencias)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                                <h5 class="mt-3">Nenhum ponto encontrado!</h5>
                                <p class="text-muted">Para o período selecionado, não há marcações de ponto registradas.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Data</th>
                                            <th>Marcções de Ponto</th>
                                            <th>Situação</th>
                                            <th>Tempo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($inconsistencias as $inconsistencia): ?>
                                            <tr <?= ($inconsistencia['CODSIT'] == 15 || $inconsistencia['CODSIT'] == 103) ? 'class="table-warning"' : '' ?>>
                                                <td><strong><?= htmlspecialchars($inconsistencia['DATA']) ?></strong></td>
                                                <td>
                                                    <?php if ($inconsistencia['MARCACOES_FORMATADAS']): ?>
                                                        <span class="badge bg-info">
                                                            <i class="bi bi-clock"></i> <?= htmlspecialchars($inconsistencia['MARCACOES_FORMATADAS']) ?>
                                                        </span>
                                                        <small class="text-muted d-block">
                                                            <?= htmlspecialchars($inconsistencia['TOTAL_MARCACOES']) ?> marcação(ões)
                                                        </small>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="bi bi-x-circle"></i> Sem marcações
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $badge_class = '';
                                                    $icon = '';
                                                    $is_problematic = false;
                                                    
                                                    switch ($inconsistencia['CODSIT']) {
                                                        case 103:
                                                            $badge_class = 'badge-atraso';
                                                            $icon = 'bi-clock-history';
                                                            $is_problematic = true;
                                                            break;
                                                        case 15:
                                                            $badge_class = 'badge-falta';
                                                            $icon = 'bi-exclamation-triangle';
                                                            $is_problematic = true;
                                                            break;
                                                        case 911:
                                                            $badge_class = 'badge-abatido';
                                                            $icon = 'bi-check-circle';
                                                            break;
                                                        case 1:
                                                            $badge_class = 'bg-success';
                                                            $icon = 'bi-check-circle-fill';
                                                            break;
                                                        default:
                                                            $badge_class = 'bg-secondary';
                                                            $icon = 'bi-info-circle';
                                                    }
                                                    ?>
                                                    <span class="badge <?= $badge_class ?> <?= $is_problematic ? 'pulse-animation' : '' ?>">
                                                        <i class="<?= $icon ?>"></i> 
                                                        <?= htmlspecialchars($inconsistencia['TIPO_SITUACAO']) ?>
                                                    </span>
                                                    <?php if ($is_problematic): ?>
                                                        <small class="text-danger d-block fw-bold">
                                                            <i class="bi bi-exclamation-circle"></i> Requer atenção!
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $tempos = [];
                                                    if (!empty($inconsistencia['TRABALHANDO_HORAS'])) {
                                                        $tempos[] = '<span class="badge bg-success me-1"><i class="bi bi-hourglass-split"></i> Trabalhando: ' . htmlspecialchars($inconsistencia['TRABALHANDO_HORAS']) . '</span>';
                                                    }
                                                    if (!empty($inconsistencia['ADICIONAL_NOTURNO_HORAS'])) {
                                                        $tempos[] = '<span class="badge bg-warning text-dark me-1"><i class="bi bi-hourglass-split"></i> Adicional Noturno: ' . htmlspecialchars($inconsistencia['ADICIONAL_NOTURNO_HORAS']) . '</span>';
                                                    }
                                                    if (!empty($inconsistencia['QTDHOR']) && $inconsistencia['QTDHOR'] > 0) {
                                                        $badge_class = 'bg-info';
                                                        if ($inconsistencia['CODSIT'] == 103) $badge_class = 'bg-danger text-white';
                                                        if ($inconsistencia['CODSIT'] == 15) $badge_class = 'badge-falta';
                                                        if ($inconsistencia['CODSIT'] == 911) $badge_class = 'badge-abatido';
                                                        
                                                        $tempo_formatado = formatar_minutos_para_horas($inconsistencia['QTDHOR']);
                                                        $tempos[] = '<span class="badge ' . $badge_class . ' me-1"><i class="bi bi-hourglass-split"></i> ' . $inconsistencia['TIPO_SITUACAO'] . ': ' . $tempo_formatado . '</span>';
                                                    }
                                                    
                                                    if (!empty($tempos)): ?>
                                                        <?= implode('', $tempos) ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>