@echo off
:: check and remove the bypass parameter
if "%~1" NEQ "-FIXED_CTRL_C" goto SETUP
shift
goto LOOP

:SETUP
:: select runtime variant (production ~ NTS, opcache+JIT)
::set PHP="E:\lab\www\php-nts\php.exe"
set PHP="E:\lab\www\php\php.exe"
goto CHECK

:CHECK
%PHP% -f "%CD%\check.php"
if %ERRORLEVEL% EQU 100 goto START
goto END

:START
:: bypass "Terminate Batch Job" prompt
:: run the batch with <NUL and -FIXED_CTRL_C
call <NUL %0 -FIXED_CTRL_C %*
goto END

:LOOP
%PHP% -f "%CD%\start.php"
if %ERRORLEVEL% EQU 100 goto LOOP
goto END

:END
exit
