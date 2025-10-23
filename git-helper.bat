@echo off
echo ========================================
echo    SISTEMA DE CONTROLE DE PONTO - GF
echo    Helper Git - Comandos Rápidos
echo ========================================
echo.

:menu
echo Escolha uma opção:
echo 1. Ver status do repositório
echo 2. Adicionar todos os arquivos
echo 3. Fazer commit
echo 4. Ver histórico
echo 5. Criar nova branch
echo 6. Trocar de branch
echo 7. Ver branches
echo 8. Sair
echo.
set /p choice="Digite sua escolha (1-8): "

if "%choice%"=="1" goto status
if "%choice%"=="2" goto add
if "%choice%"=="3" goto commit
if "%choice%"=="4" goto log
if "%choice%"=="5" goto newbranch
if "%choice%"=="6" goto switch
if "%choice%"=="7" goto branches
if "%choice%"=="8" goto exit
goto menu

:status
echo.
echo === STATUS DO REPOSITÓRIO ===
git status
echo.
pause
goto menu

:add
echo.
echo === ADICIONANDO ARQUIVOS ===
git add .
echo Arquivos adicionados com sucesso!
echo.
pause
goto menu

:commit
echo.
set /p message="Digite a mensagem do commit: "
echo.
echo === FAZENDO COMMIT ===
git commit -m "%message%"
echo.
pause
goto menu

:log
echo.
echo === HISTÓRICO DE COMMITS ===
git log --oneline -10
echo.
pause
goto menu

:newbranch
echo.
set /p branchname="Digite o nome da nova branch: "
echo.
echo === CRIANDO NOVA BRANCH ===
git checkout -b %branchname%
echo.
pause
goto menu

:switch
echo.
echo === BRANCHES DISPONÍVEIS ===
git branch
echo.
set /p branchname="Digite o nome da branch para trocar: "
echo.
echo === TROCANDO DE BRANCH ===
git checkout %branchname%
echo.
pause
goto menu

:branches
echo.
echo === BRANCHES DISPONÍVEIS ===
git branch -a
echo.
pause
goto menu

:exit
echo.
echo Obrigado por usar o Git Helper!
echo.
pause
exit
