# Fix BOM in all PHP/JS/CSS/SQL files
# Run: powershell -NoProfile -ExecutionPolicy Bypass -File scripts/fix-bom.ps1

param([string]$Root = $PSScriptRoot + "\..")

$Root = Resolve-Path $Root
$fixed = 0
$scanned = 0
$enc = New-Object System.Text.UTF8Encoding($false)
$bom = [byte[]](0xEF, 0xBB, 0xBF)

$extensions = @("*.php", "*.js", "*.css", "*.sql", "*.json", "*.html")

foreach ($ext in $extensions) {
    Get-ChildItem -Path $Root -Recurse -Filter $ext -File |
    Where-Object { $_.FullName -notmatch '\\vendor\\' -and $_.FullName -notmatch '\\node_modules\\' } |
    ForEach-Object {
        $scanned++
        $bytes = [System.IO.File]::ReadAllBytes($_.FullName)
        if ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
            $fixed++
            $text = [System.IO.File]::ReadAllText($_.FullName, [System.Text.Encoding]::UTF8)
            [System.IO.File]::WriteAllText($_.FullName, $text, $enc)
            Write-Host "Fixed BOM: $($_.FullName.Replace($Root.Path, ''))" -ForegroundColor Yellow
        }
    }
}

Write-Host ""
Write-Host "Scanned: $scanned files" -ForegroundColor Cyan
Write-Host "Fixed:   $fixed files with BOM" -ForegroundColor $(if ($fixed -gt 0) { 'Yellow' } else { 'Green' })
if ($fixed -eq 0) { Write-Host "All files are clean - no BOM found!" -ForegroundColor Green }
