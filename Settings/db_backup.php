<?php
/**
 * Settings/db_backup.php — النسخُ الاحتياطيُّ لقاعدةِ البيانات.
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **بديلُ كتلةِ الأدواتِ في `admin/settings.php`**: كانت الواجهةُ الوحيدةُ
 *   للنسخِ في المشروعِ كلِّه قابعةً داخلَ بوّابةِ المزوّدِ (SaaS)، وهي مُغلقةٌ
 *   بـ403 منذ قرارِ الشركةِ الواحدة — فكانت القدرةُ قائمةً ولا سبيلَ إليها.
 *   نُقلت هنا بحارسِ المستأجرِ وصلاحيّةِ الشاشة.
 *
 * ⛔ **والاستعادةُ والاستيرادُ لم يُنقلا عمدًا**: `db_restore` و`db_import`
 *   يدهسان القاعدةَ كاملةً. زرٌّ كهذا خلفَ جلسةِ متصفّحٍ واحدةٍ دائرةُ انفجارٍ
 *   لا تُبرَّر بالراحة — ويبقيان على الخادمِ لمن يملك وصولَه:
 *       php -r "require 'includes/db_tools.php'; \$e=''; \$a=null;
 *               var_dump(ems_dbtool_import('<المسار>', \$e, \$a));"
 *   والاستعادةُ تأخذ نسخةً وقائيّةً قبلَ الاستبدالِ في الحالتين.
 *
 * ◆ **والسردُ يقرأ القائمتين**: نسخَ هذه الشاشةِ في جذرِ `storage/backups`،
 *   ونسخَ المهمّةِ اليوميّةِ في `daily/`. وحذفُ اليوميّةِ يُردُّ — تدويرُها
 *   ملكُ `tools/ops01_daily_backup.php --keep=N`، ويدان على ملفٍّ واحدٍ تتنازعان.
 */

require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/db_tools.php';
require_once __DIR__ . '/../includes/audit_trail.php';

// ── حارسُ الشاشةِ فوقَ أيِّ معالجٍ يكتب (RF-02 · CS-01) ────────────────────
// insidebar يقع بعدَ المعالجِ، فالحجبُ به وحدَه يُنفِّذ الفعلَ ثم يعتذر.
if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}
$page_title = "النسخ الاحتياطي لقاعدة البيانات";

$bk_perms = get_current_page_permissions($conn);
if ($bk_perms['id'] !== null && !$bk_perms['can_view']) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية لهذه الصفحة ', 'GOV-PERM-403', '');
    exit();
}
$can_make   = ($bk_perms['id'] === null) || !empty($bk_perms['can_add']);   // إنشاءُ نسخةٍ وتشغيلُ المجدولة
$can_config = ($bk_perms['id'] === null) || !empty($bk_perms['can_edit']);  // حفظُ الجدولة
$can_drop   = ($bk_perms['id'] === null) || !empty($bk_perms['can_delete']); // حذفُ نسخة

$db_msg = '';
$actor  = intval($_SESSION['user']['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && strncmp((string) ($_POST['action'] ?? ''), 'db_', 3) === 0) {
    $act = (string) $_POST['action'];

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $db_msg = 'error:رمز الحماية غير صحيح، حاول مرة أخرى';
    } elseif ($act === 'db_backup') {
        if (!$can_make) {
            $db_msg = 'error:لا توجد صلاحية لإنشاء نسخة';
        } else {
            $err  = '';
            $path = ems_dbtool_backup($err);
            if ($path) {
                ems_audit_change($conn, 'settings', 'db_backup', 'backup', basename($path),
                    array(), array('file' => basename($path)));
                ems_dbtool_stream_download($path); // ينظّف المخازن ويبثّ ثم exit
            }
            $db_msg = 'error:' . $err;
        }
    } elseif ($act === 'db_download') {
        $path = ems_dbtool_resolve_backup($_POST['file'] ?? '');
        if ($path) {
            ems_dbtool_stream_download($path);
        }
        $db_msg = 'error:ملف النسخة غير موجود أو غير صالح';
    } elseif ($act === 'db_delete') {
        $name = (string) ($_POST['file'] ?? '');
        if (!$can_drop) {
            $db_msg = 'error:لا توجد صلاحية لحذف النسخ';
        } elseif (strncmp($name, 'daily/', 6) === 0) {
            // تدويرُ اليوميّةِ ملكُ المهمّةِ المجدولة — يدان على ملفٍّ واحدٍ تتنازعان.
            $db_msg = 'error:النسخ اليومية يديرها التدوير المجدول - لا تحذف من هنا';
        } else {
            $path = ems_dbtool_resolve_backup($name);
            if ($path && @unlink($path)) {
                ems_audit_change($conn, 'settings', 'db_backup', 'delete', basename($path),
                    array('file' => basename($path)), array());
                $db_msg = 'success:تم حذف النسخة الاحتياطية';
            } else {
                $db_msg = 'error:تعذر حذف النسخة (ملف غير موجود أو غير صالح)';
            }
        }
    } elseif ($act === 'db_schedule') {
        if (!$can_config) {
            $db_msg = 'error:لا توجد صلاحية لتعديل الجدولة';
        } else {
            $cfg = ems_dbtool_schedule_get();
            $old = array('enabled' => !empty($cfg['enabled']),
                         'interval_days' => intval($cfg['interval_days']),
                         'retention' => intval($cfg['retention']));
            $cfg['enabled']       = !empty($_POST['sched_enabled']);
            $cfg['interval_days'] = max(1, intval($_POST['interval_days'] ?? 1));
            $cfg['retention']     = max(1, intval($_POST['retention'] ?? 14));
            if (ems_dbtool_schedule_save($cfg)) {
                ems_audit_change($conn, 'settings', 'db_backup', 'update', 'schedule', $old,
                    array('enabled' => (bool) $cfg['enabled'],
                          'interval_days' => intval($cfg['interval_days']),
                          'retention' => intval($cfg['retention'])));
                $db_msg = 'success:تم حفظ إعدادات الجدولة';
            } else {
                $db_msg = 'error:تعذر حفظ إعدادات الجدولة';
            }
        }
    } elseif ($act === 'db_run_now') {
        if (!$can_make) {
            $db_msg = 'error:لا توجد صلاحية لتشغيل النسخ';
        } else {
            $rerr = '';
            $res  = ems_dbtool_run_scheduled($rerr, true);
            if (!empty($res['ok'])) {
                ems_audit_change($conn, 'settings', 'db_backup', 'backup', (string) ($res['file'] ?? ''),
                    array(), array('file' => (string) ($res['file'] ?? ''), 'trigger' => 'manual'));
                $db_msg = 'success:' . $res['message'];
            } else {
                $db_msg = 'error:' . $res['message'];
            }
        }
    }
}

$sizeInfo = ems_dbtool_size_info($conn);
$backups  = ems_dbtool_list_backups();
$sched    = ems_dbtool_schedule_get();
/* ⛔ **`ems_dbtool_bin_hint()` بانيةُ رسالةٍ لا فاحصة**: ترجع نصًّا دائمًا،
   فاستدعاؤها وحدَها يعرض تحذيرًا كاذبًا والأداةُ موجودة. الفحصُ يسبقها —
   بالنمطِ نفسِه الذي يستعمله المحرّك (`ems_dbtool_backup`). */
$binDir   = ems_dbtool_bin_dir();
$binOk    = ($binDir !== '') && (is_file($binDir . '/mysqldump.exe') || is_file($binDir . '/mysqldump'));
$binHint  = $binOk ? '' : ems_dbtool_bin_hint('mysqldump');

$msgKind = '';
$msgText = '';
if ($db_msg !== '') {
    $parts   = explode(':', $db_msg, 2);
    $msgKind = $parts[0];
    $msgText = isset($parts[1]) ? $parts[1] : '';
}

include("../inheader.php");
include('../insidebar.php');
?>
<style>
.bk-wrap{display:flex;flex-direction:column;gap:20px}
.bk-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px}
.bk-panel{background:var(--ems-white);border:1px solid var(--c-e5e7eb);border-radius:8px;padding:20px}
.bk-panel h5{margin:0 0 12px;font-weight:700;font-size:15px}
.bk-note{font-size:13px;color:var(--c-ink-500);line-height:1.7;margin:0}
.bk-danger{border-right:4px solid var(--c-badge-warning-b);background:var(--c-note-bg)}
.bk-kind{display:inline-block;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:600}
.bk-kind.daily{background:var(--c-e0f2fe);color:var(--c-075985)}
.bk-kind.manual{background:var(--c-f3f4f6);color:var(--c-374151)}
.bk-kind.auto{background:var(--c-fef3c7);color:var(--c-92400e)}
</style>

<div class="container-fluid p-3 bk-wrap">

 <h4 class="fw-bold mb-0"><i class="fa fa-database me-2"></i>النسخ الاحتياطي لقاعدة البيانات</h4>

 <?php if ($msgText !== ''): ?>
    <div class="alert alert-<?= $msgKind === 'success' ? 'success' : 'danger' ?> mb-0">
      <?= htmlspecialchars($msgText, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <?php if ($binHint !== ''): ?>
    <div class="alert alert-warning mb-0"><?= htmlspecialchars($binHint, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

 <!-- ① الحالة -->
 <div class="bk-grid">
 <div class="bk-panel">
 <h5>حجم القاعدة</h5>
 <div class="fs-4 fw-bold"><?= number_format((float) $sizeInfo['mb'], 1) ?> م.ب</div>
 <p class="bk-note"><?= intval($sizeInfo['tables']) ?> جدولا</p>
 </div>
 <div class="bk-panel">
 <h5>النسخ المتاحة</h5>
 <div class="fs-4 fw-bold"><?= count($backups) ?></div>
 <p class="bk-note">من هذه الشاشة ومن المهمة اليومية معا</p>
 </div>
 <div class="bk-panel">
 <h5>آخر نسخة</h5>
 <div class="fs-6 fw-bold">
 <?= $backups ? ems_fmt_date(intval($backups[0]['mtime']), 'datetime') : '- لا توجد نسخة -' ?>
      </div>
      <p class="bk-note"><?= $backups ? htmlspecialchars($backups[0]['kind'], ENT_QUOTES, 'UTF-8') : 'خذ نسخة الآن' ?></p>
 </div>
 <div class="bk-panel">
 <h5>الجدولة</h5>
 <div class="fs-6 fw-bold"><?= !empty($sched['enabled']) ? 'مفعلة كل ' . max(1, intval($sched['interval_days'])) . ' يوم' : 'معطلة' ?></div>
      <p class="bk-note">
        <?php if (!empty($sched['last_run_at'])): ?>
 آخر تشغيل: <?= htmlspecialchars((string) $sched['last_run_at'], ENT_QUOTES, 'UTF-8') ?>
          (<?= $sched['last_status'] === 'success' ? 'نجح' : 'أخفق' ?>)
        <?php else: ?>
 لم تشغل بعد
 <?php endif; ?>
 </p>
 </div>
 </div>

 <!-- ② الأفعال -->
 <div class="bk-panel">
 <h5>أخذ نسخة</h5>
 <div class="d-flex flex-wrap gap-2 align-items-center">
 <form method="post" class="d-inline">
 <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="db_backup">
        <button class="btn btn-primary btn-sm fw-semibold" <?= $can_make ? '' : 'disabled' ?>>
 <i class="fa fa-download me-1"></i>نسخة الآن (تنزيل مباشر)
 </button>
 </form>
 <form method="post" class="d-inline">
 <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="db_run_now">
        <button class="btn btn-secondary btn-sm fw-semibold" <?= $can_make ? '' : 'disabled' ?>>
 <i class="fa fa-server me-1"></i>نسخة على الخادم (بلا تنزيل)
 </button>
 </form>
 </div>
 <p class="bk-note mt-2">
 الأولى تبث الملف إلى جهازك مباشرة ولا تبقيه على الخادم؛ الثانية تحفظه في
 <code>storage/backups</code> وتخضع لتدوير الجدولة.
 </p>
 </div>

 <!-- ③ الجدولة -->
 <div class="bk-panel">
 <h5>جدولة النسخ التلقائي</h5>
 <form method="post" class="row g-3 align-items-end">
 <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="action" value="db_schedule">
      <div class="col-auto form-check ms-2">
        <input class="form-check-input" type="checkbox" id="sched_enabled" name="sched_enabled" value="1"
               <?= !empty($sched['enabled']) ? 'checked' : '' ?> <?= $can_config ? '' : 'disabled' ?>>
 <label class="form-check-label fw-semibold" for="sched_enabled">مفعلة</label>
 </div>
 <div class="col-auto">
 <label class="form-label small fw-semibold">كل كم يوم</label>
 <input type="number" min="1" max="30" class="form-control form-control-sm" name="interval_days"
 value="<?= intval($sched['interval_days']) ?>" <?= $can_config ? '' : 'disabled' ?>>
 </div>
 <div class="col-auto">
 <label class="form-label small fw-semibold">عدد النسخ المحفوظة</label>
 <input type="number" min="1" max="90" class="form-control form-control-sm" name="retention"
 value="<?= intval($sched['retention']) ?>" <?= $can_config ? '' : 'disabled' ?>>
      </div>
      <div class="col-auto">
        <button class="btn btn-primary btn-sm fw-semibold" <?= $can_config ? '' : 'disabled' ?>>حفظ الجدولة</button>
 </div>
 </form>
 <p class="bk-note mt-2">
 هذه جدولة هذه الشاشة (<code>tools/cron_backup.php</code>). وهناك نسخة يومية مستقلة
 في مجدول ويندوز تشغل <code>tools/ops01_daily_backup.php</code> وتكتب في <code>daily/</code> -
 تظهر نسخها في الجدول أدناه ولا تتأثر بهذه الإعدادات.
 </p>
 </div>

 <!-- ④ النسخ -->
 <div class="bk-panel">
 <h5>النسخ المحفوظة على الخادم</h5>
 <div class="table-responsive">
 <table class="table table-sm table-hover align-middle mb-0" data-no-datatable>
 <thead>
 <tr>
 <th>إجراءات</th>
 <th>الملف</th>
 <th>النوع</th>
 <th>الحجم</th>
 <th>التاريخ</th>
 </tr>
 </thead>
 <tbody>
 <?php if (!$backups): ?>
 <tr><td colspan="5" class="text-center text-muted py-4">لا توجد نسخ محفوظة بعد</td></tr>
 <?php else: foreach ($backups as $b): ?>
          <tr>
            <td class="text-nowrap">
              <form method="post" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="db_download">
                <input type="hidden" name="file" value="<?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?>">
 <button class="btn btn-outline-primary btn-sm" title="تنزيل"><i class="fa fa-download"></i></button>
 </form>
 <?php if ($can_drop && empty($b['daily'])): ?>
 <form method="post" class="d-inline" onsubmit="return confirm('حذف هذه النسخة نهائيا؟');">
 <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="db_delete">
                <input type="hidden" name="file" value="<?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?>">
 <button class="btn btn-outline-danger btn-sm" title="حذف"><i class="fa fa-trash"></i></button>
 </form>
 <?php endif; ?>
            </td>
            <td class="text-break"><code><?= htmlspecialchars($b['name'], ENT_QUOTES, 'UTF-8') ?></code></td>
            <td>
              <span class="bk-kind <?= !empty($b['daily']) ? 'daily' : (!empty($b['is_auto']) ? 'auto' : 'manual') ?>">
                <?= htmlspecialchars($b['kind'], ENT_QUOTES, 'UTF-8') ?>
              </span>
            </td>
            <td class="text-nowrap"><?= htmlspecialchars($b['size_h'], ENT_QUOTES, 'UTF-8') ?></td>
            <td class="text-nowrap"><?= ems_fmt_date(intval($b['mtime']), 'datetime') ?></td>
          </tr>
        <?php endforeach; endif; ?>
 </tbody>
 </table>
 </div>
 </div>

 <!-- ⑤ ما لا تفعله هذه الشاشة -->
 <div class="bk-panel bk-danger">
 <h5><i class="fa fa-triangle-exclamation me-1"></i>الاستعادة والاستيراد ليسا هنا - عن قصد</h5>
 <p class="bk-note mb-0">
 استعادة نسخة أو استيراد ملف SQL <strong>يستبدلان القاعدة كاملة</strong>. زر بهذا الأثر
 خلف جلسة متصفح واحدة دائرة انفجار لا تبرر - فهما ينفذان على الخادم لمن يملك وصوله،
 وتؤخذ نسخة وقائية تلقائيا قبل الاستبدال في الحالتين.
 </p>
 </div>

</div>

</body>
</html>
