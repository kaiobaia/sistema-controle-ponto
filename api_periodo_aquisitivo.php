<?php

/**
 * API para consulta de Período Aquisitivo de Férias
 * 
 * Parâmetros esperados:
 * - telefone: Número do telefone do colaborador (obrigatório)
 * 
 * Exemplo de uso:
 * GET: api_periodo_aquisitivo.php?telefone=11999999999
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
    $sql_colaborador = "SELECT f.numcad, f.numemp, f.numcra, f.nomfun, c.dddtel, c.numtel
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

// Função para buscar período aquisitivo por telefone
function buscar_periodo_aquisitivo_por_telefone($conn, $telefone)
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

        // Busca férias programadas do colaborador
        $sql_periodo = "SELECT p.numemp,
                               p.numcad,
                               p.qtddir,
                               p.qtddeb,
                               p.qtdsld,
                               p.sitper,
                               f.nomfun,
                               e.nomemp,
                               TO_CHAR(prg.prgdat, 'DD/MM/YYYY') as data_programada,
                               TO_CHAR(prg.prgdat + prg.prgdfe - 1,'DD/MM/YYYY') as fim_data_programada
                        FROM vetorh.r040per p
                        INNER JOIN vetorh.r034fun f ON p.numcad = f.numcad AND p.numemp = f.numemp
                        INNER JOIN vetorh.r030emp e ON p.numemp = e.numemp
                        INNER JOIN vetorh.r040prg prg ON prg.numemp = p.numemp AND prg.tipcol = p.tipcol AND prg.numcad = p.numcad AND prg.iniper = p.iniper
                        WHERE p.numcad = :numcad
                        AND p.numemp = :numemp
                        AND p.qtddeb < 30 
                        AND prg.prgdat >= SYSDATE
                        AND p.sitper = 0
                        UNION
                        SELECT p.numemp,
                                p.numcad,
                                p.qtddir,
                                p.qtddeb,
                                p.qtdsld,
                                p.sitper,
                                f.nomfun,
                                e.nomemp,
                                TO_CHAR(prg.inifer, 'DD/MM/YYYY') as data_programada,
                                TO_CHAR(prg.inifer + prg.diafer - 1, 'DD/MM/YYYY') as fim_data_programada
                            FROM vetorh.r040per p
                            INNER JOIN vetorh.r034fun f
                                ON p.numcad = f.numcad
                            AND p.numemp = f.numemp
                            INNER JOIN vetorh.r030emp e
                                ON p.numemp = e.numemp
                            INNER JOIN vetorh.r040fem prg
                                ON prg.numemp = p.numemp
                            AND prg.tipcol = p.tipcol
                            AND prg.numcad = p.numcad
                            AND prg.iniper = p.iniper
                            WHERE p.numcad = :numcad
                            AND p.numemp = :numemp
                            AND p.qtddeb <= 30
                            AND prg.inifer >= SYSDATE
                            ORDER BY 3";

        $stmt_periodo = oci_parse($conn, $sql_periodo);

        if (!$stmt_periodo) {
            return [
                'error' => true,
                'message' => 'Erro ao preparar consulta de período aquisitivo'
            ];
        }

        oci_bind_by_name($stmt_periodo, ':numcad', $colaborador['NUMCAD']);
        oci_bind_by_name($stmt_periodo, ':numemp', $colaborador['NUMEMP']);

        if (!oci_execute($stmt_periodo)) {
            oci_free_statement($stmt_periodo);
            return [
                'error' => true,
                'message' => 'Erro ao executar consulta de período aquisitivo'
            ];
        }

        $periodos = [];
        while ($row = oci_fetch_array($stmt_periodo, OCI_ASSOC + OCI_RETURN_NULLS)) {
            $periodos[] = [
                'empresa' => [
                    'codigo' => $row['NUMEMP'],
                    'nome' => $row['NOMEMP']
                ],
                'funcionario' => [
                    'matricula' => $row['NUMCAD'],
                    'nome' => $row['NOMFUN']
                ],
                'periodo_aquisitivo' => [
                    'dias_direito' => $row['QTDDIR'],
                    'dias_debitados' => $row['QTDDEB'],
                    'dias_saldo' => $row['QTDSLD'],
                    'situacao' => $row['SITPER']
                ],
                'ferias_programadas' => [
                    'data_inicio' => $row['DATA_PROGRAMADA'] ?? null,
                    'data_fim' => $row['FIM_DATA_PROGRAMADA'] ?? null
                ]
            ];
        }

        oci_free_statement($stmt_periodo);

        return [
            'error' => false,
            'data' => $periodos,
            'colaborador' => $colaborador,
            'tem_ferias_programadas' => !empty($periodos)
        ];
    } catch (Exception $e) {
        return [
            'error' => true,
            'message' => 'Erro ao buscar período aquisitivo: ' . $e->getMessage()
        ];
    }
}


// Função para calcular duração das férias baseada nas datas
function calcularDuracaoFerias($data_inicio, $data_fim)
{
    try {
        // Converter datas do formato DD/MM/YYYY para DateTime
        $inicio = DateTime::createFromFormat('d/m/Y', $data_inicio);
        $fim = DateTime::createFromFormat('d/m/Y', $data_fim);
        
        if (!$inicio || !$fim) {
            return 0;
        }
        
        // Calcular diferença em dias
        $diferenca = $fim->diff($inicio);
        $dias = $diferenca->days + 1; // +1 para incluir o dia de início
        
        return $dias;
    } catch (Exception $e) {
        return 0;
    }
}

// Função para formatar mensagem do WhatsApp
function formatarMensagemFerias($periodos, $colaborador)
{
    $mensagem = "🏢 *GRUPO FARIAS*\n\n";
    $mensagem .= "Olá " . $colaborador['NOMFUN'] . "!\n\n";
    $mensagem .= "🏖️ *FÉRIAS PROGRAMADAS*\n\n";

    // Se não houver férias programadas
    if (empty($periodos)) {
        $mensagem .= "📅 *Informação:*\n";
        $mensagem .= "Não há férias programadas para você no momento.\n\n";
        $mensagem .= "📋 *Informações:*\n";
        $mensagem .= "• Matrícula: " . $colaborador['NUMCRA'] . "\n";
        $mensagem .= "• Empresa: " . $colaborador['NUMEMP'] . "\n\n";
        $mensagem .= "❓ Para agendar suas férias, entre em contato com o RH.\n\n";
        $mensagem .= "_Mensagem enviada automaticamente pelo sistema._";
        return $mensagem;
    }

    $total_periodos = count($periodos);
    $total_dias_ferias = 0;

    // Calcular total de dias de férias baseado nas datas
    foreach ($periodos as $periodo) {
        $duracao = calcularDuracaoFerias($periodo['ferias_programadas']['data_inicio'], $periodo['ferias_programadas']['data_fim']);
        $total_dias_ferias += $duracao;
    }

    if ($total_periodos == 1) {
        $periodo = $periodos[0];
        $duracao = calcularDuracaoFerias($periodo['ferias_programadas']['data_inicio'], $periodo['ferias_programadas']['data_fim']);
        $mensagem .= "🎉 *Suas Férias Programadas:*\n";
        $mensagem .= "• 🗓️ *Início das Férias:* " . $periodo['ferias_programadas']['data_inicio'] . "\n";
        $mensagem .= "• 🗓️ *Fim das Férias:* " . $periodo['ferias_programadas']['data_fim'] . "\n";
        $mensagem .= "• 📅 *Duração:* " . $duracao . " dias\n\n";
        $mensagem .= "💡 *Informações Importantes:*\n";
        $mensagem .= "• Suas férias já estão confirmadas no sistema\n";
        $mensagem .= "• Prepare-se para o período de descanso\n";
        $mensagem .= "• Em caso de dúvidas, procure o RH\n\n";
    } else {
        $mensagem .= "🎉 *Você tem " . $total_periodos . " período(s) de férias programadas:*\n\n";

        foreach ($periodos as $index => $periodo) {
            $duracao = calcularDuracaoFerias($periodo['ferias_programadas']['data_inicio'], $periodo['ferias_programadas']['data_fim']);
            $mensagem .= "🏖️ *Férias " . ($index + 1) . ":*\n";
            $mensagem .= "• 🗓️ *Início:* " . $periodo['ferias_programadas']['data_inicio'] . "\n";
            $mensagem .= "• 🗓️ *Fim:* " . $periodo['ferias_programadas']['data_fim'] . "\n";
            $mensagem .= "• 📅 *Duração:* " . $duracao . " dias\n\n";
        }
        
        $mensagem .= "📊 *Resumo Geral:*\n";
        $mensagem .= "• Total de períodos programados: " . $total_periodos . "\n";
        $mensagem .= "• *Total de dias de férias: " . $total_dias_ferias . " dias*\n";
        $mensagem .= "• Todas as férias estão confirmadas no sistema\n\n";
    }

    $mensagem .= "📋 *Informações:*\n";
    $mensagem .= "• Matrícula: " . $colaborador['NUMCRA'] . "\n";
    $mensagem .= "• Empresa: " . $periodos[0]['empresa']['nome'] . "\n\n";
    $mensagem .= "❓ Para maiores informações sobre suas férias, entre em contato com o RH.\n\n";
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
        'externalKey' => 'FERIAS_' . uniqid()
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

    // Busca o período aquisitivo por telefone
    $resultado = buscar_periodo_aquisitivo_por_telefone($conn, $telefone);

    // Fecha a conexão
    oci_close($conn);

    // Define o código HTTP apropriado
    if (isset($resultado['error']) && $resultado['error']) {
        http_response_code(500);
        echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(200); // Sempre 200 quando a API funciona corretamente

        // Adicionar mensagem formatada para WhatsApp
        $resultado['mensagem_whatsapp'] = formatarMensagemFerias($resultado['data'], $resultado['colaborador']);

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
        $total_periodos = count($resultado['data']);
        $total_dias_disponiveis = 0;
        if ($total_periodos > 0) {
            foreach ($resultado['data'] as $periodo) {
                $duracao = calcularDuracaoFerias($periodo['ferias_programadas']['data_inicio'], $periodo['ferias_programadas']['data_fim']);
                $total_dias_disponiveis += $duracao;
            }
        }

        // Resposta de sucesso padronizada
        $mensagem_resposta = $resultado['tem_ferias_programadas'] 
            ? 'Férias programadas consultadas e enviadas via WhatsApp' 
            : 'Consultado - Não há férias programadas para este colaborador';

        echo json_encode([
            'success' => true,
            'message' => $mensagem_resposta,
            'data' => [
                'funcionario' => [
                    'nome' => $resultado['colaborador']['NOMFUN'],
                    'matricula' => $resultado['colaborador']['NUMCRA']
                ],
                'empresa' => [
                    'nome' => $resultado['data'][0]['empresa']['nome'] ?? 'N/A'
                ],
                'ferias_programadas' => [
                    'tem_ferias' => $resultado['tem_ferias_programadas'],
                    'total_periodos' => $total_periodos,
                    'total_dias_disponiveis' => $total_dias_disponiveis,
                    'periodos' => $resultado['data']
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
