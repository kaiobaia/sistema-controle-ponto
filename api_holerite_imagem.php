<?php

/**
 * API para gerar e enviar holerite como IMAGEM via WhatsApp
 * Versão que gera imagem detalhada do holerite
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Configurações da API WhatsApp (mesma das outras APIs)
$WHATSAPP_API_URL = 'https://chat-82api.jetsalesbrasil.com/v1/api/external/ace32b90-08b2-4162-b80b-7309749b0226';
$WHATSAPP_TOKEN = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ0ZW5hbnRJZCI6MSwicHJvZmlsZSI6ImFkbWluIiwic2Vzc2lvbklkIjo4LCJjaGFubmVsVHlwZSI6IndoYXRzYXBwIiwiaWF0IjoxNzU5NDkyNDE5LCJleHAiOjE4MjI1NjQ0MTl9.MFihTdRgwsjXiUWy7Vdmiex5__RdjH5Da7G2EcD-lDM';

// Função para conectar ao banco de dados
function conectar_db()
{
    $conn = oci_connect('vetorh', 'rec07gf7', '192.168.50.11/senior');
    if (!$conn) {
        $e = oci_error();
        throw new Exception("Erro de conexão: " . $e['message']);
    }
    return $conn;
}

// Função para buscar data do período pelo código de cálculo
function buscarDataPeriodoPorCodcal($codcal, $numemp)
{
    try {
        $conn = conectar_db();
        $sql = "SELECT to_char(perref, 'MM/YYYY') as periodo_formatado 
                FROM vetorh.r044cal 
                WHERE codcal = :codcal AND numemp = :numemp";
        
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':codcal', $codcal);
        oci_bind_by_name($stmt, ':numemp', $numemp);
        if (oci_execute($stmt)) {
            $row = oci_fetch_array($stmt, OCI_ASSOC + OCI_RETURN_NULLS);
            if ($row && $row['PERIODO_FORMATADO']) {
                $resultado = $row['PERIODO_FORMATADO'];
                oci_close($conn);
                return $resultado;
            }
        }
        oci_close($conn);
        return null;
    } catch (Exception $e) {
        return null;
    }
}

// Função para formatar período (ex: 885 -> 08/2025)
function formatarPeriodo($periodo, $numemp)
{
    // Se já estiver no formato MM/YYYY, retornar como está
    if (is_string($periodo) && preg_match('/^\d{2}\/\d{4}$/', $periodo)) {
        return $periodo;
    }
    
    // Buscar data real do banco pelo código de cálculo
    $data_real = buscarDataPeriodoPorCodcal($periodo, $numemp);
    if ($data_real) {
        return $data_real;
    }
    
    // Se não encontrou no banco, retornar null para indicar erro
    return null;
}

// Função para formatar data de admissão
function formatarDataAdmissao($data_adm)
{
    if (empty($data_adm)) {
        return "00/00/0000";
    }

    try {
        // Se for objeto DateTime do Oracle
        if (is_object($data_adm)) {
            if (method_exists($data_adm, 'format')) {
                return $data_adm->format('d/m/Y');
            }
            // Tentar converter para string primeiro
            $data_str = (string)$data_adm;
        } else {
            $data_str = $data_adm;
        }

        // Tentar diferentes formatos de data
        $formatos = [
            'Y-m-d H:i:s',    // 2024-08-26 00:00:00
            'Y-m-d',          // 2024-08-26
            'd/m/Y',          // 26/08/2024
            'd/m/y',          // 26/08/24 (ano com 2 dígitos)
            'm/d/Y',          // 08/26/2024
            'd-m-Y',          // 26-08-2024
        ];

        foreach ($formatos as $formato) {
            $date = DateTime::createFromFormat($formato, $data_str);
            if ($date !== false) {
                return $date->format('d/m/Y');
            }
        }

        // Tentar com strtotime como último recurso
        $timestamp = strtotime($data_str);
        if ($timestamp !== false && $timestamp > 0) {
            return date('d/m/Y', $timestamp);
        }

        // Se nada funcionar, retornar a data como string (pode ser um formato específico do Oracle)
        if (strlen($data_str) >= 8) {
            // Tentar extrair ano, mês e dia de uma string longa (formato 4 dígitos)
            preg_match('/(\d{4})-(\d{2})-(\d{2})/', $data_str, $matches);
            if (count($matches) == 4) {
                return $matches[3] . '/' . $matches[2] . '/' . $matches[1];
            }
            
            // Tentar formato brasileiro com ano de 2 dígitos (26/08/24)
            preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{2})/', $data_str, $matches);
            if (count($matches) == 4) {
                $dia = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $mes = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                $ano = $matches[3];
                
                // Converter ano de 2 dígitos para 4 dígitos
                $ano_int = (int)$ano;
                if ($ano_int < 50) {
                    $ano = '20' . $ano; // 00-49 = 2000-2049
                } else {
                    $ano = '19' . $ano; // 50-99 = 1950-1999
                }
                
                return $dia . '/' . $mes . '/' . $ano;
            }
        }

        return "00/00/0000";
    } catch (Exception $e) {
        return "00/00/0000";
    }
}

// Função para buscar dados do holerite por telefone
function buscarDadosHoleritePorTelefone($telefone)
{
    error_log("DEBUG: Iniciando busca de holerite para telefone: $telefone");
    $conn = conectar_db();

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
        
        foreach ($telefones_para_tentar as $tel_teste) {
            error_log("DEBUG: Tentando telefone: $tel_teste");
            $resultado = buscarHoleritePorTelefoneEspecifico($conn, $tel_teste);
            if ($resultado) {
                error_log("DEBUG: Sucesso com telefone: $tel_teste");
                return $resultado;
            } else {
                error_log("DEBUG: Falha com telefone: $tel_teste");
            }
        }
        
        throw new Exception("Colaborador não encontrado com o telefone informado: " . $telefone . " (testadas variações: " . implode(', ', $telefones_para_tentar) . ")");
        
    } catch (Exception $e) {
        if (isset($conn)) {
            oci_close($conn);
        }
        throw $e;
    }
}

// Função auxiliar para buscar holerite por telefone específico
function buscarHoleritePorTelefoneEspecifico($conn, $telefone, $codcal = null)
{
    try {
        // Consulta única que busca tudo por telefone
        $sql_holerite = "SELECT a.numcra cracha,
                               a.numcad matricula,
                               a.nomfun nome,
                               a.datadm admissao,
                               a.numcpf cpf,
                               v.codcal calculo,
                               a.numemp empresa,
                               b.titcar cargo,
                               f.razsoc razaosocial,
                               f.numcgc cnpj,
                               a.codban banco,
                               a.codage agencia,
                               a.conban conta,
                               a.digban digito,
                               c.datpag dat_pagamento,
                               v.codeve evento,
                               e.deseve desc_evento,
                               v.refeve referencia,
                               case
                                 when e.tipeve = 1 then '+'
                                 when e.tipeve = 3 then '-'
                                 else ' '
                               end Tip_Eve,
                               v.valeve valor,
                               a.valsal salario_base,
                               (select sum(r.valeve)
                                  from r046ver r
                                 where r.numemp = v.numemp
                                   and r.tipcol = v.tipcol
                                   and r.numcad = v.numcad
                                   and r.codcal = v.codcal
                                   and r.codeve = (select s.codeve
                                                     from r008evc s
                                                    where r.codeve = s.codeve
                                                      and s.codtab = 1
                                                      and s.tipeve = 1)) tot_provento,
                               (select sum(r.valeve)
                                  from r046ver r
                                 where r.numemp = v.numemp
                                   and r.tipcol = v.tipcol
                                   and r.numcad = v.numcad
                                   and r.codcal = v.codcal
                                   and r.codeve = (select s.codeve
                                                     from r008evc s
                                                    where r.codeve = s.codeve
                                                      and s.codtab = 1
                                                      and s.tipeve = 3)) tot_desconto,
                               (select r.valeve
                                  from r046ver r
                                 where r.numemp = v.numemp
                                   and r.tipcol = v.tipcol
                                   and r.numcad = v.numcad
                                   and r.codcal = v.codcal
                                   and r.codeve = 909) tot_Liquido,
                               (select r.valeve
                                  from r046ver r
                                 where r.numemp = v.numemp
                                   and r.tipcol = v.tipcol
                                   and r.numcad = v.numcad
                                   and r.codcal = v.codcal
                                   and r.codeve = 780) FGTS_Mes,
                               v.codcal
                          FROM vetorh.r034fun a,
                               vetorh.r024car b,
                               vetorh.r030fil f,
                               vetorh.r046ver v,
                               vetorh.r008evc e,
                               vetorh.r044cal c,
                               vetorh.r034cpl p
                         WHERE a.codcar = b.codcar
                           AND b.estcar = 100
                           AND e.codtab = 1
                           AND a.numemp = f.numemp
                           AND a.codfil = f.codfil
                           AND a.numemp = v.numemp
                           AND a.tipcol = v.tipcol
                           AND a.numcad = v.numcad
                           AND v.codcal = c.codcal
                           AND v.numemp = c.numemp
                           AND v.codeve = e.codeve
                           AND e.tipeve in (1, 3)
                           AND a.numcad = p.numcad
                           AND a.numemp = p.numemp
                           AND TRIM(p.dddtel || p.numtel) = :telefone
                           AND a.sitafa <> 7
                           AND v.codcal = (SELECT max(r.codcal) -- pega o maior cálculo disponível
                                             FROM r044cal r
                                            WHERE r.numemp = v.numemp                     
                                             AND r.usu_libfol = 'S')
                         ORDER BY v.codeve";

        $stmt = oci_parse($conn, $sql_holerite);
        if (!$stmt) {
            $e = oci_error($conn);
            throw new Exception("Erro ao preparar consulta: " . $e['message']);
        }
        
        oci_bind_by_name($stmt, ':telefone', $telefone);
        
        if (!oci_execute($stmt)) {
            $e = oci_error($stmt);
            error_log("DEBUG: Erro ao executar consulta SQL: " . $e['message']);
            throw new Exception("Erro ao executar consulta: " . $e['message']);
        }
        
        error_log("DEBUG: Consulta executada com sucesso para telefone: $telefone");

        $itens_holerite = [];
        $dados_funcionario = null;
        $total_registros = 0;

        while ($row = oci_fetch_array($stmt, OCI_ASSOC + OCI_RETURN_NULLS)) {
            $total_registros++;
            // Salvar dados do funcionário (são os mesmos para todos os registros)
            if (!$dados_funcionario) {
                $dados_funcionario = $row;
            }

            // Adicionar item do holerite
            $itens_holerite[] = [
                'CODEVE' => $row['EVENTO'],
                'DESEVE' => $row['DESC_EVENTO'],
                'REFEVE' => $row['REFERENCIA'],
                'VALEVE' => $row['VALOR'],
                'ORIEVE' => $row['TIP_EVE']
            ];
        }

        error_log("DEBUG: Total de registros encontrados: $total_registros");
        
        // Verificar se encontrou dados
        if (!$dados_funcionario) {
            error_log("DEBUG: Nenhum dado encontrado para telefone: $telefone");
            return null; // Retorna null para tentar próxima variação
        }

        error_log("DEBUG: Dados encontrados! Funcionário: " . $dados_funcionario['NOME'] . ", Total de itens: " . count($itens_holerite));

        return [
            'funcionario' => [
                'NUMCAD' => $dados_funcionario['MATRICULA'],
                'NUMEMP' => $dados_funcionario['EMPRESA'],
                'NUMCRA' => $dados_funcionario['CRACHA'],
                'NOMFUN' => $dados_funcionario['NOME'],
                'NUMCPF' => $dados_funcionario['CPF'],
                'DATADM' => $dados_funcionario['ADMISSAO'],
                'NUMCAR' => $dados_funcionario['CARGO'],
                'LOCALTRAB' => $dados_funcionario['RAZAOSOCIAL'],
                'RAZAOSOCIAL' => $dados_funcionario['RAZAOSOCIAL'],
                'CNPJ' => $dados_funcionario['CNPJ'],
                'NUMBAN' => $dados_funcionario['BANCO'],
                'AGEBAN' => $dados_funcionario['AGENCIA'],
                'NUMCTB' => $dados_funcionario['CONTA'] . '-' . $dados_funcionario['DIGITO'],
                'CALCULO' => $dados_funcionario['CALCULO']
            ],
            'itens' => $itens_holerite,
            'dados_completos' => $dados_funcionario
        ];
    } catch (Exception $e) {
        error_log("DEBUG: Erro na consulta principal: " . $e->getMessage());
        
        // Tentar consulta alternativa mais simples
        try {
            return buscarHoleriteAlternativo($conn, $telefone);
        } catch (Exception $e2) {
            error_log("DEBUG: Erro na consulta alternativa: " . $e2->getMessage());
            return null; // Retorna null para tentar próxima variação
        }
    }
}

// Função alternativa para buscar holerite (consulta mais simples)
function buscarHoleriteAlternativo($conn, $telefone)
{
    try {
        error_log("DEBUG: Tentando consulta alternativa para telefone: $telefone");
        
        // Consulta mais simples - apenas verificar se o funcionário existe
        $sql_simples = "SELECT a.numcra, a.numcad, a.nomfun, a.datadm, a.numcpf, a.numemp, 
                               f.razsoc, f.numcgc
                        FROM vetorh.r034fun a
                        INNER JOIN vetorh.r030fil f ON a.numemp = f.numemp AND a.codfil = f.codfil
                        INNER JOIN vetorh.r034cpl p ON a.numcad = p.numcad AND a.numemp = p.numemp
                        WHERE TRIM(p.dddtel || p.numtel) = :telefone
                        AND a.sitafa <> 7
                        AND ROWNUM = 1";
        
        $stmt = oci_parse($conn, $sql_simples);
        oci_bind_by_name($stmt, ':telefone', $telefone);
        
        if (!oci_execute($stmt)) {
            $e = oci_error($stmt);
            throw new Exception("Erro na consulta alternativa: " . $e['message']);
        }
        
        $row = oci_fetch_array($stmt, OCI_ASSOC + OCI_RETURN_NULLS);
        
        if (!$row) {
            error_log("DEBUG: Nenhum funcionário encontrado na consulta alternativa");
            return null;
        }
        
        error_log("DEBUG: Funcionário encontrado na consulta alternativa: " . $row['NOMFUN']);
        
        return [
            'funcionario' => [
                'NUMCAD' => $row['NUMCAD'],
                'NUMEMP' => $row['NUMEMP'],
                'NUMCRA' => $row['NUMCRA'],
                'NOMFUN' => $row['NOMFUN'],
                'NUMCPF' => $row['NUMCPF'],
                'DATADM' => $row['DATADM'],
                'NUMCAR' => '',
                'LOCALTRAB' => $row['RAZAOSOCIAL'],
                'RAZAOSOCIAL' => $row['RAZAOSOCIAL'],
                'CNPJ' => $row['NUMCGC'],
                'NUMBAN' => '',
                'AGEBAN' => '',
                'NUMCTB' => '',
                'CALCULO' => 886
            ],
            'itens' => [],
            'dados_completos' => $row
        ];
        
    } catch (Exception $e) {
        error_log("DEBUG: Erro na consulta alternativa: " . $e->getMessage());
        throw $e;
    }
}

// Função para gerar imagem no formato oficial do holerite
function gerarImagemDetalhada($dados)
{
    error_log("DEBUG: Iniciando geração de imagem");
    $funcionario = $dados['funcionario'];
    $itens = $dados['itens'];
    $dados_completos = $dados['dados_completos'] ?? null;

    $filename = "holerite_{$funcionario['NUMCAD']}_" . date('YmdHis') . ".png";
    $filepath = __DIR__ . "/holerites/" . $filename;
    error_log("DEBUG: Arquivo será salvo em: $filepath");

    // Criar diretório se não existir
    $holerites_dir = __DIR__ . "/holerites";
    if (!is_dir($holerites_dir)) {
        if (!mkdir($holerites_dir, 0755, true)) {
            throw new Exception("Erro ao criar diretório: " . $holerites_dir);
        }
    }
    
    // Verificar se o diretório é gravável
    if (!is_writable($holerites_dir)) {
        throw new Exception("Diretório não é gravável: " . $holerites_dir);
    }

    // Criar imagem com true color para melhor qualidade
    $width = 800;
    $height = 1000;
    $image = imagecreatetruecolor($width, $height);
    
    if (!$image) {
        throw new Exception("Erro ao criar imagem");
    }

    // Cores otimizadas
    $bg_color = imagecolorallocate($image, 255, 255, 255);
    $text_color = imagecolorallocate($image, 0, 0, 0);
    $header_color = imagecolorallocate($image, 30, 60, 114);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);

    // Preencher fundo branco
    imagefilledrectangle($image, 0, 0, $width, $height, $bg_color);

    // Título principal - centralizado
    $y = 50; // Posição no topo
    $title_text = "DEMONSTRATIVO DE PAGAMENTO DE SALARIO";
    $title_width = strlen($title_text) * 8; // Aproximadamente
    imagestring($image, 6, ($width - $title_width) / 2, $y, $title_text, $text_color);

    // Cabeçalho com informações do período
    $y += 40;

    // Informações da empresa - usando dados dinâmicos
    $y += 25;
    $empresa_nome = $dados_completos ? $dados_completos['RAZAOSOCIAL'] : $funcionario['RAZAOSOCIAL'];
    $empresa_cnpj = $dados_completos ? $dados_completos['CNPJ'] : $funcionario['CNPJ'];
    $numemp = $dados_completos ? $dados_completos['EMPRESA'] : $funcionario['NUMEMP'];
    
    imagestring($image, 3, 50, $y, "EMPRESA $numemp - " . strtoupper($empresa_nome), $text_color);
    $y += 20;
    imagestring($image, 3, 50, $y, "CNPJ " . $empresa_cnpj, $text_color);

    // Dados do funcionário - usando dados reais da nova consulta
    $y += 30;
    $numcad = $dados_completos ? $dados_completos['MATRICULA'] : $funcionario['NUMCAD'];
    $nome = $dados_completos ? $dados_completos['NOME'] : $funcionario['NOMFUN'];
    $admissao = $dados_completos ? $dados_completos['ADMISSAO'] : $funcionario['DATADM'];
    $cargo = $dados_completos ? $dados_completos['CARGO'] : $funcionario['NUMCAR'];
    

    imagestring($image, 4, 50, $y, "CADASTRO " . $numcad, $text_color);
    $y += 22;
    imagestring($image, 4, 50, $y, "NOME " . strtoupper($nome), $text_color);
    $y += 22;

    // Data de admissão formatada
    $data_admissao = formatarDataAdmissao($admissao);
    imagestring($image, 3, 50, $y, "DATA ADMISSAO " . $data_admissao, $text_color);

    // Cargo - usando dados reais
    $y += 50;
    imagestring($image, 3, 50, $y, "CARGO " . $cargo, $text_color);

    // Tabela de eventos (dados bancários removidos)
    $y += 40;

    // Cabeçalho da tabela
    imagefilledrectangle($image, 40, $y, $width - 40, $y + 30, $header_color);
    imagerectangle($image, 40, $y, $width - 40, $y + 30, $black);

    // Cabeçalhos das colunas
    imagestring($image, 3, 50, $y + 8, "COD.", $white);
    imagestring($image, 3, 150, $y + 8, "DESCRICAO", $white);
    imagestring($image, 3, 400, $y + 8, "REFERENCIA", $white);
    imagestring($image, 3, 500, $y + 8, "VENCIMENTOS", $white);
    imagestring($image, 3, 600, $y + 8, "DESCONTOS", $white);

    $y += 30;

    $total_proventos = 0;
    $total_descontos = 0;
    $liquido = 0;
    $linha = 0;
    $salario_base = 0;

    // Processar itens do holerite
    foreach ($itens as $item) {
        $valor = floatval(str_replace(',', '.', $item['VALEVE']));
        $orientacao = $item['ORIEVE'];
        $descricao = strtoupper($item['DESEVE']);

        // Linha da tabela
        imagerectangle($image, 40, $y, $width - 40, $y + 20, $black);

        // Código (limitado a 8 caracteres)
        imagestring($image, 2, 50, $y + 5, substr($item['CODEVE'], 0, 8), $text_color);

        // Descrição (limitada a 25 caracteres)
        imagestring($image, 2, 150, $y + 5, substr($descricao, 0, 25), $text_color);

        // Referência
        imagestring($image, 2, 400, $y + 5, $item['REFEVE'], $text_color);

        // Valores baseados na orientação
        if ($orientacao == '+') {
            // Proventos (VENCIMENTOS)
            imagestring($image, 2, 500, $y + 5, number_format($valor, 2, ',', '.'), $text_color);
            imagestring($image, 2, 600, $y + 5, "", $text_color);
            $total_proventos += $valor;

            // Identificar salário base
            if (strpos($descricao, 'SALARIO') !== false) {
                $salario_base = $valor;
            }
        } elseif ($orientacao == '-') {
            // Descontos
            imagestring($image, 2, 500, $y + 5, "", $text_color);
            imagestring($image, 2, 600, $y + 5, number_format($valor, 2, ',', '.'), $text_color);
            $total_descontos += $valor;
        } else {
            // Neutro ou líquido
            imagestring($image, 2, 500, $y + 5, "", $text_color);
            imagestring($image, 2, 600, $y + 5, "", $text_color);

            if (strpos($descricao, 'LIQUIDO') !== false || strpos($descricao, 'LIQUIDO') !== false) {
                $liquido = $valor;
            }
        }

        $y += 20;
        $linha++;

        // Limitar altura para não sair da imagem
        if ($y > $height - 400) break;
    }

    // Se não tem salário base identificado, usar o maior provento
    if ($salario_base == 0 && $total_proventos > 0) {
        $salario_base = $total_proventos;
    }

    // Resumo financeiro no formato oficial
    $y += 40;

    // Calcular valores para o resumo usando dados da nova consulta
    $valor_liquido = $liquido > 0 ? $liquido : ($total_proventos - $total_descontos);
    $base_inss = $salario_base > 0 ? $salario_base : $total_proventos;
    $base_fgts = $total_proventos;
    
    $fgts_mes = $base_fgts * 0.08;

    // Usar dados calculados pela nova consulta se disponíveis
    if ($dados_completos) {
        if (!empty($dados_completos['TOT_PROVENTO'])) {
            $total_proventos = floatval($dados_completos['TOT_PROVENTO']);
        }
        if (!empty($dados_completos['TOT_DESCONTO'])) {
            $total_descontos = floatval($dados_completos['TOT_DESCONTO']);
        }
        if (!empty($dados_completos['TOT_LIQUIDO'])) {
            $valor_liquido = floatval($dados_completos['TOT_LIQUIDO']);
        }
        if (!empty($dados_completos['FGTS_MES'])) {
            $fgts_mes = floatval($dados_completos['FGTS_MES']);
        }
        if (!empty($dados_completos['SALARIO_BASE'])) {
            $salario_base = floatval($dados_completos['SALARIO_BASE']);
        }
    }
    
    // Base de cálculo IRRF = Salário base - INSS - Dependentes (calcular após aplicar dados completos)
    $base_irrf = $salario_base > 0 ? $salario_base : $total_proventos;
    // Subtrair INSS da base de IRRF (se houver evento de INSS)
    foreach ($itens as $item) {
        if (stripos($item['DESEVE'], 'INSS') !== false && $item['ORIEVE'] == '-') {
            $base_irrf -= floatval(str_replace(',', '.', $item['VALEVE']));
        }
    }

    // Primeira linha do resumo
    imagestring($image, 3, 50, $y, "SALARIO BASE", $text_color);
    imagestring($image, 3, 200, $y, number_format($salario_base, 2, ',', '.'), $text_color);

    imagestring($image, 3, 350, $y, "SALARIO CONTR. INSS", $text_color);
    imagestring($image, 3, 550, $y, number_format($base_inss, 2, ',', '.'), $text_color);

    imagestring($image, 3, 700, $y, "FAIXA IRRF", $text_color);
    
    // Buscar faixa de IRRF do evento (referência)
    $faixa_irrf = "";
    error_log("DEBUG HOLERITE: Buscando faixa IRRF nos itens... Total de itens: " . count($itens));
    
    foreach ($itens as $item) {
        error_log("DEBUG HOLERITE: Verificando item - Código: " . $item['CODEVE'] . ", Descrição: " . $item['DESEVE'] . ", Referência: " . $item['REFEVE']);
        
        // Procurar pelo evento de IRRF por código ou descrição
        if ($item['CODEVE'] == '511' || 
            stripos($item['DESEVE'], 'IRRF') !== false || 
            stripos($item['DESEVE'], 'I.R.R.F') !== false || 
            stripos($item['DESEVE'], 'IMPOSTO DE RENDA') !== false ||
            stripos($item['DESEVE'], 'RENDIMENTO') !== false) {
            $faixa_irrf = $item['REFEVE'];
            error_log("DEBUG HOLERITE: Faixa IRRF encontrada: " . $faixa_irrf . " para evento " . $item['CODEVE'] . " - " . $item['DESEVE']);
            break;
        }
    }
    
    if (empty($faixa_irrf)) {
        error_log("DEBUG HOLERITE: Nenhuma faixa IRRF encontrada nos eventos, calculando baseado no salário");
    }
    
    // Se não encontrou faixa, calcular baseado no salário
    if (empty($faixa_irrf) || $faixa_irrf == "0,00") {
        // Calcular faixa baseada na base de IRRF
        if ($base_irrf <= 1903.98) {
            $faixa_irrf = "0,00";
        } elseif ($base_irrf <= 2826.65) {
            $faixa_irrf = "7,5";
        } elseif ($base_irrf <= 3751.05) {
            $faixa_irrf = "15,0";
        } elseif ($base_irrf <= 4664.68) {
            $faixa_irrf = "22,5";
        } else {
            $faixa_irrf = "27,5";
        }
        error_log("DEBUG HOLERITE: Calculando faixa IRRF baseada no salário: " . $base_irrf . " -> " . $faixa_irrf);
    }
    
    imagestring($image, 3, 850, $y, $faixa_irrf, $text_color);

    $y += 20;

    // Segunda linha do resumo
    imagestring($image, 3, 50, $y, "TOTAL DE VENCIMENTOS", $text_color);
    imagestring($image, 3, 250, $y, number_format($total_proventos, 2, ',', '.'), $text_color);

    imagestring($image, 3, 400, $y, "TOTAL DE DESCONTOS", $text_color);
    imagestring($image, 3, 600, $y, number_format($total_descontos, 2, ',', '.'), $text_color);

    $y += 20;

    // Terceira linha do resumo
    imagestring($image, 3, 50, $y, "BASE CALC. FGTS", $text_color);
    imagestring($image, 3, 200, $y, number_format($base_fgts, 2, ',', '.'), $text_color);

    imagestring($image, 3, 350, $y, "FGTS DO MES", $text_color);
    imagestring($image, 3, 500, $y, number_format($fgts_mes, 2, ',', '.'), $text_color);

    imagestring($image, 3, 650, $y, "BASE CALCULO IRRF", $text_color);
    
    // Garantir que a base de IRRF seja positiva
    $base_irrf_display = max(0, $base_irrf);
    error_log("DEBUG HOLERITE: Base IRRF calculada: " . $base_irrf_display);
    
    imagestring($image, 3, 850, $y, number_format($base_irrf_display, 2, ',', '.'), $text_color);

    $y += 25;

    // Valor líquido (destaque)
    imagestring($image, 3, 50, $y, "VALOR LIQUIDO", $text_color);
    imagestring($image, 4, 200, $y, number_format($valor_liquido, 2, ',', '.'), $text_color);

    // Linha de assinatura no formato da imagem
    $y += 50;
    imageline($image, 50, $y, 200, $y, $black);
    imagestring($image, 3, 220, $y + 10, "//", $text_color);
    imageline($image, 250, $y, 400, $y, $black);
    imagestring($image, 3, 450, $y + 10, "Assinatura", $text_color);

    // Salvar imagem
    error_log("DEBUG: Salvando imagem...");
    $saved = imagepng($image, $filepath);
    imagedestroy($image);

    // Verificar se a imagem foi salva
    if (!$saved || !file_exists($filepath)) {
        error_log("ERROR: Falha ao salvar imagem em: $filepath");
        throw new Exception("Erro ao salvar imagem: " . $filepath);
    }

    $filesize = filesize($filepath);
    error_log("DEBUG: Imagem salva com sucesso! Tamanho: $filesize bytes");

    return [
        'filepath' => $filepath,
        'filename' => $filename,
        'totais' => [
            'proventos' => $total_proventos,
            'descontos' => $total_descontos,
            'liquido' => $valor_liquido
        ]
    ];
}

// Função para gerar URL completa da imagem
function gerarUrlImagem($filepath)
{
    // URL completa do projeto
    $base_url = "https://www.grupofarias.com.br/pontos";

    // Extrair apenas o nome do arquivo e pasta relativa
    $relative_path = str_replace(__DIR__, '', $filepath);
    $relative_path = str_replace('\\', '/', $relative_path); // Converter para URL

    return $base_url . $relative_path;
}

// Função para enviar via WhatsApp
function enviarWhatsApp($numero, $mensagem, $mediaUrl = '')
{
    global $WHATSAPP_API_URL, $WHATSAPP_TOKEN;

    $data = [
        'number' => $numero,
        'body' => $mensagem,
        'mediaUrl' => $mediaUrl,
        'externalKey' => 'HOLERITE_' . uniqid()
    ];

    $url_completa = $WHATSAPP_API_URL . '?token=' . $WHATSAPP_TOKEN;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url_completa);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Adicionar para resolver problemas de SSL

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("Erro cURL: " . $error);
    }

    if ($http_code !== 200) {
        throw new Exception("Erro na API WhatsApp. Código: " . $http_code . " - Response: " . $response);
    }

    return json_decode($response, true);
}

// Processamento da requisição
try {
    // Aceitar tanto GET quanto POST
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // GET: parâmetros na URL
        $telefone = $_GET['telefone'] ?? null;
        $codcal = $_GET['codcal'] ?? 886; // Padrão para período 886
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // POST: JSON no body
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            throw new Exception("JSON inválido ou vazio");
        }

        $telefone = $input['telefone'] ?? null;
        $codcal = $input['codcal'] ?? 886; // Padrão para período 886
    } else {
        throw new Exception("Método não permitido. Use GET ou POST.");
    }

    // Validar parâmetros
    if (!$telefone) {
        throw new Exception("Parâmetro obrigatório: telefone");
    }

    // Limpar telefone
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    if (strlen($telefone) >= 12 && substr($telefone, 0, 2) === '55') {
        $telefone = substr($telefone, 2);
    }

    if (strlen($telefone) < 10) {
        throw new Exception("Telefone inválido");
    }

    // Buscar dados do holerite
    error_log("DEBUG: Buscando dados do holerite para telefone: $telefone");
    $dados_holerite = buscarDadosHoleritePorTelefone($telefone);
    
    if (!$dados_holerite) {
        throw new Exception("Nenhum dado de holerite encontrado para o telefone: $telefone");
    }
    
    error_log("DEBUG: Dados do holerite encontrados! Total de itens: " . count($dados_holerite['itens']));

    // Obter o código de cálculo real e empresa dos dados do holerite
    $codcal_real = $dados_holerite['funcionario']['CALCULO'] ?? $codcal;
    $numemp_real = $dados_holerite['funcionario']['NUMEMP'] ?? null;
    
    
    // Validar se a empresa foi encontrada
    if (!$numemp_real) {
        throw new Exception("Empresa do colaborador não encontrada");
    }

    // Gerar imagem detalhada
    $resultado_imagem = gerarImagemDetalhada($dados_holerite);

    // Gerar URL completa da imagem
    $imagem_url = gerarUrlImagem($resultado_imagem['filepath']);

    // Preparar mensagem
    $funcionario = $dados_holerite['funcionario'];
    $totais = $resultado_imagem['totais'];

    $mensagem = "🏢 *GRUPO FARIAS*\n\n";
    $mensagem .= "Olá " . $funcionario['NOMFUN'] . "!\n\n";
    // Buscar período formatado
    $periodo_formatado = formatarPeriodo($codcal_real, $numemp_real);
    if (!$periodo_formatado) {
        throw new Exception("Período não encontrado no banco de dados para o cálculo: $codcal_real");
    }
    
    $mensagem .= "📄 Seu holerite do período *" . $periodo_formatado . "* está pronto!\n\n";
    $mensagem .= "📋 *Resumo:*\n";
    $mensagem .= "• Matrícula: " . $funcionario['NUMCRA'] . "\n";
    $mensagem .= "• Proventos: R$ " . number_format($totais['proventos'], 2, ',', '.') . "\n";
    $mensagem .= "• Descontos: R$ " . number_format($totais['descontos'], 2, ',', '.') . "\n";
    $mensagem .= "• *Líquido: R$ " . number_format($totais['liquido'], 2, ',', '.') . "*\n\n";
    $mensagem .= "📸 *Seu holerite completo está na imagem anexa!*\n\n";
    $mensagem .= "❓ Em caso de dúvidas, entre em contato com o RH.\n\n";
    $mensagem .= "_Mensagem enviada automaticamente pelo sistema._";

    // Enviar via WhatsApp (com tratamento de erro)
    $resultado_whatsapp = null;
    $whatsapp_error = null;
    
    try {
        // Formatar telefone corretamente para WhatsApp (55 + DDD + número)
        $telefone_whatsapp = $telefone;
        if (strlen($telefone) == 10) {
            $telefone_whatsapp = '55' . $telefone;
        } elseif (strlen($telefone) == 11) {
            $telefone_whatsapp = '55' . $telefone;
        } elseif (strlen($telefone) == 12 && substr($telefone, 0, 2) == '55') {
            $telefone_whatsapp = $telefone;
        } else {
            $telefone_whatsapp = '55' . $telefone;
        }
        
        error_log("DEBUG HOLERITE: Enviando WhatsApp para: $telefone_whatsapp");
        $resultado_whatsapp = enviarWhatsApp($telefone_whatsapp, $mensagem, $imagem_url);
        error_log("DEBUG HOLERITE: WhatsApp enviado com sucesso");
    } catch (Exception $e) {
        $whatsapp_error = $e->getMessage();
        error_log("DEBUG HOLERITE: Erro WhatsApp: " . $whatsapp_error);
    }

    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => $resultado_whatsapp ? 'Holerite enviado como imagem via WhatsApp' : 'Holerite gerado com sucesso (WhatsApp temporariamente indisponível)',
        'data' => [
            'funcionario' => [
                'nome' => $funcionario['NOMFUN'],
                'matricula' => $funcionario['NUMCRA'],
                'cpf' => $funcionario['NUMCPF']
            ],
            'holerite' => [
                'periodo' => $codcal,
                'total_proventos' => number_format($totais['proventos'], 2, ',', '.'),
                'total_descontos' => number_format($totais['descontos'], 2, ',', '.'),
                'valor_liquido' => number_format($totais['liquido'], 2, ',', '.'),
                'total_itens' => count($dados_holerite['itens'])
            ],
            'whatsapp' => [
                'numero' => $telefone_whatsapp ?? $telefone,
                'mensagem_enviada' => $resultado_whatsapp !== null,
                'response' => $resultado_whatsapp ?? $whatsapp_error
            ],
            'imagem' => [
                'imagem_url' => $imagem_url,
                'arquivo_local' => $resultado_imagem['filename']
            ]
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
