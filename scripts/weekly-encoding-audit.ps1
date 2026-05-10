# Weekly Encoding Audit Schedule
# فحص التكويد الأسبوعي المجدول

# Run weekly encoding audit and create report
# This script is intended to be run automatically via Task Scheduler

param(
    [switch]$SendReport = $false,
    [string]$ReportEmail = ""
)

$timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
$emsPath = "C:\wamp64\www\ems"
$logsPath = Join-Path $emsPath "logs"
$reportPath = Join-Path $logsPath "encoding-audit-$(Get-Date -Format 'yyyy-MM-dd').log"

# Create logs directory if needed
if (-not (Test-Path $logsPath)) {
    New-Item -ItemType Directory -Path $logsPath -Force | Out-Null
}

$reportContent = @"
ENCODING AUDIT REPORT
فحص التكويد الأسبوعي
Generated: $timestamp

"@

# Run audit
Write-Host "Starting weekly encoding audit..." -ForegroundColor Cyan

$issues = 0
$filesChecked = 0

$excludePatterns = @('vendor', 'node_modules', '.git')
$allFiles = @(Get-ChildItem -Path $emsPath -Recurse -Include @("*.php", "*.js", "*.css", "*.sql", "*.json", "*.md") -ErrorAction SilentlyContinue)

foreach ($file in $allFiles) {
    $relativePath = $file.FullName.Replace($emsPath + '\', '')
    $isExcluded = $false
    
    foreach ($pattern in $excludePatterns) {
        if ($relativePath -like "*$pattern*") {
            $isExcluded = $true
            break
        }
    }
    
    if ($isExcluded) { continue }
    
    $filesChecked++
    
    try {
        $bytes = [System.IO.File]::ReadAllBytes($file.FullName)
        
        if ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
            $reportContent += "ISSUE: BOM in $relativePath`n"
            $issues++
        }
    }
    catch { }
}

$reportContent += @"

SUMMARY
Files Checked: $filesChecked
Issues Found: $issues
Status: $(if ($issues -eq 0) { "PASS" } else { "FAIL" })
Timestamp: $timestamp

"@

# Save report
$reportContent | Out-File -FilePath $reportPath -Encoding UTF8 -Force

Write-Host "Report saved: $reportPath"
Write-Host "Files checked: $filesChecked"
Write-Host "Issues found: $issues"

# Send email if requested
if ($SendReport -and -not [string]::IsNullOrEmpty($ReportEmail)) {
    Write-Host "Sending report to: $ReportEmail"
    # Add email sending logic here if needed
}

exit $issues
