# Sistema de Controle de Ponto - Grupo Farias

Sistema web para gerenciamento de controle de ponto, inconsistências e horas extras.

## 🚀 Funcionalidades

### Para Gestores
- **Dashboard Principal**: Visão geral do sistema
- **Inconsistências**: Gerenciamento de problemas de ponto e atrasos
- **Horas Extras**: Visualização e justificativa de horas extras
- **Visões Gerenciais**: Relatórios e análises gerenciais

### Para Colaboradores
- **Consulta de Inconsistências**: Visualização de problemas de ponto
- **Autenticação**: Login via CPF e matrícula

## 🛠️ Tecnologias

- **Backend**: PHP 7.4+
- **Banco de Dados**: Oracle (VETORH)
- **Frontend**: Bootstrap 5, HTML5, CSS3, JavaScript
- **Autenticação**: LDAP (Active Directory)
- **APIs**: WhatsApp Business API

## 📋 Pré-requisitos

- PHP 7.4 ou superior
- Extensão Oracle OCI8
- Servidor web (Apache/Nginx)
- Acesso ao banco Oracle VETORH
- Acesso ao servidor LDAP

## 🔧 Instalação

1. Clone o repositório:
```bash
git clone [URL_DO_REPOSITORIO]
```

2. Configure o banco de dados em `conn.php`:
```php
$conn = oci_connect('usuario', 'senha', 'servidor/senior');
```

3. Configure o LDAP em `index.php`:
```php
$ldap_host = 'ldap://seu-servidor-ldap';
```

4. Acesse o sistema via navegador

## 📁 Estrutura do Projeto

```
pontos/
├── index.php              # Página de login
├── dashboard.php          # Dashboard principal
├── menu.php              # Menu de navegação
├── ajuste.php            # Módulo de inconsistências
├── extras.php            # Módulo de horas extras
├── visoes.php            # Módulo de visões gerenciais
├── conn.php              # Conexão com banco de dados
├── style.css             # Estilos CSS
├── holerites/            # Diretório de holerites
└── README_*.md           # Documentação das APIs
```

## 🔐 Tipos de Usuários

### Gestores
- Login via LDAP (usuário@dominio)
- Acesso completo ao sistema
- Gerenciamento de inconsistências e horas extras

### Colaboradores
- Login via CPF e matrícula
- Acesso limitado às próprias inconsistências

## 📊 Tipos de Horas Extras

- **301**: Horas 50%
- **313**: Horas 70%
- **303**: Horas 75%
- **305**: Horas 100% DSR
- **311**: Horas 100% Feriado

## 🔄 Controle de Versão

### Comandos Git Básicos

```bash
# Verificar status
git status

# Adicionar arquivos
git add .

# Fazer commit
git commit -m "Descrição da alteração"

# Ver histórico
git log --oneline

# Criar branch
git checkout -b nome-da-branch

# Voltar para master
git checkout master

# Fazer merge
git merge nome-da-branch
```

### Fluxo de Trabalho Recomendado

1. **Criar branch** para nova funcionalidade
2. **Desenvolver** e testar
3. **Fazer commit** com mensagem descritiva
4. **Fazer merge** para master
5. **Deletar branch** após merge

## 📝 Log de Alterações

### v1.0.0 (2025-01-15)
- ✅ Sistema inicial implementado
- ✅ Dashboard principal
- ✅ Módulo de inconsistências
- ✅ Módulo de horas extras
- ✅ Módulo de visões gerenciais
- ✅ Sistema de autenticação LDAP
- ✅ Interface responsiva

## 🤝 Contribuição

1. Faça um fork do projeto
2. Crie uma branch para sua feature
3. Commit suas mudanças
4. Push para a branch
5. Abra um Pull Request

## 📞 Suporte

Para suporte técnico, entre em contato com a equipe de desenvolvimento.

---

**Desenvolvido para Grupo Farias** 🏢
