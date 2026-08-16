<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
enforce_current_page_view_permission($conn, '../main/dashboard.php');
$PERMS = check_page_permissions($conn, 'Contracts/unit_client_match.php');

/* المحطتان ٨ و٩ (unit.st.08 مطابقةُ العميل · unit.st.09 قبولُه النهائي) —
   الأعمدةُ client_match_* حيّةٌ في unit_entries منذ D02 وبلا شاشةٍ حتى اليوم.
   الكتابةُ عبرَ الأعمدةِ المخصصةِ لها وبفعلٍ بشريٍّ مسجَّل — لا حذفَ ولا تعديلَ كمية. */
$company_id = (int) ($_SESSION['user']['company_id'] ?? 0);
$actor_id   = (int) ($_SESSION['user']['id'] ?? 0);
$flash = null; $flash_ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($PERMS['can_edit']) && empty($PERMS['can_add'])) {
        $flash = 'لا تملك صلاحيةَ المطابقة — راجِعْ مديرَك.';
    } else {
        require_csrf();
        $eid = (int) ($_POST['entry_id'] ?? 0);
        $dec = (string) ($_POST['decision'] ?? '');
        $ref = trim((string) ($_POST['match_ref'] ?? ''));
        if ($eid <= 0 || !in_array($dec, array('matched', 'disputed'), true)) {
            $flash = 'اختر القيدَ والقرار.';
        } elseif ($ref === '') {
            $flash = 'مرجعُ المطابقةِ إلزاميّ — رقمُ كشفِ العميلِ أو محضرُه.';
        } else {
            $st = $conn->prepare("UPDATE unit_entries
                SET client_match_state = ?, client_match_at = NOW(), client_match_by = ?,
                    client_match_ref = ?, client_decision = ?
                WHERE id = ? AND company_id = ? AND seed_tag IS NULL
                  AND state IN ('sales_approved','converted')");
            $decision = ($dec === 'matched') ? 'accepted' : 'disputed';
            $st->bind_param('sissii', $dec, $actor_id, $ref, $decision, $eid, $company_id);
            $st->execute();
            $flash_ok = $st->affected_rows > 0;
            $flash = $flash_ok
                ? ($dec === 'matched' ? 'طُوبق القيدُ وقُبل — وسيظهر في كشفِ العميل.' : 'سُجِّل النزاعُ بمرجعِه — يُحسم مع العميل.')
                : 'لم يُحدَّث — القيدُ ليس في مرحلةِ المطابقةِ (يلزم اعتمادُ المبيعاتِ أولًا).';
            $st->close();
        }
    }
}

$rows = array();
$st = $conn->prepare("SELECT ue.id, ue.entry_no, ue.entry_date, ue.shift, ue.equipment_id, ue.qty, ue.unit_type,
                             ue.state, COALESCE(ue.client_match_state,'') mstate, ue.client_match_ref
                      FROM unit_entries ue
                      WHERE ue.company_id = ? AND ue.seed_tag IS NULL
                        AND ue.state IN ('sales_approved','converted')
                      ORDER BY (ue.client_match_state IS NULL OR ue.client_match_state='') DESC, ue.entry_date DESC
                      LIMIT 100");
$st->bind_param('i', $company_id);
$st->execute();
$rs = $st->get_result();
while ($x = $rs->fetch_assoc()) { $rows[] = $x; }
$st->close();

$page_title = 'إيكوبيشن | مطابقةُ العميلِ على الوحدات';
include __DIR__ . '/../inheader.php';
include __DIR__ . '/../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
$canWrite = !empty($PERMS['can_edit']) || !empty($PERMS['can_add']);
?>
<div class="content-wrapper" dir="rtl">
  <section class="content-header"><h1>مطابقةُ العميلِ على الوحدات</h1>
    <p style="color:#666;margin:4px 0 0">القيودُ التي اعتمدتها المبيعاتُ تُعرض على العميل: نطابقُ ما قَبِله ونسجّل ما نازعَ فيه — بمرجعٍ دائمًا.</p>
  </section>
  <section class="content">
    <?php if ($flash !== null): ?>
      <div class="alert <?= $flash_ok ? 'alert-success' : 'alert-danger' ?>"><?= ($flash_ok ? '✔ ' : '✖ ') . htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <div class="box"><div class="box-body table-responsive">
      <?php if (!$rows): ?>
        <p style="color:#777">لا قيودَ في مرحلةِ المطابقة — القيدُ يصل هنا بعد اعتمادِ المبيعات.</p>
      <?php else: ?>
      <table class="table table-bordered table-striped">
        <thead><tr><th>القيد</th><th>التاريخ</th><th>الوردية</th><th>الكمية</th><th>حالةُ المطابقة</th><th>المرجع</th><?php if ($canWrite): ?><th>القرار</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars((string) $r['entry_no'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $r['entry_date'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $r['shift'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= number_format((float) $r['qty'], 2) . ' ' . ($r['unit_type'] === 'hour' ? 'ساعة' : htmlspecialchars((string) $r['unit_type'], ENT_QUOTES, 'UTF-8')) ?></td>
            <td>
              <?php if ($r['mstate'] === 'matched'): ?><span class="label label-success">✔ مُطابَق</span>
              <?php elseif ($r['mstate'] === 'disputed'): ?><span class="label label-danger">✖ متنازَع</span>
              <?php else: ?><span class="label label-warning">⏳ ينتظر المطابقة</span><?php endif; ?>
            </td>
            <td><?= htmlspecialchars((string) ($r['client_match_ref'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td>
            <?php if ($canWrite): ?>
            <td>
              <?php if ($r['mstate'] === ''): ?>
              <form method="post" style="display:flex;gap:6px;align-items:center">
                <?= csrf_field() ?>
                <input type="hidden" name="entry_id" value="<?= (int) $r['id'] ?>">
                <input type="text" name="match_ref" placeholder="مرجعُ الكشف *" required class="form-control input-sm" style="width:130px">
                <button name="decision" value="matched" class="btn btn-success btn-sm">اقبل</button>
                <button name="decision" value="disputed" class="btn btn-danger btn-sm">سجّل نزاعًا</button>
              </form>
              <?php else: ?>—<?php endif; ?>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div></div>
  </section>
</div>
<?php include __DIR__ . '/../infooter.php'; ?>
