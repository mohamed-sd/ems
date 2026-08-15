<?php
/**
 * tools/fix_dup_owner_evidence.php — أدلةُ قرارِ المالكِ للمساراتِ المتنازعة
 * ═══════════════════════════════════════════════════════════════════════════
 * الفئةُ ②: ملفّانِ (أو ثلاثة) يكتبانِ في الجدولِ نفسِه. **لا يُحسم أيُّهما يبقى
 * في هذه الجلسة** — أيُّ مسارٍ يُلغى قرارُ مالكِ نطاقٍ لا قرارُ مطوِّر.
 *
 * وهذه الأداةُ تُجهّز القرارَ بأربعةِ أدلةٍ لكلِّ مسار:
 *   ① **الملفُّ والسطرُ** الذي يكتب فيه فعلًا.
 *   ② **عددُ الصفوفِ التي كتبها** من القاعدةِ — فالمسارُ **الميتُ يُحسم بلا نقاش**.
 *   ③ **الخدمةُ** التي يمرُّ بها (أو كتابةٌ مباشرةٌ بلا خدمة).
 *   ④ **أثرُ الإلغاء**: كم رابطَ تنقّلٍ يشير إليه، وأيُّ الأدوارِ تفتحه.
 *
 * ◆ ولا تُلغي هذه الأداةُ شيئًا ولا تُعدّل صفًّا — قراءةٌ محضة.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
ob_start(); require_once $ROOT . '/config.php'; ob_end_clean();
while (ob_get_level() > 0) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$MD = null;
foreach ($argv as $a) { if (strpos($a, '--md=') === 0) { $MD = substr($a, 5); } }

/* ── المتنازعاتُ الخمس — من نصِّ السجلِّ لا من ظنّي ─────────────────────────── */
$CASES = array(
    'INJ-0031' => array('table' => 'rfq_awards',
        'files' => array('Suppliers/rfq_requests.php', 'Procurement/rfq_compare_award.php'),
        'note'  => 'مساران متوازيان للترسية على البيانات نفسِها'),
    'INJ-0114' => array('table' => 'achievement_snapshots',
        'files' => array('Portal/my_achievement.php', 'main/my_workspace.php'),
        'note'  => 'محرّكان للإنجاز يكتبان سجلَّين'),
    'INJ-0168' => array('table' => 'equipments',
        'files' => array('Equipments/equipments.php', 'Equipments/equipment_child_save.php',
                         'Fleet/readiness_board.php'),
        'note'  => 'ثلاثُ شاشاتٍ تكتب جدولَ المعدات'),
    'INJ-0250' => array('table' => 'guard_classification',
        'files' => array('Settings/guard_classification.php', 'Governance/sensitive_fields.php'),
        'note'  => 'تصنيفُ الحمايات في شاشتين'),
    'INJ-0534' => array('table' => 'oprators',
        'files' => array('Oprators/oprators.php', 'Operations/operations_room.php'),
        'note'  => 'سجلُّ المشغّلين بشاشتين — والبابُ الخلفيُّ أُغلق في هذه الجولة'),
);

$writeSites = function ($rel) use ($ROOT) {
    $p = $ROOT . '/' . $rel;
    if (!is_file($p)) { return array('exists' => false, 'sites' => array(), 'svc' => array()); }
    $lines = file($p);
    $sites = array(); $svc = array();
    foreach ($lines as $i => $ln) {
        if (preg_match('~INSERT\s+(?:IGNORE\s+)?INTO\s+`?([a-z0-9_]+)~i', $ln, $m)) {
            $sites[] = ($i + 1) . ':INSERT ' . $m[1];
        }
        if (preg_match('~UPDATE\s+`?([a-z0-9_]+)`?\s+SET~i', $ln, $m2)) {
            $sites[] = ($i + 1) . ':UPDATE ' . $m2[1];
        }
        if (preg_match('~->(insert|update|deleteRow|softDelete)\s*\(\s*[\'"]([a-z0-9_]+)~i', $ln, $m3)) {
            $sites[] = ($i + 1) . ':GATE ' . $m3[2];
        }
        if (preg_match('~\\\\?App\\\\Services\\\\([A-Za-z\\\\]+)::~', $ln, $m4)) { $svc[$m4[1]] = true; }
    }
    return array('exists' => true, 'sites' => $sites, 'svc' => array_keys($svc));
};
$navRefs = function ($rel) use ($conn) {
    $st = $conn->prepare('SELECT COUNT(*) n, GROUP_CONCAT(DISTINCT role_id ORDER BY role_id) r
                            FROM nav_items WHERE route LIKE ?');
    $like = '%' . basename($rel) . '%';
    $st->bind_param('s', $like);
    $st->execute();
    $x = $st->get_result()->fetch_assoc();
    $st->close();
    return $x;
};
$rowsIn = function ($t) use ($conn) {
    if (!preg_match('~^[a-z0-9_]+$~', $t)) { return -1; }
    $r = @$conn->query('SELECT COUNT(*) FROM `' . $t . '`');
    return $r ? (int) $r->fetch_row()[0] : -1;
};

$md = "# قراراتُ المالكِ — مساراتٌ متنازعةٌ على الجدولِ نفسِه\n\n";
$md .= "> مشتقٌّ بـ`php tools/fix_dup_owner_evidence.php --md=…` · "
     . "**لم يُلغَ أيُّ مسارٍ في هذه الجلسة**\n\n";
$md .= "كلُّ حالةٍ أدناه ملفّانِ أو ثلاثةٌ تكتب في الجدولِ نفسِه. وأيُّها يبقى\n";
$md .= "**قرارُ مالكِ نطاقٍ**: يعرف أيَّ الشاشتين يستعملها الناسُ فعلًا وأيَّ\n";
$md .= "تدريبٍ يلزم. والأدلةُ هنا لتقصير النقاشِ لا ليحلَّ محلَّه.\n\n";

echo "══ أدلةُ المساراتِ المتنازعة ══\n\n";
foreach ($CASES as $id => $c) {
    echo "▸ {$id} · {$c['note']} · الجدول `{$c['table']}` (" . $rowsIn($c['table']) . " صفًّا)\n";
    $md .= "## {$id} — {$c['note']}\n\n";
    $md .= "**الجدولُ المتنازَع:** `{$c['table']}` · صفوفُه اليوم: **" . $rowsIn($c['table']) . "**\n\n";
    $md .= "| المسار | موجود | مواضعُ الكتابة | الخدمة | صفوفُ تنقُّلٍ تشير إليه | الأدوار |\n";
    $md .= "|---|---|---|---|---|---|\n";
    foreach ($c['files'] as $f) {
        $w = $writeSites($f);
        $nav = $navRefs($f);
        $sitesTxt = $w['sites'] ? implode('<br>', array_slice($w['sites'], 0, 4)) : '—';
        $svcTxt = $w['svc'] ? implode(' · ', array_slice($w['svc'], 0, 3)) : 'كتابةٌ مباشرة';
        printf("   %-42s %s · مواضع=%d · تنقّل=%s\n", $f,
            $w['exists'] ? 'موجود' : '**غيرُ موجود**', count($w['sites']), $nav['n']);
        $md .= '| `' . $f . '` | ' . ($w['exists'] ? 'نعم' : '**لا**') . ' | ' . $sitesTxt
             . ' | ' . $svcTxt . ' | ' . (int) $nav['n'] . ' | ' . ($nav['r'] ?: '—') . " |\n";
    }
    $md .= "\n**ما يلزم من المالك:** أيُّ مسارٍ يبقى؟ والآخرُ **يُحوَّل** إليه "
         . "(لا يُحذف) ويُبقى جدولُ تحويلٍ من الرابطِ القديم.\n\n";
    echo "\n";
}

if ($MD !== null) {
    $path = (strpos($MD, ':') !== false) ? $MD : ($ROOT . '/' . $MD);
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, $md);
    echo "  · كُتب: {$MD}\n";
}
exit(0);
