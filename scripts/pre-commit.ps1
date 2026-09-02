# Pre-commit Hook for Windows (PowerShell)
# Checks for encoding issues before commit

Write-Host "Encoding Validation Pre-Commit Hook" -ForegroundColor Yellow
Write-Host ""

# Get git status to find staged files
$stagingArea = git diff --cached --name-only

if (-not $stagingArea) {
    Write-Host "No files staged for commit"
    exit 0
}

# Filter for code files
$filesToCheck = @()
foreach ($file in $stagingArea) {
    if ($file -match '\.(php|js|css|json|md)$') {
        $filesToCheck += $file
    }
}

if ($filesToCheck.Count -eq 0) {
    Write-Host "No PHP/JS/CSS/JSON/MD files to check"
    exit 0
}

Write-Host "Checking $($filesToCheck.Count) files for encoding issues..."
Write-Host ""

$errors = 0

foreach ($file in $filesToCheck) {
    if (-not (Test-Path $file)) {
        continue
    }
    
    try {
        $bytes = [System.IO.File]::ReadAllBytes($file)
        
        # Check for UTF-8 BOM
        if ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
            Write-Host "X BOM detected in: $file" -ForegroundColor Red
            $errors++
        }
        
        # Check for UTF-16
        if ($bytes.Length -ge 2) {
            if (($bytes[0] -eq 0xFF -and $bytes[1] -eq 0xFE) -or ($bytes[0] -eq 0xFE -and $bytes[1] -eq 0xFF)) {
                Write-Host "X Wrong encoding (UTF-16) in: $file" -ForegroundColor Red
                $errors++
            }
        }
        
        # Check for mixed line endings
        $content = [System.IO.File]::ReadAllText($file, [System.Text.Encoding]::UTF8)
        $hasCRLF = $content -like "*`r`n*"
        $hasLF = ($content -replace "`r`n", "" -like "*`n*")
        $hasCR = ($content -replace "`r`n", "" -replace "`n", "" -like "*`r*")
        
        if (($hasCRLF -and $hasLF) -or ($hasCRLF -and $hasCR) -or ($hasLF -and $hasCR)) {
            Write-Host "X Mixed line endings in: $file" -ForegroundColor Red
            $errors++
        }
        
    }
    catch {
        Write-Host "X Error reading file: $file" -ForegroundColor Red
        $errors++
    }
}

Write-Host ""

if ($errors -eq 0) {
    # UXW-01: bawwabat al-man3 tur assib al-iltizam (owner priority 1 - 2026-08-18)
    $staged = git diff --cached --name-only
    if ($staged -match "\.(php|css)$") {
        & C:\wamp64\bin\php\php8.2.30\php.exe tools/uxw_gates.php
        if ($LASTEXITCODE -ne 0) {
            Write-Host "UXW gates rejected the commit - fix violations and retry" -ForegroundColor Red
            exit 1
        }
        # UXUI-01 (2026-08-18): bawwabat al-jawla 8 ala al-nass al-musayyar + sifr faqd
        & C:\wamp64\bin\php\php8.2.30\php.exe tools/uxui_gates.php --enforce
        if ($LASTEXITCODE -ne 0) {
            Write-Host "UXUI gates rejected the commit - rendered-text violations" -ForegroundColor Red
            exit 1
        }
        & C:\wamp64\bin\php\php8.2.30\php.exe tools/uxui_preserve_check.php --gate
        if ($LASTEXITCODE -ne 0) {
            Write-Host "UXUI preservation gate rejected the commit - link loss detected" -ForegroundColor Red
            exit 1
        }
        & C:\wamp64\bin\php\php8.2.30\php.exe tools/_uxw_mojibake_probe.php --enforce --staged
        if ($LASTEXITCODE -ne 0) {
            Write-Host "Mojibake guard rejected the commit - corrupted text detected" -ForegroundColor Red
            exit 1
        }
    }

    # === INJ-FIX-01 - two ratchets that stop a measured debt from growing =====
    # NOTE: this hook file is read by PowerShell in the system ANSI codepage,
    #       so it must stay ASCII-only. Arabic here breaks the parser with
    #       "string is missing the terminator" - which is why every message
    #       in this file is transliterated. Same reason, same rule.
    # "Freezing writers is a gate, not an instruction" (contract 3): a ratchet
    # that does not run in pre-commit is documentation, not a ratchet.
    $stagedPhp = git diff --cached --name-only
    if ($stagedPhp -match '\.php$') {
        & C:\wamp64\bin\php\php8.2.30\php.exe tests/injfix01_journal_writer_ratchet.php
        if ($LASTEXITCODE -ne 0) {
            Write-Host "INJ-FIX-01 GAP-27: undeclared new writer to the journal" -ForegroundColor Red
            exit 1
        }
        & C:\wamp64\bin\php\php8.2.30\php.exe tests/injfix01_raw_query_ratchet.php
        if ($LASTEXITCODE -ne 0) {
            Write-Host "INJ-FIX-01 GAP-29: new file with a raw query on a tenant table" -ForegroundColor Red
            exit 1
        }
        # INJ-FRD-REM-01: the 26 proofs of that round were outside the sweep
        # (the filter was injfix0*). Evidence that is never re-run rots in
        # silence. Three-way verdict: a closed requirement whose proof turns
        # red fails; a proof no requirement cites fails; a green proof for a
        # not-yet-closed requirement is news, not a fault.
        & C:\wamp64\bin\php\php8.2.30\php.exe tests/injfrd01_belt.php
        if ($LASTEXITCODE -ne 0) {
            Write-Host "INJ-FRD-REM-01: evidence belt failed (regression or orphan proof)" -ForegroundColor Red
            exit 1
        }
        # INJ-FIX-02 NF-09 (GAP-12): a sensitive-field policy whose target column
        # does not exist LOOKS like protection and protects nothing - which is
        # worse than no policy, because it silences the question. Declared debt
        # is allowed; a NEW undeclared phantom target is not.
        & C:\wamp64\bin\php\php8.2.30\php.exe tests/injfix02_sensitive_target_integrity_proof.php
        if ($LASTEXITCODE -ne 0) {
            Write-Host "INJ-FIX-02 NF-09: sensitive policy pointing at a column that does not exist" -ForegroundColor Red
            exit 1
        }
        # INJ-FIX-02 NF-24 (GAP-22): "absence is not denial" leaves any route
        # outside the classification register open by default. Flipping to
        # default-closed is a live access change and needs an owner decision;
        # this ratchet only stops the debt from growing past the measured 14.
        & C:\wamp64\bin\php\php8.2.30\php.exe tests/injfix02_space_classification_ratchet.php
        if ($LASTEXITCODE -ne 0) {
            Write-Host "INJ-FIX-02 NF-24: new active nav route outside the classification register" -ForegroundColor Red
            exit 1
        }
        # REPAIR01 W01 (plan 71): fourteen debt registers, direction-locked.
        # "Put the ratchet in from day one or we fix ten and create twenty."
        # What exists is grandfathered into the recorded baseline; what is NEW
        # is refused here. Six visual registers plus the eight ownership /
        # governance ones (screen with no registry row, route with no owner,
        # local permission reader, raw SQL on an admin path, local approval
        # logic, event written outside the publisher, search outside the
        # canonical registry, derived field with no recorded rule).
        & C:\wamp64\bin\php\php8.2.30\php.exe tools/u12_debt_ratchet.php
        if ($LASTEXITCODE -ne 0) {
            Write-Host "REPAIR01 W01: a measured debt grew - see tools/u12_debt_ratchet.php" -ForegroundColor Red
            exit 1
        }
        # NAV-ARCH-02 (order sections 39 + 40): the central rule of that round is
        # "Permission grants access; Placement grants navigation". A renderer that
        # accepts everything passes every positive test and guards nothing, so the
        # eight negative tests are the proof that survives - and a pilot measured
        # once is a memory, not a fact. Both run in under a second together.
        & C:\wamp64\bin\php\php8.2.30\php.exe tests/navarch02_negative_tests.php
        if ($LASTEXITCODE -ne 0) {
            Write-Host "NAV-ARCH-02 s39: a negative test turned red - the renderer accepts what it must refuse" -ForegroundColor Red
            exit 1
        }
        & C:\wamp64\bin\php\php8.2.30\php.exe tools/navarch/metrics.php --gate
        if ($LASTEXITCODE -ne 0) {
            Write-Host "NAV-ARCH-02 s40: DEP-11 pilot lost EXACT_WORKSPACE_NAV_CONFORMANCE" -ForegroundColor Red
            exit 1
        }
    }

    Write-Host "All files passed encoding validation" -ForegroundColor Green
    exit 0
}
else {
    Write-Host "$errors encoding issue(s) found" -ForegroundColor Red
    Write-Host ""
    Write-Host "Please fix the encoding issues or run:"
    Write-Host "  powershell -File './scripts/encoding-audit-fix.ps1'" -ForegroundColor Yellow
    Write-Host ""
    exit 1
}
