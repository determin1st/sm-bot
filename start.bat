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
:: prepare
set PHP="E:\lab\www\php\php.exe"
set BOTID=master
set TIMEOUT=120
:: run
:LOOP
%PHP% -f "%CD%\index.php" -- loop %BOTID% %TIMEOUT%
if %ERRORLEVEL% EQU 0 goto LOOP
exit
