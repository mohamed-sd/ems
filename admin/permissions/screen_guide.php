<?php
/**
 * admin/permissions/screen_guide.php — دليلُ الشاشات (تعريفاتُ ما يظهر للمستخدم)
 * ═══════════════════════════════════════════════════════════════════════════
 * تحريرُ النصوص التي تظهر في بطاقة «عن الشاشة» على كلِّ شاشةٍ في النظام.
 *
 * ◆ لماذا في كونسول المزوّد لا في شاشةٍ داخل الشركة (قرار المالك 2026-08-09):
 *   «النصُّ على مستوى كلِّ العملاء لأن الدليلَ ثابت». وجدولُ `screen_about`
 *   مصنَّفٌ `T_GLOBAL` — صفٌّ واحدٌ يخدم كلَّ الشركات. فتحريرُه من داخل شركةٍ
 *   واحدةٍ يغيّر نصًّا يراه كلُّ العملاء، وهو ما لا يجوز أن يملكه مستأجر.
 *   وهنا موضعُه الشرعيُّ بين إخوته: `modules` و`nav_items` و`link_groups`.
 *
 * ◆ الكتابةُ عبر `ems_platform_db()` حصرًا — البوابةُ العابرةُ بهوية المدير
 *   الأعلى، وهي القناةُ المعتمَدةُ لكتابة المراجع العامة (دفعة المزوّد هـ-1).
 *
 * ◆ الشاشةُ أداةُ حوكمةٍ لا نموذجُ إدخال: تُظهر **تغطيةَ الدليل** — كم نصًّا
 *   مكتوبًا بيد، وكم مركَّبًا، وكم أدنى، وكم شاشةً بلا اسمٍ عربي — فيُغلق
 *   النقصُ من المكان الذي يُريه، لا من تقريرٍ يُنسى.
 *
 * ◆ العنوانُ (`title_ar`) يُحرَّر هنا **ولا يُكتب في `nav09_file_map`**: ذاك
 *   مرآةُ وثيقة NAV-09 ويقارنه فاحصُها صفًّا صفًّا — فلا يُمسّ لسببٍ عرضي.
 */
require_once __DIR__ . '/../includes/auth.php';
super_admin_require_login();

$admin        = super_admin_current();
$page_title   = 'دليل الشاشات — تعريفات ما يظهر للمستخدم';
$current_page = 'permissions';

/* ◆ لا يُضمَّن `config.php` هنا: `admin/includes/auth.php` يحمّله بـ`require_once`
   في سطره الثاني. وأخواتُ هذه الشاشة تكتب `include '../config.php'` وهو مسارٌ
   يحلُّ إلى `admin/config.php` **ولا وجودَ له** — فالسطرُ يفشل صامتًا ولا يفعل
   شيئًا، وتعمل الشاشاتُ بفضل `auth.php` وحدَه. ولمّا كتبتُ المسارَ الصحيح
   حُمِّل الملفُّ مرتين فانفجر «Cannot redeclare» — لأن `config.php` يعرّف دوالَّه
   بلا حارس. فالصوابُ حذفُ السطر لا تصحيحُ مسارِه. */

$pg = ems_platform_db();

$error_msg = '';

/* ── الحفظ ─────────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_id'])) {
    $id    = (int) $_POST['save_id'];
    $desc  = trim((string) ($_POST['description'] ?? ''));
    $title = trim((string) ($_POST['title_ar'] ?? ''));
    if ($id <= 0) {
        $error_msg = 'سجلٌّ غير صالح ❌';
    } elseif (mb_strlen($desc) < 20) {
        $error_msg = 'النصُّ التعريفيُّ أقصرُ من أن يكون دليلًا (20 حرفًا على الأقل) ❌';
    } else {
        try {
            /* المحرَّرُ بيدٍ يُوسَم `authored` — فيُعرف أنه لا يُعاد توليدُه،
               ومولِّدُ النصوص لا يدهسه عند تشغيله ثانيةً. */
            $pg->update('screen_about', array(
                'description' => $desc,
                'title_ar'    => $title,
                'source'      => 'authored',
            ), array('id' => $id));
            ems_flash_set('حُفظ التعريف ✔');
        header('Location: screen_guide.php');
            exit;
        } catch (\Throwable $t) {
            error_log('admin/permissions/screen_guide save: ' . $t->getMessage());
            $error_msg = 'تعذّر الحفظ ❌';
        }
    }
}

/* ── القراءة ───────────────────────────────────────────────────────────── */
$rows = array();
try {
    $rows = $pg->select('screen_about', array(
        'columns' => array('id', 'screen_path', 'title_ar', 'description', 'source', 'active', 'updated_at'),
        'orderBy' => 'screen_path ASC',
    ));
} catch (\Throwable $t) { error_log('admin/permissions/screen_guide read: ' . $t->getMessage()); }

$total = count($rows);
$bySource = array('authored' => 0, 'composed' => 0, 'derived' => 0);
$noTitle = 0;
foreach ($rows as $r) {
    $s = (string) $r['source'];
    if (isset($bySource[$s])) { $bySource[$s]++; }
    // شاشةٌ بلا اسمٍ عربيٍّ معتمد: عنوانُها فارغٌ أو لاتينيٌّ خالص
    $t = trim((string) $r['title_ar']);
    if ($t === '' || !preg_match('/[\x{0600}-\x{06FF}]/u', $t)) { $noTitle++; }
}

/* ── نسبةُ الشاشة إلى إدارتها ──────────────────────────────────────────────
 * قرارُ المالك: المرشِّحُ **بالإدارات الرئيسية وحدَها** — «الإدارةُ الفرعيةُ
 * ترث دورَ أبيها»، فشاشةُ مشرفِ الأسطول تُنسب إلى إدارة الأسطول لا إلى بندٍ
 * مستقلٍّ يشتّت القائمة. فيُصعَد بكلِّ دورٍ إلى **جذرِه** ثم تُنسب الشاشةُ إليه.
 *
 * المصدران معًا كي لا تسقط شاشة: `nav_items` (ما يظهر في القوائم فعلًا) و
 * `modules.owner_role_id` (المِلكيةُ المسجَّلة، وتغطي ما لا رابطَ له).
 * ────────────────────────────────────────────────────────────────────────── */
$roleParent = array(); $roleName = array();
$deptOf = array();      // screen_path(lower) => [rootRoleId => true]
try {
    foreach ($pg->select('roles', array('columns' => array('id', 'name', 'parent_role_id'))) as $r) {
        $roleParent[(int) $r['id']] = ($r['parent_role_id'] === null) ? 0 : (int) $r['parent_role_id'];
        $roleName[(int) $r['id']]   = (string) $r['name'];
    }
    /** يصعد بالدور إلى جذره (وحارسُ عمقٍ يمنع دورةً لو أُسيء ضبطُ الشجرة). */
    $rootOf = function ($rid) use ($roleParent) {
        $seen = array(); $d = 0;
        while (isset($roleParent[$rid]) && $roleParent[$rid] > 0 && $d++ < 10) {
            if (isset($seen[$rid])) { break; }
            $seen[$rid] = true;
            $rid = $roleParent[$rid];
        }
        return (int) $rid;
    };

    foreach ($pg->select('nav_items', array('columns' => array('role_id', 'route'),
                                            'where' => array('active' => 1))) as $n) {
        $p = strtolower(trim(preg_replace('/[#?].*$/', '', (string) $n['route'])));
        if ($p === '') { continue; }
        $deptOf[$p][$rootOf((int) $n['role_id'])] = true;
    }
    foreach ($pg->select('modules', array('columns' => array('owner_role_id', 'code'))) as $m) {
        $p = strtolower(trim((string) $m['code']));
        if ($p === '' || $m['owner_role_id'] === null) { continue; }
        $deptOf[$p][$rootOf((int) $m['owner_role_id'])] = true;
    }
} catch (\Throwable $t) { error_log('admin/permissions/screen_guide depts: ' . $t->getMessage()); }

/* الإداراتُ المعروضةُ في المرشِّح: الجذورُ التي تملك شاشةً فعلًا — مرتَّبةً
   بعدد شاشاتها. ولا يُعرض جذرٌ بلا شاشةٍ فيصير خيارًا يعطي فراغًا. */
$deptCount = array();
foreach ($rows as $r) {
    $p = strtolower((string) $r['screen_path']);
    if (empty($deptOf[$p])) { continue; }
    foreach (array_keys($deptOf[$p]) as $rid) {
        $deptCount[$rid] = (isset($deptCount[$rid]) ? $deptCount[$rid] : 0) + 1;
    }
}
arsort($deptCount);

require_once __DIR__ . '/../includes/layout_head.php';
?>
<div class="page-shell" style="padding:1.5rem 2rem 3rem">

  <div style="margin-bottom:1.4rem">
    <h2 style="margin:0 0 .35rem;font-weight:800;color:var(--navy,#0C1C3E)">
      <i class="fa-solid fa-book-open"></i> دليل الشاشات
    </h2>
    <p style="margin:0;color:#666">
      النصُّ الذي يقرؤه المستخدمُ في بطاقة «عن الشاشة» عند فتحِ أيِّ شاشة.
      <b>الدليلُ ثابتٌ لكلِّ العملاء</b> — فما تحرّره هنا يظهر عند الجميع.
    </p>
  </div>

  <?php if (!empty($_GET['msg'])): ?>
    <div class="alert alert-success"><?= e($_GET['msg']) ?></div>
  <?php endif; ?>
  <?php if ($error_msg !== ''): ?>
    <div class="alert alert-danger"><?= e($error_msg) ?></div>
  <?php endif; ?>

  <!-- تغطيةُ الدليل: أداةُ حوكمةٍ تُري النقصَ بدل أن تُخفيه -->
  <div class="sg-kpis">
    <div class="sg-kpi"><span class="sg-kpi__v"><?= $total ?></span><span class="sg-kpi__l">شاشة في الدليل</span></div>
    <div class="sg-kpi sg-kpi--ok"><span class="sg-kpi__v"><?= $bySource['authored'] ?></span><span class="sg-kpi__l">نصٌّ مكتوبٌ بيد</span></div>
    <div class="sg-kpi"><span class="sg-kpi__v"><?= $bySource['composed'] ?></span><span class="sg-kpi__l">مركَّبٌ من مصادر النظام</span></div>
    <div class="sg-kpi <?= $bySource['derived'] > 0 ? 'sg-kpi--warn' : 'sg-kpi--ok' ?>">
      <span class="sg-kpi__v"><?= $bySource['derived'] ?></span><span class="sg-kpi__l">تعريفٌ أدنى — يحتاج كتابة</span></div>
    <div class="sg-kpi <?= $noTitle > 0 ? 'sg-kpi--warn' : 'sg-kpi--ok' ?>">
      <span class="sg-kpi__v"><?= $noTitle ?></span><span class="sg-kpi__l">بلا اسمٍ عربيٍّ معتمد</span></div>
  </div>

  <div class="sg-filters">
    <input type="search" id="sgSearch" placeholder="ابحث بالمسار أو الاسم أو النص…" class="form-control" aria-label="ابحث بالمسار أو الاسم أو النص…">
    <select id="sgDept" class="form-control" title="الإدارات الرئيسية فقط — والفرعيةُ محسوبةٌ مع أبيها" aria-label="الإدارات الرئيسية فقط — والفرعيةُ محسوبةٌ مع أبيها">
      <option value="">كل الإدارات</option>
      <?php foreach ($deptCount as $rid => $cnt):
          $nm = isset($roleName[$rid]) ? $roleName[$rid] : ('دور ' . $rid); ?>
        <option value="<?= (int) $rid ?>"><?= e($nm) ?> (<?= (int) $cnt ?>)</option>
      <?php endforeach; ?>
      <option value="none">— بلا إدارة —</option>
    </select>
    <select id="sgSource" class="form-control">
      <option value="">كل المصادر</option>
      <option value="authored">مكتوبٌ بيد</option>
      <option value="composed">مركَّب</option>
      <option value="derived">أدنى — يحتاج كتابة</option>
    </select>
    <span class="sg-count" id="sgCount"></span>
  </div>

  <div class="sg-table-wrap">
    <table class="table sg-table" id="sgTable">
      <thead>
        <tr>
          <th style="width:38px"></th>
          <th>المسار</th>
          <th>الاسم المعتمد</th>
          <th style="width:150px">الإدارة</th>
          <th>النصُّ التعريفي</th>
          <th style="width:110px">المصدر</th>
          <th style="width:70px">الطول</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r):
          $src = (string) $r['source'];
          $srcLabel = array('authored' => 'مكتوبٌ بيد', 'composed' => 'مركَّب', 'derived' => 'أدنى');
          $len = mb_strlen((string) $r['description']);
          $pKey  = strtolower((string) $r['screen_path']);
          $dIds  = !empty($deptOf[$pKey]) ? array_keys($deptOf[$pKey]) : array();
          $dNames = array();
          foreach ($dIds as $rid) { if (isset($roleName[$rid])) { $dNames[] = $roleName[$rid]; } }
      ?>
        <tr data-source="<?= e($src) ?>"
            data-dept="<?= e($dIds ? implode(',', $dIds) : 'none') ?>"
            data-hay="<?= e(mb_strtolower($r['screen_path'] . ' ' . $r['title_ar'] . ' ' . $r['description'] . ' ' . implode(' ', $dNames))) ?>">
          <td>
            <button type="button" class="sg-edit" title="تحرير"
                    data-id="<?= (int) $r['id'] ?>"
                    data-path="<?= e($r['screen_path']) ?>"
                    data-dept="<?= e(implode(' · ', $dNames)) ?>"
                    data-title="<?= e($r['title_ar']) ?>"
                    data-desc="<?= e($r['description']) ?>">
              <i class="fa-solid fa-pen"></i>
            </button>
          </td>
          <td><code class="sg-path"><?= e($r['screen_path']) ?></code></td>
          <td><?= $r['title_ar'] !== '' ? e($r['title_ar']) : '<span class="sg-muted">—</span>' ?></td>
          <td class="sg-dept">
            <?php if ($dNames): ?>
              <?php foreach (array_slice($dNames, 0, 2) as $dn): ?>
                <span class="sg-dept__tag"><?= e($dn) ?></span>
              <?php endforeach; ?>
              <?php if (count($dNames) > 2): ?>
                <span class="sg-dept__more" title="<?= e(implode(' · ', $dNames)) ?>">+<?= count($dNames) - 2 ?></span>
              <?php endif; ?>
            <?php else: ?><span class="sg-muted">—</span><?php endif; ?>
          </td>
          <td class="sg-desc"><?= e(mb_substr((string) $r['description'], 0, 150)) ?><?= $len > 150 ? '…' : '' ?></td>
          <td><span class="sg-tag sg-tag--<?= e($src) ?>"><?= e($srcLabel[$src] ?? $src) ?></span></td>
          <td class="sg-len"><?= $len ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- محرِّرٌ بمعاينةٍ لشكل البطاقة كما يراها المستخدم -->
<div class="sg-modal" id="sgModal" hidden>
  <!-- رمزُ CSRF يُحقن مركزيًّا في كل نموذج (config.php ⇐ ems_inject_csrf_fields) — لا يُكتب هنا -->
  <form class="sg-modal__box" method="post" action="screen_guide.php">
    <input type="hidden" name="save_id" id="sgId">
    <div class="sg-modal__head">
      <h3>تحرير تعريف الشاشة</h3>
      <button type="button" class="sg-modal__x" id="sgClose" aria-label="إغلاق"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="sg-modal__body">
      <p class="sg-modal__path">
        <i class="fa-solid fa-file-code"></i> <code id="sgPath"></code>
        <span class="sg-modal__dept" id="sgDept2"></span>
      </p>

      <label for="sgTitle">الاسم المعتمد للشاشة</label>
      <input type="text" name="title_ar" id="sgTitle" class="form-control" placeholder="اسمُ الشاشة كما يُعرَف">

      <div class="sg-editor-head">
        <label for="sgDesc" style="margin:0">النصُّ التعريفي</label>
        <div class="sg-tools">
          <button type="button" class="sg-tool" data-act="para" title="فقرة جديدة (سطرٌ فارغ يفصلها)">
            <i class="fa-solid fa-paragraph"></i></button>
          <button type="button" class="sg-tool" data-act="quote" title="ضع المحدَّد بين «قوسين عربيين»">«»</button>
          <span class="sg-tools__sep"></span>
          <button type="button" class="sg-tool" id="sgRevert" title="تراجع عن كل التعديلات">
            <i class="fa-solid fa-rotate-left"></i></button>
          <button type="button" class="sg-tool" id="sgWide" title="توسيع المحرِّر">
            <i class="fa-solid fa-up-right-and-down-left-from-center"></i></button>
        </div>
      </div>

      <textarea name="description" id="sgDesc" rows="12" class="sg-textarea" spellcheck="true"
                placeholder="اشرح ما هي الشاشةُ وما فيها…" aria-label="اشرح ما هي الشاشةُ وما فيها…"></textarea>

      <div class="sg-meter">
        <span id="sgCounts" class="sg-meter__counts"></span>
        <span id="sgWarn" class="sg-meter__warn" hidden>
          <i class="fa-solid fa-triangle-exclamation"></i> النصُّ أقصرُ من 20 حرفًا — لن يُقبل الحفظ
        </span>
        <span class="sg-meter__hint">افصل الفقراتِ بسطرٍ فارغ · <kbd>Ctrl</kbd>+<kbd>S</kbd> للحفظ</span>
      </div>

      <div class="sg-preview">
        <div class="sg-preview__label">معاينة كما يراها المستخدم</div>
        <section class="ems-about" style="margin:0">
          <div class="ems-about__head">
            <span class="ems-about__ico"><i class="fa fa-circle-question"></i></span>
            <h2 class="ems-about__title" id="sgPvTitle">عن: —</h2>
          </div>
          <div class="ems-about__body" id="sgPvBody"></div>
        </section>
      </div>
    </div>
    <div class="sg-modal__foot">
      <button type="submit" class="btn btn-primary" id="sgSave"><i class="fa-solid fa-floppy-disk"></i> حفظ</button>
      <button type="button" class="btn btn-secondary" id="sgCancel">إلغاء</button>
      <span class="sg-dirty" id="sgDirty" hidden><i class="fa-solid fa-circle"></i> تعديلاتٌ غير محفوظة</span>
    </div>
  </form>
</div>

<link rel="stylesheet" href="/ems/assets/css/ems.main.all.style.css">
<style>
.sg-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:1.2rem}
.sg-kpi{background:#fff;border:1px solid #e6e9ef;border-radius:12px;padding:14px 16px;display:flex;flex-direction:column;gap:4px;border-top:3px solid #cbd2dd}
.sg-kpi--ok{border-top-color:#16A34A}.sg-kpi--warn{border-top-color:#D97706}
.sg-kpi__v{font-size:1.5rem;font-weight:800;color:#0C1C3E;line-height:1}
.sg-kpi__l{font-size:.76rem;color:#6B7280}
.sg-filters{display:flex;gap:10px;margin-bottom:12px;flex-wrap:wrap;align-items:center}
.sg-filters .form-control{max-width:300px}
.sg-count{color:#6B7280;font-size:.8rem;margin-inline-start:auto}
.sg-dept__tag{display:inline-block;background:#EEF4FB;color:#1E3A5F;font-size:.7rem;font-weight:600;padding:2px 7px;border-radius:20px;margin:1px 0 1px 3px;white-space:nowrap}
.sg-dept__more{display:inline-block;background:#eef1f5;color:#6B7280;font-size:.68rem;padding:2px 6px;border-radius:20px;cursor:help}
.sg-table-wrap{background:#fff;border:1px solid #e6e9ef;border-radius:12px;overflow:auto;max-height:64vh}
.sg-table{margin:0;width:100%;font-size:.84rem}
.sg-table thead th{position:sticky;top:0;background:#0C1C3E;color:#fff;font-weight:700;z-index:1;padding:9px 10px;white-space:nowrap}
.sg-table td{padding:8px 10px;border-bottom:1px solid #eef1f5;vertical-align:top}
.sg-path{font-size:.76rem;color:#334155;direction:ltr;display:inline-block}
.sg-desc{color:#4b5563;line-height:1.6;max-width:520px}
.sg-muted{color:#9aa3af}
.sg-len{color:#6B7280;font-variant-numeric:tabular-nums}
.sg-tag{font-size:.7rem;font-weight:700;padding:2px 8px;border-radius:20px;white-space:nowrap}
.sg-tag--authored{background:#E8F6ED;color:#16A34A}
.sg-tag--composed{background:#EEF4FB;color:#1E3A5F}
.sg-tag--derived{background:#FFF4E0;color:#B45309}
.sg-edit{border:1px solid #dbe1ea;background:#f7f9fc;color:#0C1C3E;width:30px;height:30px;border-radius:8px;cursor:pointer}
.sg-edit:hover{background:#0C1C3E;color:#fff;border-color:#0C1C3E}
.sg-modal{position:fixed;inset:0;background:rgba(12,28,62,.45);display:flex;align-items:center;justify-content:center;z-index:2000;padding:20px}
.sg-modal[hidden]{display:none}
.sg-modal__box{background:#fff;border-radius:14px;width:min(980px,100%);max-height:94vh;display:flex;flex-direction:column;overflow:hidden;transition:width .2s}
.sg-modal__box.is-wide{width:min(1500px,100%);max-height:97vh}
.sg-modal__head{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:#0C1C3E;color:#fff}
.sg-modal__head h3{margin:0;font-size:1rem;font-weight:800}
.sg-modal__x{background:rgba(255,255,255,.15);border:none;color:#fff;width:28px;height:28px;border-radius:7px;cursor:pointer}
.sg-modal__body{padding:16px 18px;overflow:auto}
.sg-modal__body label{display:block;font-size:.78rem;font-weight:700;color:#0C1C3E;margin:12px 0 5px}
.sg-modal__path{margin:0;font-size:.78rem;color:#6B7280;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.sg-modal__dept{background:#EEF4FB;color:#1E3A5F;font-size:.7rem;font-weight:600;padding:2px 8px;border-radius:20px}

/* ── المحرِّر: يشغل عرضَ المديول كاملًا (قرار المالك) ── */
.sg-editor-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:14px 0 6px}
.sg-tools{display:flex;align-items:center;gap:4px}
.sg-tool{border:1px solid #dbe1ea;background:#f7f9fc;color:#0C1C3E;min-width:30px;height:30px;padding:0 8px;border-radius:8px;cursor:pointer;font-size:.8rem;font-weight:700;line-height:1}
.sg-tool:hover{background:#0C1C3E;color:#fff;border-color:#0C1C3E}
.sg-tools__sep{width:1px;height:20px;background:#e0e5ec;margin:0 4px}

.sg-textarea{
  display:block;width:100%;box-sizing:border-box;
  min-height:300px;resize:vertical;
  border:1px solid #dbe1ea;border-radius:10px;
  padding:14px 16px;
  font-family:"IBM Plex Sans Arabic","Tajawal","Cairo",Tahoma,sans-serif;
  font-size:.95rem;line-height:2;color:#1f2937;
  background:#fcfdff;
  transition:border-color .15s,box-shadow .15s;
}
.sg-textarea:focus{outline:none;border-color:#0C1C3E;box-shadow:0 0 0 3px rgba(12,28,62,.10);background:#fff}
.sg-modal__box.is-wide .sg-textarea{min-height:46vh}

.sg-meter{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:7px;font-size:.74rem;color:#6B7280}
.sg-meter__counts{font-variant-numeric:tabular-nums;font-weight:600;color:#0C1C3E}
.sg-meter__warn{color:#B45309;font-weight:600}
.sg-meter__hint{margin-inline-start:auto}
.sg-meter kbd{background:#eef1f5;border:1px solid #dbe1ea;border-radius:4px;padding:0 4px;font-size:.7rem}

.sg-preview{margin-top:16px;border-top:1px dashed #e6e9ef;padding-top:12px}
.sg-preview__label{font-size:.72rem;font-weight:700;color:#8A8371;margin-bottom:8px}
.sg-modal__foot{padding:12px 18px;border-top:1px solid #eef1f5;display:flex;gap:8px;align-items:center}
.sg-dirty{margin-inline-start:auto;color:#B45309;font-size:.76rem;font-weight:600}
.sg-dirty i{font-size:.5rem;vertical-align:middle}
</style>

<script>
(function () {
  var modal = document.getElementById('sgModal');
  var f = {
    id: document.getElementById('sgId'), path: document.getElementById('sgPath'),
    title: document.getElementById('sgTitle'), desc: document.getElementById('sgDesc'),
    pvT: document.getElementById('sgPvTitle'), pvB: document.getElementById('sgPvBody')
  };

  function esc(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  /* المعاينةُ تبني الفقراتِ بالقاعدة نفسِها التي يستعملها المكوِّن الحيّ:
     سطرٌ فارغٌ يفصل فقرة — فما يراه المحرِّرُ هو ما سيراه المستخدمُ حرفيًّا. */
  function paint() {
    f.pvT.textContent = 'عن: ' + (f.title.value.trim() || '—');
    var html = '';
    String(f.desc.value).split(/\n{2,}/).forEach(function (p) {
      p = p.trim(); if (p) { html += '<p class="ems-about__purpose">' + esc(p) + '</p>'; }
    });
    f.pvB.innerHTML = html || '<p class="ems-about__purpose" style="opacity:.5">— لا نصَّ بعد —</p>';
  }

  var box = modal.querySelector('.sg-modal__box');
  var orig = { title: '', desc: '' };
  var MIN = 20;

  /* عدّادٌ حيٌّ: حروفٌ وكلماتٌ وفقرات — والحدُّ الأدنى الذي يرفضه الخادمُ
     يُعلَن **قبل** الحفظ لا بعده، فلا يُردّ المحرِّرُ بخطأٍ كان يمكن تفاديه. */
  function meter() {
    var v = f.desc.value;
    var chars = v.length;
    var words = v.trim() ? v.trim().split(/\s+/).length : 0;
    var paras = v.split(/\n{2,}/).filter(function (p) { return p.trim() !== ''; }).length;
    document.getElementById('sgCounts').textContent =
      chars + ' حرفًا · ' + words + ' كلمة · ' + paras + ' فقرة';
    document.getElementById('sgWarn').hidden = (chars >= MIN);
    document.getElementById('sgSave').disabled = (chars < MIN);
    var dirty = (f.desc.value !== orig.desc || f.title.value !== orig.title);
    document.getElementById('sgDirty').hidden = !dirty;
    return dirty;
  }
  function refresh() { paint(); meter(); }

  document.querySelectorAll('.sg-edit').forEach(function (b) {
    b.addEventListener('click', function () {
      f.id.value = b.dataset.id;
      f.path.textContent = b.dataset.path;
      document.getElementById('sgDept2').textContent = b.dataset.dept || '';
      document.getElementById('sgDept2').hidden = !b.dataset.dept;
      f.title.value = b.dataset.title || '';
      f.desc.value = b.dataset.desc || '';
      orig.title = f.title.value; orig.desc = f.desc.value;
      refresh();
      modal.hidden = false;
      f.desc.focus();
      f.desc.setSelectionRange(f.desc.value.length, f.desc.value.length);
    });
  });

  f.desc.addEventListener('input', refresh);
  f.title.addEventListener('input', refresh);

  /* أدواتُ التحرير — تعمل على التحديد وتُبقي مؤشرَ الكتابة في موضعه */
  function surround(before, after) {
    var s = f.desc.selectionStart, e2 = f.desc.selectionEnd, v = f.desc.value;
    var sel = v.slice(s, e2);
    f.desc.value = v.slice(0, s) + before + sel + after + v.slice(e2);
    f.desc.focus();
    f.desc.setSelectionRange(s + before.length, s + before.length + sel.length);
    refresh();
  }
  document.querySelectorAll('.sg-tool[data-act]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (b.dataset.act === 'para')  { surround('\n\n', ''); }
      if (b.dataset.act === 'quote') { surround('«', '»'); }
    });
  });

  document.getElementById('sgRevert').addEventListener('click', function () {
    if (!meter()) { return; }
    if (!confirm('تراجعٌ عن كل التعديلات وإرجاعُ النصِّ كما كان؟')) { return; }
    f.title.value = orig.title; f.desc.value = orig.desc; refresh(); f.desc.focus();
  });

  document.getElementById('sgWide').addEventListener('click', function () {
    box.classList.toggle('is-wide');
  });

  /* الإغلاقُ لا يبتلع عملًا: تعديلٌ غيرُ محفوظٍ يُسأل عنه صراحةً */
  function close(force) {
    if (!force && meter() && !confirm('لديك تعديلاتٌ غير محفوظة — إغلاقٌ بلا حفظ؟')) { return; }
    modal.hidden = true;
    box.classList.remove('is-wide');
  }
  document.getElementById('sgClose').addEventListener('click', function () { close(false); });
  document.getElementById('sgCancel').addEventListener('click', function () { close(false); });
  modal.addEventListener('click', function (e) { if (e.target === modal) { close(false); } });
  document.addEventListener('keydown', function (e) {
    if (modal.hidden) { return; }
    if (e.key === 'Escape') { close(false); }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
      e.preventDefault();
      if (f.desc.value.length >= MIN) { modal.querySelector('form').submit(); }
    }
  });
  // الحفظُ يُطفئ حارسَ «غير محفوظ» كي لا يعترض الإرسالَ نفسَه
  modal.querySelector('form').addEventListener('submit', function () { orig.desc = f.desc.value; orig.title = f.title.value; });

  /* ── الترشيح: بحثٌ نصيٌّ + إدارةٌ رئيسية + مصدر ──
     الإدارةُ تُطابَق بالانتماء لا بالتساوي: للشاشة قد تكون إداراتٌ عدة،
     والفرعيةُ محسوبةٌ مع أبيها أصلًا وقتَ البناء في الخادم. */
  var q = document.getElementById('sgSearch'),
      s = document.getElementById('sgSource'),
      d = document.getElementById('sgDept'),
      cnt = document.getElementById('sgCount'),
      allRows = document.querySelectorAll('#sgTable tbody tr');

  function filter() {
    var t = q.value.trim().toLowerCase(), src = s.value, dep = d.value, shown = 0;
    allRows.forEach(function (tr) {
      var okT = !t || tr.dataset.hay.indexOf(t) > -1;
      var okS = !src || tr.dataset.source === src;
      var okD = true;
      if (dep === 'none')      { okD = tr.dataset.dept === 'none'; }
      else if (dep !== '')     { okD = (',' + tr.dataset.dept + ',').indexOf(',' + dep + ',') > -1; }
      var ok = okT && okS && okD;
      tr.style.display = ok ? '' : 'none';
      if (ok) { shown++; }
    });
    cnt.textContent = shown + ' من ' + allRows.length + ' شاشة';
  }
  q.addEventListener('input', filter);
  s.addEventListener('change', filter);
  d.addEventListener('change', filter);
  filter();
})();
</script>

<?php require_once __DIR__ . '/../includes/layout_foot.php'; ?>
