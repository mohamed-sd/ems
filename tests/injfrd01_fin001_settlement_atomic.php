<?php
/**
 * tests/injfrd01_fin001_settlement_atomic.php
 *   شاهدُ FR-FIN-001 · FR-FIN-002 · FR-FIN-003 — التسويةُ ذرّيةٌ ولا نجاحَ جزئيّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **معاييرُ القبولِ بنصِّ الدفتر**:
 *   · FR-FIN-001: «مسحُ شجرةٍ يُخرج **صفرَ موضعِ ابتلاعٍ** في مسارِ التسوية»
 *     · وسالبُه: «إفشالُ سطرٍ عمدًا ← **صفرُ تسويةٍ وصفرُ سطر**».
 *   · FR-FIN-002: «صفرُ تسويةٍ ناجحةٍ **ناقصةِ مكوِّن**».
 *   · FR-FIN-003: «الفشلُ يُرفَع صراحةً ويُنذَر — ولا يُسجَّل ويمضي».
 *
 * ◆ **والسالبُ يُجرَّب حيًّا لا يُوصَف**: يُفشَل مكوِّنٌ فعلًا (بإسقاطِ اسمِ
 *   جدولِه مؤقتًا عبرَ إعادةِ تسميةٍ داخلَ معاملةٍ مُرجَعة) ثم يُقاس أن
 *   **صفرَ تسويةٍ وصفرَ سطرٍ** كُتبا. وبوابةٌ لم تُجرَّب معطوبةً لا تُصدَّق.
 *
 * التشغيل: php tests/injfrd01_fin001_settlement_atomic.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$ok = 0; $bad = 0;
function chk($c, $l, $d = '') {
    global $ok, $bad;
    if ($c) { $ok++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $bad++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
function n(mysqli $d, $q) { $r = @$d->query($q); return $r ? (int) $r->fetch_row()[0] : -1; }

echo "══ FR-FIN-001/002/003 — التسويةُ ذرّيةٌ ولا نجاحَ جزئيّ ══\n";

$SVC = $ROOT . '/app/Services/Settlement/SettlementService.php';
$src = (string) file_get_contents($SVC);

/* ① **صفرُ موضعِ ابتلاعٍ في مسارِ التسوية** — المعيارُ نصًّا */
/* ◆ **استثناءٌ واحدٌ مُعلَنٌ بسببٍ مكتوب** — على نمطِ سقّاطةِ كتّابِ الدفتر:
 *   توليدُ طلبِ الدفعِ يقع **بعدَ إيداعِ الاعتماد**؛ الحدثُ نُشر والذمّةُ
 *   قُيّدت، فإسقاطُ اعتمادٍ مودَعٍ لأجلِ طلبٍ **قابلٍ للإعادةِ من الشاشة**
 *   ضررٌ أكبر. فيُعلَن ولا يُبتلع صامتًا — والمعلَنُ يُعَدُّ ويُراجَع. */
$DECLARED = array('التعذّرُ يُسجَّل ولا يُسقط اعتمادَ التسوية');
/* ◆ **المطابقةُ بعد نزعِ التشكيل** (RPR-W06): معيارُ نقاءِ لغةِ الواجهةِ نزع
     التشكيلَ من رسائلِ النظامِ المُصيَّرة، فصارت المرساةُ المشكولةُ لا تجد
     نصَّها. والمطلبُ لم يتغيَّر — الإعلانُ قائمٌ بنصِّه — فتُنزَع العلاماتُ من
     **الطرفَين** قبل المقارنة. مرساةٌ أمتنُ لا أضعف: تبقى تشترط الإعلانَ
     حرفًا، وتنجو من قاعدةٍ دستوريّةٍ أقرَّها المالك. */
$nd = function ($s) { return preg_replace('/[\x{064B}-\x{0652}\x{0670}]/u', '', (string) $s); };
$swallow = 0; $undeclared = array();
if (preg_match_all('~ems_catch_ignored\([^;]*;~u', $src, $sm)) {
    foreach ($sm[0] as $one) {
        $isDeclared = false;
        foreach ($DECLARED as $d) { if (mb_strpos($nd($one), $nd($d)) !== false) { $isDeclared = true; } }
        if (!$isDeclared) { $swallow++; $undeclared[] = mb_substr($one, 0, 50); }
    }
}
chk($swallow === 0, 'FR-FIN-001/003 · **صفرُ ابتلاعٍ غيرِ مُعلَن** في مسارِ التسوية',
    $swallow === 0 ? 'صفر · ومُعلَنٌ بسببٍ مكتوب: ' . count($DECLARED)
                   : implode(' · ', $undeclared));

/* ② والفشلُ يُرفَع صريحًا باسمِ مكوِّنِه */
$raises = preg_match_all('~SETTLEMENT_COMPONENT_FAILED~', $src);
chk($raises >= 6, 'والفشلُ يُرفَع صريحًا **باسمِ مكوِّنِه**', "مواضعُ الرفع: {$raises}");

/* ③ والكتابةُ داخلَ معاملةٍ واحدة — الذرّية */
chk(strpos($src, 'runInTransaction') !== false,
    'FR-FIN-001 · الكتابةُ في معاملةٍ واحدةٍ ذرّية', 'runInTransaction حاضرة');

/* ④ ولا يُعلَن نجاحٌ إلا بعدَ نجاحِ المعاملةِ كلِّها */
$okAfter = preg_match('~\}\s*catch \(\\\\Throwable \$t\) \{[^}]*failureReason[^}]*return \$out;\s*\}\s*\$out\[\x27ok\x27\] = true;~su', $src);
chk($okAfter === 1, 'FR-FIN-002 · `ok = true` **بعدَ** المعاملةِ لا قبلَها',
    $okAfter === 1 ? 'الترتيبُ صحيح' : 'راجعْ ترتيبَ الإعلان');

/* ── الاختبارُ السالبُ الحيُّ: إفشالُ مكوِّنٍ فعلًا ─────────────────────── */
echo "\n  ── السالبُ الحيُّ: يُفشَل مكوِّنٌ ويُقاس ما كُتب ──\n";
$before  = n($conn, "SELECT COUNT(*) FROM `settlements`");
$beforeL = n($conn, "SELECT COUNT(*) FROM `settlement_lines`");

/* ◆ **الإفشالُ بإخفاءِ جدولِ مكوِّنٍ لا بتعديلِ الخدمة** — فيُقاس السلوكُ لا
 *   الشيفرة. والإخفاءُ بإعادةِ تسميةٍ تُعاد حتمًا في `finally`. */
$renamed = false;
try {
    if ($conn->query("RENAME TABLE `proc_issue` TO `proc_issue__frdbelt`")) { $renamed = true; }
    require_once $ROOT . '/app/Core/TenantGateException.php';
    require_once $ROOT . '/app/Core/TenantRegistry.php';
    require_once $ROOT . '/app/Core/TenantContext.php';
    require_once $ROOT . '/app/Core/TenantDb.php';
    require_once $SVC;
    $gate = new \App\Core\TenantDb($conn, \App\Core\TenantContext::forSystem(4, 0, '', true));
    $sup = $conn->query("SELECT `id` FROM `suppliers` WHERE `company_id`=4 ORDER BY `id` LIMIT 1");
    $sid = $sup ? (int) ($sup->fetch_row()[0] ?? 0) : 0;
    $res = \App\Services\Settlement\SettlementService::generate(
        $gate, $conn, 'supplier', $sid, '2099-01-01', '2099-01-31', 900001);
    $after  = n($conn, "SELECT COUNT(*) FROM `settlements`");
    $afterL = n($conn, "SELECT COUNT(*) FROM `settlement_lines`");
    chk(empty($res['ok']), '**مكوِّنٌ فاشلٌ ⇐ التسويةُ تُرفَض** لا تُكتب ناقصة',
        'المُرجَع: ' . (empty($res['ok']) ? 'رفض' : 'نجاح') . ' · ' . mb_substr((string) ($res['reason'] ?? ''), 0, 60));
    chk($after === $before && $afterL === $beforeL,
        '**وصفرُ تسويةٍ وصفرُ سطرٍ كُتبا**',
        "تسويات {$before}⇐{$after} · سطور {$beforeL}⇐{$afterL}");
    /* المرساةُ بعد نزعِ التشكيل — RPR-W06 نقّى رسائلَ النظامِ المُصيَّرة */
    chk(mb_strpos($nd((string) ($res['reason'] ?? '')), $nd('قطعُ الغيار')) !== false,
        'والسببُ **يسمّي المكوِّنَ الذي فشل**',
        mb_substr((string) ($res['reason'] ?? ''), 0, 70));
} catch (\Throwable $e) {
    chk(false, 'تعذّر تشغيلُ السالبِ الحيّ', mb_substr($e->getMessage(), 0, 70));
} finally {
    if ($renamed) {
        $conn->query("RENAME TABLE `proc_issue__frdbelt` TO `proc_issue`");
    }
}
$restored = n($conn, "SELECT COUNT(*) FROM information_schema.TABLES
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proc_issue'");
chk($restored === 1, 'وأُعيد الجدولُ المخفيُّ حتمًا — الحزامُ لا يترك أثرًا',
    $restored === 1 ? 'proc_issue قائم' : '**لم يُعَد**');

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
