@echo off
REM ====================================================================
REM  Instalador del chatbot TermoPlast (Windows)
REM  Crea un entorno virtual aislado (venv) e instala las dependencias.
REM  Asi evitamos el site-packages global que puede estar roto.
REM ====================================================================
cd /d "%~dp0"
echo ============================================
echo   Instalador del chatbot TermoPlast
echo ============================================
echo.
echo [1/3] Creando entorno virtual (carpeta venv)...
python -m venv venv
if errorlevel 1 (
  echo.
  echo ERROR: no se pudo crear el entorno virtual.
  echo Verifica que Python este instalado y en el PATH ^(python --version^).
  pause
  exit /b 1
)
echo [2/3] Actualizando pip dentro del venv...
call venv\Scripts\python -m pip install --upgrade pip
echo [3/3] Instalando dependencias (flask + scikit-learn)...
call venv\Scripts\python -m pip install -r requirements.txt
if errorlevel 1 (
  echo.
  echo ERROR al instalar las dependencias. Revisa tu conexion a internet.
  pause
  exit /b 1
)
echo.
echo ===========================================================
echo   LISTO. Para iniciar el chatbot, ejecuta:  iniciar_servicio.bat
echo ===========================================================
pause
