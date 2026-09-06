<?php
/**
 * Governance/bus_monitor.php — مراقبةُ ناقلِ الأحداث (صحّةٌ وتأخّر).
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **بديلُ `admin/bus_monitor.php`**: كانت الشاشةَ الوحيدةَ التي تقرأ ثلاثةَ
 *   جداولَ لا يقرؤها سواها — `ems_event_dead_letter` و`ems_event_consumers`
 *   و`ems_processed_events`. وشاشتا `bus_outbox` و`bus_deliveries` تقرآن
 *   `ems_business_events` و`ems_event_deliveries` وحدَهما، فطابورُ الرسائلِ
 *   الميّتةِ ومؤشّراتُ المستهلكين كانا **بلا عينٍ** خارجَ بوّابةِ المزوّدِ المغلقة.
 *   نُقلت هنا قبلَ حذفِ `admin/`.
 *
 * ⛔ **قرائيّةٌ بحتة**: `SELECT` فقط. لا تلمس الناشرَ ولا الموزِّع — الخطُّ الأحمرُ
 *   مصون (A-2 · C7)، ولا زرَّ إعادةِ محاولةٍ ولا حذفَ رسالةٍ ميتة.
 *
 * ◆ **وبابان لا باب**: جداولُ `ems_event_*` بنيةُ الناقلِ تُقرأ خامًا (وهذه
 *   الشاشةُ عينُ المراقب)، أمّا دفترُ الأحداثِ `fin_financial_events` فعبرَ
 *   البوّابةِ العابرةِ `ems_tenant_db()` — لا `ems_platform_db()` التي تموت
 *   بموتِ بوّابةِ المزوّد.
 */

// ═══ ① جلسة ═══
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }

// ═══ ② إعداد ═══
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$SCREEN         = 'Governance/bus_monitor.php';
if (!$is_super_admin && $company_id <= 0) { header('Location: ../main/dashboard.php'); exit(); }

// ═══ ③ حارسُ الشاشة — قبلَ أيِّ قراءة ═══
$__pp = check_page_permissions($conn, $SCREEN);
if (!$is_super_admin && empty($__pp['can_view'])) {
    header('Location: ../main/dashboard.php?denied=' . rawurlencode($SCREEN));
    exit();
}
$page_title = 'مراقبة ناقل الأحداث';

// ═══ ④ القياس ═══
/** عدّادٌ خامٌّ على جداولِ بنيةِ الناقل (مستثناةٌ موثَّقةً — هذه عينُ المراقب). */
function bm_one($conn, $sql)
{
    $r = @mysqli_query($conn, $sql);
    if ($r && ($row = mysqli_fetch_row($r))) { return (int) $row[0]; }
    return 0;
}
/** عدّادٌ على دفترِ الأحداثِ عبرَ البوّابةِ العابرة — يفشل مغلقًا لا صامتًا. */
function bm_ledger($gate, $sql)
{
    try {
        $r = $gate->scopedQuery(array('scope' => array('fin_financial_events' => 'fin_financial_events')), $sql);
        $row = isset($r[0]) ? array_values($r[0]) : null;
        return $row ? (int) $row[0] : 0;
    } catch (\Throwable $t) {
        error_log('Governance/bus_monitor: ' . $t->getMessage());
        return -1; // ‏−1 = تعذّرت القراءة، تُعرض «—» لا صفرًا كاذبًا
    }
}

$gate = $is_super_admin
      ? ems_tenant_db()->forAllTenants('bus monitor super')
      : ems_tenant_db();

$max_event_id = bm_ledger($gate, "SELECT COALESCE(MAX(id),0) FROM fin_financial_events WHERE 1=1 AND {TENANT_SCOPE}");
$total_events = bm_ledger($gate, "SELECT COUNT(*) FROM fin_financial_events WHERE 1=1 AND {TENANT_SCOPE}");
$published    = bm_ledger($gate, "SELECT COUNT(*) FROM fin_financial_events WHERE idempotency_key IS NOT NULL AND {TENANT_SCOPE}");

$deliveries = bm_one($conn, "SELECT COUNT(*) FROM ems_event_deliveries");
$dlq        = bm_one($conn, "SELECT COUNT(*) FROM ems_event_dead_letter");
$processed  = bm_one($conn, "SELECT COUNT(*) FROM ems_processed_events");

// ── المستهلكون وتأخّرُ كلٍّ (backlog = أحداثٌ بعدَ مؤشّرِه) ──────────────────
$consumers = array();
$max_lag   = 0;
$cr = @mysqli_query($conn, "SELECT consumer, enabled, cursor_event_id, updated_at FROM ems_event_consumers ORDER BY consumer");
if ($cr) {
    while ($row = mysqli_fetch_assoc($cr)) {
        $cursor = (int) $row['cursor_event_id'];
        $lag = bm_ledger($gate, "SELECT COUNT(*) FROM fin_financial_events WHERE id > " . $cursor . " AND {TENANT_SCOPE}");
        $row['lag'] = $lag;
        if ($lag > $max_lag) { $max_lag = $lag; }
        $consumers[] = $row;
    }
}

// ── آخرُ الرسائلِ الميتة ───────────────────────────────────────────────────
$dlq_rows = array();
if ($dlq > 0) {
    $dr = @mysqli_query($conn, "SELECT consumer, event_id, attempts, last_error, failed_at
                                  FROM ems_event_dead_letter ORDER BY failed_at DESC LIMIT 20");
    if ($dr) { while ($row = mysqli_fetch_assoc($dr)) { $dlq_rows[] = $row; } }
}

$health = ($dlq === 0 && $max_lag === 0) ? 'ok' : ($dlq > 0 ? 'err' : 'warn');
$h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$n = function ($v) { return $v < 0 ? '—' : number_format($v); };

include("../inheader.php");
include('../insidebar.php');
?>
<style>
.bm-wrap{display:flex;flex-direction:column;gap:20px}
.bm-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px}
.bm-card{background:var(--ems-white);border:1px solid var(--c-e5e7eb);border-radius:8px;padding:16px 20px}
.bm-card .v{font-size:28px;font-weight:700;line-height:1.2}
.bm-card .l{font-size:12.5px;color:var(--c-ink-500);margin-top:4px}
.bm-card.ok{border-right:4px solid var(--c-state-ok)}
.bm-card.warn{border-right:4px solid var(--c-badge-warning-a)}
.bm-card.err{border-right:4px solid var(--c-badge-danger-a)}
.bm-panel{background:var(--ems-white);border:1px solid var(--c-e5e7eb);border-radius:8px;padding:20px}
.bm-panel h5{margin:0 0 12px;font-weight:700;font-size:15px}
.bm-note{font-size:12.5px;color:var(--c-ink-500);line-height:1.7;margin:0}
.bm-pill{display:inline-block;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:600}
.bm-pill.on{background:var(--c-dcfce7);color:var(--c-166534)}
.bm-pill.off{background:var(--c-f3f4f6);color:var(--c-4b5563)}
.bm-pill.lag{background:var(--c-fef3c7);color:var(--c-92400e)}
</style>

<div class="container-fluid p-3 bm-wrap">

  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h4 class="fw-bold mb-0"><i class="fa fa-satellite-dish me-2"></i>مراقبة ناقل الأحداث</h4>
    <span class="bm-pill <?= $health === 'ok' ? 'on' : ($health === 'err' ? 'off' : 'lag') ?>">
      <?= $health === 'ok' ? '● سليم' : ($health === 'err' ? '● رسائل ميتة' : '● تأخر قائم') ?>
    </span>
  </div>

  <div class="bm-grid">
    <div class="bm-card"><div class="v"><?= $n($total_events) ?></div>
      <div class="l">أحداث الدفتر (أقصى معرّف <?= $n($max_event_id) ?>)</div></div>
    <div class="bm-card"><div class="v"><?= $n($published) ?></div><div class="l">منشورة على الناقل</div></div>
    <div class="bm-card <?= $max_lag === 0 ? 'ok' : 'warn' ?>"><div class="v"><?= $n($max_lag) ?></div>
      <div class="l">أقصى تأخر (backlog)</div></div>
    <div class="bm-card <?= $dlq === 0 ? 'ok' : 'err' ?>"><div class="v"><?= number_format($dlq) ?></div>
      <div class="l">طابور الرسائل الميتة</div></div>
    <div class="bm-card"><div class="v"><?= number_format($deliveries) ?></div><div class="l">تسليمات جارية</div></div>
    <div class="bm-card"><div class="v"><?= number_format($processed) ?></div><div class="l">وقائع مُستهلَكة (exactly-once)</div></div>
  </div>

  <div class="bm-panel">
    <h5>المستهلكون ومؤشّراتهم</h5>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0" data-no-datatable>
        <thead><tr><th>المستهلك</th><th>الحالة</th><th>المؤشّر</th><th>التأخّر</th><th>آخر تحديث</th></tr></thead>
        <tbody>
        <?php if (!$consumers): ?>
          <tr><td colspan="5" class="text-center text-muted py-4">لا مستهلك مسجّل</td></tr>
        <?php else: foreach ($consumers as $c): ?>
          <tr>
            <td><code><?= $h($c['consumer']) ?></code></td>
            <td><span class="bm-pill <?= !empty($c['enabled']) ? 'on' : 'off' ?>"><?= !empty($c['enabled']) ? 'مفعّل' : 'موقوف' ?></span></td>
            <td class="text-nowrap"><?= number_format((int) $c['cursor_event_id']) ?></td>
            <td class="text-nowrap"><?= (int) $c['lag'] === 0 ? '<span class="bm-pill on">صفر</span>' : '<span class="bm-pill lag">' . $n((int) $c['lag']) . '</span>' ?></td>
            <td class="text-nowrap"><?= $h($c['updated_at']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <p class="bm-note mt-2">التأخّرُ = عددُ أحداثِ الدفترِ بعدَ مؤشّرِ المستهلك. وموقوفٌ بتأخّرٍ صفرٍ ليس سليمًا بالضرورة — راجعْ سببَ إيقافه.</p>
  </div>

  <div class="bm-panel">
    <h5>طابور الرسائل الميتة <?= $dlq > 0 ? '(آخر ' . count($dlq_rows) . ' من ' . number_format($dlq) . ')' : '' ?></h5>
    <?php if (!$dlq_rows): ?>
      <p class="bm-note mb-0">لا رسائل ميتة — كلُّ ما نُشر استُهلك أو ما زال في التسليم.</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0" data-no-datatable>
        <thead><tr><th>المستهلك</th><th>الحدث</th><th>المحاولات</th><th>آخر خطأ</th><th>وقت الفشل</th></tr></thead>
        <tbody>
        <?php foreach ($dlq_rows as $d): ?>
          <tr>
            <td><code><?= $h($d['consumer']) ?></code></td>
            <td class="text-nowrap"><?= number_format((int) $d['event_id']) ?></td>
            <td class="text-nowrap"><?= (int) $d['attempts'] ?></td>
            <td class="text-break small"><?= $h(mb_substr((string) $d['last_error'], 0, 160)) ?></td>
            <td class="text-nowrap"><?= $h($d['failed_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="bm-note mt-2">هذه الشاشةُ قرائيّةٌ: إعادةُ المحاولةِ والحذفُ يقعان في محرّكِ الناقلِ لا هنا.</p>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
