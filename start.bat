@echo off
color 0A

:: bypass "Terminate Batch Job" prompt
IF "%~1"=="-FIXED_CTRL_C" (
   REM Remove the -FIXED_CTRL_C parameter
   SHIFT
) ELSE (
   REM Run the batch with <NUL and -FIXED_CTRL_C
   CALL <NUL %0 -FIXED_CTRL_C %*
   GOTO :EOF
)

:: CONFIGURATION
set PHP="E:\lab\www\xampp\php\php.exe"

:LOOP
:: getUpdates <BOT_ID> <timeout>
%PHP% -f "%CD%\index.php" -- getUpdates master 120
if %ERRORLEVEL% EQU 0 goto LOOP
exit
