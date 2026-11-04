@echo off
REM  Inicia el microservicio del chatbot usando el entorno virtual.
cd /d "%~dp0"
if not exist "venv\Scripts\python.exe" (
  echo No encuentro el entorno virtual.
  echo Primero ejecuta:  instalar_windows.bat
  pause
  exit /b 1
)
echo Iniciando el chatbot en http://127.0.0.1:5001
echo Deja esta ventana ABIERTA mientras uses el chat. Cerrala para detenerlo.
echo.
venv\Scripts\python app.py
pause
