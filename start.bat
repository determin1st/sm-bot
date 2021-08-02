@echo off
:: bypass "Terminate Batch Job" prompt
IF "%~1"=="-FIXED_CTRL_C" (
   REM Remove the -FIXED_CTRL_C parameter
   SHIFT
) ELSE (
   REM Run the batch with <NUL and -FIXED_CTRL_C
   CALL <NUL %0 -FIXED_CTRL_C %*
   GOTO :EOF
)
echo "Press CTRL+c to make master /stop"
set PHP="E:\lab\www\php\php.exe"
:LOOP
%PHP% -f "%CD%\start.php"
if %ERRORLEVEL% EQU 0 goto LOOP
exit
