# API Informe de Rendimento

## Descrição
API para consulta e envio de Informe de Rendimento via WhatsApp, seguindo o padrão das outras APIs do sistema.

## Funcionalidades
- Consulta de dados de rendimento por telefone do colaborador
- Relacionamento com tabelas `R034FUN` (funcionário) e `R030EMP` (empresa)
- Consulta na tabela `R051REN` para dados de rendimento
- Envio automático via WhatsApp com formatação padronizada
- Suporte a diferentes variações de telefone (com/sem 9)

## Parâmetros

### Obrigatórios
- `telefone`: Número do telefone do colaborador

### Opcionais
- `ano`: Ano do informe (padrão: 2024)

## Exemplos de Uso

### GET
```
https://www.grupofarias.com.br/pontos/api_informe_rendimento.php?telefone=556493106841&ano=2024
```

### POST (JSON)
```json
{
  "telefone": "556493106841",
  "ano": 2024
}
```

## Estrutura da Consulta SQL

```sql
SELECT SUM(BASIRF) TOTAL_RENDIMENTO,
       SUM(CONPRE) CONTRIBUICAO_PREVIDENCIA,
       SUM(VALIRF) IMPOSTO_RETIDO_FONTE,       
       SUM(VAL13S) DTS_SALARIO,
       SUM(IRF13S) IMPOSTO_13_SALARIO,
       SUM(SEGVID) SEGURO_VIDA
FROM vetorh.r051ren
WHERE CPFCGC = :cpfcgc
AND CMPREN >= :data_inicio
AND CMPREN <= :data_fim
GROUP BY NUMEMP, CPFCGC
```

## Estrutura de Resposta

### Sucesso
```json
{
  "success": true,
  "message": "Informe de rendimento consultado e enviado via WhatsApp",
  "data": {
    "funcionario": {
      "nome": "KAIO DOMINGOS BAIA",
      "matricula": "2300548",
      "cpf": "02910682196"
    },
    "informe_rendimento": {
      "ano": 2024,
      "total_rendimento": "43.160,00",
      "contribuicao_previdencia": "2.939,37",
      "imposto_retido_fonte": "7.716,21",
      "dts_salario": "3.596,67",
      "imposto_13_salario": "642,84",
      "seguro_vida": "0,00"
    },
    "whatsapp": {
      "numero": "556493106841",
      "mensagem_enviada": true,
      "response": { ... }
    }
  }
}
```

### Erro
```json
{
  "success": false,
  "error": true,
  "message": "Descrição do erro",
  "timestamp": "2025-01-XX XX:XX:XX"
}
```

## Mensagem WhatsApp

### Formato
```
🏢 *GRUPO FARIAS*

Olá KAIO DOMINGOS BAIA!

📄 *INFORME DE RENDIMENTOS 2024*

💰 *Resumo dos Rendimentos:*
• Total de Rendimentos: R$ 43.160,00
• Contribuição Previdenciária: R$ 2.939,37
• Imposto Retido na Fonte: R$ 7.716,21
• 13º Salário: R$ 3.596,67
• Imposto 13º Salário: R$ 642,84
• Seguro de Vida: R$ 0,00

📋 *Informações:*
• Matrícula: 2300548
• CPF: 02910682196
• Empresa: VALE VERDE EMP AGRICOLAS LTDA
• Ano de Referência: 2024

📝 *Importante:*
• Este informe é necessário para declaração do IR 2025
• Guarde este documento para sua declaração

❓ Para mais informações sobre o informe de rendimentos, entre em contato com o RH.

_Mensagem enviada automaticamente pelo sistema._
```

## Campos do Informe

| Campo | Descrição |
|-------|-----------|
| `TOTAL_RENDIMENTO` | Total dos rendimentos (BASIRF) |
| `CONTRIBUICAO_PREVIDENCIA` | Contribuição previdenciária (CONPRE) |
| `IMPOSTO_RETIDO_FONTE` | Imposto retido na fonte (VALIRF) |
| `DTS_SALARIO` | 13º salário (VAL13S) |
| `IMPOSTO_13_SALARIO` | Imposto do 13º salário (IRF13S) |
| `SEGURO_VIDA` | Seguro de vida (SEGVID) |

## Tratamento de Telefone

A API testa automaticamente diferentes variações do telefone:
- Telefone original
- Com 9 adicionado após o DDD (se 10 dígitos)
- Sem 9 (se 11 dígitos e começar com 9)
- Sem 9 da posição 3 (se 10 dígitos)

## Logs de Debug

- `DEBUG INFORME RENDIMENTO: Colaborador encontrado`
- `DEBUG INFORME RENDIMENTO: Dados encontrados`
- `DEBUG INFORME RENDIMENTO: Erro ao enviar WhatsApp`

## Integração WhatsApp

- URL: `https://chat-82api.jetsalesbrasil.com/v1/api/external/ace32b90-08b2-4162-b80b-7309749b0226`
- Token: Configurado nas constantes
- External Key: `INFORME_RENDIMENTO_` + uniqid()

## Códigos de Status HTTP

- `200`: Sucesso
- `400`: Parâmetros inválidos
- `500`: Erro interno do servidor

## Dependências

- Oracle Database (tabelas VETORH)
- PHP com extensão OCI8
- cURL para integração WhatsApp
- Configurações de WhatsApp API




