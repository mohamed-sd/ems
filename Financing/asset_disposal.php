<?php
/**
 * Financing/asset_disposal.php — التصرفُ في الأصل (★ · update0007-ب F5)
 * ───────────────────────────────────────────────────────────────────────────
 * نقلُ حصةِ ملكيةٍ أو بيعُها: **الحصةُ القديمةُ تُغلق بتاريخٍ والجديدةُ تُفتح
 * بصفٍّ — ولا تعديلَ بأثرٍ رجعي** (SEC-01 حارس ⑮ تغييرُ ملكية أصلٍ بكسر
 * زجاجٍ فقط — وهنا بمرجع قرارٍ موثَّقٍ داخل المجال المقيَّد).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$role = intval($_SESSION['user']['role'] ?? 0);
$granted = ($role === 26) || !empty($_SESSION['user']['is_super_admin']);
if (!$granted) {
    $g = mysqli_query($conn, "SELECT 1 FROM ownership_access_grants WHERE person_id = $uid AND state = 'active' LIMIT 1");
    $granted = $g && mysqli_num_rows($g) > 0;
}
if (!$granted) { http_response_code(403); die('المجالُ المقيَّد (FIN-01 §1.1)'); }
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $share = intval($_POST['share_id'] ?? 0);
    $toEnt = intval($_POST['to_entity'] ?? 0);
    $pct   = floatval($_POST['percent'] ?? 0);
    $doc   = trim($_POST['doc_ref'] ?? '');
    if ($share <= 0 || $toEnt <= 0 || $pct <= 0 || $pct > 100 || $doc === '') {
        $msg = 'الحصةُ والمستلمُ والنسبةُ (0-100] ومرجعُ القرار إلزامية (422)';
    } else {
        $r = mysqli_query($conn, "SELECT * FROM asset_ownership_shares WHERE share_id = $share
                                  AND company_id = $company_id AND (valid_to IS NULL OR valid_to >= CURDATE())");
        if ($r && ($s = mysqli_fetch_assoc($r))) {
            $cur = floatval($s['approved_percent'] ?: $s['percent']);
            if ($pct > $cur) { $msg = "النسبةُ المنقولة ($pct) تتجاوز الحصةَ القائمة ($cur) — 409"; }
            else {
                mysqli_begin_transaction($conn);
                // ① القديمةُ تُغلق اليوم — الأصلُ باقٍ سجلًّا
                $ok1 = mysqli_query($conn, "UPDATE asset_ownership_shares SET valid_to = CURDATE() WHERE share_id = $share");
                // ② بقيةُ المالك القديم إن بقيت
                $rest = $cur - $pct; $ok2 = true;
                if ($rest > 0.001) {
                    $ok2 = mysqli_query($conn, "INSERT INTO asset_ownership_shares
                        (company_id, asset_id, asset_kind, financier_entity_id, op_id, model_code, percent,
                         valid_from, doc_ref, recorded_percent, approved_percent, created_by)
                        SELECT company_id, asset_id, asset_kind, financier_entity_id, op_id, model_code, $rest,
                               CURDATE(), '" . mysqli_real_escape_string($conn, $doc) . "', $rest, $rest, $uid
                        FROM asset_ownership_shares WHERE share_id = $share");
                }
                // ③ حصةُ المستلم الجديدة
                $ok3 = mysqli_query($conn, "INSERT INTO asset_ownership_shares
                        (company_id, asset_id, asset_kind, financier_entity_id, op_id, model_code, percent,
                         valid_from, doc_ref, recorded_percent, approved_percent, created_by)
                        SELECT company_id, asset_id, asset_kind, $toEnt, op_id, model_code, $pct,
                               CURDATE(), '" . mysqli_real_escape_string($conn, $doc) . "', $pct, $pct, $uid
                        FROM asset_ownership_shares WHERE share_id = $share");
                if ($ok1 && $ok2 && $ok3) {
                    mysqli_commit($conn);
                    mysqli_query($conn, "INSERT INTO action_execution_log (company_id, action_code, person_id, subject_ref, result)
                                         VALUES ($company_id, 'asset.share.transfer', $uid, 'share:$share→ent:$toEnt:$pct%', 'allowed')");
                    $msg = "نُقلت $pct٪ بمرجع $doc — القديمةُ أُغلقت بتاريخها والجديدةُ صفٌّ (Σ محفوظ)";
                } else { mysqli_rollback($conn); $msg = 'فشلت المعاملةُ فأُلغيت الثلاث: ' . mysqli_error($conn); }
            }
        } else { $msg = 'حصةٌ غيرُ ساريةٍ (404)'; }
    }
}

$shares = array(); $ents = array();
$r = mysqli_query($conn, "SELECT s.share_id, s.asset_id, e.name eq_name, le.name_ar financier,
                                 COALESCE(s.approved_percent, s.percent) pct
                          FROM asset_ownership_shares s
                          LEFT JOIN equipments e ON e.id = s.asset_id AND s.asset_kind = 'equipment'
                          LEFT JOIN legal_entities le ON le.entity_id = s.financier_entity_id
                          WHERE s.company_id = $company_id AND (s.valid_to IS NULL OR s.valid_to >= CURDATE())
                          ORDER BY s.asset_id");
if ($r) while ($x = mysqli_fetch_assoc($r)) $shares[] = $x;
$r = mysqli_query($conn, "SELECT entity_id, name_ar FROM legal_entities ORDER BY name_ar LIMIT 60");
if ($r) while ($x = mysqli_fetch_assoc($r)) $ents[] = $x;

$page_title = 'التصرف في الأصل';
include '../insidebar.php';
?>
<div class="content-wrapper allforms" dir="rtl">
  <div class="ems-topbar"><h4><i class="fa fa-exchange-alt"></i> التصرفُ في الأصل — نقلُ حصة</h4></div>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <form method="post" class="ems-form" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;max-width:900px">
    <div><label>الحصةُ السارية</label>
      <select name="share_id" class="form-control" required><option value="">—</option>
        <?php foreach ($shares as $s): ?>
          <option value="<?= intval($s['share_id']) ?>">
            <?= htmlspecialchars(($s['eq_name'] ?: '#' . $s['asset_id']) . ' — ' . ($s['financier'] ?: '؟') . ' (' . floatval($s['pct']) . '٪)', ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?></select></div>
    <div><label>إلى الكيان</label>
      <select name="to_entity" class="form-control" required><option value="">—</option>
        <?php foreach ($ents as $e2): ?><option value="<?= intval($e2['entity_id']) ?>"><?= htmlspecialchars($e2['name_ar'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
    <div><label>النسبةُ المنقولة ٪</label><input type="number" step="0.01" min="0.01" max="100" name="percent" class="form-control" required style="max-width:110px"></div>
    <div><label>مرجعُ القرار — إلزامي</label><input type="text" name="doc_ref" class="form-control" required></div>
    <button class="btn btn-primary">انقل الحصة</button>
  </form>
  <p class="text-muted" style="font-size:.85em;margin-top:10px">القديمةُ تُغلق بتاريخٍ والجديدةُ صفٌّ — لا تعديلَ بأثرٍ رجعيٍّ وΣ لكل أصلٍ محفوظٌ في كل لحظة.</p>
</div>
