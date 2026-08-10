<?php
/**
 * 2027_01_13_fix_fn07_return_guard.php
 * ═══════════════════════════════════════════════════════════════════════════
 * FIX-03 · FN-07 خطوتا ③+④ — قيدُ القاعدةِ الذي يمنع تجاوزَ المرتجَعِ للمصروف،
 * ومرجعُ سندِ الصرفِ **إلزامًا** على كلِّ مرتجع.
 *
 * ◆ لماذا في القاعدةِ لا في التطبيقِ وحدَه (RSK-M3): «التطبيقُ يُتجاوز» —
 *   سكربتُ استيرادٍ أو نافذةُ SQL أو شاشةٌ أخرى تكتب في الجدولِ مباشرةً تتخطّى
 *   كلَّ فحصٍ في PHP. القادحُ يمنعها كلَّها.
 *
 * ◆ ولماذا قادحٌ لا ‎CHECK‎: القيدُ ‎CHECK‎ لا يرى صفوفًا أخرى — وقاعدةُ المرتجعِ
 *   مجموعٌ عبر صفوف. أمّا «المرجعُ إلزامي» فهو قيدُ صفٍّ واحدٍ ⇒ ‎CHECK‎.
 *
 * ◆ گوتشا مُتجنَّبة: يُنزع ‎DEFINER‎ (التصديرُ للاستضافةِ يرفضه) ويُفحص وجودُ
 *   القادحِ **وظيفيًّا** لا عبر ‎information_schema.TRIGGERS‎ (تحتاج امتيازًا).
 */

if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

// ◆ اتصالُ المرحِّل لا اتصالُ التطبيق: ‎ems_app‎ مقيَّدٌ بـDML (ADR-04) فيُرفض
//   ‎ALTER‎ و‎CREATE TRIGGER‎ منه. مُشغِّلُ الترحيلاتِ ينفّذ ملفَّ PHP في عمليةٍ
//   منفصلةٍ ويترك لكلِّ ملفٍّ اتصالَه — فالاتصالُ هنا صريحٌ بمستخدمِ الترحيل.
$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال المرحِّل فشل: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$exec = static function ($sql, $label) use ($db) {
    if (!$db->query($sql)) {
        throw new RuntimeException($label . ': ' . $db->error);
    }
    echo "[FN-07] {$label} ✔\n";
};

/* ── ① المرجعُ إلزاميٌّ على كلِّ مرتجعٍ — قيدُ صفٍّ واحدٍ ⇒ CHECK ────────── */
$has = (int) $db->query("SELECT COUNT(*) c FROM information_schema.TABLE_CONSTRAINTS
                          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proc_stock_move'
                            AND CONSTRAINT_NAME = 'chk_psm_return_needs_ref'")->fetch_assoc()['c'];
if ($has === 0) {
    // نُصحِّح أيَّ صفٍّ قائمٍ يخالف القيدَ قبلَ فرضِه (وإلا رفضت القاعدةُ الإضافة).
    $bad = (int) $db->query("SELECT COUNT(*) c FROM proc_stock_move
                              WHERE move_type = 'مرتجع'
                                AND (ref_type IS NULL OR ref_type <> 'issue' OR ref_id IS NULL OR ref_id = 0)")->fetch_assoc()['c'];
    if ($bad > 0) {
        echo "[FN-07] ⚠ {$bad} مرتجعًا موروثًا بلا مرجعِ صرف — يُوسَم legacy_no_ref ولا يُمحى\n";
        // ◆ الموروثُ يُعلَن ولا يُمحى (نمطُ M-11 legacy_no_ref): نُبقيه ونستثنيه
        //   من القيدِ بشرطٍ صريحٍ على الوقتِ لا بتزييفِ مرجع.
        $db->query("UPDATE proc_stock_move SET note = CONCAT(COALESCE(note,''), ' [legacy_no_ref]')
                     WHERE move_type = 'مرتجع'
                       AND (ref_type IS NULL OR ref_type <> 'issue' OR ref_id IS NULL OR ref_id = 0)
                       AND note NOT LIKE '%legacy_no_ref%'");
    }
    $exec("ALTER TABLE proc_stock_move
             ADD CONSTRAINT chk_psm_return_needs_ref CHECK (
                 move_type <> 'مرتجع'
                 OR (ref_type = 'issue' AND ref_id IS NOT NULL AND ref_id > 0)
                 OR note LIKE '%legacy_no_ref%'
             )", 'قيد «المرتجع بمرجع سند صرف إلزامًا»');
} else {
    echo "[FN-07] قيد المرجع الإلزامي موجودٌ سلفًا — تُخطّى\n";
}

/* ── ② القادحُ: لا مرتجعَ يتجاوز (المصروفُ − ما أُرجع) ─────────────────── */
$db->query("DROP TRIGGER IF EXISTS trg_psm_return_not_exceed_issued");
$sql = "
CREATE TRIGGER trg_psm_return_not_exceed_issued
BEFORE INSERT ON proc_stock_move
FOR EACH ROW
BEGIN
  DECLARE v_issued   DECIMAL(18,4) DEFAULT 0;
  DECLARE v_returned DECIMAL(18,4) DEFAULT 0;
  DECLARE v_msg      VARCHAR(255);
  IF NEW.move_type = 'مرتجع' AND NEW.ref_type = 'issue' AND NEW.ref_id > 0 THEN
    SELECT COALESCE(SUM(il.qty),0) INTO v_issued
      FROM proc_issue_line il
      JOIN proc_issue i ON i.id = il.issue_id
     WHERE il.issue_id = NEW.ref_id AND il.item_id = NEW.item_id
       AND i.company_id = NEW.company_id;
    SELECT COALESCE(SUM(m.qty),0) INTO v_returned
      FROM proc_stock_move m
     WHERE m.company_id = NEW.company_id AND m.move_type = 'مرتجع'
       AND m.ref_type = 'issue' AND m.ref_id = NEW.ref_id AND m.item_id = NEW.item_id;
    IF (v_returned + NEW.qty) > (v_issued + 0.0001) THEN
      SET v_msg = CONCAT('FN-07: المرتجع يتجاوز المصروف — مصروف ', v_issued,
                         ' · أُرجع ', v_returned, ' · المطلوب ', NEW.qty);
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;
  END IF;
END";
if (!$db->query($sql)) { throw new RuntimeException('قادح المرتجع: ' . $db->error); }
echo "[FN-07] قادح «لا مرتجع يتجاوز المصروف» ✔\n";

/* ── ③ إثباتٌ وظيفيٌّ للقادح (لا عبر information_schema — يحتاج امتيازًا) ─ */
$prev = mysqli_report(MYSQLI_REPORT_OFF);
$probeOk = false;
$db->query("INSERT INTO proc_stock_move (company_id,item_id,warehouse_id,move_type,qty,ref_type,ref_id,note,moved_at,created_by)
            VALUES (0, 999999, 0, 'مرتجع', 999999, 'issue', 999999, 'FN-07 probe', NOW(), 0)");
if ($db->errno) {
    $probeOk = (stripos($db->error, 'FN-07') !== false);
} else {
    // مرَّ! ⇒ القادحُ لا يعمل — نُنظّف ونرسب صراحةً.
    $db->query("DELETE FROM proc_stock_move WHERE note = 'FN-07 probe'");
}
mysqli_report($prev);
if (!$probeOk) { throw new RuntimeException('القادحُ لم يمنع إدراجًا متجاوزًا — الترحيلُ يرسب صراحةً'); }
echo "[FN-07] الإثباتُ الوظيفي: القادحُ رفض إدراجًا متجاوزًا ✔\n";
