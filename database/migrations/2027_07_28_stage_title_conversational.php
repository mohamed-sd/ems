<?php
/**
 * 2027_07_28_stage_title_conversational.php — آخرُ تبويبٍ محادثيّ (ثانيًا-٣)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ الطلب: «تبويبٌ محادثيٌّ واحدٌ باقٍ: **«نراقب الأحداثَ والمهام»** ⇐
 *   **«الأحداث والمهام الخلفية»**».
 *
 * ◆ **والمصدرُ ليس حيث يبدو**: اسمُ المجموعةِ المخزَّنُ `ناقلُ الأحداث` — والاسمُ
 *   المُصيَّرُ يأتي من `stage_title` («عاشرًا: نراقب الأحداثَ والمهام»).
 *   فتعديلُ `name` ما كان ليغيّر شيئًا. **والمقياسُ على المُصيَّرِ هو الذي دلَّ
 *   على الحقل**، لا قراءةُ الجدولِ الأولى.
 *
 * ◆ **وثلاثُ مجموعاتٍ تحمله لا واحدة** — والبوابةُ تُبلّغ موضعًا واحدًا لأنها
 *   تعدُّ **ما يُصيَّر لدورٍ فعليّ**. فلو غُيِّر المُصيَّرُ وحدَه لعاد العيبُ عند
 *   أولِ منحِ صلاحيةٍ لدورٍ آخر.
 *
 * ◆ **والترقيمُ اللفظيُّ يبقى**: «عاشرًا:» جزءٌ من نظامِ ترتيبِ المراحلِ القائمِ
 *   في 163 عنوانًا — فيُستبدل **النصُّ المحادثيُّ وحدَه** ولا يُمَسُّ الترقيم.
 *   وإسقاطُه هنا يكسر اتساقَ المراحلِ في كلِّ الأدوار.
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

$OLD = 'نراقب الأحداثَ والمهام';
$NEW = 'الأحداث والمهام الخلفية';

$before = array();
$r = $conn->query("SELECT id, stage_title FROM link_groups WHERE stage_title LIKE '%" . $conn->real_escape_string($OLD) . "%'");
while ($r && ($x = $r->fetch_assoc())) { $before[$x['id']] = $x['stage_title']; }

$st = $conn->prepare("UPDATE link_groups SET stage_title = REPLACE(stage_title, ?, ?)
                       WHERE stage_title LIKE CONCAT('%', ?, '%')");
$st->bind_param('sss', $OLD, $NEW, $OLD);
$st->execute();
$n = $conn->affected_rows;

/* ◆ **والموضعُ الفعليُّ للتصييرِ ثالثٌ لم يظهر في الجدولَين الأولَين**:
     `nav_canonical_current.cur_group` يحمل موضعَ المعلَّقِ الحاليَّ لكلِّ دور،
     ومنه يُصيَّر اسمُ التبويبِ فعلًا. فتعديلُ `link_groups` وحدَه **لم يغيّر
     شيئًا في المُصيَّر** — أُثبت ذلك بإعادةِ القياسِ بعد التعديلِ الأول.
     **والدرسُ: تتبَّعِ الاسمَ إلى حيث يُقرأ، لا إلى أولِ جدولٍ يحمله.** */
$st2 = $conn->prepare("UPDATE nav_canonical_current SET cur_group = REPLACE(cur_group, ?, ?)
                        WHERE cur_group LIKE CONCAT('%', ?, '%')");
$st2->bind_param('sss', $OLD, $NEW, $OLD);
$st2->execute();
$n2 = $conn->affected_rows;

echo "════ آخرُ تبويبٍ محادثيّ ════\n";
printf("  في `link_groups`: %d ⇐ عُدِّل %d · وفي `nav_canonical_current`: عُدِّل %d\n", count($before), $n, $n2);
foreach ($before as $id => $t) {
    $q = $conn->query("SELECT stage_title FROM link_groups WHERE id = " . (int) $id);
    $now = $q ? $q->fetch_assoc()['stage_title'] : '?';
    printf("    #%-5s «%s»\n           ⇐ «%s»\n", $id, $t, $now);
}
$left = (int) $conn->query("SELECT COUNT(*) c FROM link_groups
                             WHERE stage_title LIKE '%" . $conn->real_escape_string($OLD) . "%'")->fetch_assoc()['c']
       + (int) $conn->query("SELECT COUNT(*) c FROM nav_canonical_current
                             WHERE cur_group LIKE '%" . $conn->real_escape_string($OLD) . "%'")->fetch_assoc()['c'];
printf("  المتبقّي بالنصِّ القديم: %d\n", $left);
echo $left === 0 ? "✔ استُبدل النصُّ المحادثيُّ — والترقيمُ اللفظيُّ باقٍ كما هو\n"
                 : "✗ ما زال\n";
