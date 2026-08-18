<?php
/**
 * 2027_07_01_uxui_pending_closure.php — إغلاقُ المعلَّقِ بقاعدةِ الثلاثةِ أيام
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ تفويضُ المالكِ (2026-08-18) رابعًا: «أرسل لكلِّ مديرِ إدارةٍ ما يخصُّه من
 *   الـ76 فقط مع المقترحِ وأسئلتِه الستة · من وافق أو عدَّل خلال ثلاثةِ أيامٍ
 *   يُثبَّت قرارُه · ومن لم يردَّ خلالها يُعتمد المقترحُ تلقائيًّا بوسم
 *   auto-approved by silence ويبقى قابلًا للتعديلِ لاحقًا من الشاشة».
 * ◆ ثلاثةُ جداول: الطلبُ لكلِّ مديرٍ (nav_pending_closure) · وأسئلتُه الستةُ
 *   بأجوبتِها (nav_pending_answers) · وسجلُّ الأحكامِ البشريةِ للأزواجِ الـ59
 *   (nav_dedup_verdicts) — «تثبيتُ أحكامِ الـ59 حقولًا» من بند (د).
 * ◆ الصمتُ لا يُغلق شيئًا في الخفاء: الوسمُ يبقى ظاهرًا في الصفِّ وقابلًا
 *   للنقضِ من الشاشةِ متى شاء المالكُ أو المديرُ — ولا حذفَ ولا تجميد.
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

/* ── ① طلبُ إغلاقِ المعلَّق — صفٌّ لكلِّ مسارٍ معلَّقٍ مع مديرِ إدارتِه ── */
$conn->query("CREATE TABLE IF NOT EXISTS `nav_pending_closure` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `route` VARCHAR(160) NOT NULL,
  `owner_dept` VARCHAR(120) NOT NULL,
  `proposed_label` VARCHAR(190) NOT NULL COMMENT 'الاسمُ المعياريُّ المقترَح',
  `proposed_group` VARCHAR(190) NOT NULL,
  `proposed_level` TINYINT NOT NULL,
  `proposed_order` INT NOT NULL,
  `sent_at` DATETIME NOT NULL COMMENT 'بدءُ مهلةِ الثلاثةِ أيام',
  `due_at` DATETIME NOT NULL,
  `decision` ENUM('pending','approved','modified','auto_approved_by_silence') NOT NULL DEFAULT 'pending',
  `decided_by` INT NULL COMMENT 'users.id — يُقرأ من الجلسةِ لا يُكتب نصًّا',
  `decided_at` DATETIME NULL,
  `modification_note` VARCHAR(500) NULL,
  `reopened_at` DATETIME NULL COMMENT 'الصمتُ قابلٌ للنقضِ لاحقًا من الشاشة',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_route` (`route`),
  KEY `ix_dept_state` (`owner_dept`, `decision`, `due_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='UXUI-01: إغلاقُ المعلَّقِ بقاعدةِ ثلاثةِ أيامٍ — الصمتُ يعتمد المقترحَ ويبقى قابلًا للنقض'");

/* ── ② الأسئلةُ الستةُ لكلِّ مسار (ورقة جلسةِ إغلاقِ المعلَّق) ── */
$conn->query("CREATE TABLE IF NOT EXISTS `nav_pending_answers` (
  `closure_id` INT UNSIGNED NOT NULL,
  `q_no` TINYINT NOT NULL COMMENT '1 الاسم · 2 المجموعة · 3 الترتيب · 4 المستندُ الداخل · 5 الناتج · 6 الحالةُ التالية',
  `answer` ENUM('yes','no','unanswered') NOT NULL DEFAULT 'unanswered',
  `note` VARCHAR(500) NULL,
  PRIMARY KEY (`closure_id`, `q_no`),
  CONSTRAINT `fk_ans_closure` FOREIGN KEY (`closure_id`) REFERENCES `nav_pending_closure`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='UXUI-01: أسئلةُ الإغلاقِ الستةُ بأجوبتِها'");

/* ── ③ أحكامُ الأزواجِ الـ59 حقولًا (بند د-①) ── */
$conn->query("CREATE TABLE IF NOT EXISTS `nav_dedup_verdicts` (
  `pair_no` SMALLINT NOT NULL,
  `route_a` VARCHAR(160) NOT NULL,
  `route_b` VARCHAR(160) NOT NULL,
  `similarity` DECIMAL(5,4) NULL,
  `auto_hint` VARCHAR(190) NULL COMMENT 'الترجيحُ الآليُّ — اقتراحٌ لا حكم',
  `verdict_class` ENUM('independent','related_containment','same_function','view_of_same_data','sequential_stages','header_lines') NOT NULL,
  `verdict_text` VARCHAR(600) NOT NULL COMMENT 'الحكمُ الفنيُّ المسبَّبُ من الكود',
  `evidence` VARCHAR(400) NULL COMMENT 'الجداولُ/الحوارسُ التي بُني عليها الحكم',
  `decided_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`pair_no`),
  KEY `ix_class` (`verdict_class`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='UXUI-01: أحكامُ ازدواجِ المعنى الـ59 — مثبَّتةٌ حقولًا لا نصًّا في ملف'");

/* ── بذرُ طلباتِ الإغلاق من السجلِّ المعياريِّ نفسِه ── */
$sent = date('Y-m-d H:i:s');
$due  = date('Y-m-d H:i:s', strtotime('+3 days'));
$ins = $conn->prepare("INSERT INTO nav_pending_closure
    (route, owner_dept, proposed_label, proposed_group, proposed_level, proposed_order, sent_at, due_at)
    VALUES (?,?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE proposed_label=VALUES(proposed_label), proposed_group=VALUES(proposed_group),
      proposed_level=VALUES(proposed_level), proposed_order=VALUES(proposed_order)");
$q = $conn->query("SELECT route, owner_dept, canonical_ar, group_name, level_no, sort_no
                     FROM nav_canonical WHERE status IN ('PENDING_OWNER','PENDING_OWNER_MERGE') ORDER BY owner_dept, sort_no");
$n = 0;
while ($x = $q->fetch_assoc()) {
    $dept = $x['owner_dept'] !== '' ? $x['owner_dept'] : 'غيرُ مسنَدة';
    $ins->bind_param('ssssiiss', $x['route'], $dept, $x['canonical_ar'], $x['group_name'], $x['level_no'], $x['sort_no'], $sent, $due);
    $ins->execute(); $n++;
}
echo "طلباتُ الإغلاقِ المبذورة: {$n} · المهلةُ تنتهي: {$due}\n";

/* الأسئلةُ الستةُ لكلِّ طلبٍ — غيرُ مُجابةٍ حتى يردَّ المدير */
$conn->query("INSERT IGNORE INTO nav_pending_answers (closure_id, q_no, answer)
              SELECT c.id, q.n, 'unanswered' FROM nav_pending_closure c
              JOIN (SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6) q");

$c1 = (int) $conn->query("SELECT COUNT(*) c FROM nav_pending_closure")->fetch_assoc()['c'];
$c2 = (int) $conn->query("SELECT COUNT(*) c FROM nav_pending_answers")->fetch_assoc()['c'];
$c3 = (int) $conn->query("SELECT COUNT(DISTINCT owner_dept) c FROM nav_pending_closure")->fetch_assoc()['c'];
echo "الطلبات={$c1} · الأسئلة={$c2} (={$c1}×6) · الإداراتُ المالكة={$c3}\n";
echo "✔ آليةُ إغلاقِ المعلَّقِ جاهزةٌ — والصمتُ يعتمد بعد {$due} ويبقى قابلًا للنقض\n";
