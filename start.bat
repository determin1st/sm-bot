@echo off
:: bypass "Terminate Batch Job" prompt
if "%~1"=="-FIXED_CTRL_C" (
    rem Remove the -FIXED_CTRL_C parameter
    shift
) else (
    rem Run the batch with <NUL and -FIXED_CTRL_C
    call <NUL %0 -FIXED_CTRL_C %*
    goto END
)
:: configure
::set PHP="E:\lab\www\php-nts\php.exe"
set PHP="E:\lab\www\php\php.exe"

:LOOP
echo [104m Press CTRL+c to restart, CTRL+BREAK to stop [0m
%PHP% -f "%CD%\start.php"
if %ERRORLEVEL% EQU 1 goto LOOP
goto END

:END
exit
