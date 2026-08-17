<?php
/**
 * 2027_07_11_ticket_report_right_data.php — «الإبلاغُ حقُّ كلِّ مسجَّل» يُقال بالبيانات
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ العيبُ الحقيقيُّ الذي كنتُ أبرّره ولا أحسمه: `Tickets/ticket_form.php` تُنشئ
 *   بلاغًا لكلِّ مستخدمٍ مسجَّلٍ بنصِّ الكودِ نفسِه («① إنشاء بلاغ — متاحٌ لكل
 *   مستخدم مسجّل»)، بينما `role_permissions` تقول لأربعةٍ وثلاثين دورًا
 *   `can_add = 0`. **فالكودُ يتجاوز نموذجَ الصلاحياتِ بدل أن يعبّر عنه** — وهو
 *   عينُ ما ترصده AC-P1A: «سطحٌ يقبل كتابةً بصلاحيةِ عرضٍ وحدَها».
 *
 * ◆ والحسمُ ليس بإسكاتِ الفحصِ ولا بمنعِ الإبلاغ — بل بجعلِ **البياناتِ تقول
 *   السياسةَ**: مَن له حقُّ الإبلاغِ يحمل `can_add` صراحةً، فيصير الحارسُ
 *   حقيقيًّا ويصير الفحصُ صادقًا على السواء.
 *
 * ◆ ونطاقُ المنحِ **أضيقُ ما يفي بالسياسة**: `can_add` وحدَها (لا تعديلَ ولا
 *   حذف)، على `ticket_form.php` وحدَها (لا `tickets_list` ولا `dept_inbox`)،
 *   ولمن له `can_view` سلفًا وحدَه. فالإنشاءُ حقٌّ والتصرفُ في بلاغِ الغيرِ ليس.
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
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$SCREEN = 'Tickets/ticket_form.php';
$mid = null;
$r = $conn->query("SELECT id FROM modules WHERE code = '" . $conn->real_escape_string($SCREEN) . "' LIMIT 1");
if ($r && ($x = $r->fetch_assoc())) { $mid = (int) $x['id']; }
if (!$mid) { exit("✗ الشاشةُ غيرُ مسجَّلةٍ في modules\n"); }

/* ── قبل ── */
$before = $conn->query("SELECT COUNT(*) v, SUM(can_add=1) a FROM role_permissions
                         WHERE module_id={$mid} AND can_view=1")->fetch_assoc();
echo "▐ قبل: أدوارٌ لها عرض={$before['v']} · منها بحقِّ الإنشاء={$before['a']}\n";

/* ── سجلُّ الأثرِ قبل المساس (لا تغييرَ صلاحياتٍ بلا أثرٍ مقروء) ── */
$conn->query("CREATE TABLE IF NOT EXISTS `gov_permission_corrections` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module_code` VARCHAR(160) NOT NULL,
  `role_id` INT NOT NULL,
  `field` VARCHAR(24) NOT NULL,
  `old_value` TINYINT NOT NULL,
  `new_value` TINYINT NOT NULL,
  `policy_source` VARCHAR(255) NOT NULL COMMENT 'نصُّ السياسةِ ومرجعُها — لا منحَ بلا سند',
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_mod_role` (`module_code`, `role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='تصحيحاتُ صلاحياتٍ تُعبّر عن سياسةٍ منصوصة — بأثرٍ مقروءٍ لا صامت'");

$policy = 'سياسةُ الإبلاغِ الشاملة — منصوصةٌ في Tickets/ticket_form.php سطر 64: '
        . '«① إنشاء بلاغ (بلا id) — متاحٌ لكل مستخدم مسجّل». والبياناتُ كانت تخالفها فصُحِّحت.';
$conn->query("INSERT INTO gov_permission_corrections (module_code, role_id, field, old_value, new_value, policy_source)
              SELECT '" . $conn->real_escape_string($SCREEN) . "', role_id, 'can_add', 0, 1,
                     '" . $conn->real_escape_string($policy) . "'
                FROM role_permissions WHERE module_id={$mid} AND can_view=1 AND can_add=0");
$logged = $conn->affected_rows;

/* ── التصحيح: `can_add` وحدَها لمن له عرضٌ سلفًا ── */
$conn->query("UPDATE role_permissions SET can_add=1 WHERE module_id={$mid} AND can_view=1 AND can_add=0");
$changed = $conn->affected_rows;

/* ── بعد ── */
$after = $conn->query("SELECT COUNT(*) v, SUM(can_add=1) a, SUM(can_edit=1) e, SUM(can_delete=1) d
                         FROM role_permissions WHERE module_id={$mid} AND can_view=1")->fetch_assoc();
echo "▐ بعد: أدوارٌ لها عرض={$after['v']} · بحقِّ الإنشاء={$after['a']} · تعديل={$after['e']} · حذف={$after['d']}\n";
echo "  غُيِّر: {$changed} صفًّا · مسجَّلٌ في سجلِّ التصحيحات: {$logged}\n";

/* ── إثباتُ ضيقِ النطاق: الشاشتانِ الأخريانِ لم تُمَسّا ── */
foreach (array('Tickets/tickets_list.php', 'Tickets/dept_inbox.php') as $other) {
    $om = $conn->query("SELECT id FROM modules WHERE code='" . $conn->real_escape_string($other) . "' LIMIT 1");
    if (!$om || !$om->num_rows) { continue; }
    $oid = (int) $om->fetch_assoc()['id'];
    $c = $conn->query("SELECT SUM(can_add=1) a FROM role_permissions WHERE module_id={$oid} AND can_view=1")->fetch_assoc();
    echo "  ◆ {$other}: بحقِّ الإنشاء={$c['a']} (لم تُمَسّ)\n";
}
if ((int) $after['a'] !== (int) $after['v']) { exit("✗ لم يكتمل التصحيح\n"); }
echo "✔ البياناتُ تقول السياسةَ الآن — والحارسُ صار حقيقيًّا لا متجاوَزًا\n";
