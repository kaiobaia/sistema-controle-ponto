# API de Banco de Horas

## Descrição
API para consulta de banco de horas de colaboradores por telefone. Retorna o saldo de horas em formato `HH:MM`, informações do colaborador e uma mensagem amigável.

## Endpoint
```
api_banco_horas.php
```

## Métodos Suportados
- **GET**: Parâmetros via query string
- **POST**: Parâmetros via form-data ou JSON

## Parâmetros

| Parâmetro | Tipo | Obrigatório | Descrição |
|-----------|------|-------------|-----------|
| `telefone` | string | Sim | Número do telefone do colaborador (apenas números). O código do país "55" será removido. A API tentará encontrar o colaborador tanto com quanto sem o "9" adicional. |

## Exemplos de Uso

### 1. Requisição GET
```
GET http://seu-dominio/pontos/api_banco_horas.php?telefone=11999999999
GET http://seu-dominio/pontos/api_banco_horas.php?telefone=5511999999999
```

### 2. Requisição POST (Form-Data)
```http
POST http://seu-dominio/pontos/api_banco_horas.php
Content-Type: application/x-www-form-urlencoded

telefone=11999999999
```

### 3. Requisição POST (JSON)
```http
POST http://seu-dominio/pontos/api_banco_horas.php
Content-Type: application/json

{
  "telefone": "11999999999"
}
```

**Nota:** A API aceita telefones com ou sem o código do país "55" e com ou sem o "9" adicional. A API tentará encontrar o colaborador em ambos os formatos:
- `11999999999` (formato padrão - tenta encontrar como está)
- `5511999999999` (com código do país - remove "55" e tenta `11999999999`)
- `1199999999` (sem 9 - tenta `1199999999` e também `91199999999`)
- `551199999999` (com código 55 mas sem 9 - tenta `1199999999` e `91199999999`)

### 4. Exemplos com cURL

**GET:**
```bash
curl "http://seu-dominio/pontos/api_banco_horas.php?telefone=11999999999"
```

**POST (Form-Data):**
```bash
curl -X POST "http://seu-dominio/pontos/api_banco_horas.php" \
  -d "telefone=11999999999"
```

**POST (JSON):**
```bash
curl -X POST "http://seu-dominio/pontos/api_banco_horas.php" \
  -H "Content-Type: application/json" \
  -d '{"telefone": "11999999999"}'
```

### 5. Exemplo com JavaScript (Fetch API)

**GET:**
```javascript
fetch('http://seu-dominio/pontos/api_banco_horas.php?telefone=11999999999')
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      console.log('Banco de Horas:', data.horas_formatadas);
      console.log('Mensagem:', data.mensagem_amigavel);
    } else {
      console.log('Erro:', data.message);
    }
  })
  .catch(error => console.error('Erro:', error));
```

**POST (JSON):**
```javascript
fetch('http://seu-dominio/pontos/api_banco_horas.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    telefone: '11999999999'
  })
})
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      console.log('Banco de Horas:', data.horas_formatadas);
      console.log('Mensagem:', data.mensagem_amigavel);
    } else {
      console.log('Erro:', data.message);
    }
  })
  .catch(error => console.error('Erro:', error));
```

### 6. Exemplo com PHP

```php
<?php
// Usando file_get_contents
$telefone = '11999999999';
$url = "http://seu-dominio/pontos/api_banco_horas.php?telefone={$telefone}";

$response = file_get_contents($url);
$data = json_decode($response, true);

if (isset($data['success']) && $data['success']) {
    echo "Banco de Horas: " . $data['horas_formatadas'];
    echo "\nMensagem: " . $data['mensagem_amigavel'];
} else {
    echo "Erro: " . $data['message'];
}

// Ou usando cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://seu-dominio/pontos/api_banco_horas.php");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['telefone' => '11999999999']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
print_r($data);
?>
```

## Respostas da API

### Colaborador encontrado (HTTP 200)
```json
{
  "success": true,
  "encontrado": true,
  "horas_formatadas": "08:30",
  "horas_decimal": 8.5,
  "tipo_saldo": "crédito",
  "mensagem_amigavel": "Olá João Silva! Seu saldo de banco de horas é de +08:30 (crédito)."
}
```

ou para saldo negativo:

```json
{
  "success": true,
  "encontrado": true,
  "horas_formatadas": "-02:15",
  "horas_decimal": -2.25,
  "tipo_saldo": "débito",
  "mensagem_amigavel": "Olá Maria Santos! Seu saldo de banco de horas é de -02:15 (débito)."
}
```

### Colaborador não encontrado (HTTP 200)
```json
{
  "success": true,
  "encontrado": false,
  "message": "Colaborador não encontrado com o telefone informado",
  "telefone": "11999999999",
  "mensagem_amigavel": "Telefone não cadastrado na base de dados. Por favor, procure o RH para cadastrar seu número."
}
```

### Erro - Parâmetro faltando (HTTP 400)
```json
{
  "error": true,
  "message": "Parâmetro obrigatório não fornecido: telefone",
  "exemplo_uso": {
    "GET": "api_banco_horas.php?telefone=11999999999",
    "POST": "form-data ou json: {\"telefone\": \"11999999999\"}"
  }
}
```

### Erro no servidor (HTTP 500)
```json
{
  "error": true,
  "message": "Erro ao executar consulta: [detalhes do erro]"
}
```

## Estrutura da Resposta

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `success` | boolean | Indica se a consulta foi bem-sucedida |
| `error` | boolean | Indica se houve erro |
| `horas_formatadas` | string | Saldo de horas no formato HH:MM (com sinal - se negativo) |
| `horas_decimal` | float | Saldo de horas em formato decimal |
| `tipo_saldo` | string | Tipo do saldo: "crédito" ou "débito" |
| `mensagem_amigavel` | string | Mensagem personalizada com nome e saldo |
| `message` | string | Mensagem informativa ou de erro |

## Observações

1. O saldo de horas pode ser **positivo** (crédito) ou **negativo** (débito)
2. O formato de horas é sempre `HH:MM`, com o sinal `-` quando negativo
3. A API suporta CORS (Cross-Origin Resource Sharing)
4. Os dados são retornados em formato JSON com charset UTF-8
5. **Tratamento do código do país**: A API automaticamente remove o código "55" se presente no telefone
6. **Busca inteligente**: A API tenta encontrar o colaborador tanto com quanto sem o "9" adicional (para qualquer DDD)
7. **Formato do telefone**: Aceita telefones com 10-13 dígitos (com ou sem código 55 e 9 adicional)
8. **Busca no banco**: A consulta concatena `dddtel + numtel` para encontrar o colaborador

### Exemplos de telefones aceitos:
- `11999999999` → Tenta buscar por `11999999999`
- `5511999999999` → Remove "55" e tenta buscar por `11999999999`
- `1199999999` → Tenta buscar por `1199999999`, depois `11999999999`, depois `119999999`
- `911999999999` → Tenta buscar por `911999999999` e também por `11999999999`
- `6292929747` → Tenta buscar por `6292929747`, depois `62992929747`, depois `622929747`
- `556292929747` → Remove "55" e tenta buscar por `6292929747`, depois `62992929747`, depois `622929747`

## Segurança

⚠️ **IMPORTANTE**: Esta API atualmente não possui autenticação. Para uso em produção, considere implementar:

- Token de autenticação (Bearer Token)
- API Key
- Autenticação OAuth
- Limitação de taxa (Rate Limiting)
- Lista de IPs permitidos
- Validação adicional de dados

## Manutenção

Para ajustar as credenciais do banco de dados, edite a função `conectar_db()` no arquivo `api_banco_horas.php`.

