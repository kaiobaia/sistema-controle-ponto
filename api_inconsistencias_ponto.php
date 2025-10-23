<?php

/**
 * API para consulta de Inconsistências de Ponto
 * 
 * Parâmetros esperados:
 * - telefone: Número do telefone do colaborador (obrigatório)
 * 
 * Exemplo de uso:
 * GET: api_inconsistencias_ponto.php?telefone=11999999999
 * POST: form-data ou json com o campo telefone
 */

// Configurações de resposta JSON
header('Content-Type: application/json; charset=utf-8');

// Permite requisições de qualquer origem (CORS) - ajuste conforme necessário
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Configurações da API WhatsApp
$WHATSAPP_API_URL = 'https://chat-82api.jetsalesbrasil.com/v1/api/external/ace32b90-08b2-4162-b80b-7309749b0226';
$WHATSAPP_TOKEN = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ0ZW5hbnRJZCI6MSwicHJvZmlsZSI6ImFkbWluIiwic2Vzc2lvbklkIjo4LCJjaGFubmVsVHlwZSI6IndoYXRzYXBwIiwiaWF0IjoxNzU5NDkyNDE5LCJleHAiOjE4MjI1NjQ0MTl9.MFihTdRgwsjXiUWy7Vdmiex5__RdjH5Da7G2EcD-lDM';

// Função de conexão com o banco de dados
function conectar_db()
{
    $conn = oci_connect('vetorh', 'rec07gf7', '192.168.50.11/senior');
    if (!$conn) {
        $e = oci_error();
        return ['error' => true, 'message' => 'Erro de conexão com banco de dados: ' . $e['message']];
    }
    return $conn;
}

// Função auxiliar para buscar colaborador por telefone
function buscar_colaborador_por_telefone($conn, $telefone)
{
    $sql_colaborador = "SELECT f.numcad, f.numemp, f.numcra, f.nomfun, f.tipcol, c.dddtel, c.numtel
                       FROM vetorh.r034fun f
                       INNER JOIN vetorh.r034cpl c ON f.numcad = c.numcad AND f.numemp = c.numemp
                       WHERE c.dddtel || c.numtel = :telefone
                       AND f.tipcon <> 6
                       AND f.sitafa <> 7";

    $stmt_colab = oci_parse($conn, $sql_colaborador);

    if (!$stmt_colab) {
        return false;
    }

    oci_bind_by_name($stmt_colab, ':telefone', $telefone);

    if (!oci_execute($stmt_colab)) {
        oci_free_statement($stmt_colab);
        return false;
    }

    $colaborador = oci_fetch_array($stmt_colab, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stmt_colab);

    return $colaborador;
}

// Função para buscar inconsistências de ponto por telefone
function buscar_inconsistencias_por_telefone($conn, $telefone)
{
    try {
        // Tentar diferentes variações do telefone
        $telefones_para_tentar = [];

        // Telefone original
        $telefones_para_tentar[] = $telefone;

        // Se for número de 10 dígitos (sem o 9), adicionar o 9
        if (strlen($telefone) == 10) {
            $telefone_com_9 = substr($telefone, 0, 2) . '9' . substr($telefone, 2);
            $telefones_para_tentar[] = $telefone_com_9;
        }

        // Se for número de 11 dígitos (com o 9), tentar sem o 9
        if (strlen($telefone) == 11 && substr($telefone, 2, 1) == '9') {
            $telefone_sem_9 = substr($telefone, 0, 2) . substr($telefone, 3);
            $telefones_para_tentar[] = $telefone_sem_9;
        }

        // Remover duplicatas
        $telefones_para_tentar = array_unique($telefones_para_tentar);

        $colaborador = null;
        foreach ($telefones_para_tentar as $tel_teste) {
            $colaborador = buscar_colaborador_por_telefone($conn, $tel_teste);
            if ($colaborador) {
                break;
            }
        }

        if (!$colaborador) {
            return [
                'error' => true,
                'message' => 'Colaborador não encontrado com o telefone informado: ' . $telefone . ' (testadas variações: ' . implode(', ', $telefones_para_tentar) . ')'
            ];
        }

        // Busca as inconsistências do colaborador nos últimos 30 dias
        $sql_inconsistencias = "SELECT DISTINCT R034FUN.NUMEMP,
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
                                          AND R066.CODSIT IN (103, 15) 
                                        FETCH FIRST 1 ROWS ONLY) AS QTDHOR,
                                       (SELECT R066.CODSIT
                                        FROM VETORH.R066SIT R066
                                        WHERE R066.NUMEMP = R034FUN.NUMEMP
                                          AND R066.TIPCOL = R034FUN.TIPCOL
                                          AND R066.NUMCAD = R034FUN.NUMCAD
                                          AND R066.DATAPU = R070ACC.DATACC
                                          AND R066.CODSIT IN (103, 15) 
                                        FETCH FIRST 1 ROWS ONLY) AS CODSIT
                                FROM VETORH.R034FUN
                                LEFT JOIN VETORH.R070ACC
                                  ON R034FUN.NUMEMP = R070ACC.NUMEMP
                                 AND R034FUN.NUMCRA = R070ACC.NUMCRA
                                 AND R070ACC.DATAPU >= TRUNC(SYSDATE) - 30
                                 AND R070ACC.DATAPU <= TRUNC(SYSDATE)
                                INNER JOIN VETORH.R024CAR
                                  ON R034FUN.CODCAR = R024CAR.CODCAR
                                WHERE R034FUN.NUMCAD = :numcad
                                  AND R034FUN.NUMEMP = :numemp
                                  AND R034FUN.TIPCOL = :tipcol
                                  AND R070ACC.ORIACC <> 'G'
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
                                                   AND JUS.DATACC = R070ACC.DATACC
                                                   AND JUS.CODJMA = 70)
                                UNION
                                SELECT sit.numemp,
                                       sit.tipcol,
                                       sit.numcad,
                                       fun.numcra,
                                       TO_CHAR(sit.datapu, 'DD/MM/YYYY') AS DATACC,
                                       0 AS HORACC,
                                       fun.nomfun,
                                       car.titcar,
                                       sit.qtdhor,
                                       sit.codsit
                                FROM VETORH.r066sit sit
                                INNER JOIN VETORH.r034fun fun
                                  ON fun.numemp = sit.numemp
                                 AND fun.tipcol = sit.tipcol
                                 AND fun.numcad = sit.numcad
                                INNER JOIN VETORH.r024car car
                                  ON fun.codcar = car.codcar
                                WHERE fun.numcad = :numcad
                                  AND fun.numemp = :numemp
                                  AND fun.tipcol = :tipcol
                                  AND sit.codsit = 15
                                  AND sit.datapu >= TRUNC(SYSDATE) - 30
                                  AND sit.datapu <= TRUNC(SYSDATE)
                                  AND NOT EXISTS (SELECT 1
                                                 FROM VETORH.r070acc acc
                                                 WHERE acc.DATAPU = sit.datapu
                                                   AND acc.numemp = sit.numemp
                                                   AND acc.tipcol = sit.tipcol
                                                   AND acc.numcad = sit.numcad)
                                ORDER BY 5 DESC, 6";

        $stmt_inconsistencias = oci_parse($conn, $sql_inconsistencias);

        if (!$stmt_inconsistencias) {
            return [
                'error' => true,
                'message' => 'Erro ao preparar consulta de inconsistências'
            ];
        }

        oci_bind_by_name($stmt_inconsistencias, ':numcad', $colaborador['NUMCAD']);
        oci_bind_by_name($stmt_inconsistencias, ':numemp', $colaborador['NUMEMP']);
        oci_bind_by_name($stmt_inconsistencias, ':tipcol', $colaborador['TIPCOL']);

        if (!oci_execute($stmt_inconsistencias)) {
            oci_free_statement($stmt_inconsistencias);
            return [
                'error' => true,
                'message' => 'Erro ao executar consulta de inconsistências'
            ];
        }

        $inconsistencias = [];
        $datas_agrupadas = [];
        
        while ($row = oci_fetch_array($stmt_inconsistencias, OCI_ASSOC + OCI_RETURN_NULLS)) {
            $data = $row['DATACC'];
            
            if (!isset($datas_agrupadas[$data])) {
                $datas_agrupadas[$data] = [
                    'data' => $data,
                    'empresa' => $row['NUMEMP'],
                    'marcacoes' => [],
                    'atraso_minutos' => isset($row['QTDHOR']) ? (int)$row['QTDHOR'] : 0,
                    'tipo_inconsistencia' => isset($row['CODSIT']) ? (int)$row['CODSIT'] : null,
                    'descricao_inconsistencia' => ''
                ];
                
                // Definir descrição da inconsistência
                if ($row['CODSIT'] == 103) {
                    $datas_agrupadas[$data]['descricao_inconsistencia'] = 'Atraso';
                } elseif ($row['CODSIT'] == 15) {
                    $datas_agrupadas[$data]['descricao_inconsistencia'] = 'Sem marcações';
                }
            }
            
            // Adicionar marcação se houver
            if (isset($row['HORACC']) && $row['HORACC'] > 0) {
                $horas = floor($row['HORACC'] / 60);
                $minutos = $row['HORACC'] % 60;
                $horario_formatado = sprintf('%02d:%02d', $horas, $minutos);
                $datas_agrupadas[$data]['marcacoes'][] = $horario_formatado;
            }
        }
        
        // Converter para array indexado
        foreach ($datas_agrupadas as $data_info) {
            $inconsistencias[] = $data_info;
        }

        oci_free_statement($stmt_inconsistencias);

        return [
            'error' => false,
            'data' => $inconsistencias,
            'colaborador' => $colaborador,
            'tem_inconsistencias' => !empty($inconsistencias)
        ];
    } catch (Exception $e) {
        return [
            'error' => true,
            'message' => 'Erro ao buscar inconsistências: ' . $e->getMessage()
        ];
    }
}

// Função para formatar mensagem do WhatsApp
function formatarMensagemInconsistencias($inconsistencias, $colaborador)
{
    $mensagem = "🏢 *GRUPO FARIAS*\n\n";
    $mensagem .= "Olá " . $colaborador['NOMFUN'] . "!\n\n";
    $mensagem .= "⚠️ *INCONSISTÊNCIAS DE PONTO*\n\n";

    // Se não houver inconsistências
    if (empty($inconsistencias)) {
        $mensagem .= "✅ *Boa notícia!*\n";
        $mensagem .= "Não há inconsistências de ponto nos últimos 30 dias.\n\n";
        $mensagem .= "📋 *Informações:*\n";
        $mensagem .= "• Matrícula: " . $colaborador['NUMCRA'] . "\n\n";
        $mensagem .= "Continue mantendo suas marcações em dia! 👏\n\n";
        $mensagem .= "_Mensagem enviada automaticamente pelo sistema._";
        return $mensagem;
    }

    $total_inconsistencias = count($inconsistencias);
    $mensagem .= "📅 *Foram encontradas " . $total_inconsistencias . " inconsistência(s) nos últimos 30 dias:*\n\n";

    foreach ($inconsistencias as $index => $inc) {
        $numero = $index + 1;
        $mensagem .= "⚠️ *Inconsistência " . $numero . ":*\n";
        $mensagem .= "• 📅 Data: " . $inc['data'] . "\n";
        $mensagem .= "• ❌ Tipo: " . $inc['descricao_inconsistencia'] . "\n";
        
        if ($inc['atraso_minutos'] > 0) {
            $horas = floor($inc['atraso_minutos'] / 60);
            $minutos = $inc['atraso_minutos'] % 60;
            $atraso_formatado = sprintf('%02d:%02d', $horas, $minutos);
            $mensagem .= "• ⏱️ Atraso: " . $atraso_formatado . "\n";
        }
        
        if (!empty($inc['marcacoes'])) {
            $mensagem .= "• 🕐 Marcações: " . implode(', ', $inc['marcacoes']) . "\n";
        } else {
            $mensagem .= "• 🕐 Marcações: Nenhuma\n";
        }
        
        $mensagem .= "\n";
    }

    $mensagem .= "📋 *Informações:*\n";
    $mensagem .= "• Matrícula: " . $colaborador['NUMCRA'] . "\n\n";

    $mensagem .= "❓ Para regularizar suas inconsistências, entre em contato com o RH.\n\n";
    $mensagem .= "_Mensagem enviada automaticamente pelo sistema._";

    return $mensagem;
}

// Função para enviar via WhatsApp
function enviarWhatsApp($numero, $mensagem, $mediaUrl = '')
{
    global $WHATSAPP_API_URL, $WHATSAPP_TOKEN;

    $data = [
        'number' => $numero,
        'body' => $mensagem,
        'mediaUrl' => $mediaUrl,
        'externalKey' => 'INCONSISTENCIAS_' . uniqid()
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $WHATSAPP_API_URL . '?token=' . $WHATSAPP_TOKEN);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("Erro cURL: " . $error);
    }

    if ($http_code !== 200) {
        throw new Exception("Erro na API WhatsApp. Código: " . $http_code);
    }

    return json_decode($response, true);
}

// Processamento da requisição
try {
    // Aceitar tanto GET quanto POST
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $telefone = $_GET['telefone'] ?? null;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $telefone = $input['telefone'] ?? null;
    } else {
        throw new Exception("Método não permitido. Use GET ou POST.");
    }

    // Validar parâmetro obrigatório
    if (!$telefone) {
        throw new Exception("Parâmetro obrigatório: telefone");
    }

    // Limpar telefone (remover caracteres não numéricos)
    $telefone = preg_replace('/[^0-9]/', '', $telefone);

    // Remove o código do país 55 se presente
    if (strlen($telefone) >= 12 && substr($telefone, 0, 2) === '55') {
        $telefone = substr($telefone, 2);
    }

    // Conecta ao banco
    $conn = conectar_db();

    if (is_array($conn)) {
        // Se retornou array, é um erro
        echo json_encode($conn, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        http_response_code(500);
        exit;
    }

    // Busca as inconsistências por telefone
    $resultado = buscar_inconsistencias_por_telefone($conn, $telefone);

    // Fecha a conexão
    oci_close($conn);

    // Define o código HTTP apropriado
    if (isset($resultado['error']) && $resultado['error']) {
        http_response_code(500);
        echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(200); // Sempre 200 quando a API funciona corretamente

        // Adicionar mensagem formatada para WhatsApp
        $resultado['mensagem_whatsapp'] = formatarMensagemInconsistencias($resultado['data'], $resultado['colaborador']);

        // Enviar via WhatsApp
        try {
            $telefone_whatsapp = '55' . $resultado['colaborador']['DDDTEL'] . $resultado['colaborador']['NUMTEL'];
            $whatsapp_response = enviarWhatsApp($telefone_whatsapp, $resultado['mensagem_whatsapp']);
            $resultado['whatsapp'] = [
                'numero' => $telefone_whatsapp,
                'mensagem_enviada' => true,
                'response' => $whatsapp_response
            ];
        } catch (Exception $e) {
            $resultado['whatsapp'] = [
                'numero' => $telefone_whatsapp ?? $telefone,
                'mensagem_enviada' => false,
                'error' => $e->getMessage()
            ];
        }

        // Calcular estatísticas
        $total_inconsistencias = count($resultado['data']);

        // Resposta de sucesso padronizada
        $mensagem_resposta = $resultado['tem_inconsistencias'] 
            ? 'Inconsistências de ponto consultadas e enviadas via WhatsApp' 
            : 'Consultado - Não há inconsistências de ponto nos últimos 30 dias';

        echo json_encode([
            'success' => true,
            'message' => $mensagem_resposta,
            'data' => [
                'funcionario' => [
                    'nome' => $resultado['colaborador']['NOMFUN'],
                    'matricula' => $resultado['colaborador']['NUMCRA']
                ],
                'tem_inconsistencias' => $resultado['tem_inconsistencias'],
                'inconsistencias' => [
                    'total' => $total_inconsistencias,
                    'periodo_consultado' => 'Últimos 30 dias',
                    'detalhes' => $resultado['data']
                ],
                'whatsapp' => [
                    'numero' => $resultado['whatsapp']['numero'],
                    'mensagem_enviada' => $resultado['whatsapp']['mensagem_enviada'],
                    'response' => $resultado['whatsapp']['response'] ?? $resultado['whatsapp']['error'] ?? null
                ]
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => true,
        'message' => 'Erro ao processar requisição: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}



