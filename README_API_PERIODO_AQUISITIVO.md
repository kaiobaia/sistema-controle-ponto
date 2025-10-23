# API de Período Aquisitivo de Férias

Esta API permite consultar períodos aquisitivos de férias em aberto, relacionando com as tabelas de funcionários e empresas.

## Endpoint

```
GET/POST /api_periodo_aquisitivo.php
```

## Parâmetros

### GET (URL)
- `numcad` (opcional): Matrícula do funcionário
- `numemp` (opcional): Código da empresa
- `tipo` (opcional): Tipo de consulta (`geral`, `matricula`, `empresa`)

### POST (JSON)
```json
{
    "numcad": 548,
    "numemp": 23,
    "tipo": "matricula"
}
```

## Tipos de Consulta

### 1. Consulta Geral
Retorna todos os períodos aquisitivos em aberto.

**Exemplo:**
```
GET /api_periodo_aquisitivo.php
```

### 2. Consulta por Matrícula
Retorna períodos aquisitivos de um funcionário específico.

**Exemplo:**
```
GET /api_periodo_aquisitivo.php?numcad=548&tipo=matricula
```

### 3. Consulta por Empresa
Retorna períodos aquisitivos de todos os funcionários de uma empresa.

**Exemplo:**
```
GET /api_periodo_aquisitivo.php?numemp=23&tipo=empresa
```

## Resposta de Sucesso

```json
{
    "success": true,
    "data": [
        {
            "empresa": {
                "codigo": 23,
                "nome": "VALE VERDE EMP AGRICOLAS LTDA"
            },
            "funcionario": {
                "matricula": 548,
                "nome": "KAIO DOMINGOS BAIA"
            },
            "periodo_aquisitivo": {
                "inicio": "26/08/2024",
                "fim": "25/08/2025",
                "dias_direito": 30.00,
                "dias_debitados": 0.00,
                "dias_saldo": 30.00,
                "situacao": 0
            }
        }
    ],
    "total": 1,
    "timestamp": "2025-01-10 15:30:00"
}
```

## Resposta de Erro

```json
{
    "success": false,
    "error": "Mensagem de erro",
    "timestamp": "2025-01-10 15:30:00"
}
```

## Campos da Resposta

### Empresa
- `codigo`: Código da empresa
- `nome`: Nome da empresa

### Funcionário
- `matricula`: Matrícula do funcionário
- `nome`: Nome do funcionário

### Período Aquisitivo
- `inicio`: Data de início do período aquisitivo (DD/MM/YYYY)
- `fim`: Data de fim do período aquisitivo (DD/MM/YYYY)
- `dias_direito`: Dias de direito do período aquisitivo
- `dias_debitados`: Dias debitados (férias tiradas)
- `dias_saldo`: Dias de saldo disponível
- `situacao`: Situação do período (0 = ativo)

## Filtros Aplicados

A consulta aplica automaticamente os seguintes filtros:
- `qtddeb < 30`: Dias debitados menores que 30
- `iniper <= SYSDATE`: Período iniciado até a data atual
- `sitper = 0`: Situação ativa

## Tabelas Relacionadas

- `vetorh.r040per`: Períodos aquisitivos
- `vetorh.r034fun`: Funcionários
- `vetorh.r030emp`: Empresas

## Exemplos de Uso

### 1. Buscar todos os períodos em aberto
```bash
curl -X GET "http://localhost/pontos/api_periodo_aquisitivo.php"
```

### 2. Buscar período de um funcionário específico
```bash
curl -X GET "http://localhost/pontos/api_periodo_aquisitivo.php?numcad=548&tipo=matricula"
```

### 3. Buscar períodos de uma empresa
```bash
curl -X GET "http://localhost/pontos/api_periodo_aquisitivo.php?numemp=23&tipo=empresa"
```

### 4. Buscar com POST
```bash
curl -X POST "http://localhost/pontos/api_periodo_aquisitivo.php" \
  -H "Content-Type: application/json" \
  -d '{"numcad": 548, "tipo": "matricula"}'
```

## Códigos de Status HTTP

- `200`: Sucesso
- `400`: Erro de validação ou parâmetros inválidos
- `500`: Erro interno do servidor

## Observações

- A API retorna apenas períodos aquisitivos em aberto (situação = 0)
- Os dias debitados devem ser menores que 30
- O período deve ter iniciado até a data atual
- As datas são formatadas como DD/MM/YYYY
- A API suporta CORS para requisições cross-origin




