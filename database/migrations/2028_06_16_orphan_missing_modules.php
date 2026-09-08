<?php
/**
 * 2028_06_16 — تسجيلُ الشاشاتِ الثلاثِ التي بلا هويّةٍ في سجلِّ الوحدات
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **العطبُ المقيس**: إحدى عشرةَ شاشةً في `WS-ORPHAN` ظهرت محجوبةً
 *   `NO_PERMISSION` — لا لأنَّ الصلاحيّةَ مُنعت بل لأنَّ **لا صفَّ لها في
 *   `modules`**، و`setProfileItem` ترفض رمزًا لا يقابل وحدةً مسجَّلة.
 *   فالشاشةُ موجودةٌ على القرصِ **بلا هويّةٍ في نظامِ الصلاحيّاتِ أصلًا**.
 *
 * ◆ **وفحصُ الإحدى عشرةَ فرزها**: ثمانٍ منها **ليست شاشات** —
 *   `chats/mark_read` · `send_message` · `send_broadcast` نقاطُ نهايةٍ تُنادى
 *   بـPOST · و`_board_decisions` و`_board_waiting` **أجزاءٌ مُضمَّنة** ببادئةِ
 *   `_` · و`oprationprojects` و`project_mines` **ملفّانِ فارغانِ صفرَ بايت** ·
 *   و`setup_permissions` نصُّ تهيئةٍ يُشغَّل مرّة. ⇒ نُقّيت من الماسحِ بحكمِها.
 *
 * ◆ **والثلاثُ الباقيةُ شاشاتٌ حقيقيّةٌ** تُسجَّل هنا لتُفتَح وتُراجَع:
 *     `Approvals/hours_approval_followup.php` (44 ك.ب)
 *     `main/ops_manager_board.php`            (16 ك.ب)
 *     `main/org_structure.php`                ( 7 ك.ب)
 *   ⛔ **والتسجيلُ هويّةٌ لا منحة**: صفُّ `modules` يعرِّف الشاشةَ، والفتحُ
 *     يبقى محكومًا بالقالبِ — فلا يفتحها إلّا من مُنح.
 * التشغيل: php database/migrate.php up
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                   ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل\n"); exit(1); }
$conn->set_charset('utf8mb4');
$ROOT = dirname(__DIR__, 2);
$one = function ($sql) use ($conn) { $r = $conn->query($sql); $x = $r ? $r->fetch_row() : null; return $x ? $x[0] : null; };

$NEW = array(
    'Approvals/hours_approval_followup.php' => array('متابعة اعتماد الساعات', 'fa fa-list-check'),
    'main/ops_manager_board.php'            => array('لوحة مدير التشغيل',     'fa fa-gauge-high'),
    'main/org_structure.php'                => array('الهيكل التنظيمي',        'fa fa-sitemap'),
);

echo "── تسجيلُ هويّةِ ثلاثِ شاشاتٍ يتيمة ──\n";
$added = 0;
foreach ($NEW as $code => $meta) {
    if (!is_file($ROOT . '/' . $code)) { echo "  ⚠ {$code}: لا ملفَّ — تُخطّى\n"; continue; }
    $ex = $one("SELECT id FROM modules WHERE code='" . $conn->real_escape_string($code) . "'");
    if ($ex) { echo "  {$code}: مسجَّلٌ سلفًا (#{$ex})\n"; continue; }
    $st = $conn->prepare("INSERT INTO modules (name, code, owner_role_id, group_id, is_link, is_quick, icon, display_order)
                          VALUES (?,?,NULL,NULL,'0',0,?,0)");
    $st->bind_param('sss', $meta[0], $code, $meta[1]);
    if (!$st->execute()) { fwrite(STDERR, "  ✘ {$code}: {$conn->error}\n"); exit(1); }
    echo "  ✔ {$code} ⇐ وحدة #{$conn->insert_id}\n";
    $added++;
}
echo "  المسجَّلُ حديثًا = {$added}\n";

/* مزامنةُ سجلِّ اليتامى بالهويّةِ الجديدة */
$conn->query("UPDATE gov_orphan_screens o
                JOIN modules m ON m.code = o.route
                 SET o.module_id = m.id, o.module_code = m.code
               WHERE o.module_id IS NULL");
echo "  صفوفُ السجلِّ التي نالت هويّة = {$conn->affected_rows}\n";
$left = (int) $one("SELECT COUNT(*) FROM gov_orphan_screens WHERE module_id IS NULL AND decision<>'WIRED'");
echo "  ما زال بلا هويّة = {$left}\n";
exit(0);
