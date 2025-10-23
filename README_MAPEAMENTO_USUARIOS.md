# Sistema de Mapeamento de Usuários

## Descrição

Este sistema permite que um usuário faça login com suas próprias credenciais, mas visualize e gerencie os dados de outro usuário. Isso é útil para situações onde um supervisor ou administrador precisa acessar os dados de um colaborador específico.

## Como Funciona

1. **Login Simples**: Se o usuário digitar apenas o nome (ex: `kaio.baia`), o sistema funciona normalmente
2. **Login com Mapeamento**: Se o usuário digitar um email completo (ex: `kaio.baia@kamilla.oliveira`), o sistema:
   - Autentica com a primeira parte do email (antes do @)
   - Exibe dados da segunda parte do email (após o @)
3. **Logs**: Todas as ações são registradas com o usuário que realmente fez login

## Configuração

### ⚠️ IMPORTANTE: Sistema Simplificado

**Não é mais necessário configurar mapeamentos!** O sistema agora funciona automaticamente:

- **Qualquer email** no formato `usuario@outro.usuario` funcionará
- **Não precisa editar arquivos** de configuração
- **Funciona instantaneamente** com qualquer combinação

### Exemplo de Uso

#### Com Email Completo (Mapeamento Ativo):
1. **Usuário faz login**: `kaio.baia@kamilla.oliveira`
2. **Sistema autentica**: Com as credenciais de `kaio.baia` (antes do @)
3. **Sistema exibe**: Dados de `kamilla.oliveira` (após o @)
4. **Logs registram**: Ações feitas por `kaio.baia`

#### Com Nome Simples (Sem Mapeamento):
1. **Usuário faz login**: `kaio.baia`
2. **Sistema autentica**: Com as credenciais de `kaio.baia`
3. **Sistema exibe**: Dados de `kaio.baia`
4. **Logs registram**: Ações feitas por `kaio.baia`

## Indicadores Visuais

Quando um usuário está visualizando dados de outro usuário, o sistema mostra:

- Um badge amarelo no cabeçalho com o nome do usuário cujos dados estão sendo exibidos
- Ícone de olho para indicar que está "visualizando" dados de outro usuário

## Segurança

- A autenticação sempre é feita com as credenciais do usuário que fez login
- Os logs sempre registram o usuário que realmente executou a ação
- O mapeamento é apenas para exibição de dados, não para permissões

## Exemplos Práticos

### Exemplo 1: Supervisor acessando dados de colaborador
```php
// Usuário digita: joao.supervisor@maria.colaboradora
// Sistema autentica com: joao.supervisor
// Sistema exibe dados de: maria.colaboradora
// Logs registram: "Ajustado por joao.supervisor em DD/MM/YYYY"
```

### Exemplo 2: Administrador acessando dados de usuário
```php
// Usuário digita: admin.sistema@usuario.teste
// Sistema autentica com: admin.sistema
// Sistema exibe dados de: usuario.teste
// Logs registram: "Ajustado por admin.sistema em DD/MM/YYYY"
```

### Exemplo 3: Login normal
```php
// Usuário digita: kaio.baia
// Sistema autentica com: kaio.baia
// Sistema exibe dados de: kaio.baia
// Logs registram: "Ajustado por kaio.baia em DD/MM/YYYY"
```

## Notas Importantes

- **Login com nome simples**: Se o usuário digitar apenas o nome (ex: `kaio.baia`), o sistema adiciona automaticamente `@gf.local` e funciona normalmente
- **Login com email completo**: Se o usuário digitar o email completo (ex: `kaio.baia@kamilla.oliveira`), o sistema automaticamente:
  - Usa a parte antes do @ para autenticação
  - Usa a parte após o @ para exibição de dados
- **Não precisa configuração**: Qualquer email no formato correto funcionará
- **Compatibilidade total**: O sistema mantém a compatibilidade com usuários sem mapeamento
- **Todas as funcionalidades** existentes continuam funcionando normalmente 