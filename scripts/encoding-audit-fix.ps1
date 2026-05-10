# Comprehensive Encoding Audit and Fix Script
# فحص وإصلاح شامل لمشاكل التكويد

param(
    [switch]$FixAll = $false,
    [switch]$Report = $false
)

$emsPath = "C:\wamp64\www\ems"
$issues = @()
$fixed = @()
$filesToCheck = @()

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Encoding Audit and Fix" -ForegroundColor Cyan
Write-Host "  فحص وإصلاح التكويد" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Get all files
Write-Host "Scanning files..." -ForegroundColor Cyan
$excludePatterns = @('vendor', 'node_modules', '.git')

$allFiles = @(Get-ChildItem -Path $emsPath -Recurse -Include @("*.php", "*.js", "*.css", "*.sql", "*.json", "*.md") -ErrorAction SilentlyContinue)

$filesToCheck = @()
foreach ($file in $allFiles) {
    $relativePath = $file.FullName.Replace($emsPath + '\', '')
    $isExcluded = $false
    
    foreach ($pattern in $excludePatterns) {
        if ($relativePath -like "*$pattern*") {
            $isExcluded = $true
            break
        }
    }
    
    if (-not $isExcluded) {
        $filesToCheck += $file
    }
}

Write-Host "Found $($filesToCheck.Count) files" -ForegroundColor Yellow
Write-Host ""

# Scan files
$processedCount = 0
foreach ($file in $filesToCheck) {
    $filePath = $file.FullName
    $relPath = $filePath.Replace($emsPath + '\', '')
    
    $processedCount++
    if ($processedCount % 100 -eq 0) {
        Write-Host "Processing... ($processedCount/$($filesToCheck.Count))"
    }
    
    try {
        $bytes = [System.IO.File]::ReadAllBytes($filePath)
        
        # Check for UTF-8 BOM
        if ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
            $issues += @{
                File = $relPath
                Issue = "UTF-8 BOM"
                Severity = "Medium"
                Path = $filePath
                Type = "BOM"
            }
            Write-Host "WARNING: $relPath (BOM)" -ForegroundColor Yellow
        }
        
        # Check for UTF-16
        if ($bytes.Length -ge 2 -and (($bytes[0] -eq 0xFF -and $bytes[1] -eq 0xFE) -or ($bytes[0] -eq 0xFE -and $bytes[1] -eq 0xFF))) {
            $issues += @{
                File = $relPath
                Issue = "UTF-16 BOM"
                Severity = "Critical"
                Path = $filePath
                Type = "UTF16"
            }
            Write-Host "CRITICAL: $relPath (UTF-16)" -ForegroundColor Red
        }
    }
    catch {
        Write-Host "ERROR reading $relPath" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Results" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

$critical = @($issues | Where-Object { $_.Severity -eq "Critical" })
$medium = @($issues | Where-Object { $_.Severity -eq "Medium" })

if ($critical.Count -gt 0) {
    Write-Host "CRITICAL ISSUES: $($critical.Count)" -ForegroundColor Red
    foreach ($item in $critical) {
        Write-Host "  - $($item.File)" -ForegroundColor Red
    }
    Write-Host ""
}

if ($medium.Count -gt 0) {
    Write-Host "MEDIUM ISSUES: $($medium.Count)" -ForegroundColor Yellow
    foreach ($item in $medium) {
        Write-Host "  - $($item.File)" -ForegroundColor Yellow
    }
    Write-Host ""
}

Write-Host "Total Issues Found: $($issues.Count)" -ForegroundColor Green
Write-Host "Total Files Checked: $($filesToCheck.Count)" -ForegroundColor Green
Write-Host ""

# Fix issues
if ($issues.Count -gt 0) {
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  Fixing Issues" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""
    
    $fixedCount = 0
    foreach ($issue in $issues) {
        try {
            $bytes = [System.IO.File]::ReadAllBytes($issue.Path)
            
            # Remove BOM if present
            if ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
                $bytes = $bytes[3..($bytes.Length - 1)]
            }
            
            # Get content as UTF-8
            $content = [System.Text.Encoding]::UTF8.GetString($bytes)
            
            # Normalize line endings
            $content = $content -replace "`r`n", "`n"
            $content = $content -replace "`r", "`n"
            
            # Write back as UTF-8 without BOM
            $utf8NoBOM = New-Object System.Text.UTF8Encoding $false
            [System.IO.File]::WriteAllText($issue.Path, $content, $utf8NoBOM)
            
            Write-Host "FIXED: $($issue.File)" -ForegroundColor Green
            $fixedCount++
        }
        catch {
            Write-Host "ERROR fixing $($issue.File): $_" -ForegroundColor Red
        }
    }
    
    Write-Host ""
    Write-Host "Fixed: $fixedCount files" -ForegroundColor Green
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "  Complete" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
