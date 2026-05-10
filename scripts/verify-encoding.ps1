# Encoding Verification Script
# Checks current status of encoding across all project files
# Run: powershell -NoProfile -ExecutionPolicy Bypass -File scripts/verify-encoding.ps1

param([string]$Root = $PSScriptRoot + "\..")
$Root = (Resolve-Path $Root).Path

$issues = 0
$scanned = 0
$enc = [System.Text.Encoding]::UTF8

Write-Host ""
Write-Host "=== EMS Encoding Verification ===" -ForegroundColor Cyan
Write-Host "Root: $Root" -ForegroundColor Gray
Write-Host ""

$extensions = @("*.php", "*.js", "*.css", "*.sql")

foreach ($ext in $extensions) {
    Get-ChildItem -Path $Root -Recurse -Filter $ext -File |
    Where-Object { $_.FullName -notmatch '\\vendor\\' -and $_.FullName -notmatch '\\node_modules\\' } |
    ForEach-Object {
        $scanned++
        $bytes = [System.IO.File]::ReadAllBytes($_.FullName)
        $relPath = $_.FullName.Replace($Root, "")

        # Check BOM
        if ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
            Write-Host "  [BOM]   $relPath" -ForegroundColor Red
            $issues++
        }

        # Check CRLF line endings
        $content = $enc.GetString($bytes)
        if ($content -match "\r\n") {
            Write-Host "  [CRLF]  $relPath" -ForegroundColor Yellow
            $issues++
        }
    }
}

Write-Host ""
Write-Host "Scanned: $scanned files" -ForegroundColor Cyan
if ($issues -eq 0) {
    Write-Host "Result:  OK - No encoding issues found" -ForegroundColor Green
} else {
    Write-Host "Result:  $issues issue(s) found - run scripts/fix-bom.ps1 to fix BOM issues" -ForegroundColor Red
}
Write-Host ""
