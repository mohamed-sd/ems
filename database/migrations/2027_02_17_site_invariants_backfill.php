<?php
/**
 * 2027_02_17 — ثوابتُ المواقع: نطاقٌ رئيسٌ لكلِّ عقدٍ ذي موقع · وموقعٌ افتراضيٌّ لكلِّ مشروع
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ثابتان يحرسهما `sites_entity_test` و`contract_sites_test` — وكلاهما مثقوب:**
 *     ① كلُّ عقدٍ له `site_id` له **نطاقٌ رئيسٌ** في `contract_operational_sites`
 *        — والمقيس: **112 عقدًا مقابل 110 نطاقًا**، فعقدان تخلّفا عن تعبئةِ
 *        `P-01` الرجعية (2019 و2020 · أُنشئا 2026-08-02 بعدَها).
 *     ② كلُّ مشروعٍ حيٍّ له **موقعٌ افتراضيّ** — والترحيلُ عبّأه مرةً ولا خطّافَ
 *        يصونه، فمشروعٌ يُفتح من الشاشةِ اليومَ يُنشأ بلا موقعٍ افتراضي.
 *
 * ◆ **والتعبئةُ اشتقاقٌ من نمطٍ قائمٍ لا اختراع**: صفوفُ `P-01` تحمل
 *   `scope_name` = اسمَ مشروعِ العقد، و`is_primary=1`، و`close_reason` يشرح
 *   نسبَها. فتُنسَخ الصياغةُ نفسُها ويُكتب في `note` أنها **تعبئةٌ متأخرةٌ**
 *   لهذين العقدَين لا تعبئةٌ أصلية — فيُقرأ الأثرُ صحيحًا بعد سنة.
 *
 * ◆ **وما لا يُشتَقُّ لا يُخترع**: ثمانيةُ عقودٍ **بلا `site_id` أصلًا** لا تُعبَّأ
 *   هنا — لأن اشتقاقَ موقعٍ لعقدٍ لا موقعَ له يعني اختيارَ موقعٍ عنه، وذاك
 *   قرارُ تشغيلٍ لا قرارُ مُرحِّل. يُعلَنون بأعيانهم، ويسدُّ الخطّافُ في
 *   `Contracts/contracts.php` البابَ أمام أمثالِهم من الجديد.
 *
 * ◆ مُتحمِّلٌ للتكرار · ويُجَسُّ الثابتان بعده.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

echo "══ ثوابتُ المواقع ══\n";

/* ══ ① نطاقٌ رئيسٌ لكلِّ عقدٍ ذي موقع ═══════════════════════════════════ */
echo "\n── ① نطاقُ العقدِ الرئيس\n";
$miss = array();
$r = $db->query("SELECT c.id, c.company_id, c.site_id, c.project_id, c.contract_status,
                        c.actual_start, c.actual_end
                   FROM contracts c
                  WHERE c.site_id IS NOT NULL
                    AND NOT EXISTS (SELECT 1 FROM contract_operational_sites o
                                     WHERE o.contract_id = c.id AND o.is_primary = 1
                                       AND COALESCE(o.is_deleted,0) = 0)");
while ($r && ($x = $r->fetch_assoc())) { $miss[] = $x; }
echo '  عقودٌ لها موقعٌ بلا نطاقٍ رئيس: ' . count($miss) . "\n";

$added = 0;
foreach ($miss as $c) {
    /* اسمُ النطاقِ = اسمُ المشروعِ كما في تعبئةِ P-01 — لا اسمٌ مُختار */
    $pname = '';
    if ((int) $c['project_id'] > 0) {
        $q = $db->query('SELECT name FROM project WHERE id = ' . (int) $c['project_id']);
        $pr = $q ? $q->fetch_row() : null;
        if ($pr) { $pname = (string) $pr[0]; }
    }
    if ($pname === '') {
        $q = $db->query('SELECT name FROM sites WHERE id = ' . (int) $c['site_id']);
        $sr = $q ? $q->fetch_row() : null;
        $pname = $sr ? (string) $sr[0] : ('نطاقُ العقد #' . (int) $c['id']);
    }
    $closed = in_array((string) $c['contract_status'], array('منتهٍ', 'ملغى', 'مغلق'), true);
    $st = $db->prepare("INSERT INTO contract_operational_sites
              (company_id, contract_id, site_id, scope_name, start_date, end_date,
               state, is_primary, primary_flag, close_reason, note)
              VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, ?, ?)");
    $state = $closed ? 'closed' : 'active';
    $reason = $closed ? 'العقدُ «' . $c['contract_status'] . '» عند التعبئة — الحالةُ مصرَّحةٌ لا مفترَضة' : null;
    $note = 'تعبئةٌ متأخرةٌ 2027_02_17 — تخلّف عن تعبئةِ P-01 الرجعيةِ لأنه أُنشئ بعدها';
    /* ثلاثةُ أعدادٍ وستُّ سلاسلَ = تسعةٌ — والعدَّةُ تُحسَب لا تُقدَّر */
    $st->bind_param('iiissssss', $c['company_id'], $c['id'], $c['site_id'], $pname,
                    $c['actual_start'], $c['actual_end'], $state, $reason, $note);
    if ($st->execute()) { $added++; echo "  ✔ عقد #{$c['id']} ⇒ نطاقٌ رئيسٌ «{$pname}» ({$state})\n"; }
    else { echo "  ✘ عقد #{$c['id']}: " . mb_substr($st->error, 0, 90) . "\n"; }
    $st->close();
}

/* ثمانيةُ عقودٍ بلا موقعٍ أصلًا — يُعلَنون ولا يُخترع لهم */
$noSite = array();
$r = $db->query('SELECT id, project_id, first_party FROM contracts WHERE site_id IS NULL ORDER BY id');
while ($r && ($x = $r->fetch_assoc())) { $noSite[] = $x; }
if ($noSite) {
    echo '  ⚠ عقودٌ **بلا موقعٍ أصلًا** (لا يُشتَقُّ لها ولا يُخترع): ' . count($noSite) . "\n";
    foreach ($noSite as $x) {
        echo '      #' . str_pad($x['id'], 7) . 'مشروع ' . str_pad((string) $x['project_id'], 8)
           . mb_substr((string) $x['first_party'], 0, 34) . "\n";
    }
}

/* ══ ② موقعٌ افتراضيٌّ لكلِّ مشروعٍ حي ═══════════════════════════════════ */
echo "\n── ② الموقعُ الافتراضيُّ للمشروع\n";
$pmiss = array();
$r = $db->query("SELECT p.id, p.company_id, p.name FROM project p
                  WHERE COALESCE(p.status,1) = 1
                    AND NOT EXISTS (SELECT 1 FROM sites s
                                     WHERE s.project_id = p.id AND s.is_default = 1)
                  ORDER BY p.id");
while ($r && ($x = $r->fetch_assoc())) { $pmiss[] = $x; }
echo '  مشاريعُ حيّةٌ بلا موقعٍ افتراضي: ' . count($pmiss) . "\n";

$pAdded = 0;
foreach ($pmiss as $p) {
    /* الاسمُ = اسمُ المشروعِ حرفيًّا — وهو الثابتُ الذي يحرسه الفاحص */
    $st = $db->prepare("INSERT INTO sites (company_id, project_id, name, site_kind, status, is_default)
                        VALUES (?, ?, ?, 'site', 1, 1)");
    $st->bind_param('iis', $p['company_id'], $p['id'], $p['name']);
    if ($st->execute()) { $pAdded++; }
    else { echo '  ✘ مشروع #' . $p['id'] . ': ' . mb_substr($st->error, 0, 90) . "\n"; }
    $st->close();
}
echo "  ✔ أُنشئ موقعٌ افتراضيٌّ باسمِ مشروعِه لـ{$pAdded} مشروعًا\n";

/* ══ الجسّ ═══════════════════════════════════════════════════════════════ */
echo "\n── الجسّ\n";
$b1 = (int) $db->query("SELECT COUNT(*) FROM contracts c WHERE c.site_id IS NOT NULL
                          AND NOT EXISTS (SELECT 1 FROM contract_operational_sites o
                                           WHERE o.contract_id = c.id AND o.is_primary = 1
                                             AND COALESCE(o.is_deleted,0) = 0)")->fetch_row()[0];
echo '  ' . ($b1 === 0 ? '✔' : '✘') . " عقودٌ لها موقعٌ بلا نطاقٍ رئيس: {$b1}\n";
$b2 = (int) $db->query("SELECT COUNT(*) FROM project p WHERE COALESCE(p.status,1) = 1
                          AND NOT EXISTS (SELECT 1 FROM sites s
                                           WHERE s.project_id = p.id AND s.is_default = 1)")
               ->fetch_row()[0];
echo '  ' . ($b2 === 0 ? '✔' : '✘') . " مشاريعُ حيّةٌ بلا موقعٍ افتراضي: {$b2}\n";
$b3 = (int) $db->query("SELECT COUNT(*) FROM project p JOIN sites s
                          ON s.project_id = p.id AND s.is_default = 1
                         WHERE COALESCE(p.status,1) = 1 AND s.name <> p.name")->fetch_row()[0];
echo '  ' . ($b3 === 0 ? '✔' : '⚠') . " افتراضيٌّ باسمٍ يخالف مشروعَه: {$b3}"
   . ($b3 > 0 ? ' (بعضُه من مسارِ ميثاقِ المشروعِ — يُعلَن)' : '') . "\n";
$dup = (int) $db->query("SELECT COUNT(*) FROM (SELECT project_id FROM sites WHERE is_default = 1
                          GROUP BY project_id HAVING COUNT(*) > 1) x")->fetch_row()[0];
echo '  ' . ($dup === 0 ? '✔' : '✘') . " مشاريعُ بأكثرَ من افتراضيٍّ واحد: {$dup}\n";

$ok = ($b1 === 0 && $b2 === 0 && $dup === 0);
echo "\n" . ($ok ? "✅ الثابتان مستوفَيان — والخطّافانِ في الشاشتين يصونانهما للجديد.\n" : "⚠ راجِع أعلاه\n");
exit($ok ? 0 : 1);
