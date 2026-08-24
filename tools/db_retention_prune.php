<?php
/**
 * db_retention_prune.php — سياسةُ استبقاءٍ دوريةٌ للجداولِ السجلّية
 * ═══════════════════════════════════════════════════════════════════════════
 * لماذا وُجد:
 *   `activity_logs` ينمو ~2216 صفًّا/يوم (≈2 م.ب) بلا سقف. بلغ 212,715 صفًّا
 *   و184 م.ب — أكثرَ من نصفِ القاعدة. ولا سياسةَ استبقاءٍ في النظام.
 *
 * ◆ لماذا الحذفُ بالدفعات: حذفُ 200 ألفِ صفٍّ في أمرٍ واحدٍ يبني سجلَّ تراجعٍ
 *   ضخمًا ويقفل الجدولَ طويلًا. الدفعاتُ تُبقي كلَّ معاملةٍ قصيرة.
 *
 * ◆ حارسٌ قبلَ الحذف: لو أشار إلى الجدولِ مفتاحٌ أجنبيٌّ، تتوقّف الأداة —
 *   لأنّ الحذفَ حينئذٍ يُيتِّم صفوفًا أو يُرفَض. (فُحص وقتَ الكتابة: صفر.)
 *
 * ◆ بعدَ الحذفِ تُعاد بناءُ الجدولِ بـ`ALTER … FORCE`، وإلا بقيت المساحةُ
 *   محجوزةً في .ibd ولم يتغيّر حجمُ القرصِ بشيء.
 *
 * التشغيل:
 *   php tools/db_retention_prune.php                     # عرضٌ فقط
 *   php tools/db_retention_prune.php --apply
 *   php tools/db_retention_prune.php --apply --keep-days=14
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

$ROOT     = dirname(__DIR__);
$apply    = in_array('--apply', $argv, true);
$keepDays = 7;
$batch    = 5000;
foreach ($argv as $a) {
    if (preg_match('/^--keep-days=(\d+)$/', $a, $m)) {
        $keepDays = max(1, (int) $m[1]);
    }
    if (preg_match('/^--batch=(\d+)$/', $a, $m)) {
        $batch = max(100, min(50000, (int) $m[1]));
    }
}

/* الجداولُ الخاضعةُ للسياسة: اسمُ الجدول ⇒ عمودُ الطابعِ الزمنيّ.
   ◆ لا تُدرَج هنا الطوابيرُ (`ems_job_queue`) ولا تسليماتُ الأحداث
     (`ems_event_deliveries`): صفوفُها المنتهيةُ تعمل حارسَ «نُفِّذ سلفًا»،
     وحذفُها قد يُعيد جدولةَ مهمّةٍ أو تسليمَ حدثٍ مرةً ثانية.
   ◆ ولا `gov_test_residue_archive`: هو ذاكرةُ تراجعِ مسارِ `--revert` في
     database/migrations/2027_08_27_leaktest_audit_residue_sweep.php */
$POLICY = array(
    'activity_logs' => 'created_at',
);

$env = array();
foreach (file($ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || $line[0] === ';' || !str_contains($line, '=')) {
        continue;
    }
    list($k, $v) = explode('=', $line, 2);
    $env[trim($k)] = trim($v, " \t\"'");
}
list($host, $port) = array_pad(explode(':', $env['DB_HOST'] ?? 'localhost'), 2, 3306);
$db = $env['DB_NAME'] ?? '';

$m = @new mysqli($host, $env['DB_ADMIN_USER'] ?? 'root', $env['DB_ADMIN_PASS'] ?? '', $db, (int) $port);
if ($m->connect_errno) {
    fwrite(STDERR, "تعذَّر الاتصال: {$m->connect_error}\n");
    exit(2);
}
$m->set_charset('utf8mb4');
$dbEsc = $m->real_escape_string($db);

printf("سياسةُ الاستبقاء: %d يومًا · دفعة=%d · الوضع=%s\n\n",
    $keepDays, $batch, $apply ? 'تنفيذ' : 'عرضٌ فقط');

$grandDeleted = 0;
foreach ($POLICY as $table => $timeCol) {
    $t = $m->real_escape_string($table);
    $c = $m->real_escape_string($timeCol);

    $exists = $m->query("SELECT 1 FROM information_schema.TABLES
                          WHERE TABLE_SCHEMA='{$dbEsc}' AND TABLE_NAME='{$t}'");
    if (!$exists || $exists->num_rows === 0) {
        printf("— %s: غيرُ موجود، تُخطّى\n", $table);
        continue;
    }

    // ◆ الحارس: مفتاحٌ أجنبيٌّ يشير إليه ⇒ لا حذف.
    $fk = $m->query("SELECT COUNT(*) c FROM information_schema.KEY_COLUMN_USAGE
                      WHERE REFERENCED_TABLE_SCHEMA='{$dbEsc}' AND REFERENCED_TABLE_NAME='{$t}'");
    $fkN = (int) $fk->fetch_assoc()['c'];
    if ($fkN > 0) {
        printf("⛔ %s: يشير إليه %d مفتاحًا أجنبيًّا — تُخطّى (الحذفُ يُيتِّم)\n", $table, $fkN);
        continue;
    }

    $total = (int) $m->query("SELECT COUNT(*) c FROM `{$t}`")->fetch_assoc()['c'];
    $old   = (int) $m->query("SELECT COUNT(*) c FROM `{$t}`
                               WHERE `{$c}` < DATE_SUB(NOW(), INTERVAL {$keepDays} DAY)")
                     ->fetch_assoc()['c'];

    printf("%s: %s صفًّا · أقدمُ من %d يومًا: %s (%.1f٪)\n",
        $table, number_format($total), $keepDays, number_format($old),
        $total ? $old / $total * 100 : 0);

    if (!$apply || $old === 0) {
        continue;
    }

    $deleted = 0;
    do {
        if (!$m->query("DELETE FROM `{$t}`
                         WHERE `{$c}` < DATE_SUB(NOW(), INTERVAL {$keepDays} DAY)
                         LIMIT {$batch}")) {
            fwrite(STDERR, "فشل الحذف في {$table}: {$m->error}\n");
            break;
        }
        $n = $m->affected_rows;
        $deleted += $n;
    } while ($n > 0);

    $left = (int) $m->query("SELECT COUNT(*) c FROM `{$t}`")->fetch_assoc()['c'];
    printf("  ✔ حُذف %s · بقي %s\n", number_format($deleted), number_format($left));
    $grandDeleted += $deleted;

    // بلا إعادةِ بناءٍ تبقى المساحةُ محجوزةً ولا يتغيّر القرص.
    $t0 = microtime(true);
    if ($m->query("ALTER TABLE `{$t}` FORCE")) {
        printf("  ✔ أُعيد البناء (%.1fث)\n", microtime(true) - $t0);
    } else {
        fwrite(STDERR, "  ⚠ تعذَّرت إعادةُ البناء: {$m->error}\n");
    }
}

if ($apply) {
    printf("\n✔ المجموع: %s صفًّا محذوفًا\n", number_format($grandDeleted));
} else {
    echo "\nعرضٌ فقط. أضِفْ --apply للتنفيذ.\n";
}
