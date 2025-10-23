<?php
/**
 * API para consulta de Informe de Rendimento
 * 
 * Parâmetros esperados:
 * - telefone: Número do telefone do colaborador (obrigatório)
 * 
 * Exemplo de uso:
 * GET: api_informe_rendimento.php?telefone=11999999999
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

// Função para converter valores do Oracle (com vírgula) para float
function converterValorOracleParaFloat($valor) {
    if ($valor === null || $valor === '') {
        return 0;
    }
    // Substitui vírgula por ponto e converte para float
    $valor_limpo = str_replace(',', '.', $valor);
    return (float)$valor_limpo;
}

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
    error_log("DEBUG INFORME RENDIMENTO: Buscando colaborador por telefone: $telefone");
    
    $sql_colaborador = "SELECT f.numcad, f.numemp, f.numcra, f.nomfun, f.numcpf, c.dddtel, c.numtel, e.nomemp, e.numcgc
                       FROM vetorh.r034fun f
                       INNER JOIN vetorh.r034cpl c ON f.numcad = c.numcad AND f.numemp = c.numemp
                       INNER JOIN vetorh.r030emp e ON f.numemp = e.numemp
                       WHERE TRIM(c.dddtel || c.numtel) = :telefone
                       AND f.sitafa <> 7";
    
    $stmt_colab = oci_parse($conn, $sql_colaborador);
    
    if (!$stmt_colab) {
        $e = oci_error($conn);
        error_log("DEBUG INFORME RENDIMENTO: Erro ao preparar consulta: " . $e['message']);
        return false;
    }
    
    oci_bind_by_name($stmt_colab, ':telefone', $telefone);
    
    if (!oci_execute($stmt_colab)) {
        $e = oci_error($stmt_colab);
        error_log("DEBUG INFORME RENDIMENTO: Erro ao executar consulta: " . $e['message']);
        oci_free_statement($stmt_colab);
        return false;
    }
    
    $colaborador = oci_fetch_array($stmt_colab, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stmt_colab);
    
    if ($colaborador) {
        error_log("DEBUG INFORME RENDIMENTO: Colaborador encontrado: " . $colaborador['NOMFUN']);
    } else {
        error_log("DEBUG INFORME RENDIMENTO: Nenhum colaborador encontrado para telefone: $telefone");
    }
    
    return $colaborador;
}

// Função alternativa para buscar colaborador (exatamente igual ao holerite)
function buscar_colaborador_alternativo($conn, $telefone)
{
    
    // Consulta simplificada - removendo a condição sitafa <> 7 que estava causando problema
    $sql_alternativo = "SELECT a.numcra, a.numcad, a.nomfun, a.datadm, a.numcpf, a.numemp, 
                               f.razsoc, f.numcgc, p.dddtel, p.numtel
                        FROM vetorh.r034fun a
                        INNER JOIN vetorh.r030fil f ON a.numemp = f.numemp AND a.codfil = f.codfil
                        INNER JOIN vetorh.r034cpl p ON a.numcad = p.numcad AND a.numemp = p.numemp
                        WHERE TRIM(p.dddtel || p.numtel) = :telefone
                        AND a.sitafa <> 7
                        AND ROWNUM = 1";
    
    $stmt = oci_parse($conn, $sql_alternativo);
    
    if (!$stmt) {
        $e = oci_error($conn);
        return false;
    }
    
    oci_bind_by_name($stmt, ':telefone', $telefone);
    
    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        oci_free_statement($stmt);
        return false;
    }
    
    $colaborador = oci_fetch_array($stmt, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stmt);
    
    return $colaborador;
}

// Função para formatar mensagem do WhatsApp
function formatarMensagemInformeRendimento($dados_informe, $colaborador, $ano)
{
    $rendimentos = $dados_informe['informe_rendimento'];
    
    $mensagem = "🏢 *GRUPO FARIAS*\n\n";
    $mensagem .= "Olá " . $colaborador['NOMFUN'] . "!\n\n";
    $mensagem .= "📄 *INFORME DE RENDIMENTOS $ano*\n\n";
    
    $mensagem .= "💰 *Resumo dos Rendimentos:*\n";
    $mensagem .= "• Total de Rendimentos: R$ " . number_format(converterValorOracleParaFloat($rendimentos['total_rendimento'] ?? 0), 2, ',', '.') . "\n";
    $mensagem .= "• Contribuição Previdenciária: R$ " . number_format(converterValorOracleParaFloat($rendimentos['contribuicao_previdencia'] ?? 0), 2, ',', '.') . "\n";
    $mensagem .= "• Imposto Retido na Fonte: R$ " . number_format(converterValorOracleParaFloat($rendimentos['imposto_retido_fonte'] ?? 0), 2, ',', '.') . "\n";
    $mensagem .= "• 13º Salário: R$ " . number_format(converterValorOracleParaFloat($rendimentos['dts_salario'] ?? 0), 2, ',', '.') . "\n";
    $mensagem .= "• Imposto 13º Salário: R$ " . number_format(converterValorOracleParaFloat($rendimentos['imposto_13_salario'] ?? 0), 2, ',', '.') . "\n";
    $mensagem .= "• Seguro de Vida: R$ " . number_format(converterValorOracleParaFloat($rendimentos['seguro_vida'] ?? 0), 2, ',', '.') . "\n\n";
    
    $mensagem .= "📋 *Informações:*\n";
    $mensagem .= "• Matrícula: " . $colaborador['NUMCRA'] . "\n";
    $mensagem .= "• CPF: " . $dados_informe['funcionario']['cpf'] . "\n";
    $mensagem .= "• Empresa: " . $dados_informe['empresa']['nome'] . "\n";
    $mensagem .= "• Ano de Referência: $ano\n\n";
    
    $mensagem .= "📝 *Importante:*\n";
    $mensagem .= "• Este informe é necessário para declaração do IR 2025\n";
    $mensagem .= "• Guarde este documento para sua declaração\n\n";
    
    $mensagem .= "❓ Para mais informações sobre o informe de rendimentos, entre em contato com o RH.\n\n";
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
        'externalKey' => 'INFORME_RENDIMENTO_' . uniqid()
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

// Função para gerar imagem do Informe de Rendimento
function gerarImagemInformeRendimento($dados_informe, $colaborador, $ano, $planos_saude = [], $responsavel_nome = null)
{
    // Configurações da imagem
    $width = 800;
    $height = 2000;
    
    // Criar imagem
    $image = imagecreate($width, $height);
    if (!$image) {
        throw new Exception("Erro ao criar imagem");
    }
    
    // Cores
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    $blue = imagecolorallocate($image, 0, 0, 139);
    $gray = imagecolorallocate($image, 128, 128, 128);
    $light_gray = imagecolorallocate($image, 240, 240, 240);
    
    // Preencher fundo
    imagefill($image, 0, 0, $white);
    
    $y = 30;
    
    // Cabeçalho - MINISTÉRIO DA ECONOMIA
    imagestring($image, 3, 50, $y, "MINISTERIO DA ECONOMIA", $black);
    $y += 25;
    imagestring($image, 3, 50, $y, "Secretaria Especial da Receita Federal do Brasil", $black);
    $y += 30;
    
    // Título principal
    imagestring($image, 4, 50, $y, "Imposto sobre a Renda da Pessoa Fisica", $black);
    $y += 25;
    imagestring($image, 4, 50, $y, "Comprovante de Rendimentos Pagos e de Imposto sobre a Renda Retido na Fonte", $black);
    $y += 30;
    
    // Exercício e Ano-Calendário
    imagestring($image, 3, 50, $y, "Exercicio de " . ($ano + 1), $black);
    $y += 20;
    imagestring($image, 3, 50, $y, "Ano-Calendario de $ano", $black);
    $y += 40;
    
    // Seção 1: Fonte Pagadora
    imagestring($image, 3, 50, $y, "1. FONTE PAGADORA PESSOA JURIDICA OU PESSOA FISICA", $blue);
    $y += 30;
    
    $empresa = $dados_informe['empresa'];
    
    // Formatar CNPJ com máscara
    $cnpj_formatado = $empresa['cnpj'];
    if (strlen($cnpj_formatado) == 14) {
        $cnpj_formatado = substr($cnpj_formatado, 0, 2) . '.' . 
                         substr($cnpj_formatado, 2, 3) . '.' . 
                         substr($cnpj_formatado, 5, 3) . '/' . 
                         substr($cnpj_formatado, 8, 4) . '-' . 
                         substr($cnpj_formatado, 12, 2);
    }
    
    imagestring($image, 2, 50, $y, "CNPJ/CPF: " . $cnpj_formatado, $black);
    imagestring($image, 2, 400, $y, "Fone: 55 (64) 35645500", $black);
    $y += 20;
    imagestring($image, 2, 50, $y, "Nome Empr: " . $empresa['nome'], $black);
    $y += 20;
    imagestring($image, 2, 50, $y, "Endereco: ROD GO 156", $black);
    imagestring($image, 2, 400, $y, "Bairro: Zona Rural", $black);
    $y += 20;
    imagestring($image, 2, 50, $y, "Cidade: Anicuns", $black);
    imagestring($image, 2, 400, $y, "UF: GO", $black);
    $y += 40;
    
    // Seção 2: Pessoa Física Beneficiária
    imagestring($image, 3, 50, $y, "2. PESSOA FISICA BENEFICIARIA DOS RENDIMENTOS", $blue);
    $y += 30;
    
    imagestring($image, 2, 50, $y, "Local: 12520000000 - DEPTO.TECNOLOGIA DA INFORMACAO", $black);
    $y += 20;
    
    // Formatar CPF com máscara
    $cpf_beneficiario = $colaborador['NUMCPF'];
    if (strlen($cpf_beneficiario) == 11) {
        $cpf_beneficiario = substr($cpf_beneficiario, 0, 3) . '.' . 
                           substr($cpf_beneficiario, 3, 3) . '.' . 
                           substr($cpf_beneficiario, 6, 3) . '-' . 
                           substr($cpf_beneficiario, 9, 2);
    }
    
    imagestring($image, 2, 50, $y, "CPF: " . $cpf_beneficiario, $black);
    $y += 20;
    imagestring($image, 2, 50, $y, "Cadastro: " . $colaborador['NUMCRA'], $black);
    $y += 20;
    imagestring($image, 2, 50, $y, "Beneficiario: " . $colaborador['NOMFUN'], $black);
    $y += 20;
    imagestring($image, 2, 50, $y, "Natureza do Rendimento: 000561 - RENDIMENTOS TRAB. ASSALARIADO", $black);
    $y += 40;
    
    // Seção 3: Rendimentos Tributáveis
    imagestring($image, 3, 50, $y, "3. RENDIMENTOS TRIBUTAVEIS, DEDUCOES E IMPOSTO RETIDO NA FONTE - VALORES EM REAIS", $blue);
    $y += 30;
    
    $rendimentos = $dados_informe['informe_rendimento'];
    
    // Tabela de rendimentos
    $items = [
        ["01", "Total dos Rendimentos (inclusive ferias)", number_format(converterValorOracleParaFloat($rendimentos['total_rendimento'] ?? 0), 2, ',', '.')],
        ["02", "Contribuicao Previdenciaria Oficial", number_format(converterValorOracleParaFloat($rendimentos['contribuicao_previdencia'] ?? 0), 2, ',', '.')],
        ["03", "Contribuicao Previd. Complem. publica ou privada e FAPI", "0,00"],
        ["04", "Pensao Alimenticia", "0,00"],
        ["05", "Imposto sobre a Renda Retido na Fonte", number_format(converterValorOracleParaFloat($rendimentos['imposto_retido_fonte'] ?? 0), 2, ',', '.')]
    ];
    
    foreach ($items as $item) {
        imagestring($image, 2, 50, $y, $item[0] . " " . $item[1] . ":", $black);
        imagestring($image, 2, 600, $y, $item[2], $black);
        $y += 20;
    }
    
    $y += 20;
    
    // Seção 4: Rendimentos Isentos e Não Tributáveis
    imagestring($image, 3, 50, $y, "4. RENDIMENTOS ISENTOS E NAO TRIBUTAVEIS", $blue);
    $y += 30;
    
    $items_isentos = [
        ["01", "Parc. Isenta, Aposent., Reserva, Reforma e Pensao (65 anos ou +), exceto 13°", "0,00"],
        ["02", "Parc. Isenta 13°, Aposent., Reserva, Reforma e Pensao (65 anos ou +)", "0,00"],
        ["03", "Diarias e Ajuda de Custo", "0,00"],
        ["04", "Prov. Pensao, Aposent, Reforma molestia grave, inval.permanente", "0,00"],
        ["05", "Lucro e divid.a partir 1996 pg p/ PJ (Lucro Real, Pres. Arbitr.", "0,00"],
        ["06", "Valores Socio Microempresa ou Peq. Porte exceto pro labore", "0,00"],
        ["07", "Indenizacoes rescisao de contrato de trabalho, PDV, Acid. Trabalho", "0,00"],
        ["08", "Juro mora recebidos, p/ atraso pgto remun. p/ exerc. emprego, cargo, funcao", "0,00"],
        ["09", "Outros (Especificar)", "0,00"]
    ];
    
    foreach ($items_isentos as $item) {
        imagestring($image, 2, 50, $y, $item[0] . " " . $item[1] . ":", $black);
        imagestring($image, 2, 600, $y, $item[2], $black);
        $y += 20;
    }
    
    $y += 30;
    
    // Seção 5: Rendimentos Sujeitos a Tributação Exclusiva
    imagestring($image, 3, 50, $y, "5. RENDIMENTOS SUJEITOS A TRIBUTACAO EXCLUSIVA (Rend. Liquido)", $blue);
    $y += 30;
    error_log("DEBUG IMAGEM: Iniciando seção 5 - Posição Y: $y");
    
    $items_tributacao_exclusiva = [
        ["01", "Decimo Terceiro Salario", number_format(converterValorOracleParaFloat($rendimentos['dts_salario'] ?? 0), 2, ',', '.')],
        ["02", "IRRF 13° Salario", number_format(converterValorOracleParaFloat($rendimentos['imposto_13_salario'] ?? 0), 2, ',', '.')]
    ];
    
    foreach ($items_tributacao_exclusiva as $item) {
        imagestring($image, 2, 50, $y, $item[0] . " " . $item[1] . ":", $black);
        imagestring($image, 2, 600, $y, $item[2], $black);
        $y += 20;
    }
    $y += 30;
    
    // Seção 6: Rendimentos Recebidos Acumuladamente
    imagestring($image, 3, 50, $y, "6. RENDIMENTOS RECEBIDOS ACUMULADAMENTE- Art.12-A da Lei n°7.713, de 1988 (sujeitos a trib. excl.)", $blue);
    $y += 30;
    error_log("DEBUG IMAGEM: Iniciando seção 6 - Posição Y: $y");
    
    imagestring($image, 2, 50, $y, "6.1 Numero do processo:", $black);
    imagestring($image, 2, 479, $y, "Quantidade de meses: 0,0", $black);
    $y += 20;
    imagestring($image, 2, 50, $y, "Natureza do rendimento:", $black);
    $y += 30;
    
    $items_acumulados = [
        ["01", "Total dos rendimentos tributaveis (inclusive ferias e 13° salario)", "0,00"],
        ["02", "Exclusao: Despesas com a acao judicial", "0,00"],
        ["03", "Deducao: Contribuicao previdenciaria oficial", "0,00"],
        ["04", "Deducao: Pensao alimenticia (Preencher tambem o quadro 7)", "0,00"],
        ["05", "Imposto sobre a renda retido na fonte", "0,00"]
    ];
    
    foreach ($items_acumulados as $item) {
        imagestring($image, 2, 50, $y, $item[0] . " " . $item[1] . ":", $black);
        imagestring($image, 2, 600, $y, $item[2], $black);
        $y += 20;
    }
    
    // Item 06 com quebra de linha
    imagestring($image, 2, 50, $y, "06 Rendimentos isentos de pensao, proventos de aposentadoria ou reforma por", $black);
    $y += 15;
    imagestring($image, 2, 50, $y, "   molestia grave ou aposentadoria ou reforma por acidente em servico:", $black);
    imagestring($image, 2, 600, $y, "0,00", $black);
    $y += 20;
    
    $y += 30;
    
    // Seção 7: Informações Complementares
    imagestring($image, 3, 50, $y, "7. INFORMACOES COMPLEMENTARES", $blue);
    $y += 30;
    error_log("DEBUG IMAGEM: Iniciando seção 7 - Posição Y: $y");
    
    // Adiciona Seguro de Vida - METLIFE (sempre aparece se tiver valor)
    $valor_seguro_vida = converterValorOracleParaFloat($rendimentos['seguro_vida'] ?? 0);
    if ($valor_seguro_vida > 0) {
        imagestring($image, 2, 50, $y, "METLIFE  CNPJ:  02.102.498/0001-29", $black);
        $valor_formatado = number_format($valor_seguro_vida, 2, ',', '.');
        imagestring($image, 2, 720, $y, $valor_formatado, $black);
        $y += 20;
    }
    
    // Adiciona os dados do Plano de Saúde (Titular e Dependentes) se existirem
    error_log("DEBUG IMAGEM PLANOS: Total de planos recebidos: " . count($planos_saude));
    error_log("DEBUG IMAGEM PLANOS: Conteúdo: " . print_r($planos_saude, true));
    
    if (!empty($planos_saude)) {
        imagestring($image, 2, 50, $y, "Plano de Saude - Empresarial", $black);
        $y += 20;
        
        // Agrupa por operadora
        $planos_por_operadora = [];
        foreach ($planos_saude as $plano) {
            $key = $plano['NOMOEM'] . '|' . $plano['NUMCGC'];
            if (!isset($planos_por_operadora[$key])) {
                $planos_por_operadora[$key] = [
                    'operadora' => $plano['NOMOEM'],
                    'cnpj' => $plano['NUMCGC'],
                    'planos' => []
                ];
            }
            $planos_por_operadora[$key]['planos'][] = $plano;
        }
        
        // Exibe cada operadora com seus titulares e dependentes
        foreach ($planos_por_operadora as $grupo) {
            imagestring($image, 2, 50, $y, "Operadora:   " . ($grupo['cnpj'] ?? 'N/A') . "  " . ($grupo['operadora'] ?? 'N/A'), $black);
            $y += 20;
            
            foreach ($grupo['planos'] as $plano) {
                if ($plano['TIPO'] == 'TITULAR') {
                    imagestring($image, 2, 80, $y, "Titular", $black);
                    $valor_plano_formatado = number_format(converterValorOracleParaFloat($plano['VALOR_PLANO'] ?? 0), 2, ',', '.');
                    imagestring($image, 2, 720, $y, $valor_plano_formatado, $black);
                } else {
                    // Formata CPF do dependente
                    $cpf_dep = $plano['CPFDEP'] ?? '';
                    $cpf_formatado = '';
                    if (strlen($cpf_dep) == 11) {
                        $cpf_formatado = substr($cpf_dep, 0, 3) . '.' . substr($cpf_dep, 3, 3) . '.' . substr($cpf_dep, 6, 3) . '-' . substr($cpf_dep, 9, 2);
                    } else {
                        $cpf_formatado = $cpf_dep;
                    }
                    
                    // Formato: CPF-Nome-Data Nasc
                    $linha_dep = $cpf_formatado . "  " . ($plano['NOMDEP'] ?? 'N/A') . "     Nasc: " . ($plano['DATNAS_DEP'] ?? 'N/A');
                    imagestring($image, 2, 80, $y, $linha_dep, $black);
                    $valor_plano_formatado = number_format(converterValorOracleParaFloat($plano['VALOR_PLANO'] ?? 0), 2, ',', '.');
                    imagestring($image, 2, 720, $y, $valor_plano_formatado, $black);
                }
                $y += 20;
            }
        }
    }
    
    $y += 30;
    
    // Debug: Log da posição Y atual
    error_log("DEBUG IMAGEM: Posição Y após seção 7: $y");
    
    // Seção 8: Responsável pelas Informações
    imagestring($image, 3, 50, $y, "8. Responsavel  pelas  Informacoes", $blue);
    $y += 30;
    
    if ($responsavel_nome) {
        imagestring($image, 2, 50, $y, "Nome : " . strtoupper($responsavel_nome), $black);
        $y += 30;
        // Data sempre 31/12 do ano do informe (ano anterior ao atual)
        $data_responsavel = "31/12/" . $ano;
        imagestring($image, 2, 50, $y, "Data: " . $data_responsavel, $black);
        imagestring($image, 2, 300, $y, "Assinatura:", $black);
        $y += 5;
        imageline($image, 380, $y, 700, $y, $black); // Linha para assinatura
        $y += 20;
    }
    
    $y += 10;
    imagestring($image, 2, 50, $y, "Aprovado pela Instrucao Normativa RFB n° 2.060, de 13 de dezembro de 2021.", $gray);
    
    // Salvar imagem
    $filename = "informe_rendimento_" . $colaborador['NUMCRA'] . "_" . $ano . "_" . date('YmdHis') . ".png";
    $filepath = __DIR__ . "/holerites/" . $filename;
    
    // Criar diretório se não existir
    if (!is_dir(__DIR__ . "/holerites")) {
        mkdir(__DIR__ . "/holerites", 0755, true);
    }
    
    if (!imagepng($image, $filepath)) {
        imagedestroy($image);
        throw new Exception("Erro ao salvar imagem: $filepath");
    }
    
    imagedestroy($image);
    
    return [
        'filepath' => $filepath,
        'filename' => $filename
    ];
}

// Função para gerar URL completa da imagem
function gerarUrlImagemInforme($filepath)
{
    // URL completa do projeto
    $base_url = "https://www.grupofarias.com.br/pontos";
    
    // Extrair apenas o nome do arquivo e pasta relativa
    $relative_path = str_replace(__DIR__, '', $filepath);
    $relative_path = str_replace('\\', '/', $relative_path); // Converter para URL
    
    return $base_url . $relative_path;
}

// Função para buscar informe de rendimento por telefone
function buscar_informe_rendimento_por_telefone($conn, $telefone)
{
    try {
        // Tentar diferentes variações do telefone
        $telefones_para_tentar = [];
        
        // Telefone original
        $telefones_para_tentar[] = $telefone;
        error_log("DEBUG INFORME RENDIMENTO: Telefone original: $telefone (tamanho: " . strlen($telefone) . ")");
        
        // Se for número de 10 dígitos (sem o 9), adicionar o 9
        if (strlen($telefone) == 10) {
            $telefone_com_9 = substr($telefone, 0, 2) . '9' . substr($telefone, 2);
            $telefones_para_tentar[] = $telefone_com_9;
            error_log("DEBUG INFORME RENDIMENTO: Adicionando telefone com 9: $telefone_com_9");
        }
        
        // Se for número de 11 dígitos (com o 9), tentar sem o 9
        if (strlen($telefone) == 11 && substr($telefone, 2, 1) == '9') {
            $telefone_sem_9 = substr($telefone, 0, 2) . substr($telefone, 3);
            $telefones_para_tentar[] = $telefone_sem_9;
            error_log("DEBUG INFORME RENDIMENTO: Adicionando telefone sem 9: $telefone_sem_9");
        }
        
        // Remover duplicatas
        $telefones_para_tentar = array_unique($telefones_para_tentar);
        error_log("DEBUG INFORME RENDIMENTO: Telefones para tentar: " . implode(', ', $telefones_para_tentar));
        
        // Usar apenas a consulta alternativa que é igual ao holerite
        $colaborador = null;
        foreach ($telefones_para_tentar as $tel_teste) {
            error_log("DEBUG INFORME RENDIMENTO: Tentando telefone: $tel_teste");
            $colaborador = buscar_colaborador_alternativo($conn, $tel_teste);
            if ($colaborador) {
                error_log("DEBUG INFORME RENDIMENTO: Colaborador encontrado com telefone: $tel_teste");
                break;
            } else {
                error_log("DEBUG INFORME RENDIMENTO: Nenhum colaborador encontrado com telefone: $tel_teste");
            }
        }
        
        if (!$colaborador) {
            return [
                'success' => true,
                'encontrado' => false,
                'message' => 'Colaborador não encontrado com o telefone informado: ' . $telefone . ' (testadas variações: ' . implode(', ', $telefones_para_tentar) . ')',
                'telefone' => $telefone,
                'mensagem_amigavel' => 'Telefone não cadastrado na base de dados. Por favor, procure o RH para cadastrar seu número.'
            ];
        }
        
        // Definir ano como ano anterior ao corrente
        $ano = date('Y') - 1;
        
        
        // Formatar CPF com zeros à esquerda (11 dígitos)
        $cpf_formatado = str_pad($colaborador['NUMCPF'], 11, '0', STR_PAD_LEFT);
        
        // Log para debug
        error_log("DEBUG INFORME RENDIMENTO: CPF original: " . $colaborador['NUMCPF'] . " - CPF formatado: " . $cpf_formatado . " - NUMEMP: " . $colaborador['NUMEMP']);
        
        // Busca o informe de rendimento do colaborador usando o CPF encontrado
        $sql_informe = "SELECT SUM(BASIRF) + SUM(BASFER) TOTAL_RENDIMENTO,
                               SUM(CONPRE) CONTRIBUICAO_PREVIDENCIA,
                               SUM(VALIRF) + SUM(IRFFER)  IMPOSTO_RETIDO_FONTE,        
                               SUM(VAL13S) DTS_SALARIO,
                               SUM(IRF13S) IMPOSTO_13_SALARIO,
                               SUM(SEGVID) SEGURO_VIDA
                        FROM vetorh.r051ren
                        WHERE CPFCGC = :cpfcgc
                        AND NUMEMP = :numemp
                        AND CMPREN >= :data_inicio
                        AND CMPREN <= :data_fim
                        GROUP BY NUMEMP, CPFCGC";
        
        $stmt_informe = oci_parse($conn, $sql_informe);
        
        if (!$stmt_informe) {
            $e = oci_error($conn);
            return ['error' => true, 'message' => 'Erro ao preparar consulta do informe de rendimento: ' . $e['message']];
        }
        
        $data_inicio = "01/01/$ano";
        $data_fim = "31/12/$ano";
        
        oci_bind_by_name($stmt_informe, ':cpfcgc', $cpf_formatado);
        oci_bind_by_name($stmt_informe, ':numemp', $colaborador['NUMEMP']);
        oci_bind_by_name($stmt_informe, ':data_inicio', $data_inicio);
        oci_bind_by_name($stmt_informe, ':data_fim', $data_fim);
        
        // Log para debug dos parâmetros
        error_log("DEBUG INFORME RENDIMENTO: Parâmetros da consulta:");
        error_log("  - CPF formatado: '$cpf_formatado' (tamanho: " . strlen($cpf_formatado) . ")");
        error_log("  - NUMEMP: '" . $colaborador['NUMEMP'] . "' (tipo: " . gettype($colaborador['NUMEMP']) . ")");
        error_log("  - Data início: '$data_inicio'");
        error_log("  - Data fim: '$data_fim'");
        error_log("  - CPF original da R034FUN: '" . $colaborador['NUMCPF'] . "'");
        
        if (!oci_execute($stmt_informe)) {
            $e = oci_error($stmt_informe);
            return ['error' => true, 'message' => 'Erro ao executar consulta do informe de rendimento: ' . $e['message']];
        }
        
        $informe_rendimento = oci_fetch_array($stmt_informe, OCI_ASSOC + OCI_RETURN_NULLS);
        
        // Log para debug dos resultados
        if ($informe_rendimento) {
            error_log("DEBUG INFORME RENDIMENTO: Dados encontrados:");
            error_log("  - TOTAL_RENDIMENTO: " . ($informe_rendimento['TOTAL_RENDIMENTO'] ?? 'NULL'));
            error_log("  - CONTRIBUICAO_PREVIDENCIA: " . ($informe_rendimento['CONTRIBUICAO_PREVIDENCIA'] ?? 'NULL'));
            error_log("  - IMPOSTO_RETIDO_FONTE: " . ($informe_rendimento['IMPOSTO_RETIDO_FONTE'] ?? 'NULL'));
            error_log("  - DTS_SALARIO: " . ($informe_rendimento['DTS_SALARIO'] ?? 'NULL'));
            error_log("  - IMPOSTO_13_SALARIO: " . ($informe_rendimento['IMPOSTO_13_SALARIO'] ?? 'NULL'));
            error_log("  - SEGURO_VIDA: " . ($informe_rendimento['SEGURO_VIDA'] ?? 'NULL'));
        } else {
            error_log("DEBUG INFORME RENDIMENTO: Nenhum dado encontrado na consulta");
        }
        
        if (!$informe_rendimento || !$informe_rendimento['TOTAL_RENDIMENTO']) {
            return [
                'success' => true,
                'encontrado' => false,
                'message' => 'Nenhum informe de rendimento encontrado para o ano ' . $ano,
                'telefone' => $telefone,
                'mensagem_amigavel' => 'Não foram encontrados dados de rendimento para o ano ' . $ano . '. Verifique se você trabalhou neste período.'
            ];
        }
        
        // Busca os dados do Plano de Saúde do TITULAR (R051SAC - tabela do titular)
        $sql_plano_titular = "SELECT NOMOEM, NUMCGC, SUM(VALDES) VALOR_PLANO
                              FROM vetorh.r051sac S, vetorh.r044cal C, vetorh.r032oem O
                              WHERE S.NUMEMP = C.NUMEMP
                              AND C.CODCAL = S.CODCAL
                              AND O.CODOEM = S.CODOEM
                              AND S.NUMCAD = :numcad
                              AND C.NUMEMP = :numemp
                              AND C.DATPAG >= TO_DATE(:data_inicio, 'DD/MM/YYYY')
                              AND C.DATPAG <= TO_DATE(:data_fim, 'DD/MM/YYYY')
                              GROUP BY NOMOEM, NUMCGC";
        
        $stmt_titular = oci_parse($conn, $sql_plano_titular);
        $plano_titular = null;
        
        if (!$stmt_titular) {
            $e = oci_error($conn);
            error_log("DEBUG PLANO TITULAR: Erro ao preparar statement - " . print_r($e, true));
        } else {
            oci_bind_by_name($stmt_titular, ':numcad', $colaborador['NUMCAD']);
            oci_bind_by_name($stmt_titular, ':numemp', $colaborador['NUMEMP']);
            oci_bind_by_name($stmt_titular, ':data_inicio', $data_inicio);
            oci_bind_by_name($stmt_titular, ':data_fim', $data_fim);
            
            error_log("DEBUG PLANO TITULAR: Executando query com NUMCAD=" . $colaborador['NUMCAD'] . ", NUMEMP=" . $colaborador['NUMEMP'] . ", DATA_INICIO=" . $data_inicio . ", DATA_FIM=" . $data_fim);
            
            if (oci_execute($stmt_titular)) {
                $planos_titular = [];
                while ($row = oci_fetch_array($stmt_titular, OCI_ASSOC + OCI_RETURN_NULLS)) {
                    error_log("DEBUG PLANO TITULAR: Linha encontrada - " . print_r($row, true));
                    $planos_titular[] = $row;
                }
                error_log("DEBUG PLANO TITULAR: Total de planos titular encontrados: " . count($planos_titular));
                oci_free_statement($stmt_titular);
            } else {
                $e = oci_error($stmt_titular);
                error_log("DEBUG PLANO TITULAR: Erro ao executar query - " . print_r($e, true));
            }
        }
        
        
        // Busca os dados do Plano de Saúde dos DEPENDENTES (R051SAD - tabela dos dependentes)
        $sql_plano_dependentes = "SELECT NOMOEM, NUMCGC, SUM(VALDES) VALOR_PLANO, D.NOMDEP, D.NUMCPF CPFDEP, 
                                         TO_CHAR(D.DATNAS, 'DD/MM/YYYY') DATNAS_DEP
                                  FROM vetorh.r051sad S, vetorh.r044cal C, vetorh.r032oem O, vetorh.r036dep D
                                  WHERE S.NUMEMP = C.NUMEMP
                                  AND C.CODCAL = S.CODCAL
                                  AND O.CODOEM = S.CODOEM
                                  AND D.CODDEP = S.CODDEP
                                  AND D.NUMEMP = S.NUMEMP
                                  AND D.NUMCAD = S.NUMCAD
                                  AND S.NUMCAD = :numcad
                                  AND C.NUMEMP = :numemp
                                  AND C.DATPAG >= TO_DATE(:data_inicio, 'DD/MM/YYYY')
                                  AND C.DATPAG <= TO_DATE(:data_fim, 'DD/MM/YYYY')
                                  GROUP BY NOMOEM, NUMCGC, D.NOMDEP, D.NUMCPF, D.DATNAS";
        
        $stmt_dependentes = oci_parse($conn, $sql_plano_dependentes);
        $planos_dependentes = [];
        
        if (!$stmt_dependentes) {
            $e = oci_error($conn);
        } else {
            oci_bind_by_name($stmt_dependentes, ':numcad', $colaborador['NUMCAD']);
            oci_bind_by_name($stmt_dependentes, ':numemp', $colaborador['NUMEMP']);
            oci_bind_by_name($stmt_dependentes, ':data_inicio', $data_inicio);
            oci_bind_by_name($stmt_dependentes, ':data_fim', $data_fim);
            
            if (oci_execute($stmt_dependentes)) {
                while ($row = oci_fetch_array($stmt_dependentes, OCI_ASSOC + OCI_RETURN_NULLS)) {
                    $planos_dependentes[] = $row;
                }
                oci_free_statement($stmt_dependentes);
            }
        }
        
        // Busca o responsável pelas informações (RH)
        $sql_responsavel = "select che.nomfun
                              from r034fun che
                             where (che.numemp, che.tipcol, che.numcad) in
                                   (select f.emprrh, f.tiprrh, f.cadrrh
                                      from r030fil f
                                     where (f.numemp, f.tiprrh, f.codfil) in
                                           (select fu.numemp, fu.tipcol, fu.codfil
                                              from r034fun fu
                                             where fu.numemp = :numemp
                                               and fu.numcad = :numcad))";
        
        $stmt_responsavel = oci_parse($conn, $sql_responsavel);
        $responsavel_nome = null;
        
        if ($stmt_responsavel) {
            oci_bind_by_name($stmt_responsavel, ':numemp', $colaborador['NUMEMP']);
            oci_bind_by_name($stmt_responsavel, ':numcad', $colaborador['NUMCAD']);
            
            error_log("DEBUG RESPONSAVEL: Executando query com NUMEMP=" . $colaborador['NUMEMP'] . ", NUMCAD=" . $colaborador['NUMCAD']);
            
            if (oci_execute($stmt_responsavel)) {
                $responsavel = oci_fetch_array($stmt_responsavel, OCI_ASSOC + OCI_RETURN_NULLS);
                if ($responsavel) {
                    $responsavel_nome = $responsavel['NOMFUN'];
                    error_log("DEBUG RESPONSAVEL RH: " . $responsavel_nome);
                } else {
                    error_log("DEBUG RESPONSAVEL: Nenhum responsável encontrado");
                }
                oci_free_statement($stmt_responsavel);
            } else {
                $e = oci_error($stmt_responsavel);
                error_log("DEBUG RESPONSAVEL: Erro ao executar query - " . print_r($e, true));
            }
        }
        
        // Monta array unificado com titular e dependentes
        $planos_saude = [];
        if (isset($planos_titular) && count($planos_titular) > 0) {
            foreach ($planos_titular as $plano_titular) {
                error_log("DEBUG: Plano titular encontrado - " . $plano_titular['NOMOEM'] . " - Valor: " . $plano_titular['VALOR_PLANO']);
                $planos_saude[] = [
                    'NOMOEM' => $plano_titular['NOMOEM'],
                    'NUMCGC' => $plano_titular['NUMCGC'],
                    'VALOR_PLANO' => $plano_titular['VALOR_PLANO'],
                    'NOMDEP' => null,
                    'TIPO' => 'TITULAR'
                ];
            }
        } else {
            error_log("DEBUG: Nenhum plano titular encontrado na R051SAC");
        }
        
        foreach ($planos_dependentes as $dependente) {
            $planos_saude[] = [
                'NOMOEM' => $dependente['NOMOEM'],
                'NUMCGC' => $dependente['NUMCGC'],
                'VALOR_PLANO' => $dependente['VALOR_PLANO'],
                'NOMDEP' => $dependente['NOMDEP'],
                'CPFDEP' => $dependente['CPFDEP'],
                'DATNAS_DEP' => $dependente['DATNAS_DEP'],
                'TIPO' => 'DEPENDENTE'
            ];
        }
        
        // Busca dados da empresa 7 filial 1 se necessário
        error_log("DEBUG: Iniciando busca de dados da empresa - NUMEMP: " . $colaborador['NUMEMP']);
        $empresa_fonte_pagadora = [
            'codigo' => $colaborador['NUMEMP'],
            'nome' => $colaborador['NOMEMP'],
            'cnpj' => $colaborador['NUMCGC']
        ];
        
        // Se a empresa for 4, 5, 10 ou 23, buscar dados da empresa 7 filial 1
        if (in_array($colaborador['NUMEMP'], ['4', '5', '10', '23'])) {
            error_log("DEBUG: Empresa " . $colaborador['NUMEMP'] . " detectada, buscando dados da empresa 7 filial 1");
            $sql_fonte_pagadora = "SELECT * FROM vetorh.r030fil WHERE numemp = 7 AND codfil = 1";
            $stmt_fonte = oci_parse($conn, $sql_fonte_pagadora);
            
            if ($stmt_fonte && oci_execute($stmt_fonte)) {
                $fonte_pagadora = oci_fetch_array($stmt_fonte, OCI_ASSOC + OCI_RETURN_NULLS);
                if ($fonte_pagadora) {
                    error_log("DEBUG: Dados da empresa 7 filial 1 encontrados - NOMCOM: " . $fonte_pagadora['NOMCOM'] . ", NUMCGC: " . $fonte_pagadora['NUMCGC']);
                    $empresa_fonte_pagadora = [
                        'codigo' => $colaborador['NUMEMP'],
                        'nome' => $fonte_pagadora['NOMCOM'],
                        'cnpj' => $fonte_pagadora['NUMCGC']
                    ];
                } else {
                    error_log("DEBUG: Nenhum dado encontrado na empresa 7 filial 1");
                }
                oci_free_statement($stmt_fonte);
            } else {
                error_log("DEBUG: Erro ao executar consulta da empresa 7 filial 1");
            }
        } else {
            error_log("DEBUG: Empresa " . $colaborador['NUMEMP'] . " não está na lista [4,5,10,23], usando dados originais");
        }
        
        // Log dos dados finais da empresa
        error_log("DEBUG: Dados finais da empresa - Nome: " . $empresa_fonte_pagadora['nome'] . ", CNPJ: " . $empresa_fonte_pagadora['cnpj']);
        
        // Monta a resposta estruturada
        $dados_informe = [
            'success' => true,
            'encontrado' => true,
            'empresa' => $empresa_fonte_pagadora,
            'funcionario' => [
                'matricula' => $colaborador['NUMCRA'],
                'nome' => $colaborador['NOMFUN'],
                'cpf' => $cpf_formatado
            ],
            'informe_rendimento' => [
                'ano' => $ano,
                'total_rendimento' => $informe_rendimento['TOTAL_RENDIMENTO'] ?? 0,
                'contribuicao_previdencia' => $informe_rendimento['CONTRIBUICAO_PREVIDENCIA'] ?? 0,
                'imposto_retido_fonte' => $informe_rendimento['IMPOSTO_RETIDO_FONTE'] ?? 0,
                'dts_salario' => $informe_rendimento['DTS_SALARIO'] ?? 0,
                'imposto_13_salario' => $informe_rendimento['IMPOSTO_13_SALARIO'] ?? 0,
                'seguro_vida' => $informe_rendimento['SEGURO_VIDA'] ?? 0
            ],
            'planos_saude' => $planos_saude,
            'mensagem_amigavel' => formatarMensagemInformeRendimento([
                'informe_rendimento' => [
                    'total_rendimento' => $informe_rendimento['TOTAL_RENDIMENTO'] ?? 0,
                    'contribuicao_previdencia' => $informe_rendimento['CONTRIBUICAO_PREVIDENCIA'] ?? 0,
                    'imposto_retido_fonte' => $informe_rendimento['IMPOSTO_RETIDO_FONTE'] ?? 0,
                    'dts_salario' => $informe_rendimento['DTS_SALARIO'] ?? 0,
                    'imposto_13_salario' => $informe_rendimento['IMPOSTO_13_SALARIO'] ?? 0,
                    'seguro_vida' => $informe_rendimento['SEGURO_VIDA'] ?? 0
                ],
                'empresa' => [
                    'nome' => $colaborador['NOMEMP']
                ],
                'funcionario' => [
                    'cpf' => $cpf_formatado
                ]
            ], $colaborador, $ano)
        ];
        
        // Gerar imagem do informe
        error_log("DEBUG INFORME RENDIMENTO: Iniciando geração de imagem");
        try {
            $resultado_imagem = gerarImagemInformeRendimento($dados_informe, $colaborador, $ano, $planos_saude, $responsavel_nome);
            error_log("DEBUG INFORME RENDIMENTO: Imagem gerada com sucesso");
            
            // Gerar URL completa da imagem
            $imagem_url = gerarUrlImagemInforme($resultado_imagem['filepath']);
            error_log("DEBUG INFORME RENDIMENTO: URL da imagem gerada: $imagem_url");
        } catch (Exception $e) {
            error_log("DEBUG INFORME RENDIMENTO: Erro ao gerar imagem: " . $e->getMessage());
            throw $e;
        }
        
        // Formatar mensagem para WhatsApp
        error_log("DEBUG INFORME RENDIMENTO: Formatando mensagem para WhatsApp");
        $mensagem_whatsapp = formatarMensagemInformeRendimento($dados_informe, $colaborador, $ano);
        
        // Enviar via WhatsApp com imagem - sempre com código do país 55
        $telefone_completo = trim($colaborador['DDDTEL']) . trim($colaborador['NUMTEL']);
        
        // Remover 55 se já existir no início
        if (substr($telefone_completo, 0, 2) === '55') {
            $telefone_completo = substr($telefone_completo, 2);
        }
        
        // Sempre adicionar 55 no início
        $telefone_whatsapp = '55' . $telefone_completo;
        
        error_log("DEBUG INFORME RENDIMENTO: DDD: " . $colaborador['DDDTEL'] . ", NUMTEL: " . $colaborador['NUMTEL']);
        error_log("DEBUG INFORME RENDIMENTO: Telefone WhatsApp formatado: $telefone_whatsapp");
        
        try {
            $whatsapp_response = enviarWhatsApp($telefone_whatsapp, $mensagem_whatsapp, $imagem_url);
            error_log("DEBUG INFORME RENDIMENTO: WhatsApp enviado com sucesso");
            
            // Adicionar resposta do WhatsApp aos dados
            $dados_informe['whatsapp'] = [
                'numero' => $telefone_whatsapp,
                'mensagem_enviada' => true,
                'response' => $whatsapp_response
            ];
        } catch (Exception $e) {
            error_log("DEBUG INFORME RENDIMENTO: Erro ao enviar WhatsApp - " . $e->getMessage());
            $dados_informe['whatsapp'] = [
                'numero' => $telefone_whatsapp,
                'mensagem_enviada' => false,
                'error' => $e->getMessage()
            ];
        }
        
        // Adicionar informações da imagem aos dados
        $dados_informe['imagem'] = [
            'imagem_url' => $imagem_url,
            'arquivo_local' => $resultado_imagem['filename']
        ];
        
        return $dados_informe;
        
    } catch (Exception $e) {
        return ['error' => true, 'message' => 'Erro inesperado: ' . $e->getMessage()];
    } finally {
        if (isset($stmt_colab)) {
            oci_free_statement($stmt_colab);
        }
        if (isset($stmt_informe)) {
            oci_free_statement($stmt_informe);
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
                'GET' => 'api_informe_rendimento.php?telefone=11999999999',
                'POST' => 'form-data ou json: {"telefone": "11999999999"}'
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        http_response_code(400);
        exit;
    }
    
    // Ano será sempre o anterior ao corrente
    $ano = date('Y') - 1;
    
    // Remove caracteres não numéricos do telefone
    $telefone_original = $telefone;
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    error_log("DEBUG INFORME RENDIMENTO: Telefone após limpeza: $telefone");
    
    // Remove o código do país 55 se presente (qualquer tamanho)
    if (substr($telefone, 0, 2) === '55') {
        $telefone = substr($telefone, 2);
        error_log("DEBUG INFORME RENDIMENTO: Telefone após remover 55: $telefone");
    }
    
    error_log("DEBUG INFORME RENDIMENTO: Telefone final para busca: $telefone (original: $telefone_original)");
    
    // Conecta ao banco
    $conn = conectar_db();
    
    if (is_array($conn)) {
        // Se retornou array, é um erro
        echo json_encode($conn, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        http_response_code(500);
        exit;
    }
    
    // Busca o informe de rendimento por telefone
    $resultado = buscar_informe_rendimento_por_telefone($conn, $telefone);
    
    error_log("DEBUG INFORME RENDIMENTO: Resultado da busca: " . json_encode($resultado));
    
    // Fecha a conexão
    oci_close($conn);
    
    // Verificar se houve erro na busca
    if (isset($resultado['error']) && $resultado['error']) {
        echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        http_response_code(500);
        exit;
    }
    
    // Verificar se não foi encontrado
    if (isset($resultado['encontrado']) && !$resultado['encontrado']) {
        echo json_encode([
            'success' => false,
            'message' => $resultado['mensagem_amigavel'],
            'debug' => $resultado['message']
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Informe de rendimento consultado e enviado via WhatsApp',
        'data' => [
            'funcionario' => [
                'nome' => $resultado['funcionario']['nome'],
                'matricula' => $resultado['funcionario']['matricula'],
                'cpf' => $resultado['funcionario']['cpf']
            ],
            'informe_rendimento' => [
                'ano' => $resultado['informe_rendimento']['ano'],
                'total_rendimento' => number_format(converterValorOracleParaFloat($resultado['informe_rendimento']['total_rendimento'] ?? 0), 2, ',', '.'),
                'contribuicao_previdencia' => number_format(converterValorOracleParaFloat($resultado['informe_rendimento']['contribuicao_previdencia'] ?? 0), 2, ',', '.'),
                'imposto_retido_fonte' => number_format(converterValorOracleParaFloat($resultado['informe_rendimento']['imposto_retido_fonte'] ?? 0), 2, ',', '.'),
                'dts_salario' => number_format(converterValorOracleParaFloat($resultado['informe_rendimento']['dts_salario'] ?? 0), 2, ',', '.'),
                'imposto_13_salario' => number_format(converterValorOracleParaFloat($resultado['informe_rendimento']['imposto_13_salario'] ?? 0), 2, ',', '.'),
                'seguro_vida' => number_format(converterValorOracleParaFloat($resultado['informe_rendimento']['seguro_vida'] ?? 0), 2, ',', '.')
            ],
            'whatsapp' => [
                'numero' => $resultado['whatsapp']['numero'],
                'mensagem_enviada' => $resultado['whatsapp']['mensagem_enviada'],
                'response' => $resultado['whatsapp']['response'] ?? $resultado['whatsapp']['error'] ?? null
            ],
            'imagem' => [
                'imagem_url' => $resultado['imagem']['imagem_url'],
                'arquivo_local' => $resultado['imagem']['arquivo_local']
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
