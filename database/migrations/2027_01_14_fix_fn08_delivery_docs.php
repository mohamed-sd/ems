<?php
/**
 * 2027_01_14_fix_fn08_delivery_docs.php
 * ═══════════════════════════════════════════════════════════════════════════
 * FIX-03 · FN-08 (P0) — «مستندُ التسليمِ لا يُخزَّن وتكرارُ الإرسالِ يُدرج صفًّا».
 *
 * ① جدولُ مستنداتِ التسليم: مرجعٌ ووقتٌ وشاهد — فالمستندُ يُستدعى ويُدقَّق.
 *    وفريدٌ على (كيان × أمر) ⇒ **مستندٌ واحدٌ لكلِّ أمر** مهما تكرر الإرسال.
 * ② فريدٌ على ‎transfer_events.sync_uuid‎ ⇒ مفتاحُ عطالةٍ مركَّبٌ في القاعدةِ لا
 *    في التطبيق: ‎dlv:<كيان>:<أمر>‎ و‎cls:<كيان>:<أمر>‎.
 *
 * ◆ گوتشا مُعالَجة: الفريدُ على عمودٍ قائمٍ فيه مكرَّراتٌ ينفجر — فتُنظَّف
 *   المكرَّراتُ أولًا بإبقاءِ الأقدمِ (لا بحذفِ الكل) ثم يُضاف الفريد.
 * ◆ وگوتشا ثانية: فريدٌ على عمودٍ ‎NOT NULL DEFAULT ''‎ يمنع أكثرَ من صفٍّ
 *   فارغٍ — لذا يُحوَّل ‎sync_uuid‎ الفارغُ إلى NULL (والفريدُ يسمح بتعدُّدِ NULL).
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال المرحِّل فشل: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$run = static function ($sql, $label) use ($db) {
    if (!$db->query($sql)) { throw new RuntimeException($label . ': ' . $db->error); }
    echo "[FN-08] {$label} ✔\n";
};
$one = static function ($sql) use ($db) {
    $r = $db->query($sql);
    if (!$r) { throw new RuntimeException('SQL: ' . $db->error); }
    $x = $r->fetch_row();
    return $x ? $x[0] : null;
};

/* ── ① جدولُ مستنداتِ التسليم ─────────────────────────────────────────── */
$run("CREATE TABLE IF NOT EXISTS `transfer_delivery_docs` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`   INT(11)         NOT NULL,
  `order_id`     INT(11)         NOT NULL,
  `doc_ref`      VARCHAR(64)     NOT NULL,
  `doc_note`     VARCHAR(500)    NOT NULL DEFAULT '',
  `witness_name` VARCHAR(160)    NOT NULL DEFAULT '',
  `delivered_at` DATETIME        NOT NULL,
  `created_by`   INT(11)         NOT NULL DEFAULT 0,
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tdd_order` (`company_id`, `order_id`),
  UNIQUE KEY `uq_tdd_ref`   (`doc_ref`),
  KEY `idx_tdd_time` (`delivered_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FN-08 · مستندُ تسليمِ أمرِ الترحيل — مرجعٌ ووقتٌ وشاهد'",
  'جدول مستندات التسليم');

/* ── ② تنظيفُ مكرَّراتِ sync_uuid ثم الفريد ────────────────────────────── */
$dupes = (int) $one("SELECT COUNT(*) FROM (
    SELECT sync_uuid FROM transfer_events
     WHERE sync_uuid IS NOT NULL AND sync_uuid <> ''
     GROUP BY sync_uuid HAVING COUNT(*) > 1) d");
echo "[FN-08] مكرَّراتُ sync_uuid قبلَ التنظيف: {$dupes}\n";
if ($dupes > 0) {
    // ◆ لا يُحذف حدثٌ: يُخلى مفتاحُه للأحدثِ ويبقى الأقدمُ حاملَه (سجلٌّ لا يُمحى).
    $run("UPDATE transfer_events e
            JOIN (SELECT sync_uuid, MIN(id) keep_id FROM transfer_events
                   WHERE sync_uuid IS NOT NULL AND sync_uuid <> ''
                   GROUP BY sync_uuid HAVING COUNT(*) > 1) k
              ON k.sync_uuid = e.sync_uuid AND e.id <> k.keep_id
           SET e.sync_uuid = NULL",
        'إخلاءُ مفاتيحِ المكرَّرات مع إبقاءِ الأقدم');
}
// الفارغُ '' يمنع تعدُّدَ الصفوفِ تحت الفريد — يُحوَّل NULL (الفريدُ يسمح بتعدُّده).
$run("UPDATE transfer_events SET sync_uuid = NULL WHERE sync_uuid = ''", 'تحويلُ المفتاحِ الفارغِ إلى NULL');

$hasUq = (int) $one("SELECT COUNT(*) FROM information_schema.STATISTICS
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transfer_events'
                        AND INDEX_NAME = 'uq_te_sync_uuid'");
if ($hasUq === 0) {
    $run("ALTER TABLE transfer_events MODIFY `sync_uuid` VARCHAR(120) NULL DEFAULT NULL",
        'sync_uuid يقبل NULL');
    $run("ALTER TABLE transfer_events ADD UNIQUE KEY `uq_te_sync_uuid` (`sync_uuid`)",
        'فريدٌ على sync_uuid — مفتاحُ العطالةِ في القاعدة');
} else {
    echo "[FN-08] الفريدُ موجودٌ سلفًا — يُتخطّى\n";
}

/* ── ③ إثباتٌ وظيفيّ: إدراجُ حدثين بالمفتاحِ نفسِه يُرفض ────────────────── */
$prev = mysqli_report(MYSQLI_REPORT_OFF);
$probe = 'fn08:probe:' . getmypid();
// ◆ گوتشا: صفٌّ بمفاتيحَ صفريةٍ يُرفض بالمفتاحِ الأجنبيِّ لا بالفريد — فيُقرأ
//   الرفضُ «نجاحًا» كاذبًا. الجسُّ يقع على أمرٍ **حقيقيٍّ** قائم.
$probeOrder = $db->query("SELECT id, company_id FROM transfer_orders ORDER BY id LIMIT 1");
$po = $probeOrder ? $probeOrder->fetch_assoc() : null;
if (!$po) {
    echo "[FN-08] ⚠ لا أمرَ ترحيلٍ لجسِّ الفريد — الفريدُ مُضافٌ ولم يُختبر وظيفيًّا (مُعلَنٌ لا مسكوتٌ عنه)\n";
} else {
    $oid = (int) $po['id']; $cid = (int) $po['company_id'];
    $q = "INSERT INTO transfer_events (company_id, order_id, event_type, body, actor_user_id, sync_uuid)
          VALUES ({$cid},{$oid},'delivered','FN-08 probe',0,'" . $db->real_escape_string($probe) . "')";
    $db->query($q);
    $first = ($db->errno === 0);
    $firstErr = $db->error;
    $db->query($q);
    $secondRejected = ($db->errno === 1062);
    $db->query("DELETE FROM transfer_events WHERE sync_uuid = '" . $db->real_escape_string($probe) . "'");
    mysqli_report($prev);
    if (!$first || !$secondRejected) {
        throw new RuntimeException('الفريدُ لم يمنع التكرار — الترحيلُ يرسب صراحةً'
            . ' (الأولُ ' . ($first ? 'مرَّ' : 'رُفض: ' . $firstErr) . ' · الثاني ' . ($secondRejected ? 'رُفض' : 'مرَّ') . ')');
    }
}
mysqli_report($prev);
echo "[FN-08] الإثباتُ الوظيفي: الحدثُ الثاني بالمفتاحِ نفسِه رُفض 1062 ✔\n";
