<?php
/**
 * tests/deploy_seed_and_escalation_test.php — خطُّ النشرِ يحمل ما تحتاجه الشاشة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0060 · INJ-0167
 *
 * · 0060: «بعد نشرٍ نظيفٍ من `database/` وحدَه: كلُّ شاشةٍ من الأربعِ والأربعين
 *   تعرض أعمدتَها المصنَّفة، و`SELECT COUNT(*) FROM gov_field_class` > 0».
 * · 0167: «تشغيلُ السكربت سبعةَ أيامٍ متتاليةٍ على الوثيقة نفسِها ينتج **إشعارًا
 *   واحدًا** لمرحلة 7d».
 *
 * ── وكلاهما «مخرَجٌ لا يعمل لأنَّ مفتاحَه خطأ» ────────────────────────────
 * الأولُ: خطوةُ بذرٍ خارجَ خطِّ النشرِ فتُنسى، والشاشاتُ تخلو أبدًا.
 * الثاني: مفتاحُ عطالةٍ من **نصٍّ يحوي `days_left` المتغيّرَ يوميًّا** — فلا
 * يطابق شيئًا غدًا، والوثيقةُ الواحدةُ تولّد ثمانيةَ إشعاراتٍ بدل واحد.
 *
 * ◆ **والسبعةُ أيامٍ تُحاكى فعلًا**: تُزحزح تاريخُ انتهاءِ وثيقةٍ حقيقيةٍ من ٧
 *   إلى ٠ ويُشغَّل السكربتُ في كلِّ يوم — فالحكمُ على سلوكٍ لا على شِفرة.
 * ◆ والوسمُ عائليٌّ ثابتٌ · ويُفحص مُرجَعُ كلِّ حذف · وتُستعاد الوثيقةُ لحالِها.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
ob_start(); require_once $ROOT . '/config.php'; ob_end_clean();
while (ob_get_level() > 0) { ob_end_clean(); }

$conn = $GLOBALS['conn'];
$CO   = 4;
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ خطُّ النشرِ يحمل ما تحتاجه الشاشة · والمفتاحُ ثابتٌ لا زمنيّ');

/* ── ① INJ-0060: التصنيفُ في البذرةِ لا في أداةٍ تُنسى ───────────────────── */
$say("\n── ① تصنيفُ الحقولِ جزءٌ من خطِّ النشر");
$r = $conn->query('SELECT COUNT(*) FROM gov_field_class');
$live = ($r && ($x = $r->fetch_row())) ? (int) $x[0] : -1;
$ok($live > 0, '«`SELECT COUNT(*) FROM gov_field_class` > 0» — حيًّا: ' . $live);
$r = $conn->query('SELECT COUNT(DISTINCT screen_code) FROM gov_field_class WHERE active = 1');
$scr = ($r && ($x = $r->fetch_row())) ? (int) $x[0] : -1;
$ok($scr === 44, '«كلُّ شاشةٍ من **الأربعِ والأربعين**» مصنَّفةٌ: ' . $scr);

$seed = $ROOT . '/database/schema/seed_reference.sql';
$ok(is_file($seed), 'ملفُّ البذرةِ المرجعيةِ موجود');
$seedTxt = (string) @file_get_contents($seed);
$ok(strpos($seedTxt, 'gov_field_class') !== false,
    '**والبذرةُ تحمله** — فنشرٌ من `database/` وحدَه لا يترك الجدولَ خاويًا');
/* والعدُّ في الملفِّ يطابق الحيَّ — لا سطرٌ رمزيّ */
$inFile = preg_match_all("~INSERT INTO `gov_field_class`~", $seedTxt);
$ok($inFile > 0, 'وفيه عباراتُ إدراجٍ فعلية: ' . $inFile);
$dumper = (string) @file_get_contents($ROOT . '/app/Install/SchemaDumper.php');
$ok(strpos($dumper, "'gov_field_class'") !== false,
    'ومولّدُ البذرةِ يُدرجه — فالإصلاحُ في المولِّدِ لا في مُخرَجِه');
$inst = (string) @file_get_contents($ROOT . '/app/Install/Installer.php');
$ok(strpos($inst, 'INSTALL-500') !== false && strpos($inst, 'gov_field_class') !== false,
    '«**وبلا فحصٍ يمنع النشرَ عند خلوِّ الجدول**» — المُثبِّت يُفشل التثبيتَ صراحةً');

/* ── ② INJ-0167: سبعةُ أيامٍ ⇒ إشعارٌ واحد ─────────────────────────────── */
$say("\n── ② سبعةُ أيامٍ متتاليةٍ على الوثيقةِ نفسِها ⇒ إشعارٌ واحد");
$cron = (string) @file_get_contents($ROOT . '/Fleet/cron_doc_expiry_escalation.php');
$ok(strpos($cron, "'doc:' . intval(\$x['doc_id']) . ':stage:7d'") !== false,
    'المفتاحُ صار (وثيقة × مرحلة) ثابتًا');
$ok(!preg_match("~WHERE company_id = [^\n]*\n\s*AND title = '~", $cron),
    'ولم تعد العطالةُ بمقارنةِ نصِّ العنوان');

/* وثيقةٌ حقيقيةٌ تُزحزح ثم تُستعاد */
$doc = null;
$r = $conn->query("SELECT doc_id, company_id, expiry_date, is_deleted
                     FROM equipment_documents
                    WHERE COALESCE(is_deleted,0)=0 AND company_id={$CO}
                    ORDER BY doc_id LIMIT 1");
if ($r) { $doc = $r->fetch_assoc(); }
$ok($doc !== null, 'وثيقةٌ حقيقيةٌ للقياس #' . ($doc ? $doc['doc_id'] : '؟'));

if ($doc) {
    $docId  = (int) $doc['doc_id'];
    $snapEx = $doc['expiry_date'];
    $link   = 'Equipments/equipments.php?doc=' . $docId . '&esc=' . rawurlencode('doc:' . $docId . ':stage:7d');
    $cntN = function () use ($conn, $link, $CO) {
        $r = $conn->query("SELECT COUNT(*) FROM fin_notifications
                            WHERE company_id={$CO} AND link='" . $conn->real_escape_string($link) . "'");
        return ($r && ($x = $r->fetch_row())) ? (int) $x[0] : -1;
    };
    /* كنسٌ قبليٌّ بعائلةِ الرابطِ ومقيسُ العنوانِ القديم */
    $conn->query("DELETE FROM fin_notifications WHERE company_id={$CO}
                   AND (link = '" . $conn->real_escape_string($link) . "'
                        OR title LIKE '%تصعيدٌ E-11: وثيقة #{$docId} %')");
    $ok($cntN() === 0, 'الكنسُ القبليُّ نظيفٌ');

    $runs = 0; $okRuns = true;
    for ($d = 7; $d >= 0; $d--) {
        /* «اليومُ التالي» يُحاكى بتقريبِ تاريخِ الانتهاء — لا بتغييرِ ساعةِ النظام */
        $conn->query("UPDATE equipment_documents SET expiry_date = DATE_ADD(CURDATE(), INTERVAL {$d} DAY)
                       WHERE doc_id = {$docId}");
        $o = array(); $rc = 0;
        @exec(escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg($ROOT . '/Fleet/cron_doc_expiry_escalation.php') . ' 2>&1', $o, $rc);
        if ($rc !== 0) { $okRuns = false; }
        $runs++;
    }
    $ok($okRuns && $runs === 8, 'شُغّل السكربتُ ' . $runs . ' مراتٍ (٧..٠ أيام) بلا رسوب');
    $n = $cntN();
    $ok($n === 1, '«ينتج **إشعارًا واحدًا** لمرحلة 7d»: العدد=' . $n,
        'وقبلَ الإصلاحِ كان ثمانيةً — واحدًا لكلِّ يوم');

    /* الاستعادةُ من اللقطة */
    $st = $conn->prepare('UPDATE equipment_documents SET expiry_date = ? WHERE doc_id = ?');
    if ($st) { $st->bind_param('si', $snapEx, $docId); $st->execute(); $st->close(); }
    $r = $conn->query("SELECT expiry_date FROM equipment_documents WHERE doc_id={$docId}");
    $back = ($r && ($x = $r->fetch_row())) ? (string) $x[0] : '';
    $ok($back === (string) $snapEx, 'واستُعيد تاريخُ انتهاءِ الوثيقةِ إلى ما كان: ' . $back);
    $del = $conn->query("DELETE FROM fin_notifications WHERE company_id={$CO}
                          AND link = '" . $conn->real_escape_string($link) . "'");
    $ok($del !== false && $cntN() === 0, 'وكُنست إشعاراتُ الشاهدِ (مُرجَعُ الحذفِ مفحوص)');
}

$say("\n══ النتيجة: ناجحٌ {$PASS} · راسبٌ {$FAIL}");
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL > 0 ? 1 : 0);
