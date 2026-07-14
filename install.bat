@echo off 
title Geoval - Installation

echo.
echo  =============================================
echo   GEOVAL - Script d'installation automatique
echo  =============================================
echo.

:: Repertoire de ce script
set "BASE=%~dp0"
set "PROJECT=%BASE%geoval"

:: -----------------------------------------------
:: 1. Verification de Git
:: -----------------------------------------------
echo [1/4] Verification de Git...
git --version >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo.
    echo  [ERREUR] Git n'est pas installe.
    echo  Telechargez-le : https://git-scm.com/download/win
    echo.
    goto :end_error
)
echo  [OK] Git est installe.

:: -----------------------------------------------
:: 2. Verification de Docker
:: -----------------------------------------------
echo.
echo [2/4] Verification de Docker...
docker --version >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo.
    echo  [ERREUR] Docker n'est pas installe.
    echo  Telechargez-le : https://www.docker.com/products/docker-desktop
    echo.
    goto :end_error
)
echo  [OK] Docker est installe.

docker info >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo.
    echo  [ERREUR] Docker est installe mais pas demarre.
    echo  Lancez Docker Desktop puis relancez ce script.
    echo.
    goto :end_error
)
echo  [OK] Docker est en cours d'execution.

:: -----------------------------------------------
:: 3. Clonage du depot
:: -----------------------------------------------
echo.
echo [3/4] Clonage du projet...

if exist "%PROJECT%\" (
    echo  [INFO] Dossier "geoval" existe deja, clonage ignore.
) else (
    git clone https://github.com/spartiate226/geoval.git "%PROJECT%"
    if %ERRORLEVEL% neq 0 (
        echo.
        echo  [ERREUR] Echec du clonage. Verifiez votre connexion internet.
        echo.
        goto :end_error
    )
    echo  [OK] Projet clone avec succes.
)

:: -----------------------------------------------
:: 4. Lancement de Docker Compose
:: -----------------------------------------------
echo.
echo [4/4] Lancement des conteneurs Docker...

docker compose -f "%PROJECT%\docker-compose.yml" up -d --build
if %ERRORLEVEL% neq 0 (
    echo.
    echo  [ERREUR] Docker Compose a echoue.
    echo  Verifiez les logs : docker compose -f "%PROJECT%\docker-compose.yml" logs
    echo.
    goto :end_error
)

echo.
echo  =============================================
echo   Installation terminee avec succes !
echo  =============================================
echo.
echo  Frontend  ^: http://localhost  ou  http://localhost:8080
echo  Backend   ^: http://localhost:8000
echo  Base de donnees PostgreSQL ^: localhost:5432
echo.
goto :end_ok

:end_error
echo  Appuyez sur une touche pour fermer...
pause >nul
exit /b 1

:end_ok
echo  Appuyez sur une touche pour fermer...
pause >nul
exit /b 0
