<?php
/**
 * Exemplo de uso da API para o Get Sales
 * API: api_holerite_imagem.php
 */

echo "<h1>📱 API Holerite para Get Sales</h1>\n";

echo "<h2>🔗 URL da API:</h2>\n";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
echo "<strong>https://www.grupofarias.com.br/pontos/api_holerite_imagem.php</strong>\n";
echo "</div>\n";

echo "<h2>📋 Parâmetros:</h2>\n";
echo "<ul>\n";
echo "<li><strong>telefone</strong> (obrigatório): Número do telefone do colaborador</li>\n";
echo "<li><strong>codcal</strong> (opcional): Código do período (padrão: 885 para 08/2025)</li>\n";
echo "</ul>\n";

echo "<h2>🌐 Método GET (Recomendado para Get Sales):</h2>\n";
echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
echo "<h3>URL com parâmetros:</h3>\n";
echo "<p><strong>https://www.grupofarias.com.br/pontos/api_holerite_imagem.php?telefone=64993106841</strong></p>\n";
echo "<p><em>Ou com período específico:</em></p>\n";
echo "<p><strong>https://www.grupofarias.com.br/pontos/api_holerite_imagem.php?telefone=64993106841&codcal=885</strong></p>\n";
echo "</div>\n";

echo "<h2>📤 Exemplo de Requisição:</h2>\n";
echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
echo "<h3>JSON:</h3>\n";
echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>\n";
echo json_encode([
    'telefone' => '64993106841',
    'codcal' => 885
], JSON_PRETTY_PRINT);
echo "</pre>\n";
echo "</div>\n";

echo "<h2>🌐 Exemplo cURL (GET):</h2>\n";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>\n";
echo "curl 'https://www.grupofarias.com.br/pontos/api_holerite_imagem.php?telefone=64993106841&codcal=885'";
echo "</pre>\n";
echo "</div>\n";

echo "<h2>📤 Exemplo cURL (POST):</h2>\n";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>\n";
echo "curl --location 'https://www.grupofarias.com.br/pontos/api_holerite_imagem.php' \\\n";
echo "  --header 'Content-Type: application/json' \\\n";
echo "  --data '{\n";
echo "    \"telefone\": \"64993106841\",\n";
echo "    \"codcal\": 885\n";
echo "  }'";
echo "</pre>\n";
echo "</div>\n";

echo "<h2>📱 Exemplo JavaScript (Fetch):</h2>\n";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>\n";
echo "fetch('https://www.grupofarias.com.br/pontos/api_holerite_imagem.php', {\n";
echo "  method: 'POST',\n";
echo "  headers: {\n";
echo "    'Content-Type': 'application/json'\n";
echo "  },\n";
echo "  body: JSON.stringify({\n";
echo "    telefone: '64993106841',\n";
echo "    codcal: 885\n";
echo "  })\n";
echo "})\n";
echo ".then(response => response.json())\n";
echo ".then(data => {\n";
echo "  if (data.success) {\n";
echo "    console.log('Holerite enviado:', data.data);\n";
echo "    console.log('Imagem:', data.data.imagem.imagem_url);\n";
echo "  } else {\n";
echo "    console.error('Erro:', data.error);\n";
echo "  }\n";
echo "});";
echo "</pre>\n";
echo "</div>\n";

echo "<h2>✅ Resposta de Sucesso:</h2>\n";
echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>\n";
echo json_encode([
    'success' => true,
    'message' => 'Holerite enviado como imagem via WhatsApp',
    'data' => [
        'funcionario' => [
            'nome' => 'KAIO DOMINGOS BAIA',
            'matricula' => '0',
            'cpf' => '12345678901'
        ],
        'holerite' => [
            'periodo' => 885,
            'total_proventos' => '14.196,00',
            'total_descontos' => '4.595,39',
            'valor_liquido' => '9.600,61',
            'total_itens' => 9
        ],
        'whatsapp' => [
            'numero' => '64993106841',
            'mensagem_enviada' => true
        ],
        'imagem' => [
            'imagem_url' => 'https://www.grupofarias.com.br/pontos/holerites/holerite_0_885_20250115120000.png',
            'arquivo_local' => 'holerite_0_885_20250115120000.png'
        ]
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "</pre>\n";
echo "</div>\n";

echo "<h2>❌ Resposta de Erro:</h2>\n";
echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>\n";
echo json_encode([
    'success' => false,
    'error' => 'Colaborador não encontrado com o telefone informado: 64993106841',
    'timestamp' => '2025-01-15 12:00:00'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "</pre>\n";
echo "</div>\n";

echo "<h2>📋 Códigos de Status HTTP:</h2>\n";
echo "<ul>\n";
echo "<li><strong>200:</strong> Sucesso - Holerite gerado e enviado</li>\n";
echo "<li><strong>400:</strong> Erro nos parâmetros (telefone ou codcal ausentes)</li>\n";
echo "<li><strong>500:</strong> Erro interno (colaborador não encontrado, erro no banco, etc.)</li>\n";
echo "</ul>\n";

echo "<h2>🎯 Funcionalidades:</h2>\n";
echo "<ul>\n";
echo "<li>✅ Busca colaborador por telefone</li>\n";
echo "<li>✅ Recupera dados reais do holerite do período 885</li>\n";
echo "<li>✅ Gera imagem no formato oficial</li>\n";
echo "<li>✅ Envia via WhatsApp automaticamente</li>\n";
echo "<li>✅ Retorna URL da imagem gerada</li>\n";
echo "</ul>\n";

echo "<h2>📱 Formato da Imagem:</h2>\n";
echo "<ul>\n";
echo "<li>📄 Título: DEMONSTRATIVO DE PAGAMENTO DE SALARIO</li>\n";
echo "<li>🏢 Empresa: VALE VERDE EMP AGRICOLAS LTDA</li>\n";
echo "<li>👤 Dados do funcionário (nome, matrícula, admissão, cargo, local)</li>\n";
echo "<li>🏦 Dados bancários (banco, agência, conta)</li>\n";
echo "<li>📊 Tabela de eventos com valores reais</li>\n";
echo "<li>💰 Resumo financeiro (proventos, descontos, líquido)</li>\n";
echo "<li>🔖 Watermark GRUPO FARIAS</li>\n";
echo "<li>✍️ Linha de assinatura</li>\n";
echo "</ul>\n";

echo "<p><em>Documentação gerada em: " . date('d/m/Y H:i:s') . "</em></p>\n";
?>
