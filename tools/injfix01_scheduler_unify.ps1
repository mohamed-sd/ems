# ===========================================================================
# tools/injfix01_scheduler_unify.ps1
#   Scheduler environment unification - INJ-FIX-01 . Wave B . Barrier 3 . GAP-17
# ===========================================================================
# NOTE (learned the hard way, twice):
#   PowerShell reads this file in the system ANSI codepage. Any Arabic text
#   here - even inside a comment or a single-quoted string - makes the parser
#   fail with "Unexpected token" / "string is missing the terminator", and the
#   script can NEVER run. The first version of this file was written with
#   Arabic messages and was therefore dead on arrival: it was not "blocked by
#   permissions", it simply could not parse. Same rule as .git/hooks/pre-commit.ps1.
#   => THIS FILE MUST STAY ASCII-ONLY. Do not localise it.
#
# Measured defect (2026-08-21): six EMS tasks across FOUR PHP builds:
#       EMS Job Worker        8.2.30   ok (baseline)
#       EMS_WFM_Engine        8.2.30   ok
#       EMS_cron_events       8.0.30   drift
#       EMS_cron_requests     8.5.0    drift
#       EMS_E02_ChainSLA      8.2.29   drift
#       EMS_E02_ChainWeekly   8.2.29   drift
#   and cron_proc_replenish.php is on disk with no scheduled task at all.
#
# Why 8.2.30 is the baseline: WAMP's DO_NOT_DELETE_8.2.30.txt declares it the
# bundle default, and the whole BL-20260821 baseline was measured on it.
#
# Why drift is dangerous: two tasks writing the same tables under different
# engine builds differ in type coercion and in which warnings are raised, so a
# number can be read as valid while being wrong.
#
# Every task definition is exported BEFORE it is touched, so -Revert is a
# tested step and not an attempt.
#
# Usage (run as Administrator):
#   powershell -ExecutionPolicy Bypass -File tools\injfix01_scheduler_unify.ps1
#   powershell -ExecutionPolicy Bypass -File tools\injfix01_scheduler_unify.ps1 -WhatIf
#   powershell -ExecutionPolicy Bypass -File tools\injfix01_scheduler_unify.ps1 -Revert
# ===========================================================================
param(
    [switch]$WhatIf,
    [switch]$Revert
)

$ErrorActionPreference = 'Stop'
$Target    = 'C:\wamp64\bin\php\php8.2.30\php.exe'
$Root      = 'C:\wamp64\www\ems'
$BackupDir = Join-Path $Root 'storage\injfix01\schtasks_backup'

if (-not (Test-Path $Target)) { Write-Error "Baseline PHP not found: $Target"; exit 1 }

# -- Revert: restore every definition from its exported copy ----------------
if ($Revert) {
    if (-not (Test-Path $BackupDir)) { Write-Error "No exported definitions in $BackupDir"; exit 1 }
    Get-ChildItem -Path $BackupDir -Filter '*.xml' | ForEach-Object {
        $xml  = Get-Content $_.FullName -Raw
        $name = $_.BaseName -replace '_', ' '
        try {
            Unregister-ScheduledTask -TaskName $name -Confirm:$false -ErrorAction SilentlyContinue
            Register-ScheduledTask -TaskName $name -Xml $xml | Out-Null
            Write-Output "  restored: $name"
        } catch { Write-Output "  FAILED to restore $name : $_" }
    }
    exit 0
}

New-Item -ItemType Directory -Force -Path $BackupDir | Out-Null

Write-Output '==== Scheduler unification - Barrier 3 ===='
Write-Output "  Baseline PHP: $Target"
Write-Output ''

# -- 1. Export definitions before touching anything -------------------------
Get-ScheduledTask | Where-Object { $_.TaskName -match '^EMS' } | ForEach-Object {
    $f = Join-Path $BackupDir (($_.TaskName -replace '[^A-Za-z0-9_]', '_') + '.xml')
    Export-ScheduledTask -TaskName $_.TaskName | Out-File -FilePath $f -Encoding utf8
}
Write-Output "  1. definitions exported to: $BackupDir"

# -- 2. Unify the PHP build -------------------------------------------------
$script:fixed = 0
Get-ScheduledTask | Where-Object { $_.TaskName -match '^EMS' } | ForEach-Object {
    $task = $_
    $act  = $task.Actions[0]
    $old  = "$($act.Execute) $($act.Arguments)"
    if ($old -notmatch 'php[0-9]+\.[0-9]+\.[0-9]+') { return }
    if ($old -match [regex]::Escape($Target)) {
        Write-Output "  ok   $($task.TaskName): already on baseline"
        return
    }
    if ($act.Execute -match 'cmd') {
        $newArgs   = $act.Arguments -replace 'C:\\wamp64\\bin\\php\\php[0-9.]+\\php\.exe', $Target
        $newAction = New-ScheduledTaskAction -Execute 'cmd' -Argument $newArgs
    } else {
        $newAction = New-ScheduledTaskAction -Execute $Target -Argument ($act.Arguments -replace '"', '')
    }
    if ($WhatIf) {
        Write-Output "  WHATIF $($task.TaskName): $($act.Execute) -> $Target"
    } else {
        Set-ScheduledTask -TaskName $task.TaskName -Action $newAction | Out-Null
        Write-Output "  FIXED  $($task.TaskName): unified onto baseline"
        $script:fixed++
    }
}

# -- 3. Missing tasks -------------------------------------------------------
# The daily backup: Barrier 4 proved ops01_daily_backup.php runs and that
# restore from it succeeds - but NO scheduled task ran it, so the declared
# RPO of "<= 24h" was a capability, not a guarantee. A proven capability with
# no schedule is exactly what the owner refused to accept as "backup enabled".
$toAdd = @(
    @{ Name  = 'EMS_cron_proc_replenish'
       Args  = "$Root\cron_proc_replenish.php"
       Every = 30
       Desc  = 'INJ-FIX-01 Wave B/3 - periodic stock replenishment (GAP-17: was on disk with no schedule)' },
    @{ Name  = 'EMS_daily_backup'
       Args  = "$Root\tools\ops01_daily_backup.php"
       Every = 1440
       Desc  = 'INJ-FIX-01 Wave B/4 - full daily backup (restore proven in RESTORE_DRILL.md)' }
)
foreach ($m in $toAdd) {
    $exists = Get-ScheduledTask -TaskName $m.Name -ErrorAction SilentlyContinue
    if ($exists) {
        Write-Output "  ok   $($m.Name): already scheduled"
    } elseif ($WhatIf) {
        Write-Output "  WHATIF schedule: $($m.Name) (every $($m.Every) min)"
    } else {
        # NOTE: [TimeSpan]::MaxValue serialises to P99999999DT23H59M59S which the
        #       Task Scheduler rejects outright ("value out of range"). Omitting
        #       -RepetitionDuration is what actually yields "repeat indefinitely".
        $a = New-ScheduledTaskAction -Execute $Target -Argument $m.Args
        $t = New-ScheduledTaskTrigger -Once -At (Get-Date) `
                -RepetitionInterval (New-TimeSpan -Minutes $m.Every)
        Register-ScheduledTask -TaskName $m.Name -Action $a -Trigger $t -Description $m.Desc | Out-Null
        Write-Output "  FIXED  $($m.Name): scheduled every $($m.Every) min"
    }
}

Write-Output ''
Write-Output '-- verification --'
Get-ScheduledTask | Where-Object { $_.TaskName -match '^EMS' } | ForEach-Object {
    $a = $_.Actions[0]
    $v = if ("$($a.Execute) $($a.Arguments)" -match 'php([0-9]+\.[0-9]+\.[0-9]+)') { $Matches[1] } else { '-' }
    '{0,-26} {1}' -f $_.TaskName, $v
}
Write-Output ''
Write-Output '  proof: php tests/injfix01_scheduler_parity_proof.php'
