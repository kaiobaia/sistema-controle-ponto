# API de Holerite via WhatsApp

Esta API permite gerar holerites em formato HTML e enviá-los via WhatsApp para os funcionários.

## 📋 Funcionalidades

- ✅ Busca dados do holerite no banco Oracle
- ✅ Gera holerite em HTML responsivo e moderno
- ✅ Envia holerite via WhatsApp usando API externa
- ✅ Validação de dados e tratamento de erros
- ✅ Interface visual baseada nos dados da imagem fornecida
- ✅ Suporte a impressão e visualização mobile

## 🚀 Arquivos Criados

### 1. `api_holerite_whatsapp.php`
API completa que gera imagem do holerite e envia via WhatsApp. Requer `wkhtmltoimage` instalado.

### 2. `api_whatsapp_simples.php` ⭐ **RECOMENDADO**
API simplificada que gera HTML e envia link via WhatsApp. Mais fácil de implementar.

### 3. `gerador_imagem_holerite.php`
Gerador apenas de HTML do holerite, sem envio via WhatsApp.

### 4. `exemplo_uso_api.php`
Exemplos de como usar a API em diferentes linguagens.

## 📡 Como Usar a API

### Endpoint
```
POST /pontos/api_whatsapp_simples.php
```

### Parâmetros (JSON)
```json
{
    "telefone": "64993106841",
    "codcal": 630
}
```

### Exemplo via cURL
```bash
curl --location 'http://localhost/pontos/api_whatsapp_simples.php' \
--header 'Content-Type: application/json' \
--data '{
    "telefone": "64993106841",
    "codcal": 630
}'
```

### Exemplo via JavaScript
```javascript
fetch('http://localhost/pontos/api_whatsapp_simples.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        telefone: '64993106841',
        codcal: 630
    })
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log('Holerite enviado!', data);
    } else {
        console.error('Erro:', data.error);
    }
});
```

## 📊 Estrutura da Resposta

### Sucesso
```json
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
            "numero": "11999999999",
            "mensagem_enviada": true,
            "response": {...}
        },
        "links": {
            "holerite_url": "http://localhost/pontos/holerites/holerite_283_630_20240101120000.html",
            "arquivo_local": "holerite_283_630_20240101120000.html"
        }
    }
}
```

### Erro
```json
{
    "success": false,
    "error": "Mensagem de erro descritiva",
    "timestamp": "2024-01-01 12:00:00"
}
```

## 🎨 Características do Holerite Gerado

### Design
- Interface moderna e responsiva
- Cores baseadas na identidade visual do Grupo Farias
- Layout otimizado para impressão e visualização mobile
- Tabela organizada com os dados do holerite

### Dados Exibidos
- **Cabeçalho**: Logo e título do holerite
- **Dados do Funcionário**: Nome, matrícula, CPF, período
- **Tabela Detalhada**: Código, descrição, referência, valor, tipo
- **Resumo Financeiro**: Total de proventos, descontos e valor líquido
- **Rodapé**: Informações da empresa e data de geração

### Responsividade
- Adapta-se automaticamente a diferentes tamanhos de tela
- Botão de impressão disponível
- Layout otimizado para mobile

## 🔧 Configuração

### 1. Banco de Dados
A API usa a mesma conexão do sistema existente (`conn.php`):
- Oracle Database
- Tabelas: `VETORH.R034FUN`, `VETORH.R034CPL`, `VETORH.R008EVC`, `VETORH.r046ver`
- Busca colaborador por telefone usando a mesma lógica da API de banco de horas

### 2. WhatsApp API
Configure as credenciais no arquivo da API:
```php
$WHATSAPP_API_URL = 'https://chat-82api.jetsalesbrasil.com/v1/api/external/aa7deb3c-dd06-41c6-9105-7f1d367afc9b/';
$WHATSAPP_TOKEN = 'SEU_TOKEN_AQUI';
```

### 3. Diretórios
A API criará automaticamente:
- `/pontos/holerites/` - Para armazenar os holerites gerados
- `/pontos/temp/` - Para arquivos temporários (se necessário)

## 📱 Mensagem WhatsApp

A mensagem enviada inclui:
- Saudação personalizada
- Resumo dos dados do holerite
- Totais de proventos, descontos e líquido
- Link para visualizar o holerite completo
- Instruções para impressão

## 🔒 Segurança

- Validação de todos os parâmetros de entrada
- Sanitização de dados HTML
- Verificação de existência do funcionário
- Tratamento de erros detalhado
- Timeout nas requisições externas

## 🚨 Tratamento de Erros

A API trata os seguintes erros:
- Parâmetros obrigatórios ausentes
- Funcionário não encontrado
- Erro de conexão com banco de dados
- Erro na API do WhatsApp
- Formato de número inválido

## 📈 Monitoramento

Cada holerite gerado inclui:
- Timestamp de geração
- Número de itens processados
- Status do envio via WhatsApp
- URL de acesso ao holerite

## 🛠️ Manutenção

### Limpeza de Arquivos
Os holerites são salvos permanentemente. Para limpeza automática, você pode:
1. Implementar um cron job para remover arquivos antigos
2. Adicionar data de expiração nos links
3. Mover arquivos antigos para backup

### Logs
Para adicionar logs, modifique a API para registrar:
- Tentativas de envio
- Erros ocorridos
- Dados dos holerites gerados

## 🔄 Integração com Sistema Existente

A API pode ser integrada ao sistema existente:
1. Adicionar botão "Enviar Holerite" na interface
2. Chamar a API via AJAX
3. Exibir status do envio para o usuário
4. Registrar histórico de envios

## 📞 Suporte

Para dúvidas ou problemas:
1. Verifique os logs do servidor web
2. Teste a conexão com o banco de dados
3. Valide as credenciais da API WhatsApp
4. Verifique permissões dos diretórios

---

**Desenvolvido para o Grupo Farias** 🏢
