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

// Usar o usuário original para logs (quem realmente fez o ajuste)
$user_original = isset($_SESSION['user_original']) ? $_SESSION['user_original'] : $_SESSION['user'];
$user = htmlspecialchars(strstr($user_original, '@', true));
$dataAtual = date('d/m/Y');

$obsjus = 'Ajustado por ' . $user_original . ' em  ' . $dataAtual;

// PROCESSAMENTO DE POST (antes de qualquer saída HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'justificar_extra') {
    require_once 'conn.php'; // Garante conexão
    $conn = conectar_db();
    
    $numemp = $_POST['numemp'];
    $numcra = $_POST['numcra']; 
    $nomfun = $_POST['nomfun'];
    $usuche = $_POST['usuche'];
    $datjus = $_POST['datjus'];
    $qtdhor = $_POST['qtdhor'];
    $desjus = $_POST['desjus'];
    $obsavu = $_POST['obsavu'];
    
    // Inserir justificativa na tabela Usu_tJusExt
    $sql_insert = "INSERT INTO VETORH.Usu_tJusExt (
        USU_NUMEMP, USU_NUMCRA, USU_NOMFUN, USU_USUCHE, USU_DATJUS, USU_QTDHOR, USU_DESJUS, USU_OBSAVU
    ) VALUES (
        :numemp, :numcra, :nomfun, :usuche, TO_DATE(:datjus, 'DD/MM/YYYY'), :qtdhor, :desjus, :obsavu
    )";
    
    $stmt_insert = oci_parse($conn, $sql_insert);
    oci_bind_by_name($stmt_insert, ':numemp', $numemp);
    oci_bind_by_name($stmt_insert, ':numcra', $numcra);
    oci_bind_by_name($stmt_insert, ':nomfun', $nomfun);
    oci_bind_by_name($stmt_insert, ':usuche', $usuche);
    oci_bind_by_name($stmt_insert, ':datjus', $datjus);
    oci_bind_by_name($stmt_insert, ':qtdhor', $qtdhor);
    oci_bind_by_name($stmt_insert, ':desjus', $desjus);
    oci_bind_by_name($stmt_insert, ':obsavu', $obsavu);
    
    if (!oci_execute($stmt_insert)) {
        $e = oci_error($stmt_insert);
        $_SESSION['mensagem'] = exibir_mensagem('Erro ao inserir justificativa: ' . $e['message'], 'danger');
    } else {
        $_SESSION['mensagem'] = exibir_mensagem('Justificativa de hora extra registrada com sucesso!', 'success');
    }
    
    // Redireciona mantendo o filtro de data
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
    <title>Gerenciamento de Horas Extras</title>
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
                <span class="fw-bold" style="font-size:1.25rem;">Horas Extras - GF</span>
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

                    // Consulta aos dados
                    $conn = conectar_db();
                    $numcad = buscarNumcadPorLogin($conn);
                    $datacc = isset($_GET['datacc']) ? formatar_data_para_ddmmyyyy($_GET['datacc']) : date('d/m/Y');

                    // Query específica para horas extras - buscando situações 301, 313, 303, 305, 311
                    $sql = "SELECT 
                                R034FUN.NUMEMP,
                                R034FUN.TIPCOL,
                                R034FUN.NUMCAD,
                                R034FUN.NUMCRA,
                                R034FUN.NOMFUN,
                                R024CAR.TITCAR,
                                TO_CHAR(R070ACC.DATACC, 'DD/MM/YYYY') AS DATACC,
                                SUM(R066SIT.QTDHOR) AS TOTAL_HORAS_EXTRAS,
                                COUNT(DISTINCT R066SIT.CODSIT) AS QTD_TIPOS_EXTRAS,
                                LISTAGG(DISTINCT
                                    CASE R066SIT.CODSIT
                                        WHEN 301 THEN 'Horas 50%'
                                        WHEN 313 THEN 'Horas 70%'
                                        WHEN 303 THEN 'Horas 75%'
                                        WHEN 305 THEN 'Horas 100% DSR'
                                        WHEN 311 THEN 'Horas 100% Feriado'
                                        ELSE 'Outro'
                                    END, ', '
                                ) WITHIN GROUP (ORDER BY R066SIT.CODSIT) AS TIPOS_EXTRAS,
                                COUNT(*) AS TOTAL_REGISTROS
                            FROM VETORH.R080SUB
                            INNER JOIN VETORH.R034FUN
                                ON R080SUB.NUMLOC = R034FUN.NUMLOC
                            AND R034FUN.NUMEMP = R080SUB.NUMEMP
                            INNER JOIN VETORH.usu_tlocenc LOC
                                ON LOC.Usu_CodLen = R034FUN.Usu_CodLen
                            INNER JOIN VETORH.R024CAR
                                ON R034FUN.CODCAR = R024CAR.CODCAR
                            INNER JOIN VETORH.R070ACC
                                ON R034FUN.NUMEMP = R070ACC.NUMEMP
                            AND R034FUN.NUMCRA = R070ACC.NUMCRA
                            AND R070ACC.DATACC = TO_DATE(:datacc, 'DD/MM/YYYY')
                            INNER JOIN VETORH.R066SIT
                                ON R066SIT.NUMEMP = R034FUN.NUMEMP
                            AND R066SIT.TIPCOL = R034FUN.TIPCOL
                            AND R066SIT.NUMCAD = R034FUN.NUMCAD
                            AND R066SIT.DATAPU = R070ACC.DATACC
                            WHERE LOC.Usu_NumCad = $numcad
                            AND R066SIT.CODSIT IN (301, 313, 303, 305, 311)
                            AND R034FUN.SITAFA <> 7
                            AND R034FUN.TIPCON <> 6
                            GROUP BY R034FUN.NUMEMP, R034FUN.TIPCOL, R034FUN.NUMCAD, R034FUN.NUMCRA, 
                                     R034FUN.NOMFUN, R024CAR.TITCAR, R070ACC.DATACC
                            ORDER BY R034FUN.NUMCRA";

                    $stmt = oci_parse($conn, $sql);
                    oci_bind_by_name($stmt, ':datacc', $datacc);
                    oci_execute($stmt);
                    ?>

                    <div>
                        <?php
                        if (isset($_SESSION['mensagem'])) {
                            echo $_SESSION['mensagem'];
                            unset($_SESSION['mensagem']);
                        }
                        ?>

                        <h5 class="mb-3"><i class="bi bi-clock"></i> Horas Extras - <?= htmlspecialchars($datacc) ?></h5>

                        <?php
                        // Exibir registros consolidados por colaborador
                        $colaboradores = [];
                        while ($row = oci_fetch_array($stmt, OCI_ASSOC + OCI_RETURN_NULLS)) {
                            $colaboradores[] = $row;
                        }

                        if (!empty($colaboradores)) {
                            echo "<table class='table table-striped table-hover'>";
                            echo "<thead class='table-dark'>";
                            echo "<tr><th>Crachá</th><th>Nome</th><th>Cargo</th><th>Data</th><th>Total Horas Extras</th><th>Tipos de Extras</th><th>Registros</th><th>Ação</th></tr>";
                            echo "</thead>";
                            echo "<tbody>";
                            
                            foreach ($colaboradores as $colab) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($colab['NUMCRA']) . "</td>";
                                echo "<td>" . htmlspecialchars($colab['NOMFUN']) . "</td>";
                                echo "<td>" . htmlspecialchars($colab['TITCAR']) . "</td>";
                                echo "<td>" . htmlspecialchars($colab['DATACC']) . "</td>";
                                // Converter minutos para formato HH:MM
                                $total_minutos = $colab['TOTAL_HORAS_EXTRAS'];
                                $horas = floor($total_minutos / 60);
                                $minutos = $total_minutos % 60;
                                $horas_formatadas = sprintf('%02d:%02d', $horas, $minutos);
                                echo "<td><span class='badge bg-info'>" . htmlspecialchars($horas_formatadas) . "</span></td>";
                                echo "<td><small>" . htmlspecialchars($colab['TIPOS_EXTRAS']) . "</small></td>";
                                echo "<td><span class='badge bg-secondary'>" . htmlspecialchars($colab['TOTAL_REGISTROS']) . "</span></td>";
                                echo "<td>";
                                echo "<button type='button' class='btn btn-warning btn-sm' data-bs-toggle='modal' data-bs-target='#modalJustificativa' 
                                      onclick=\"abrirModalJustificativa('" . htmlspecialchars($colab['NUMEMP']) . "', '" . 
                                      htmlspecialchars($colab['NUMCRA']) . "', '" . 
                                      htmlspecialchars($colab['NOMFUN']) . "', '" . 
                                      htmlspecialchars($user) . "', '" . 
                                      htmlspecialchars($colab['DATACC']) . "', '" . 
                                      htmlspecialchars($colab['TOTAL_HORAS_EXTRAS']) . "', '" . 
                                      htmlspecialchars($horas_formatadas) . "')\">";
                                echo "<i class='bi bi-pencil'></i> Justificar";
                                echo "</button>";
                                echo "</td>";
                                echo "</tr>";
                            }
                            
                            echo "</tbody>";
                            echo "</table>";
                        } else {
                            echo "<div class='alert alert-info text-center mt-3'>Não há horas extras para a data selecionada.</div>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Justificativa -->
    <div class="modal fade" id="modalJustificativa" tabindex="-1" aria-labelledby="modalJustificativaLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalJustificativaLabel">Justificar Hora Extra</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="acao" value="justificar_extra">
                        <input type="hidden" name="numemp" id="modal_numemp">
                        <input type="hidden" name="numcra" id="modal_numcra">
                        <input type="hidden" name="nomfun" id="modal_nomfun">
                        <input type="hidden" name="usuche" id="modal_usuche">
                        <input type="hidden" name="datjus" id="modal_datjus">
                        <input type="hidden" name="qtdhor" id="modal_qtdhor">
                        
                        <div class="mb-3">
                            <label class="form-label">Colaborador:</label>
                            <input type="text" class="form-control" id="modal_colaborador_display" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Data:</label>
                            <input type="text" class="form-control" id="modal_data_display" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Quantidade de Horas:</label>
                            <input type="text" class="form-control" id="modal_qtdhor_display" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="desjus" class="form-label">Justificativa:</label>
                            <textarea class="form-control" id="desjus" name="desjus" rows="3" required placeholder="Descreva o motivo da hora extra..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="obsavu" class="form-label">Observações Adicionais:</label>
                            <textarea class="form-control" id="obsavu" name="obsavu" rows="2" placeholder="Observações adicionais (opcional)..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar Justificativa</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function abrirModalJustificativa(numemp, numcra, nomfun, usuche, datjus, qtdhor, qtdhor_formatado) {
            document.getElementById('modal_numemp').value = numemp;
            document.getElementById('modal_numcra').value = numcra;
            document.getElementById('modal_nomfun').value = nomfun;
            document.getElementById('modal_usuche').value = usuche;
            document.getElementById('modal_datjus').value = datjus;
            document.getElementById('modal_qtdhor').value = qtdhor;
            
            document.getElementById('modal_colaborador_display').value = numcra + ' - ' + nomfun;
            document.getElementById('modal_data_display').value = datjus;
            document.getElementById('modal_qtdhor_display').value = qtdhor_formatado;
        }
    </script>
</body>
</html>
