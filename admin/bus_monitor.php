<?php
/**
 * لوحة صحّة ناقل الأحداث ومقياس التأخّر — A-2 (المسار أ · C7)
 * ───────────────────────────────────────────────────────────────────────────
 * قرائية بحتة (SELECT فقط على جداول الناقل المنصّية) — لا تلمس الناشر/الموزّع
 * (الخط الأحمر مصون). تعرض: تأخّر الناقل لكل مستهلك (max(id)−cursor)، حالة
 * المستهلكين، التسليمات الجارية، طابور الرسائل الميتة (DLQ)، عطالة الاستهلاك
 * الموزّع (ems_processed_events)، وتغطية المُصالِح. للمدير الأعلى فقط.
 *
 * مصادر المقياس (كلها جداول قائمة): fin_financial_events · ems_event_consumers ·
 * ems_event_deliveries · ems_event_dead_letter · ems_processed_events.
 */
require_once __DIR__ . '/includes/auth.php';
super_admin_require_login();

$admin        = super_admin_current();
$page_title   = 'مراقبة ناقل الأحداث';
$current_page = 'bus-monitor';

// [مُستثنى موثَّق — مراقبة بنية الناقل] جداول ems_event_* مقيَّدة تعاقديًا حتى عن بوابة
// المزوّد (الوصول عبر محرّكاتها حصرًا)، وهذه الشاشةُ عينُ المراقب — قراءاتها الخام
// عليها تبقى كما هي؛ أما عدّادات دفتر الأحداث (fin_financial_events) فعبر البوابة العابرة.
function _bus_one($conn, $sql)
{
    $r = @mysqli_query($conn, $sql);
    if ($r && ($row = mysqli_fetch_row($r))) { return $row[0]; }
    return 0;
}
function _bus_gate_one($g, $sql)
{
    try {
        $r = $g->scopedQuery(array('scope' => array('fin_financial_events' => 'fin_financial_events')), $sql);
        $row = isset($r[0]) ? array_values($r[0]) : null;
        return $row ? $row[0] : 0;
    } catch (\Throwable $t) {
        error_log('admin/bus_monitor: ' . $t->getMessage());
        return 0;
    }
}

// ── مؤشرات الجذر ─────────────────────────────────────────────────────────────
$bm_pg = ems_platform_db();
$max_event_id   = (int) _bus_gate_one($bm_pg, "SELECT COALESCE(MAX(id),0) FROM fin_financial_events WHERE 1=1 AND {TENANT_SCOPE}");
$total_events   = (int) _bus_gate_one($bm_pg, "SELECT COUNT(*) FROM fin_financial_events WHERE 1=1 AND {TENANT_SCOPE}");
$published      = (int) _bus_gate_one($bm_pg, "SELECT COUNT(*) FROM fin_financial_events WHERE idempotency_key IS NOT NULL AND {TENANT_SCOPE}");
$reversed       = (int) _bus_gate_one($bm_pg, "SELECT COUNT(*) FROM fin_financial_events WHERE event_status='reversed' AND {TENANT_SCOPE}");
$deliveries     = (int) _bus_one($conn, "SELECT COUNT(*) FROM ems_event_deliveries");
$dlq            = (int) _bus_one($conn, "SELECT COUNT(*) FROM ems_event_dead_letter");
$processed      = (int) _bus_one($conn, "SELECT COUNT(*) FROM ems_processed_events");

// ── المستهلكون + تأخّر كلٍّ (backlog = أحداثٌ بعد مؤشّره) ─────────────────────
$consumers = array();
$max_lag = 0;
$cr = @mysqli_query($conn, "SELECT consumer, enabled, cursor_event_id, updated_at FROM ems_event_consumers ORDER BY consumer");
if ($cr) {
    while ($row = mysqli_fetch_assoc($cr)) {
        $cursor = (int) $row['cursor_event_id'];
        $lag = (int) _bus_gate_one($bm_pg, "SELECT COUNT(*) FROM fin_financial_events WHERE id > " . $cursor . " AND {TENANT_SCOPE}");
        $row['lag'] = $lag;
        $max_lag = max($max_lag, $lag);
        $consumers[] = $row;
    }
}

// ── آخر رسائل DLQ (إن وُجدت) ─────────────────────────────────────────────────
$dlq_rows = array();
if ($dlq > 0) {
    $dr = @mysqli_query($conn, "SELECT consumer, event_id, attempts, last_error, failed_at FROM ems_event_dead_letter ORDER BY failed_at DESC LIMIT 10");
    if ($dr) { while ($row = mysqli_fetch_assoc($dr)) { $dlq_rows[] = $row; } }
}

// ── تغطية المُصالِح (مقروءة من المستهلكين المسجَّلين) ─────────────────────────
$health = ($dlq === 0 && $max_lag === 0) ? 'ok' : ($dlq > 0 ? 'err' : 'warn');

require_once __DIR__ . '/includes/layout_head.php';
?>

<div class="phead">
    <div>
        <h2>مراقبة ناقل الأحداث</h2>
        <p class="sub">صحة الناقل ومقياس التأخر (exactly-once) — قرائي، لا يمس الناشر/الموزع (A-2 · C7)</p>
    </div>
    <div class="phead-right">
        <span class="badge <?php echo $health === 'ok' ? 'badge-green' : ($health === 'err' ? 'badge-red' : 'badge-amber'); ?>">
            <?php echo $health === 'ok' ? '● سليم' : ($health === 'err' ? '● رسائل ميتة' : '● تأخر قائم'); ?>
        </span>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card hex-stat-card hex-stat-blue"><div class="stat-row">
        <div><div class="stat-val"><?php echo number_format($total_events); ?></div><div class="stat-lbl">أحداث الدفتر (max id=<?php echo $max_event_id; ?>)</div></div>
        <div class="stat-ico"><i class="fas fa-book"></i></div>
    </div></div>
    <div class="stat-card hex-stat-card hex-stat-green"><div class="stat-row">
        <div><div class="stat-val"><?php echo number_format($published); ?></div><div class="stat-lbl">منشورة على الناقل</div></div>
        <div class="stat-ico"><i class="fas fa-satellite-dish"></i></div>
    </div></div>
    <div class="stat-card hex-stat-card <?php echo $max_lag === 0 ? 'hex-stat-green' : 'hex-stat-orange'; ?>"><div class="stat-row">
        <div><div class="stat-val"><?php echo number_format($max_lag); ?></div><div class="stat-lbl">أقصى تأخر (backlog)</div></div>
        <div class="stat-ico"><i class="fas fa-hourglass-half"></i></div>
    </div></div>
    <div class="stat-card hex-stat-card <?php echo $dlq === 0 ? 'hex-stat-green' : 'hex-stat-red'; ?>"><div class="stat-row">
        <div><div class="stat-val"><?php echo number_format($dlq); ?></div><div class="stat-lbl">طابور الرسائل الميتة</div></div>
        <div class="stat-ico"><i class="fas fa-skull-crossbones"></i></div>
    </div></div>
    <div class="stat-card hex-stat-card hex-stat-blue"><div class="stat-row">
        <div><div class="stat-val"><?php echo number_format($deliveries); ?></div><div class="stat-lbl">تسليمات جارية (إعادة محاولة)</div></div>
        <div class="stat-ico"><i class="fas fa-paper-plane"></i></div>
    </div></div>
    <div class="stat-card hex-stat-card hex-stat-blue"><div class="stat-row">
        <div><div class="stat-val"><?php echo number_format($processed); ?></div><div class="stat-lbl">عطالة موزعة (processed)</div></div>
        <div class="stat-ico"><i class="fas fa-fingerprint"></i></div>
    </div></div>
</div>

<div class="card" style="margin-top:16px">
    <div class="card-h"><i class="fas fa-users-gear"></i> المستهلكون وتأخر كل</div>
    <div class="card-b" style="overflow-x:auto">
        <table class="tbl" style="width:100%">
            <thead><tr><th>المستهلك</th><th>مفعل</th><th>المؤشر (cursor)</th><th>التأخر (أحداث بعده)</th><th>آخر تحديث</th></tr></thead>
            <tbody>
            <?php if (empty($consumers)): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--muted)">لا مستهلكون مسجلون بعد</td></tr>
            <?php else: foreach ($consumers as $c): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($c['consumer']); ?></strong></td>
                    <td><?php echo $c['enabled'] ? '<span class="badge badge-green">نعم</span>' : '<span class="badge badge-red">لا</span>'; ?></td>
                    <td><?php echo (int) $c['cursor_event_id']; ?></td>
                    <td><?php echo $c['lag'] === 0 ? '<span class="badge badge-green">0 — محدث</span>' : '<span class="badge badge-amber">' . (int) $c['lag'] . '</span>'; ?></td>
                    <td><?php echo htmlspecialchars((string) ($c['updated_at'] ?? '—')); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($dlq_rows)): ?>
<div class="card" style="margin-top:16px">
    <div class="card-h" style="color:var(--red,#c0392b)"><i class="fas fa-triangle-exclamation"></i> آخر الرسائل الميتة (DLQ)</div>
    <div class="card-b" style="overflow-x:auto">
        <table class="tbl" style="width:100%">
            <thead><tr><th>المستهلك</th><th>الحدث</th><th>المحاولات</th><th>آخر خطأ</th><th>وقت الفشل</th></tr></thead>
            <tbody>
            <?php foreach ($dlq_rows as $d): ?>
                <tr>
                    <td><?php echo htmlspecialchars($d['consumer']); ?></td>
                    <td><?php echo (int) $d['event_id']; ?></td>
                    <td><?php echo (int) $d['attempts']; ?></td>
                    <td style="max-width:340px;overflow:hidden;text-overflow:ellipsis"><?php echo htmlspecialchars((string) $d['last_error']); ?></td>
                    <td><?php echo htmlspecialchars((string) $d['failed_at']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card" style="margin-top:16px">
    <div class="card-h"><i class="fas fa-circle-info"></i> ملاحظات التشغيل</div>
    <div class="card-b" style="font-size:.86rem;line-height:1.9">
        <p><strong>ضمان «مرة واحدة بالضبط»</strong> قائم بالمؤشر (<code>cursor_event_id</code>) + جدول التسليمات (<code>ems_event_deliveries</code>) + الرسائل الميتة. جدول <code>ems_processed_events</code> عطالة موزعة احتياطية تفعل عند تعدد الموزعات.</p>
        <p><strong>المصالح</strong> (في <code>cron_events.php</code>) يعيد نشر الأحداث الفائتة ويرصد اليتيمة في كل دورة. <strong>الجدولة المقترحة</strong>: مهمة نظام التشغيل كل دقيقة — <code>php <?php echo dirname(__DIR__); ?>/cron_events.php</code> (أو عبر مفتاح <code>?key=</code> من .env).</p>
        <p><strong>التأخر (backlog)</strong> = عدد أحداث الدفتر الأحدث من مؤشر المستهلك؛ صفر = محدث. أي رسالة ميتة = تدخل يدوي (فحص <code>last_error</code>).</p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
