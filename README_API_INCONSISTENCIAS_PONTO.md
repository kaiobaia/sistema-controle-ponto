# API de Inconsistências de Ponto

API para consulta de inconsistências de ponto dos colaboradores do Grupo Farias, com envio automático via WhatsApp.

## 📋 Descrição

Esta API busca todas as inconsistências de ponto de um colaborador nos **últimos 30 dias**, incluindo:
- ⚠️ Atrasos (CODSIT 103)
- ❌ Sem marcações (CODSIT 15)

A API retorna os dados em formato JSON e envia automaticamente uma mensagem formatada via WhatsApp para o colaborador.

## 🔧 Endpoint

```
GET/POST: api_inconsistencias_ponto.php
```

## 📥 Parâmetros

| Parâmetro | Tipo   | Obrigatório | Descrição                           |
|-----------|--------|-------------|-------------------------------------|
| telefone  | string | Sim         | Número de telefone do colaborador   |

### Formato do Telefone

- O telefone pode ser enviado com ou sem formatação
- Aceita formatos: `11999999999`, `(11) 99999-9999`, `+55 11 99999-9999`
- A API remove automaticamente caracteres não numéricos
- Tenta variações com e sem o dígito 9 adicional

## 📤 Exemplos de Requisição

### GET Request

```bash
curl "http://localhost/pontos/api_inconsistencias_ponto.php?telefone=11999999999"
```

### POST Request (JSON)

```bash
curl -X POST http://localhost/pontos/api_inconsistencias_ponto.php \
  -H "Content-Type: application/json" \
  -d '{"telefone":"11999999999"}'
```

### POST Request (Form Data)

```bash
curl -X POST http://localhost/pontos/api_inconsistencias_ponto.php \
  -d "telefone=11999999999"
```

## 📨 Respostas

### ✅ Sucesso COM Inconsistências

```json
{
    "success": true,
    "message": "Inconsistências de ponto consultadas e enviadas via WhatsApp",
    "data": {
        "funcionario": {
            "nome": "JOÃO DA SILVA",
            "matricula": "12345"
        },
        "tem_inconsistencias": true,
        "inconsistencias": {
            "total": 3,
            "periodo_consultado": "Últimos 30 dias",
            "detalhes": [
                {
                    "data": "08/10/2024",
                    "empresa": 1,
                    "marcacoes": ["08:15", "12:00", "13:00", "18:00"],
                    "atraso_minutos": 15,
                    "tipo_inconsistencia": 103,
                    "descricao_inconsistencia": "Atraso"
                },
                {
                    "data": "07/10/2024",
                    "empresa": 1,
                    "marcacoes": [],
                    "atraso_minutos": 0,
                    "tipo_inconsistencia": 15,
                    "descricao_inconsistencia": "Sem marcações"
                }
            ]
        },
        "whatsapp": {
            "numero": "11999999999",
            "mensagem_enviada": true,
            "response": {
                "id": "mensagem_id_exemplo",
                "status": "sent"
            }
        }
    }
}
```

### ✅ Sucesso SEM Inconsistências

```json
{
    "success": true,
    "message": "Consultado - Não há inconsistências de ponto nos últimos 30 dias",
    "data": {
        "funcionario": {
            "nome": "JOÃO DA SILVA",
            "matricula": "12345"
        },
        "tem_inconsistencias": false,
        "inconsistencias": {
            "total": 0,
            "periodo_consultado": "Últimos 30 dias",
            "detalhes": []
        },
        "whatsapp": {
            "numero": "11999999999",
            "mensagem_enviada": true,
            "response": {
                "id": "mensagem_id_exemplo",
                "status": "sent"
            }
        }
    }
}
```

### ❌ Erro - Colaborador não encontrado

```json
{
    "error": true,
    "message": "Colaborador não encontrado com o telefone informado: 11999999999 (testadas variações: 11999999999, 1199999999)"
}
```

### ❌ Erro - Parâmetro faltando

```json
{
    "success": false,
    "error": true,
    "message": "Erro ao processar requisição: Parâmetro obrigatório: telefone",
    "timestamp": "2024-10-09 14:30:00"
}
```

## 📱 Mensagem WhatsApp

### Com Inconsistências

```
🏢 *GRUPO FARIAS*

Olá JOÃO DA SILVA!

⚠️ *INCONSISTÊNCIAS DE PONTO*

📅 *Foram encontradas 3 inconsistência(s) nos últimos 30 dias:*

⚠️ *Inconsistência 1:*
• 📅 Data: 08/10/2024
• ❌ Tipo: Atraso
• ⏱️ Atraso: 00:15
• 🕐 Marcações: 08:15, 12:00, 13:00, 18:00

⚠️ *Inconsistência 2:*
• 📅 Data: 07/10/2024
• ❌ Tipo: Sem marcações
• 🕐 Marcações: Nenhuma

⚠️ *Inconsistência 3:*
• 📅 Data: 05/10/2024
• ❌ Tipo: Atraso
• ⏱️ Atraso: 00:30
• 🕐 Marcações: 08:30, 12:00, 13:00

📋 *Informações:*
• Matrícula: 12345

❓ Para regularizar suas inconsistências, entre em contato com o RH.

_Mensagem enviada automaticamente pelo sistema._
```

### Sem Inconsistências

```
🏢 *GRUPO FARIAS*

Olá JOÃO DA SILVA!

⚠️ *INCONSISTÊNCIAS DE PONTO*

✅ *Boa notícia!*
Não há inconsistências de ponto nos últimos 30 dias.

📋 *Informações:*
• Matrícula: 12345

Continue mantendo suas marcações em dia! 👏

_Mensagem enviada automaticamente pelo sistema._
```

## 🔍 Tipos de Inconsistências

| Código | Descrição       | Detalhes                                           |
|--------|-----------------|---------------------------------------------------|
| 103    | Atraso          | Colaborador chegou atrasado                       |
| 15     | Sem marcações   | Colaborador não registrou nenhuma marcação no dia |

## 🗄️ Tabelas Utilizadas

- **R034FUN**: Dados dos funcionários
- **R034CPL**: Complemento (telefone)
- **R070ACC**: Registro de marcações de ponto
- **R066SIT**: Situações/inconsistências de ponto
- **R024CAR**: Cargos
- **R070JUS**: Justificativas de inconsistências

## 📊 Regras de Negócio

1. **Período de Consulta**: Últimos 30 dias a partir da data atual
2. **Inconsistências Consideradas**: 
   - CODSIT 103 (Atraso)
   - CODSIT 15 (Sem marcações)
3. **Filtro de Justificativas**: Não retorna inconsistências já autorizadas (CODJMA = 70)
4. **Agrupamento**: Inconsistências são agrupadas por data
5. **Ordenação**: Decrescente por data (mais recentes primeiro)

## 🔐 Segurança

- A API valida o telefone e busca variações automaticamente
- Remove colaboradores inativos (SITAFA <> 7)
- Remove colaboradores com contrato tipo 6 (TIPCON <> 6)
- Valida a existência do colaborador antes de buscar inconsistências

## ⚙️ Configurações

### Banco de Dados

```php
$conn = oci_connect('vetorh', 'rec07gf7', '192.168.50.11/senior');
```

### API WhatsApp

```php
$WHATSAPP_API_URL = 'https://chat-82api.jetsalesbrasil.com/v1/api/external/ace32b90-08b2-4162-b80b-7309749b0226';
$WHATSAPP_TOKEN = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...';
```

## 🐛 Tratamento de Erros

A API trata os seguintes erros:

1. **Erro de Conexão**: Problema ao conectar com o banco de dados
2. **Colaborador Não Encontrado**: Telefone não cadastrado ou inválido
3. **Erro na Consulta**: Problema ao executar a query SQL
4. **Erro WhatsApp**: Falha ao enviar mensagem via WhatsApp
5. **Método Inválido**: Uso de método HTTP não suportado

## 📝 Notas Importantes

- A API sempre retorna HTTP 200 para consultas bem-sucedidas, mesmo sem inconsistências
- A mensagem WhatsApp é enviada independentemente de haver ou não inconsistências
- O campo `tem_inconsistencias` indica se foram encontradas inconsistências
- As marcações são formatadas no formato HH:MM
- O atraso é exibido em horas e minutos (HH:MM)

## 🔄 Fluxo de Funcionamento

1. Recebe o telefone do colaborador
2. Valida e limpa o número de telefone
3. Busca o colaborador no banco (tenta variações)
4. Consulta inconsistências dos últimos 30 dias
5. Agrupa inconsistências por data
6. Formata mensagem para WhatsApp
7. Envia mensagem via WhatsApp
8. Retorna JSON com todos os dados

## 💡 Exemplos de Uso

### PHP

```php
$telefone = '11999999999';
$url = "http://localhost/pontos/api_inconsistencias_ponto.php?telefone=$telefone";

$response = file_get_contents($url);
$data = json_decode($response, true);

if ($data['success']) {
    echo "Total de inconsistências: " . $data['data']['inconsistencias']['total'];
} else {
    echo "Erro: " . $data['message'];
}
```

### JavaScript (Fetch)

```javascript
fetch('http://localhost/pontos/api_inconsistencias_ponto.php?telefone=11999999999')
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      console.log('Total de inconsistências:', data.data.inconsistencias.total);
      console.log('Detalhes:', data.data.inconsistencias.detalhes);
    } else {
      console.error('Erro:', data.message);
    }
  });
```

## 📞 Suporte

Para dúvidas ou problemas com a API, entre em contato com o setor de TI.

---

**Desenvolvido para Grupo Farias**  
*Última atualização: Outubro 2024*



