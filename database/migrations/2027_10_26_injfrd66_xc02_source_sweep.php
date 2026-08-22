<?php
/**
 * 2027_10_26_injfrd66_xc02_source_sweep.php
 *   XC-02 — كنسُ المصدرِ قبلَ الاشتقاق: الممنوعُ واللفظُ المتقاعدُ والمحادثيّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار** (INJ-FRD-01 · XC-02): «صفرُ صيغةٍ ممنوعةٍ في دفتر الدورة —
 *   **لا في المخرَجِ وحده**». فالكنسُ يقع على النصِّ المخزونِ لا على ما يُصيَّر.
 *
 * ◆ **وما قِيس خالفَ ما وُصف** — وهذا ما تكنسه هذه الهجرةُ فعلًا:
 *   ① الوثيقةُ تقول «ستون صفًّا محادثيًّا في دفتر الدورة» — و`gov_screen_cycle`
 *      **نظيفٌ صفرًا** مقيسًا. والصيغُ في `nav_canonical.old_names` (٥٢ صفًّا
 *      في تسعِ إداراتٍ لا ستّ).
 *   ② الوثيقةُ تقول «أربعةٌ باللفظ المتقاعد» — والمقيسُ **سبعةَ عشرَ صفًّا**:
 *      اثنان في `group_name` وخمسةَ عشرَ في `output_doc`/`inputs_note`.
 *   ③ ولم تذكرِ الوثيقةُ الأخطرَ: **ثلاثةَ عشرَ صفًّا في `nav_canonical_current`
 *      اسمُ مجموعتِها مصطلحٌ ممنوع** — وهي الطبقةُ التي تُرسم منها القائمة.
 *
 * ◆ **والاستبدالُ اشتقاقٌ لا تأليف**: كلُّ صفٍّ ملوَّثٍ في `cur_group` له
 *   مجموعةٌ **معتمَدةٌ سلفًا** في `nav_canonical.group_name` بحالة APPROVED —
 *   فتُنسخ منها ولا تُخترع. وما لا مصدرَ له يُحجَز ولا يُخمَّن.
 *
 * ◆ **ولماذا `old_names` آمنٌ للكنس**: هو عمودُ أرشيفٍ تعتمد عليه بوابةُ
 *   `uxui_preserve_check --gate` لقبولِ إعادةِ التسمية — وكنسُه على عَمًى
 *   يُرسِّب كلَّ التزام. فقِيس قبلَ المساس: صفرُ إصابةٍ لأيِّ لفظٍ محادثيٍّ في
 *   `docs/uxui_live_positions.tsv` ذي الـ911 موضعًا — فلا بندَ سايدبارٍ حملَ
 *   يومًا اسمًا محادثيًّا، وهذه الرموزُ **عاجزةٌ بنيويًّا** عن التصديقِ على أيِّ
 *   إعادةِ تسمية. وكنسُها لا يُضعِف البوابةَ بل يُزيل ما يُعميها.
 *   والبوابةُ تشقُّ `old_names` على «/» و«·» — فالكنسُ بالحدِّ نفسِه وإلا
 *   تفرَّق القارئان.
 *
 * التشغيل:  php database/migrations/2027_10_26_injfrd66_xc02_source_sweep.php
 * الرجوع :  php database/migrations/2027_10_26_injfrd66_xc02_source_sweep.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/includes/conv_form_detect.php';
require_once __DIR__ . '/_ledger.php';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

/* جدولُ الحفظِ للرجوع — يحمل القيمَ قبلَ الكنس */
$conn->query("CREATE TABLE IF NOT EXISTS `injfrd66_xc02_backup` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tbl` VARCHAR(64) NOT NULL,
    `row_key` VARCHAR(255) NOT NULL,
    `col` VARCHAR(64) NOT NULL,
    `old_value` TEXT NULL,
    `swept_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `k_tbl` (`tbl`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

/* ── الرجوع ───────────────────────────────────────────────────────────── */
if (in_array('--revert', $argv, true)) {
    $n = 0;
    $res = $conn->query("SELECT * FROM `injfrd66_xc02_backup` ORDER BY id DESC");
    while ($res && ($r = $res->fetch_assoc())) {
        $k = json_decode($r['row_key'], true);
        if ($r['tbl'] === 'nav_canonical_current') {
            $st = $conn->prepare("UPDATE `nav_canonical_current` SET `cur_group`=? WHERE `role_id`=? AND `route`=?");
            $st->bind_param('sis', $r['old_value'], $k['role_id'], $k['route']);
        } elseif ($r['tbl'] === 'gov_screen_cycle') {
            $col = preg_replace('/[^a-z_]/', '', $r['col']);
            $st = $conn->prepare("UPDATE `gov_screen_cycle` SET `{$col}`=? WHERE `id`=?");
            $st->bind_param('si', $r['old_value'], $k['id']);
        } else {
            $st = $conn->prepare("UPDATE `nav_canonical` SET `old_names`=? WHERE `id`=?");
            $st->bind_param('si', $r['old_value'], $k['id']);
        }
        $st->execute(); $n += $st->affected_rows; $st->close();
    }
    $conn->query("TRUNCATE `injfrd66_xc02_backup`");
    echo "↺ أُعيد {$n} صفًّا إلى قيمتِه قبلَ الكنس\n";
    exit(0);
}

$save = function ($tbl, array $key, $col, $old) use ($conn) {
    $st = $conn->prepare("INSERT INTO `injfrd66_xc02_backup` (`tbl`,`row_key`,`col`,`old_value`) VALUES (?,?,?,?)");
    $jk = json_encode($key, JSON_UNESCAPED_UNICODE);
    $st->bind_param('ssss', $tbl, $jk, $col, $old);
    $st->execute(); $st->close();
};

$FORBIDDEN_RE = 'خارج الوثيقة|بانتظار المالك|بانتظار قرار المالك|إضافات المالك|إضافاتُ المالك|إضافات للمالك|Activation Pattern|Visibility Guard';

/* ═══ ① المجموعاتُ الممنوعةُ في nav_canonical_current — اشتقاقًا لا تأليفًا ═══ */
echo "① اسمُ المجموعةِ الممنوعُ في nav_canonical_current\n";
$rows = $conn->query(
    "SELECT c.role_id, c.route, c.cur_group, k.group_name, k.status
       FROM `nav_canonical_current` c
       LEFT JOIN `nav_canonical` k ON LOWER(k.route) = LOWER(c.route)
      WHERE c.cur_group REGEXP '{$FORBIDDEN_RE}'"
);
$done = 0; $held = array();
while ($rows && ($r = $rows->fetch_assoc())) {
    /* لا يُشتقُّ إلا من مصدرٍ معتمَدٍ نظيف — وإلا حُجز */
    if ($r['status'] !== 'APPROVED' || $r['group_name'] === null
        || preg_match('/' . $FORBIDDEN_RE . '/u', (string) $r['group_name'])) {
        $held[] = "{$r['route']} (دور {$r['role_id']}) — لا مجموعةَ معتمَدةً يُشتقُّ منها";
        continue;
    }
    $save('nav_canonical_current', array('role_id' => (int) $r['role_id'], 'route' => $r['route']), 'cur_group', $r['cur_group']);
    $st = $conn->prepare("UPDATE `nav_canonical_current` SET `cur_group`=? WHERE `role_id`=? AND `route`=?");
    $st->bind_param('sis', $r['group_name'], $r['role_id'], $r['route']);
    $st->execute(); $done += $st->affected_rows; $st->close();
    printf("   ✔ دور %-3d %-44s «%s» ← «%s»\n", $r['role_id'], $r['route'], $r['cur_group'], $r['group_name']);
}
printf("   الحصيلة: %d صفًّا مُشتقًّا · %d محجوزًا\n", $done, count($held));
foreach ($held as $h) { echo "   ⏸ حُجز: {$h}\n"; }
echo "\n";

/* ═══ ② اللفظُ المتقاعدُ في دفترِ الدورة ═══════════════════════════════════ */
echo "② اللفظُ المتقاعدُ «الحاوية» في gov_screen_cycle\n";
$MAP = array(
    'حصص الموردين وحاوياتها' => 'حصص الموردين والتغطية التعاقدية',
    'جداولُ نظامِ الحاويات'   => 'جداولُ التغطيةِ التعاقدية',
    'حاويةُ كلِّ معدة'        => 'التغطيةُ التعاقديةُ لكلِّ معدة',
);
$cols = array('stage_name', 'group_name', 'screen_title', 'output_doc', 'inputs_note');
$n2 = 0; $unmapped = 0;
$res = $conn->query("SELECT id, " . implode(',', $cols) . " FROM `gov_screen_cycle`");
while ($res && ($r = $res->fetch_assoc())) {
    foreach ($cols as $c) {
        $v = (string) $r[$c];
        if (mb_strpos($v, 'حاوي') === false) { continue; }
        $new = strtr($v, $MAP);
        if ($new === $v) { $unmapped++; echo "   ⚠ #{$r['id']} {$c}: لفظٌ متقاعدٌ بلا بديلٍ معلَن — «{$v}»\n"; continue; }
        $save('gov_screen_cycle', array('id' => (int) $r['id']), $c, $v);
        $st = $conn->prepare("UPDATE `gov_screen_cycle` SET `{$c}`=? WHERE `id`=?");
        $st->bind_param('si', $new, $r['id']);
        $st->execute(); $n2 += $st->affected_rows; $st->close();
    }
}
printf("   الحصيلة: %d خليةً مكنوسة · %d بلا بديلٍ معلَن\n\n", $n2, $unmapped);

/* ═══ ③ الصيغُ المحادثيةُ في nav_canonical.old_names — بالرمزِ لا بالنصّ ═══ */
echo "③ الصيغُ المحادثيةُ في nav_canonical.old_names\n";
$n3 = 0; $tokDropped = 0;
$res = $conn->query("SELECT id, route, old_names FROM `nav_canonical` WHERE old_names IS NOT NULL AND old_names <> ''");
$batch = array();
while ($res && ($r = $res->fetch_assoc())) { $batch[] = $r; }
foreach ($batch as $r) {
    $raw = (string) $r['old_names'];
    /* البوابةُ تشقُّ على «/» و«·» — فالكنسُ بالحدِّ نفسِه وإلا تفرَّق القارئان */
    $parts = preg_split('~[/·]+~u', $raw);
    $keep = array(); $drop = 0;
    foreach ($parts as $t) {
        $t = trim($t);
        if ($t === '') { continue; }
        if (ems_is_conversational($t)) { $drop++; continue; }
        $keep[] = $t;
    }
    if ($drop === 0) { continue; }
    $new = implode(' · ', $keep);
    $save('nav_canonical', array('id' => (int) $r['id']), 'old_names', $raw);
    $st = $conn->prepare("UPDATE `nav_canonical` SET `old_names`=? WHERE `id`=?");
    $st->bind_param('si', $new, $r['id']);
    $st->execute(); $n3 += $st->affected_rows; $st->close();
    $tokDropped += $drop;
    printf("   ✔ #%-5d %-46s أُسقط %d رمزًا · بقي %d\n", $r['id'], $r['route'], $drop, count($keep));
}
printf("   الحصيلة: %d صفًّا · %d رمزًا محادثيًّا مُسقَطًا\n\n", $n3, $tokDropped);

ems_migration_recorded(__FILE__, $conn, 0);
echo "✔ اكتمل كنسُ المصدرِ — والقيمُ السابقةُ محفوظةٌ في injfrd66_xc02_backup للرجوع\n";
