# ═══════════════════════════════════════════════════════════════════════════
# tools/injfix01_scheduler_unify.ps1
#   توحيدُ بيئةِ المجدول — INJ-FIX-01 · الموجة ب · الحاجز ③ · GAP-17
# ═══════════════════════════════════════════════════════════════════════════
# ◆ **لماذا سكربتٌ لا تنفيذٌ مباشر**: تعديلُ مهامِّ ويندوز المجدولةِ تغييرٌ
#   خارجَ الشجرةِ يمسُّ إعدادَ الجهاز، فيُسلَّم أمرًا مقروءًا يُنفَّذ بعلمٍ لا
#   يُجرى صامتًا. **ويُصدَّر تعريفُ كلِّ مهمةٍ قبلَ مسِّها** ليكون الرجوعُ
#   خطوةً مجرَّبةً لا محاولة.
#
# ◆ **العطبُ المقيس** (2026-08-21): ستُّ مهامٍّ على **أربعةِ إصداراتٍ** من PHP —
#   والبطاقةُ كانت تقول ثلاثة:
#       EMS Job Worker        8.2.30   ✔ الأساس
#       EMS_WFM_Engine        8.2.30   ✔ الأساس
#       EMS_cron_events       8.0.30   ✘
#       EMS_cron_requests     8.5.0    ✘
#       EMS_E02_ChainSLA      8.2.29   ✘
#       EMS_E02_ChainWeekly   8.2.29   ✘
#   و`cron_proc_replenish.php` **على القرصِ بلا مهمةٍ مجدولة** — وهي «المهمةُ
#   الناقصة» في معيارِ القبول.
#
# ◆ **ولماذا 8.2.30 هو الأساس**: ملفُّ تثبيتِ WAMP `DO_NOT_DELETE_8.2.30.txt`
#   يعلنه إصدارَ الحزمةِ الافتراضيّ، وهو ما قِيس عليه الأساسُ كلُّه.
#
# ◆ **وسلوكٌ مختلفٌ بحسبِ المهمةِ خطرٌ صامت**: مهمّتان تكتبان في الجداولِ
#   نفسِها بمحرّكَي لغةٍ مختلفَين تختلفان في تحويلِ الأنواعِ وفي التحذيراتِ
#   المرفوعة — فيُقرأ الرقمُ صحيحًا وهو خطأ.
#
# التشغيل (بصلاحيةِ مسؤول):
#   powershell -ExecutionPolicy Bypass -File tools\injfix01_scheduler_unify.ps1
#   powershell -ExecutionPolicy Bypass -File tools\injfix01_scheduler_unify.ps1 -WhatIf
#   powershell -ExecutionPolicy Bypass -File tools\injfix01_scheduler_unify.ps1 -Revert
# ═══════════════════════════════════════════════════════════════════════════
param(
    [switch]$WhatIf,
    [switch]$Revert
)

$ErrorActionPreference = 'Stop'
$Target    = 'C:\wamp64\bin\php\php8.2.30\php.exe'
$Root      = 'C:\wamp64\www\ems'
$BackupDir = Join-Path $Root 'storage\injfix01\schtasks_backup'

if (-not (Test-Path $Target)) { Write-Error "إصدارُ الأساسِ غيرُ موجود: $Target"; exit 1 }

# ── الرجوع: يُعاد كلُّ تعريفٍ من نسخته المصدَّرة ────────────────────────────
if ($Revert) {
    if (-not (Test-Path $BackupDir)) { Write-Error "لا نسخَ محفوظةً في $BackupDir"; exit 1 }
    Get-ChildItem -Path $BackupDir -Filter '*.xml' | ForEach-Object {
        $xml  = Get-Content $_.FullName -Raw
        $name = $_.BaseName -replace '_', ' '
        try {
            Unregister-ScheduledTask -TaskName $name -Confirm:$false -ErrorAction SilentlyContinue
            Register-ScheduledTask -TaskName $name -Xml $xml | Out-Null
            Write-Output "  ↺ أُعيد: $name"
        } catch { Write-Output "  ✘ تعذّر إرجاع $name : $_" }
    }
    exit 0
}

New-Item -ItemType Directory -Force -Path $BackupDir | Out-Null

Write-Output '════ توحيدُ بيئةِ المجدول — الحاجز ③ ════'
Write-Output "  إصدارُ الأساس: $Target"
Write-Output ''

# ── ① تصديرُ التعريفاتِ قبلَ أيِّ مسّ ────────────────────────────────────────
Get-ScheduledTask | Where-Object { $_.TaskName -match '^EMS' } | ForEach-Object {
    $f = Join-Path $BackupDir (($_.TaskName -replace '[^A-Za-z0-9_]', '_') + '.xml')
    Export-ScheduledTask -TaskName $_.TaskName | Out-File -FilePath $f -Encoding utf8
}
Write-Output "  ① صُدِّرت التعريفاتُ إلى: $BackupDir"

# ── ② توحيدُ الإصدار ───────────────────────────────────────────────────────
$fixed = 0
Get-ScheduledTask | Where-Object { $_.TaskName -match '^EMS' } | ForEach-Object {
    $task = $_
    $act  = $task.Actions[0]
    $old  = "$($act.Execute) $($act.Arguments)"
    if ($old -notmatch 'php[0-9]+\.[0-9]+\.[0-9]+') { return }
    if ($old -match [regex]::Escape($Target)) {
        Write-Output "  ✔ $($task.TaskName): على الأساسِ سلفًا"
        return
    }
    if ($act.Execute -match 'cmd') {
        $newArgs   = $act.Arguments -replace 'C:\\wamp64\\bin\\php\\php[0-9.]+\\php\.exe', $Target
        $newAction = New-ScheduledTaskAction -Execute 'cmd' -Argument $newArgs
    } else {
        $newAction = New-ScheduledTaskAction -Execute $Target -Argument ($act.Arguments -replace '"', '')
    }
    if ($WhatIf) {
        Write-Output "  ◆ [WhatIf] $($task.TaskName): $($act.Execute) ⇐ $Target"
    } else {
        Set-ScheduledTask -TaskName $task.TaskName -Action $newAction | Out-Null
        Write-Output "  ✔ $($task.TaskName): وُحِّد على الأساس"
        $script:fixed++
    }
}

# ── ③ المهامُّ الناقصة ─────────────────────────────────────────────────────
# ◆ **والنسخةُ اليوميةُ منها**: الحاجزُ ④ أثبت أن `ops01_daily_backup.php` يعمل
#   وأن الاستعادةَ منه تنجح — **لكنَّ لا مهمةَ مجدولةً تُشغّله**، فالـRPO
#   المُعلَنُ «≤ 24 ساعة» يبقى قدرةً لا ضمانًا. والقدرةُ المُثبَتةُ بلا جدولةٍ
#   هي بالضبط ما رفضه المالك: «لا أقبل ‹النسخُ الاحتياطيُّ مفعَّل›».
$toAdd = @(
    @{ Name = 'EMS_cron_proc_replenish'
       Args = "$Root\cron_proc_replenish.php"
       Every = 30
       Desc  = 'INJ-FIX-01 §ب③ — تعبئةُ المخزونِ الدوريّة (GAP-17: كانت على القرصِ بلا جدولة)' },
    @{ Name = 'EMS_daily_backup'
       Args = "$Root\tools\ops01_daily_backup.php"
       Every = 1440
       Desc  = 'INJ-FIX-01 §ب④ — النسخةُ اليوميةُ الكاملة (الاستعادةُ منها مُثبَتةٌ في RESTORE_DRILL.md)' }
)
foreach ($m in $toAdd) {
    $exists = Get-ScheduledTask -TaskName $m.Name -ErrorAction SilentlyContinue
    if ($exists) {
        Write-Output "  ✔ $($m.Name): مجدولةٌ سلفًا"
    } elseif ($WhatIf) {
        Write-Output "  ◆ [WhatIf] تُجدوَل: $($m.Name) (كلَّ $($m.Every) دقيقة)"
    } else {
        $a = New-ScheduledTaskAction -Execute $Target -Argument $m.Args
        $t = New-ScheduledTaskTrigger -Once -At (Get-Date) `
                -RepetitionInterval (New-TimeSpan -Minutes $m.Every) `
                -RepetitionDuration ([TimeSpan]::MaxValue)
        Register-ScheduledTask -TaskName $m.Name -Action $a -Trigger $t -Description $m.Desc | Out-Null
        Write-Output "  ✔ $($m.Name): جُدولت كلَّ $($m.Every) دقيقة"
    }
}

Write-Output ''
Write-Output '── الاستيثاق ──'
Get-ScheduledTask | Where-Object { $_.TaskName -match '^EMS' } | ForEach-Object {
    $a = $_.Actions[0]
    $v = if ("$($a.Execute) $($a.Arguments)" -match 'php([0-9]+\.[0-9]+\.[0-9]+)') { $Matches[1] } else { '—' }
    '{0,-24} {1}' -f $_.TaskName, $v
}
Write-Output ''
Write-Output '  الشاهد: php tests/injfix01_scheduler_parity_proof.php'
