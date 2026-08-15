<?php
/**
 * tests/permission_guard_core_test.php — شاهدُ حارسِ الصلاحياتِ المركزيّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0008 · INJ-0202 · INJ-0261
 *
 * ثلاثةُ بنودٍ جذرُها واحدٌ: `check_page_permissions()` — البوابةُ التي تقف أمام
 * كلِّ شاشةٍ في النظام.
 *
 * · **INJ-0008**: «شاشةٌ **غيرُ مسجَّلةٍ** في `modules` تُعيد `can_view=false`
 *   وتُحجب لكلِّ دورٍ عدا السوبر». وكان الافتراضُ القديمَ **فتحَ كلِّ الصلاحيات**
 *   للشاشةِ غيرِ المسجَّلة — أي أنَّ نسيانَ التسجيلِ يفتحها للجميع. وهو أخطرُ
 *   عيبٍ في العائلة: الغيابُ يُقرأ إذنًا.
 *
 * · **INJ-0261**: «كلُّ شاشةٍ تُرجع `id` مودولِها **الصحيحِ حصرًا**». والفخُّ:
 *   ملفّان باسمٍ متشابهٍ (`equipments` و`equipments_types`) — والمطابقةُ
 *   التقريبيةُ تحلُّ الأوّلَ للثاني، فيرث دورٌ صلاحيةَ شاشةٍ أخرى.
 *
 * · **INJ-0202**: «كلُّ دورٍ بحالة `status=1` يملك منحةَ قراءةٍ واحدةً على الأقل
 *   وصفَّ `nav_items` واحدًا فعّالًا» — فدورٌ بلا منحةٍ ولا قائمةٍ يدخل إلى فراغ.
 *
 * ── والقياسُ على الدالّةِ الحيّةِ لا على نصِّها ─────────────────────────────
 * تُنادى `check_page_permissions()` بمدخلاتٍ حقيقيةٍ: اسمٍ غيرِ مسجَّلٍ قطعًا،
 * واسمَي شاشتين متشابهتَي البادئة — ويُقارَن المُرجَعُ بالصفِّ الحيِّ في `modules`.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once $ROOT . '/includes/permissions_helper.php';

$conn = $GLOBALS['conn'];
$CO = 4;
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ حارسُ الصلاحياتِ المركزيّ — ثلاثةُ بنودٍ بجذرٍ واحد');

/* سياقُ جلسةٍ لدورٍ عاديٍّ — الحارسُ يقرأ الدورَ من الجلسة */
$roleId = 0; $userId = 0;
$r = $conn->query("SELECT id, role FROM users WHERE company_id = {$CO} AND role NOT IN ('-1','')
                    AND username <> '' ORDER BY id LIMIT 1");
if ($r && ($x = $r->fetch_assoc())) { $userId = (int) $x['id']; $roleId = (int) $x['role']; }
$ok($roleId > 0, "سياقُ دورٍ عاديٍّ (دور {$roleId})");
$_SESSION['user'] = array('id' => $userId, 'role' => (string) $roleId, 'company_id' => $CO);

/* ── ① INJ-0008 · الشاشةُ غيرُ المسجَّلةِ تُحجب — والغيابُ ليس إذنًا ─────────── */
$ghost = 'Ghost/never_registered_' . substr(md5('permguard'), 0, 8) . '.php';
$exists = false;
$st = $conn->prepare('SELECT 1 FROM modules WHERE code = ? LIMIT 1');
$st->bind_param('s', $ghost);
$st->execute();
$exists = (bool) $st->get_result()->fetch_row();
$st->close();
$ok(!$exists, 'اسمُ شاشةٍ غيرُ مسجَّلٍ قطعًا يُقاس عليه');

$g = check_page_permissions($conn, $ghost);
$ok(is_array($g) && empty($g['can_view']) && empty($g['can_add'])
    && empty($g['can_edit']) && empty($g['can_delete']),
    '**شاشةٌ غيرُ مسجَّلةٍ ⇒ كلُّ الصلاحياتِ false** — فالغيابُ حجبٌ لا إذن',
    is_array($g) ? json_encode($g, JSON_UNESCAPED_UNICODE) : 'لا مصفوفة');
$ok(!empty($g['unregistered']),
    'ويُعلَن السببُ صراحةً (`unregistered`) — لا صفرٌ صامتٌ يُقرأ «لا صلاحية»');
$ok($g['id'] === null, 'ولا يُنسب إلى مودولٍ شبيهٍ بالصدفة');

/* ── ② INJ-0261 · اسمانِ متشابهانِ يُحَلّان إلى مودولَيهما لا إلى أوّلِهما ───── */
$pairs = array();
$r = $conn->query("SELECT code FROM modules WHERE code LIKE '%/%' ORDER BY code");
$codes = array();
while ($r && ($x = $r->fetch_row())) { $codes[] = (string) $x[0]; }
foreach ($codes as $c1) {
    $b1 = basename($c1, '.php');
    foreach ($codes as $c2) {
        if ($c1 === $c2) { continue; }
        $b2 = basename($c2, '.php');
        /* زوجٌ يبدأ أحدُهما بالآخرِ — موضعُ الالتباس */
        if ($b2 !== $b1 && strpos($b2, $b1) === 0) { $pairs[] = array($c1, $c2, $b1, $b2); }
        if (count($pairs) >= 6) { break 2; }
    }
}
$ok(count($pairs) > 0, 'وُجدت أزواجٌ متشابهةُ البادئةِ يُقاس عليها الالتباس (' . count($pairs) . ')');
$wrong = array();
foreach ($pairs as $p) {
    foreach (array(0, 1) as $i) {
        $code = $p[$i];
        $want = 0;
        $st = $conn->prepare('SELECT id FROM modules WHERE code = ? LIMIT 1');
        $st->bind_param('s', $code);
        $st->execute();
        $x = $st->get_result()->fetch_row();
        $st->close();
        if ($x) { $want = (int) $x[0]; }
        $got = check_page_permissions($conn, $code);
        if ($want > 0 && (int) $got['id'] !== $want) {
            $wrong[] = $code . ' ⇒ ' . (int) $got['id'] . ' بدل ' . $want;
        }
    }
}
$ok(empty($wrong),
    '**وكلُّ شاشةٍ تُرجع مودولَها الصحيحَ حصرًا** (' . (count($pairs) * 2) . ' مطابقةً)',
    implode(' · ', array_slice($wrong, 0, 3)));

/* ── ③ INJ-0202 · لا دورَ فعّالٌ بلا منحةِ قراءةٍ ولا صفِّ قائمة ─────────────── */
$orphans = array();
$r = $conn->query("SELECT r.id, r.name FROM roles r
                    WHERE COALESCE(r.status,1) = 1
                      AND NOT EXISTS (SELECT 1 FROM role_permissions rp
                                       WHERE rp.role_id = r.id AND rp.can_view = 1)
                    ORDER BY r.id");
while ($r && ($x = $r->fetch_assoc())) { $orphans[] = '#' . $x['id'] . ' ' . $x['name']; }
$ok(empty($orphans),
    '**كلُّ دورٍ فعّالٍ يملك منحةَ قراءةٍ واحدةً على الأقل** (' . count($orphans) . ' استثناء)',
    implode(' · ', array_slice($orphans, 0, 4)));

$navless = array();
$r = $conn->query("SELECT r.id, r.name FROM roles r
                    WHERE COALESCE(r.status,1) = 1
                      AND NOT EXISTS (SELECT 1 FROM nav_items ni
                                       WHERE ni.role_id = r.id AND COALESCE(ni.is_active,1) = 1)
                    ORDER BY r.id");
while ($r && ($x = $r->fetch_assoc())) { $navless[] = '#' . $x['id'] . ' ' . $x['name']; }
$ok(empty($navless),
    '**وصفَّ قائمةٍ فعّالًا واحدًا على الأقل** (' . count($navless) . ' استثناء)',
    implode(' · ', array_slice($navless, 0, 4)));

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
