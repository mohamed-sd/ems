# Convert CRLF to LF in all project files
# This makes line endings consistent with .editorconfig and .gitattributes settings
# Run: powershell -NoProfile -ExecutionPolicy Bypass -File scripts/fix-line-endings.ps1

param([string]$Root = $PSScriptRoot + "\..")
$Root = (Resolve-Path $Root).Path

$fixed = 0
$scanned = 0
$enc = New-Object System.Text.UTF8Encoding($false)

Write-Host ""
Write-Host "=== Converting CRLF to LF ===" -ForegroundColor Cyan
Write-Host "Root: $Root" -ForegroundColor Gray
Write-Host ""

$extensions = @("*.php", "*.js", "*.css", "*.sql", "*.json", "*.html", "*.md", "*.txt")

foreach ($ext in $extensions) {
    Get-ChildItem -Path $Root -Recurse -Filter $ext -File |
    Where-Object { $_.FullName -notmatch '\\vendor\\' -and $_.FullName -notmatch '\\node_modules\\' -and $_.FullName -notmatch '\\.git\\' } |
    ForEach-Object {
        $scanned++
        $bytes = [System.IO.File]::ReadAllBytes($_.FullName)

        # Skip BOM if present (don't add it back)
        $startIdx = 0
        if ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
            $startIdx = 3
        }

        $content = [System.Text.Encoding]::UTF8.GetString($bytes, $startIdx, $bytes.Length - $startIdx)

        if ($content -match "\r\n") {
            $fixed++
            $newContent = $content.Replace("`r`n", "`n")
            [System.IO.File]::WriteAllText($_.FullName, $newContent, $enc)
        }
    }
}

Write-Host "Scanned: $scanned files" -ForegroundColor Cyan
Write-Host "Fixed:   $fixed files (CRLF -> LF)" -ForegroundColor $(if ($fixed -gt 0) { 'Yellow' } else { 'Green' })
Write-Host ""
Write-Host "Done! All files now use LF line endings." -ForegroundColor Green
