<?php
/**
 * Risk/risk_units.php — وحدات المخاطر والتصنيف (M-16 §11-3 · الشاشة ٣)
 * ─────────────────────────────────────────────────────────────────────────
 * الوحدات الإحدى عشرة بمعاييرها المرجعية ونوافذ منع التكرار — «التصنيف منهج
 * تملكه إدارة المخاطر» (§14-4): الإدارات تقترح ولا تعدّل، والتعديل بترحيل
 * لا بحذف — فلا تُعطَّل وحدةٌ وعليها مخاطر مفتوحة.
 */
require_once __DIR__ . '/_risk_common.php';
$__pp = risk_guard_screen($conn, $is_super_admin);

require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes($__pp);

$canEdit = $is_super_admin || !empty($__pp['can_edit']);

$rows = array();
$res = $conn->query("SELECT ru.*,
                            (SELECT COUNT(*) FROM risk_register r
                              WHERE r.company_id = ru.company_id AND r.ru_id = ru.id
                                AND r.merged_into_id IS NULL) total_risks,
                            (SELECT COUNT(*) FROM risk_register r
                              WHERE r.company_id = ru.company_id AND r.ru_id = ru.id
                                AND r.state <> 'closed' AND r.merged_into_id IS NULL) open_risks,
                            (SELECT COUNT(*) FROM risk_kris k
                              WHERE k.company_id = ru.company_id AND k.ru_id = ru.id AND k.active = 1) kris
                       FROM risk_units ru
                      WHERE ru.company_id = {$company_id}
                      ORDER BY ru.ru_code");
while ($x = $res->fetch_assoc()) { $rows[] = $x; }

$activeCount = 0;
foreach ($rows as $x) { if ((int) $x['active'] === 1) { $activeCount++; } }

$page_title = 'إيكوبيشن | وحدات المخاطر والتصنيف';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'وحدات المخاطر والتصنيف';
    $header_icon = 'fas fa-sitemap';
    $header_actions = array();
    $header_back = array();
    $header_context = array(
        'المقام' => 'إحدى عشرة وحدة (§11-3)',
        'النشطة' => $activeCount . ' من ' . count($rows),
        'حقك هنا' => $canEdit ? 'تعديل المنهج' : 'قراءة — والتعديل بطلب',
    );
    include('../includes/page_header.php');
    ems_screen_about(
        'وحدات المخاطر الإحدى عشرة: تصنيفُ منشأِ الخطر ومعيارُه المرجعيُّ ونافذةُ منعِ التكرار. '
        . 'التصنيفُ منهجٌ تملكه إدارةُ المخاطر — والإداراتُ تقترح ولا تعدّل (§14-4).',
        array('التعديلُ بترحيلٍ لا بحذف — ولا تُعطَّل وحدةٌ وعليها خطرٌ مفتوح',
              'نافذةُ التكرار تُقاس بالوحدة: الاستراتيجيةُ أطولُ والتشغيليةُ أقصر (ورقة 32)'));
    ?>

    <?php if (empty($rows)): ?>
    <div class="ems-card" id="ruEmpty"></div>
    <script>document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('ruEmpty').appendChild(EmsUI.emptyState({
            reason: 'لا وحدات مخاطر مبذورة لهذا الكيان — البذرة المرجعية شرطُ تسجيل أي خطر'
        }));
    });</script>
    <?php else: ?>
    <div class="card"><div class="card-body table-responsive">
        <table class="table table-striped" style="width:100%">
            <thead><tr>
                <th>الرمز</th><th>الوحدة</th><th>الإدارات المرتبطة</th>
                <th>المعيار المرجعي</th><th>نافذة التكرار</th>
                <th>مخاطر مفتوحة</th><th>الإجمالي</th><th>مؤشرات</th>
                <th>الحالة</th><?php if ($canEdit): ?><th>تعديل</th><?php endif; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $x): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($x['ru_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($x['name_ar']); ?>
                        <?php if (!empty($x['coverage'])): ?>
                        <div style="font-size:.72rem;opacity:.7;max-width:420px">
                            <?php echo htmlspecialchars(mb_substr((string) $x['coverage'], 0, 150)); ?></div>
                        <?php endif; ?></td>
                    <td style="font-size:.78rem"><?php echo htmlspecialchars($x['linked_depts']); ?></td>
                    <td style="font-size:.78rem"><?php echo htmlspecialchars($x['ref_standard']); ?></td>
                    <td><?php echo (int) $x['dedup_window_days']; ?> يومًا</td>
                    <td><?php echo (int) $x['open_risks']; ?></td>
                    <td><?php echo (int) $x['total_risks']; ?></td>
                    <td><?php echo (int) $x['kris']; ?></td>
                    <td><span class="badge <?php echo (int) $x['active'] === 1 ? 'badge-success' : 'badge-secondary'; ?>">
                        <?php echo (int) $x['active'] === 1 ? 'نشطة' : 'معطَّلة'; ?></span></td>
                    <?php if ($canEdit): ?>
                    <td><button class="btn btn-sm btn-outline-dark ruEdit"
                        data-id="<?php echo (int) $x['id']; ?>"
                        data-code="<?php echo htmlspecialchars($x['ru_code']); ?>"
                        data-name="<?php echo htmlspecialchars($x['name_ar']); ?>"
                        data-win="<?php echo (int) $x['dedup_window_days']; ?>"
                        data-active="<?php echo (int) $x['active']; ?>"
                        data-open="<?php echo (int) $x['open_risks']; ?>">تعديل</button></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
    <?php endif; ?>

    <?php if ($canEdit): ?>
    <div class="card" id="ruEditCard" style="display:none;margin-top:16px"><div class="card-body">
        <h5>تعديل وحدة <span id="ruEditCode"></span></h5>
        <p style="font-size:.8rem;opacity:.8">التعديلُ بترحيلٍ لا بحذف — والتعطيلُ مرفوضٌ إن كانت عليها مخاطرُ مفتوحة.</p>
        <form id="ruEditForm" class="allforms">
            <input type="hidden" name="ru_id" id="ruEditId">
            <div class="row">
                <div class="col-md-6"><label>اسم الوحدة *<input name="name_ar" id="ruEditName" class="form-control" required></label></div>
                <div class="col-md-3"><label>نافذة التكرار (أيام) *<input name="dedup_window_days" id="ruEditWin" type="number" min="1" max="3650" class="form-control" required></label></div>
                <div class="col-md-3"><label>الحالة<select name="active" id="ruEditActive" class="form-control">
                    <option value="1">نشطة</option><option value="0">معطَّلة</option>
                </select></label></div>
                <div class="col-md-6"><label>المعيار المرجعي<input name="ref_standard" class="form-control"></label></div>
                <div class="col-md-6"><label>نطاق التغطية<input name="coverage" class="form-control"></label></div>
            </div>
            <button type="submit" class="ems-btn-primary">حفظ (RSK-TAXONOMY-DEFINE)</button>
            <span id="ruEditMsg" style="margin-inline-start:10px"></span>
        </form>
    </div></div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var card = document.getElementById('ruEditCard');
        document.querySelectorAll('.ruEdit').forEach(function (b) {
            b.addEventListener('click', function () {
                document.getElementById('ruEditId').value = b.dataset.id;
                document.getElementById('ruEditCode').textContent = b.dataset.code;
                document.getElementById('ruEditName').value = b.dataset.name;
                document.getElementById('ruEditWin').value = b.dataset.win;
                document.getElementById('ruEditActive').value = b.dataset.active;
                document.getElementById('ruEditMsg').textContent = b.dataset.open > 0
                    ? '⚠ عليها ' + b.dataset.open + ' خطرًا مفتوحًا — التعطيل سيُرفض'
                    : '';
                card.style.display = '';
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });
        document.getElementById('ruEditForm').addEventListener('submit', function (ev) {
            ev.preventDefault();
            var fd = new FormData(this);
            fd.append('do', 'taxonomy_define');
            if (window.csrfToken) { fd.append('csrf_token', window.csrfToken); }
            fetch('risk_actions.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); })
            .then(function (j) {
                var m = document.getElementById('ruEditMsg');
                if (j.ok) { m.textContent = '✔ حُفظ'; setTimeout(function () { location.reload(); }, 700); }
                else { m.textContent = '✘ ' + (j.code || '') + ' ' + (j.msg || ''); }
            });
        });
    });
    </script>
    <?php endif; ?>
</div>
</body>
</html>
