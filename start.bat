@echo off
:: bypass "Terminate Batch Job" prompt
if "%~1"=="-FIXED_CTRL_C" (
    :: Remove the -FIXED_CTRL_C parameter
    shift
) else (
    :: Run the batch with <NUL and -FIXED_CTRL_C
    call <NUL %0 -FIXED_CTRL_C %*
    goto END
)
:: configure
::set PHP="E:\lab\www\php-nts\php.exe"
set PHP="E:\lab\www\php\php.exe"
goto CHECK

:CHECK
%PHP% -f "%CD%\bots\check.php"
if %ERRORLEVEL% EQU 0 goto LOOP
goto END

:LOOP
echo [104m Press Ctrl+C to restart, Ctrl+Break to stop [0m
::%PHP% -f "%CD%\start.php"
::if %ERRORLEVEL% EQU 1 goto LOOP
goto END

:END
exit
