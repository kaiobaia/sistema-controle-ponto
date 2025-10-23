<?php
/**
 * Exemplo de uso da API de Holerite via WhatsApp
 * Este arquivo demonstra como usar a API criada
 */

// Configuração do exemplo
$API_URL = 'http://localhost/pontos/api_whatsapp_simples.php';

// Dados de exemplo (substitua pelos dados reais)
$dados_exemplo = [
    'telefone' => '11999999999',  // Número do telefone do colaborador (mesmo padrão do banco de horas)
    'codcal' => 630               // Código do período/competência
];

echo "<h1>Exemplo de Uso da API de Holerite WhatsApp</h1>\n";
echo "<h2>Dados que serão enviados:</h2>\n";
echo "<pre>" . json_encode($dados_exemplo, JSON_PRETTY_PRINT) . "</pre>\n";

// Função para fazer requisição POST
function fazerRequisicao($url, $dados) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'response' => $response,
        'http_code' => $http_code,
        'error' => $error
    ];
}

// Fazer a requisição (descomente para testar)
/*
echo "<h2>Fazendo requisição para a API...</h2>\n";

$resultado = fazerRequisicao($API_URL, $dados_exemplo);

echo "<h3>Resposta da API:</h3>\n";
echo "<p><strong>Código HTTP:</strong> " . $resultado['http_code'] . "</p>\n";

if ($resultado['error']) {
    echo "<p><strong>Erro cURL:</strong> " . htmlspecialchars($resultado['error']) . "</p>\n";
} else {
    echo "<pre>" . htmlspecialchars($resultado['response']) . "</pre>\n";
    
    // Tentar decodificar JSON
    $json_response = json_decode($resultado['response'], true);
    if ($json_response) {
            if ($json_response['success']) {
                echo "<h3>✅ Sucesso!</h3>\n";
                echo "<p>Holerite enviado com sucesso para: " . $dados_exemplo['telefone'] . "</p>\n";
                if (isset($json_response['data']['links']['holerite_url'])) {
                    echo "<p><strong>Link do holerite:</strong> <a href='" . $json_response['data']['links']['holerite_url'] . "' target='_blank'>Ver Holerite</a></p>\n"; 
                }
            } else {
                echo "<h3>❌ Erro!</h3>\n";
                echo "<p><strong>Erro:</strong> " . htmlspecialchars($json_response['error']) . "</p>\n";
            }
    }
}
*/

echo "<h2>Como usar via cURL:</h2>\n";
echo "<pre>";
echo htmlspecialchars('curl --location "' . $API_URL . '" \\
--header "Content-Type: application/json" \\
--data \'' . json_encode($dados_exemplo) . '\'');
echo "</pre>\n";

echo "<h2>Como usar via JavaScript (fetch):</h2>\n";
echo "<pre>";
echo htmlspecialchars('
fetch("' . $API_URL . '", {
    method: "POST",
    headers: {
        "Content-Type": "application/json"
    },
    body: JSON.stringify(' . json_encode($dados_exemplo) . ')
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log("Holerite enviado com sucesso!", data);
        // Abrir link do holerite
        if (data.data.links.holerite_url) {
            window.open(data.data.links.holerite_url, "_blank");
        }
    } else {
        console.error("Erro:", data.error);
    }
})
.catch(error => {
    console.error("Erro na requisição:", error);
});
');
echo "</pre>\n";

echo "<h2>Como usar via PHP:</h2>\n";
echo "<pre>";
echo htmlspecialchars('
$dados = [
    "numcad" => 283,
    "codcal" => 630,
    "numero_whatsapp" => "5511999999999"
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "' . $API_URL . '");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$resultado = json_decode($response, true);

if ($resultado && $resultado["success"]) {
    echo "Holerite enviado com sucesso!";
    echo "Link: " . $resultado["data"]["links"]["holerite_url"];
} else {
    echo "Erro: " . ($resultado["error"] ?? "Erro desconhecido");
}
');
echo "</pre>\n";

echo "<h2>Estrutura da Resposta de Sucesso:</h2>\n";
echo "<pre>";
echo htmlspecialchars('
{
    "success": true,
    "message": "Holerite enviado com sucesso via WhatsApp",
    "data": {
        "funcionario": {
            "nome": "NOME DO FUNCIONARIO",
            "matricula": "12345",
            "cpf": "12345678901"
        },
        "holerite": {
            "periodo": "630",
            "total_proventos": "4.368,00",
            "total_descontos": "906,07",
            "valor_liquido": "3.461,93",
            "total_itens": 15
        },
        "whatsapp": {
            "numero": "5511999999999",
            "mensagem_enviada": true,
            "response": {...}
        },
        "links": {
            "holerite_url": "http://localhost/pontos/holerites/holerite_283_630_20240101120000.html",
            "arquivo_local": "holerite_283_630_20240101120000.html"
        }
    }
}
');
echo "</pre>\n";

echo "<h2>Estrutura da Resposta de Erro:</h2>\n";
echo "<pre>";
echo htmlspecialchars('
{
    "success": false,
    "error": "Mensagem de erro descritiva",
    "timestamp": "2024-01-01 12:00:00"
}
');
echo "</pre>\n";

echo "<h2>Parâmetros da API:</h2>\n";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr><th>Parâmetro</th><th>Tipo</th><th>Obrigatório</th><th>Descrição</th></tr>\n";
echo "<tr><td>telefone</td><td>string</td><td>Sim</td><td>Número do telefone do colaborador (mesmo padrão do banco de horas)</td></tr>\n";
echo "<tr><td>codcal</td><td>integer</td><td>Sim</td><td>Código do período/competência do holerite</td></tr>\n";
echo "</table>\n";

echo "<h2>Observações Importantes:</h2>\n";
echo "<ul>\n";
echo "<li>O parâmetro 'telefone' segue o mesmo padrão da API de banco de horas</li>\n";
echo "<li>A API busca automaticamente o colaborador pelo telefone cadastrado no sistema</li>\n";
echo "<li>O telefone é validado e formatado automaticamente (remove código do país 55 se presente)</li>\n";
echo "<li>O holerite será salvo como arquivo HTML e um link será enviado via WhatsApp</li>\n";
echo "<li>A API valida se o colaborador existe no banco de dados</li>\n";
echo "<li>O arquivo HTML gerado é responsivo e pode ser visualizado em qualquer dispositivo</li>\n";
echo "<li>Os holerites são salvos na pasta 'holerites/' do projeto</li>\n";
echo "</ul>\n";
?>
