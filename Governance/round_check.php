<?php
/**
 * Governance/round_check.php — فحص جاهزية الالتزام (شاشة)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الغرض: بوابة ما قبل الالتزام تعرض العرض بعيدا عن سببه — تقول «سقط الحزام»
 *   ولا تقول لماذا. هذه الشاشة تعرض مخرج tools/round_closeout.php: حكم كل
 *   حارس على حدة، ومعه الامر الذي يعالجه.
 *
 * ⛔ ولا تشغيل متزامن في طلب متصفح: الفحص يقارب دقيقتين و max_execution_time
 *   مئة وعشرون ثانية، فالطلب المتزامن ينتهي بمهلة لا بنتيجة. فالتشغيل في
 *   الخلفية، والشاشة تعرض اخر نتيجة محفوظة وزمنها.
 *
 * ⛔ وقراءة فقط: لا تكتب في قاعدة ولا تولد مخططا ولا تشغل تمرين استنساخ.
 *   والاصلاح يبقى على سطر الاوامر لان بعضه يكتب في القاعدة ويستغرق دقائق.
 * ═══════════════════════════════════════════════════════════════════════════
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../company/login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/date_format.php';

$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$MODULE_CODE = 'Governance/round_check.php';
$__pp = check_page_permissions($conn, $MODULE_CODE);
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا صلاحية لفحص جاهزية الالتزام', 'GOV-PERM-403',
        'اطلب المنحة من مدير الصلاحيات ان كانت ضمن عملك');
    exit();
}

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
$TOOL = $ROOT . '/tools/round_closeout.php';
$DIR  = $ROOT . '/storage/round_check';
$OUT  = $DIR . '/last.txt';
$LOCK = $DIR . '/running.lock';
if (!is_dir($DIR)) { @mkdir($DIR, 0777, true); }

/* ── لا اطلاق من المتصفح البتة ─────────────────────────────────────────
   ⛔ **وسبب المنع مقيس لا مظنون**: اطلاق عملية من داخل Apache على ويندوز
     يربك عملية الاب فتظهر `AH02965: Child: Unable to retrieve my generation
     from the parent` في سجل الخادم. وقعت فعلا 2026-09-07.
   ◆ فالشاشة **تعرض** اخر نتيجة والتشغيل من سطر الاوامر او بمهمة مجدولة. */
$msg = '';

$running = is_file($LOCK) && (time() - (int) @filemtime($LOCK) <= 600);
$raw     = is_file($OUT) ? (string) @file_get_contents($OUT) : '';
$when    = is_file($OUT) ? (int) @filemtime($OUT) : 0;
$ran     = (trim($raw) !== '');

/* ── تفكيك المخرج الى صفوف حكم ────────────────────────────────────────── */
$checks = array(); $todo = array();
foreach (explode("\n", $raw) as $line) {
    $l = trim($line);
    if ($l === '') { continue; }
    if (preg_match('~^[\x{2460}-\x{2469}]\s*(.+?):\s*(✔|✘)\s*(.*)$~u', $l, $m)) {
        $checks[] = array('name' => trim($m[1]), 'ok' => ($m[2] === '✔'), 'note' => trim($m[3]));
    } elseif (preg_match('~^\d+\.\s+(.+)$~u', $l, $m)) {
        $todo[] = trim($m[1]);
    } elseif (preg_match('~^⇒\s*(.+)$~u', $l, $m) && $todo) {
        $todo[count($todo) - 1] .= ': ' . trim($m[1]);
    }
}
$ready = ($ran && $checks && !$todo);

include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'فحص جاهزية الالتزام';
    $header_icon = 'fa fa-clipboard-check';
    $header_actions = array();
    $header_back = false;
    include '../includes/page_header.php';
    ?>
<div class="rc-page">

  <div class="rc-head">
    <div>
      <p class="rc-sub">يشغل حراس ما قبل الالتزام ويسمي الراسب منهم وسببه. قراءة فقط.</p>
    </div>
    <code class="rc-code rc-run">php tools/round_closeout.php</code>
  </div>

  <?php if ($running) { ?>
    <div class="rc-empty"><p>الفحص يعمل الان. اعد تحميل الصفحة بعد نحو دقيقتين.</p></div>
  <?php } ?>

<?php if (!$ran) { ?>
  <div class="rc-empty">
    <p>لا نتيجة محفوظة بعد. شغل الامر اعلاه على سطر الاوامر ثم اعد التحميل.</p>
  </div>
<?php } else { ?>

  <div class="rc-verdict <?= $ready ? 'rc-ok' : 'rc-bad' ?>">
    <strong><?= $ready ? 'الشجرة جاهزة للالتزام' : 'الالتزام سيرد' ?></strong>
    <span class="rc-secs">اخر فحص: <?= htmlspecialchars(ems_fmt_date($when, 'datetime'), ENT_QUOTES, 'UTF-8') ?></span>
  </div>

  <table class="table table-sm rc-table">
    <thead><tr><th>الحارس</th><th>الحكم</th><th>الحال</th></tr></thead>
    <tbody>
    <?php foreach ($checks as $c) { ?>
      <tr>
        <td><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><span class="rc-badge <?= $c['ok'] ? 'rc-badge-ok' : 'rc-badge-bad' ?>"><?= $c['ok'] ? 'سليم' : 'راسب' ?></span></td>
        <td><?= htmlspecialchars($c['note'], ENT_QUOTES, 'UTF-8') ?></td>
      </tr>
    <?php } ?>
    <?php if (!$checks) { ?>
      <tr><td colspan="3">تعذر تفكيك مخرج الاداة. راجع النص الخام ادناه.</td></tr>
    <?php } ?>
    </tbody>
  </table>

  <?php if ($todo) { ?>
  <h3 class="rc-h3">ما يحتاج يدك</h3>
  <ol class="rc-todo">
    <?php foreach ($todo as $t) { ?>
      <li><?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?></li>
    <?php } ?>
  </ol>
  <?php } ?>

  <?php if (!$ready) { ?>
  <div class="rc-fix">
    <p class="rc-fix-lead">اكثر ما سبق يصلح بامر واحد على سطر الاوامر:</p>
    <code class="rc-code">php tools/round_closeout.php --apply</code>
    <p class="rc-fix-why">ولا يشغل من المتصفح: يكتب في القاعدة ويولد المخطط ويشغل تمرين تثبيت.</p>
  </div>
  <?php } ?>

  <details class="rc-raw">
    <summary>النص الخام لمخرج الاداة</summary>
    <pre class="rc-pre"><?= htmlspecialchars($raw, ENT_QUOTES, 'UTF-8') ?></pre>
  </details>

<?php } ?>
</div>
</div>
