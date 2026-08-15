<?php
/**
 * 2027_04_17_site_gate_real_links.php
 * ═══════════════════════════════════════════════════════════════════════════
 * إذنُ دخولِ المعدةِ يرتبط بسجلَّيه لا بنصٍّ حرّ — ⇐ INJ-0370
 *
 * نصُّ القبول: «إذنُ دخولِ معدةٍ **يرتبط بمعدةٍ من سجل المعدات وبموقعٍ من سجل
 * المواقع**، وهويةُ المعتمِدِ **تُشتق من الحساب المعتمِد ولا تُكتب يدويًّا**».
 *
 * ── المقيس ───────────────────────────────────────────────────────────────
 * `scr_site_gate_equip` أعمدتُه كلُّها `varchar(300)`: المعدةُ نصٌّ
 * (`code_equipment`) والموقعُ نصٌّ (`site_name`) والمعتمِدُ نصٌّ يكتبه المُدخِلُ
 * بيدِه (`approval_manager_site`). فلا يُعرف أيُّ معدةٍ دخلت ولا أيُّ موقعٍ
 * دخلَته، ولا يُحاسَب معتمِدٌ على إذنٍ وقّعه غيرُه باسمه.
 *
 * ── والعلاجُ ربطٌ لا هجرةُ مخزن ──────────────────────────────────────────
 * الإذنُ مستندُ الموقعِ وموضعُه هذا الجدول — فلا يُنقل. الناقصُ **مفاتيحُه**:
 * ثلاثةُ أعمدةٍ مرجعيةٍ إلى `equipments` و`project` و`users`.
 *
 * ◆ والنصوصُ القديمةُ تبقى كما هي (`code_equipment` · `site_name`) — تُقرأ
 *   ولا تُحذف؛ والجديدُ يُكتب بالمفاتيحِ **وبالنصِّ معًا** فلا ينكسر عارضٌ.
 * ◆ ولا رَدْمَ بأثرٍ رجعيّ: صفٌّ قديمٌ نصُّه «حفار ٢٢» لا يُنسب إلى معدةٍ
 *   بالتخمين — يبقى بلا مفتاحٍ ويُعلَن.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_USER') : ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_PASS') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

echo "══ إذنُ دخولِ المعدةِ يرتبط بسجلَّيه ══\n\n";
$has = function ($col) use ($conn) {
    $r = $conn->query("SHOW COLUMNS FROM scr_site_gate_equip LIKE '{$col}'");
    return $r && $r->num_rows > 0;
};
$ADD = array(
    array('equipment_id', 'INT NULL', 'INJ-0370: مرجعُ المعدةِ من سجلِّ المعدات — لا نصٌّ حر'),
    array('site_project_id', 'INT NULL', 'INJ-0370: مرجعُ الموقعِ من سجلِّ المشاريع/المواقع'),
    array('approved_by_user', 'INT NULL', 'INJ-0370: هويةُ المعتمِدِ من الحسابِ لا من الكتابة'),
);
$n = 0;
foreach ($ADD as $a) {
    list($c, $def, $why) = $a;
    if ($has($c)) { echo "  · {$c} قائمٌ سلفًا\n"; continue; }
    if (!$conn->query("ALTER TABLE scr_site_gate_equip ADD COLUMN `{$c}` {$def}
                        COMMENT '" . $conn->real_escape_string($why) . "'")) {
        echo "  ✘ {$c}: {$conn->error}\n";
        continue;
    }
    $n++;
    echo "  ✔ {$c} — {$why}\n";
}
foreach (array(array('ix_sge_eq', 'equipment_id'), array('ix_sge_site', 'site_project_id')) as $ix) {
    $r = $conn->query("SHOW INDEX FROM scr_site_gate_equip WHERE Key_name = '{$ix[0]}'");
    if ($r && $r->num_rows > 0) { continue; }
    $conn->query("ALTER TABLE scr_site_gate_equip ADD KEY `{$ix[0]}` (`{$ix[1]}`)");
}
echo "\n  أُضيف: {$n}\n";

$r = $conn->query('SELECT COUNT(*) FROM scr_site_gate_equip');
$all = $r ? (int) $r->fetch_row()[0] : -1;
$r = $conn->query('SELECT COUNT(*) FROM scr_site_gate_equip WHERE equipment_id IS NULL');
$bare = $r ? (int) $r->fetch_row()[0] : -1;
echo "  أذونٌ قائمةٌ: {$all} · منها بلا مفتاحِ معدةٍ: {$bare}"
   . " (موروثٌ نصيٌّ — يُعلَن ولا يُخمَّن)\n";
echo "\n✔ تمّت\n";
