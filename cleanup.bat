@echo off
echo WARNING: This will remove directories and files!
echo Make sure you have backed up your project.
echo.
set /p choice="Continue? (y/n): "
if /i "%choice%" neq "y" (
  echo Cleanup cancelled.
  pause
  exit /b
)

echo Removing duplicate Git repositories...
rmdir /s /q .\.git 2>nul
rmdir /s /q .\ability_app_master 2>nul
rmdir /s /q .\vendor\phpqrcode\.git 2>nul

echo Removing node_modules...
rmdir /s /q .\node_modules 2>nul

echo Removing empty/unused folders...
rmdir /s /q .\controllers 2>nul
rmdir /s /q .\models 2>nul
rmdir /s /q .\api\qr 2>nul
rmdir /s /q .\views\qr 2>nul
rmdir /s /q .\qr 2>nul

echo Removing cache folders...
rmdir /s /q .\vendor\phpqrcode\cache 2>nul
rmdir /s /q .\vendor\phpqrcode\temp 2>nul
rmdir /s /q .\vendor\phpqrcode\tools 2>nul
rmdir /s /q .\vendor\phpqrcode\bindings 2>nul

echo Removing IDE folder...
rmdir /s /q .\.vscode 2>nul

echo.
echo Cleanup complete!
echo.
echo Important folders kept:
echo - api\items\ (your create.php API)
echo - assets\ (CSS, JS, images)
echo - includes\ (database and functions)
echo - uploads\ (item images)
echo - qrcodes\ (generated QR codes)
echo - vendor\chillerlan\ (QR code library)
echo - vendor\composer\ (autoloader)
echo - views\ (templates)
echo.
pause
