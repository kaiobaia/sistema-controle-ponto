<?php
/**
 * API para consulta de Banco de Horas
 * 
 * Parâmetros esperados:
 * - telefone: Número do telefone do colaborador (obrigatório)
 * 
 * Exemplo de uso:
 * GET: api_banco_horas.php?telefone=11999999999
 * POST: form-data ou json com o campo telefone
 */

// Configurações de resposta JSON
header('Content-Type: application/json; charset=utf-8');

// Permite requisições de qualquer origem (CORS) - ajuste conforme necessário
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

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
    $sql_colaborador = "SELECT f.numcad, f.numemp, f.numcra, f.nomfun, c.dddtel, c.numtel, e.nomemp
                       FROM vetorh.r034fun f
                       INNER JOIN vetorh.r034cpl c ON f.numcad = c.numcad AND f.numemp = c.numemp
                       INNER JOIN vetorh.r030emp e ON f.numemp = e.numemp
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

// Função para formatar mensagem do WhatsApp
function formatarMensagemBancoHoras($dados_banco, $colaborador)
{
    $saldo = $dados_banco['banco_horas']['saldo_formatado'];
    $tipo_saldo = $dados_banco['banco_horas']['tipo_saldo'];
    $status = $dados_banco['banco_horas']['status'];
    
    $mensagem = "🏢 *GRUPO FARIAS*\n\n";
    $mensagem .= "Olá " . $colaborador['NOMFUN'] . "!\n\n";
    $mensagem .= "⏰ *BANCO DE HORAS*\n\n";
    
    $mensagem .= "🕐 *Saldo Atual:*\n";
    $mensagem .= "• *" . $saldo . "* (" . $tipo_saldo . ")\n\n";
    
    if ($status === 'positivo' && $dados_banco['banco_horas']['saldo_decimal'] > 0) {
        $mensagem .= "✅ Você possui horas extras acumuladas!\n";
    } elseif ($status === 'positivo' && $dados_banco['banco_horas']['saldo_decimal'] == 0) {
        $mensagem .= "⚖️ Seu saldo está equilibrado (zero).\n";
        $mensagem .= "💡 Não há horas extras nem débitos no momento.\n\n";
    } else {
        $mensagem .= "⚠️ Você possui horas em débito.\n";
        $mensagem .= "💡 É necessário compensar essas horas trabalhando mais tempo.\n\n";
    }
    
    $mensagem .= "📋 *Informações:*\n";
    $mensagem .= "• Matrícula: " . $colaborador['NUMCRA'] . "\n";
    $mensagem .= "• Empresa: " . $dados_banco['empresa']['nome'] . "\n\n";
    
    $mensagem .= "❓ Para mais informações sobre banco de horas, entre em contato com o RH.\n\n";
    $mensagem .= "_Mensagem enviada automaticamente pelo sistema._";
    
    return $mensagem;
}

// Configurações da API WhatsApp
$WHATSAPP_API_URL = 'https://chat-82api.jetsalesbrasil.com/v1/api/external/ace32b90-08b2-4162-b80b-7309749b0226';
$WHATSAPP_TOKEN = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ0ZW5hbnRJZCI6MSwicHJvZmlsZSI6ImFkbWluIiwic2Vzc2lvbklkIjo4LCJjaGFubmVsVHlwZSI6IndoYXRzYXBwIiwiaWF0IjoxNzU5NDkyNDE5LCJleHAiOjE4MjI1NjQ0MTl9.MFihTdRgwsjXiUWy7Vdmiex5__RdjH5Da7G2EcD-lDM';

// Função para enviar via WhatsApp
function enviarWhatsApp($numero, $mensagem, $mediaUrl = '')
{
    global $WHATSAPP_API_URL, $WHATSAPP_TOKEN;

    $data = [
        'number' => $numero,
        'body' => $mensagem,
        'mediaUrl' => $mediaUrl,
        'externalKey' => 'BANCO_HORAS_' . uniqid()
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

// Função para buscar banco de horas por telefone
function buscar_banco_horas_por_telefone($conn, $telefone)
{
    try {
        // Tenta encontrar o colaborador com o telefone fornecido
        $colaborador = buscar_colaborador_por_telefone($conn, $telefone);
        
        // Se não encontrou, tenta com o 9 adicionado após o DDD (para qualquer DDD)
        if (!$colaborador && strlen($telefone) === 10) {
            $telefone_com_9 = substr($telefone, 0, 2) . '9' . substr($telefone, 2); // Adiciona 9 após o DDD
            $colaborador = buscar_colaborador_por_telefone($conn, $telefone_com_9);
        }
        
        // Se ainda não encontrou, tenta sem o 9 (removendo o 9 da posição 3)
        if (!$colaborador && strlen($telefone) === 10) {
            $telefone_sem_9 = substr($telefone, 0, 2) . substr($telefone, 3); // Remove o 9 da posição 3
            $colaborador = buscar_colaborador_por_telefone($conn, $telefone_sem_9);
        }
        
        // Se não encontrou, tenta sem o 9 (removendo o primeiro 9)
        if (!$colaborador && strlen($telefone) === 11 && substr($telefone, 0, 1) === '9') {
            $telefone_sem_9 = substr($telefone, 1);
            $colaborador = buscar_colaborador_por_telefone($conn, $telefone_sem_9);
        }
        
        if (!$colaborador) {
            $telefones_para_tentar = [$telefone];
            if (strlen($telefone) === 10) {
                $telefones_para_tentar[] = substr($telefone, 0, 2) . '9' . substr($telefone, 2);
                $telefones_para_tentar[] = substr($telefone, 0, 2) . substr($telefone, 3);
            }
            if (strlen($telefone) === 11 && substr($telefone, 0, 1) === '9') {
                $telefones_para_tentar[] = substr($telefone, 1);
            }
            
            return [
                'success' => true,
                'encontrado' => false,
                'message' => 'Colaborador não encontrado com o telefone informado: ' . $telefone . ' (testadas variações: ' . implode(', ', $telefones_para_tentar) . ')',
                'telefone' => $telefone,
                'mensagem_amigavel' => 'Telefone não cadastrado na base de dados. Por favor, procure o RH para cadastrar seu número.'
            ];
        }
        
        // Log para debug
        error_log("DEBUG BANCO HORAS: Colaborador encontrado - Nome: " . $colaborador['NOMFUN'] . " - Matrícula: " . $colaborador['NUMCRA']);
        
        // Agora busca o banco de horas do colaborador encontrado
        $sql_banco = "SELECT CASE
                        WHEN diferenca_horas < 0 THEN
                            '-' || LPAD(ABS(TRUNC(diferenca_horas)), 2, '0') || ':' ||
                            LPAD(ABS(ROUND((diferenca_horas - TRUNC(diferenca_horas)) * 60)), 2, '0')
                        ELSE
                            LPAD(TRUNC(diferenca_horas), 2, '0') || ':' ||
                            LPAD(ROUND((diferenca_horas - TRUNC(diferenca_horas)) * 60), 2, '0')
                    END AS horas_banco,
                    diferenca_horas AS diferenca_horas_decimal
                FROM (
                    SELECT COALESCE(SUM(CASE
                                        WHEN sinlan = '+' THEN qtdhor
                                        ELSE -qtdhor
                                    END) / 60, 0) AS diferenca_horas
                    FROM vetorh.R011LAN, vetorh.r034fun
                    WHERE R011LAN.numemp = r034fun.numemp
                        AND R011LAN.numcad = r034fun.numcad
                        AND R011LAN.tipcol = r034fun.tipcol
                        AND r034fun.numcad = :numcad
                        AND r034fun.numemp = :numemp
                        AND r034fun.tipcon <> 6
                        AND r034fun.sitafa <> 7
                        AND R011LAN.codbhr = 15
                )";
        
        $stmt_banco = oci_parse($conn, $sql_banco);
        
        if (!$stmt_banco) {
            $e = oci_error($conn);
            return ['error' => true, 'message' => 'Erro ao preparar consulta do banco de horas: ' . $e['message']];
        }
        
        oci_bind_by_name($stmt_banco, ':numcad', $colaborador['NUMCAD']);
        oci_bind_by_name($stmt_banco, ':numemp', $colaborador['NUMEMP']);
        
        if (!oci_execute($stmt_banco)) {
            $e = oci_error($stmt_banco);
            return ['error' => true, 'message' => 'Erro ao executar consulta do banco de horas: ' . $e['message']];
        }
        
        $banco_horas = oci_fetch_array($stmt_banco, OCI_ASSOC + OCI_RETURN_NULLS);
        
        // Log para debug
        error_log("DEBUG BANCO HORAS: Saldo encontrado - " . ($banco_horas ? $banco_horas['HORAS_BANCO'] : 'N/A'));
        
        // Monta a resposta estruturada
        $horas_banco = $banco_horas ? $banco_horas['HORAS_BANCO'] : '00:00';
        $horas_decimal = $banco_horas ? $banco_horas['DIFERENCA_HORAS_DECIMAL'] : 0;
        
        // Determina se é positivo ou negativo
        $tipo_saldo = $horas_decimal >= 0 ? 'crédito' : 'débito';
        $sinal = $horas_decimal >= 0 ? '+' : '';
        
        $dados_banco = [
            'success' => true,
            'encontrado' => true,
            'empresa' => [
                'codigo' => $colaborador['NUMEMP'],
                'nome' => $colaborador['NOMEMP']
            ],
            'funcionario' => [
                'matricula' => $colaborador['NUMCRA'],
                'nome' => $colaborador['NOMFUN']
            ],
            'banco_horas' => [
                'saldo_formatado' => $sinal . $horas_banco,
                'saldo_decimal' => $horas_decimal,
                'tipo_saldo' => $tipo_saldo,
                'status' => $horas_decimal >= 0 ? 'positivo' : 'negativo'
            ],
            'mensagem_amigavel' => formatarMensagemBancoHoras([
                'banco_horas' => [
                    'saldo_formatado' => $sinal . $horas_banco,
                    'tipo_saldo' => $tipo_saldo,
                    'status' => $horas_decimal >= 0 ? 'positivo' : 'negativo'
                ],
                'empresa' => [
                    'nome' => $colaborador['NOMEMP']
                ]
            ], $colaborador)
        ];
        
        // Formatar mensagem para WhatsApp
        $mensagem_whatsapp = formatarMensagemBancoHoras($dados_banco, $colaborador);
        
        // Enviar via WhatsApp
        $telefone_whatsapp = '55' . $colaborador['DDDTEL'] . $colaborador['NUMTEL'];
        try {
            $whatsapp_response = enviarWhatsApp($telefone_whatsapp, $mensagem_whatsapp);
            
            // Adicionar resposta do WhatsApp aos dados
            $dados_banco['whatsapp'] = [
                'numero' => $telefone_whatsapp,
                'mensagem_enviada' => true,
                'response' => $whatsapp_response
            ];
        } catch (Exception $e) {
            error_log("DEBUG BANCO HORAS: Erro ao enviar WhatsApp - " . $e->getMessage());
            $dados_banco['whatsapp'] = [
                'numero' => $telefone_whatsapp,
                'mensagem_enviada' => false,
                'error' => $e->getMessage()
            ];
        }
        
        return $dados_banco;
        
    } catch (Exception $e) {
        return ['error' => true, 'message' => 'Erro inesperado: ' . $e->getMessage()];
    } finally {
        if (isset($stmt_colab)) {
            oci_free_statement($stmt_colab);
        }
        if (isset($stmt_banco)) {
            oci_free_statement($stmt_banco);
        }
    }
}

// Processa a requisição
try {
    // Obtém os parâmetros (suporta GET e POST)
    $telefone = null;
    
    // Verifica se é POST com JSON
    $input = file_get_contents('php://input');
    $json_data = json_decode($input, true);
    
    if ($json_data) {
        $telefone = isset($json_data['telefone']) ? $json_data['telefone'] : null;
    } else {
        // Se não for JSON, tenta GET ou POST normal
        $telefone = isset($_REQUEST['telefone']) ? $_REQUEST['telefone'] : null;
    }
    
    // Valida o parâmetro obrigatório
    if ($telefone === null || $telefone === '') {
        echo json_encode([
            'error' => true,
            'message' => 'Parâmetro obrigatório não fornecido: telefone',
            'exemplo_uso' => [
                'GET' => 'api_banco_horas.php?telefone=11999999999',
                'POST' => 'form-data ou json: {"telefone": "11999999999"}'
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        http_response_code(400);
        exit;
    }
    
    // Remove caracteres não numéricos do telefone
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
    
    // Busca o banco de horas por telefone
    $resultado = buscar_banco_horas_por_telefone($conn, $telefone);
    
    // Fecha a conexão
    oci_close($conn);
    
    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Banco de horas consultado e enviado via WhatsApp',
        'data' => [
            'funcionario' => [
                'nome' => $resultado['funcionario']['nome'],
                'matricula' => $resultado['funcionario']['matricula']
            ],
            'banco_horas' => [
                'saldo_formatado' => $resultado['banco_horas']['saldo_formatado'],
                'saldo_decimal' => $resultado['banco_horas']['saldo_decimal'],
                'tipo_saldo' => $resultado['banco_horas']['tipo_saldo'],
                'status' => $resultado['banco_horas']['status']
            ],
            'whatsapp' => [
                'numero' => $resultado['whatsapp']['numero'],
                'mensagem_enviada' => $resultado['whatsapp']['mensagem_enviada'],
                'response' => $resultado['whatsapp']['response'] ?? $resultado['whatsapp']['error'] ?? null
            ]
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => true,
        'message' => 'Erro ao processar requisição: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>

