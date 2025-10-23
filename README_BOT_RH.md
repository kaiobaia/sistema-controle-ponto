# Bot de RH do Grupo Farias

Sistema de atendimento automatizado via WhatsApp que integra com as APIs existentes do sistema de pontos, sem depender da plataforma Jetsales.

## Funcionalidades

O bot oferece as seguintes opções de atendimento:

1. 🕐 **Banco de Horas** - Consulta de saldo de horas
2. 💰 **Holerite** - Envio de holerite como imagem
3. 🌴 **Férias** - Consulta de férias programadas
4. 📄 **Faltas e Inconsistências de Ponto** - Relatório de inconsistências
5. 📋 **Informe de Rendimentos** - Informe para declaração de IR
6. 💼 **Vagas em Aberto** - Em desenvolvimento
7. 🧩 **Recrutamento e Seleção** - Informações de contato
8. ⏱️ **Ponto** - Informações sobre ponto eletrônico
9. 👤 **Departamento Pessoal** - Informações de contato
10. 🏥 **Ambulatório Médico** - Informações de contato

## Arquivos do Sistema

- `bot_rh_grupo_farias.php` - Bot principal
- `config_whatsapp_bot.php` - Configurações do sistema
- `webhook_whatsapp.php` - Webhook para receber mensagens
- `teste_bot_rh.php` - Arquivo para testar o bot
- `README_BOT_RH.md` - Este arquivo de documentação

## Pré-requisitos

1. **API WhatsApp** já configurada (mesma das outras APIs)
2. **Token de acesso** da API WhatsApp (já existente)
3. **Servidor web** com PHP 7.4+ e cURL habilitado
4. **APIs existentes** do sistema de pontos funcionando

## Configuração

### 1. Configurar o Sistema

O sistema já está configurado para usar a mesma API WhatsApp das outras APIs existentes. Não é necessário configuração adicional.

### 2. Testar o Sistema

#### Teste Rápido
Execute o arquivo de teste:
```bash
php teste_bot_rh.php
```

#### Teste Manual
1. Envie uma mensagem para o número do WhatsApp Business
2. O bot deve responder com o menu de opções
3. Teste cada opção para verificar se está funcionando

## Como Funciona

### Fluxo de Atendimento

1. **Usuário envia mensagem** → Webhook recebe
2. **Bot processa mensagem** → Identifica opção ou telefone
3. **Se for opção** → Mostra instruções ou chama API
4. **Se for telefone** → Chama API correspondente
5. **API processa** → Retorna dados ou erro
6. **Bot responde** → Confirma sucesso ou erro

### Integração com APIs

O bot integra com as seguintes APIs existentes:

- `api_banco_horas.php` - Para consulta de banco de horas
- `api_holerite_imagem.php` - Para envio de holerite
- `api_periodo_aquisitivo.php` - Para consulta de férias
- `api_inconsistencias_ponto.php` - Para inconsistências
- `api_informe_rendimento.php` - Para informe de rendimentos

### Comandos Suportados

- **Números 1-10**: Selecionar opção do menu
- **Palavras-chave**: "holerite", "ferias", "banco de horas", etc.
- **Telefone**: Números de 10-11 dígitos para consultas
- **"menu"**: Voltar ao menu principal

## Personalização

### Adicionar Novas Opções

1. Edite `bot_rh_grupo_farias.php`
2. Adicione nova opção no menu
3. Crie função `processarNovaOpcao()`
4. Adicione case no switch de `processarOpcao()`

### Modificar Mensagens

Edite as funções de processamento para personalizar as mensagens enviadas.

### Adicionar Novas APIs

1. Adicione URL da API em `config_whatsapp_bot.php`
2. Crie função de processamento
3. Adicione case no switch de `processarTelefone()`

## Logs e Debug

### Habilitar Logs

No arquivo `config_whatsapp_bot.php`:

```php
'sistema' => [
    'log_errors' => true,
    'debug_mode' => true
]
```

### Visualizar Logs

Os logs são salvos no log de erros do PHP. Para visualizar:

```bash
tail -f /var/log/php_errors.log
```

## Troubleshooting

### Problemas Comuns

1. **Webhook não recebe mensagens**
   - Verifique se a URL está correta
   - Confirme se o verify token está correto
   - Teste se o servidor está acessível

2. **APIs não respondem**
   - Verifique se as URLs estão corretas
   - Confirme se as APIs estão funcionando
   - Teste manualmente as APIs

3. **Mensagens não são enviadas**
   - Verifique o access token
   - Confirme se o phone number ID está correto
   - Teste a API do WhatsApp manualmente

### Teste Manual

Para testar o bot manualmente:

```bash
curl -X POST https://www.grupofarias.com.br/pontos/webhook_whatsapp.php \
  -H "Content-Type: application/json" \
  -d '{
    "entry": [{
      "changes": [{
        "value": {
          "messages": [{
            "from": "5511999999999",
            "text": {"body": "menu"}
          }]
        }
      }]
    }]
  }'
```

## Segurança

### Recomendações

1. **Use HTTPS** para todas as URLs
2. **Valide tokens** de verificação
3. **Limite rate** de requisições
4. **Monitore logs** regularmente
5. **Mantenha tokens** seguros

### Validação de Entrada

O sistema valida:
- Formato de telefone
- Opções do menu
- Tokens de verificação
- Estrutura de dados

## Suporte

Para suporte técnico:
- 📧 Email: ti@grupofarias.com.br
- 📞 Telefone: (64) 3564-5500

## Licença

Sistema proprietário do Grupo Farias. Todos os direitos reservados.
