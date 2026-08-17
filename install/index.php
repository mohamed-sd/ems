<?php
/**
 * install/index.php — مدخلُ التثبيت عبر المتصفّح (EMS Installer · Web)
 * ═══════════════════════════════════════════════════════════════════════════
 * موجودٌ لأن كثيرًا من الاستضافات المشتركة لا تمنح SSH، ومُثبِّتٌ لا يعمل على
 * خادمك ليس مُثبِّتًا. المنطقُ كلُّه في App\Install\Installer — هذا الملفّ نموذجٌ
 * وعرضٌ فقط، ويشترك مع مدخل CLI في الشيفرة نفسها حرفًا بحرف.
 *
 * ── الحرّاسُ الثلاثة ────────────────────────────────────────────────────────
 * ① `.installed` في الجذر موجود      → رفضٌ قاطع (النظام مثبَّتٌ سلفًا).
 * ② القاعدةُ غيرُ فارغة              → رفضٌ داخل الفحص القبلي.
 * ③ ملفُّ الرمز `install/install.token` غيرُ موجودٍ أو محتواه لا يطابق المُدخَل
 *    → رفضٌ قاطع.
 *
 * لماذا ملفُّ رمزٍ لا مفتاحٌ في `.env`: التثبيتُ يسبق وجودَ `.env`، فالشرطُ
 * الوحيدُ القابلُ للتحقّق قبله هو إثباتُ الوصول إلى نظام الملفّات — وهو بالضبط
 * الصلاحيةُ التي يجب أن يملكها من يحقُّ له التثبيت. أنشئ الملفَّ بمحتوًى عشوائي:
 *   php -r "echo bin2hex(random_bytes(16));" > install/install.token
 *
 * وبعد نجاح التثبيت: احذف مجلَّد install/ بالكامل.
 */

error_reporting(E_ALL & ~E_DEPRECATED);
$ROOT = dirname(__DIR__);

require_once $ROOT . '/app/Install/SchemaDumper.php';
require_once $ROOT . '/app/Install/Installer.php';

use App\Install\Installer;

// ═══ الحارس ① — النظامُ مثبَّتٌ سلفًا ═══
if (is_file($ROOT . '/.installed')) {
    http_response_code(403);
    render_gate(
        'النظامُ مثبَّتٌ سلفًا',
        'وُجد الملفُّ <code>.installed</code> في جذر المشروع، فالمُثبِّتُ يرفض العمل — تشغيلُه فوق نظامٍ قائمٍ إتلاف.',
        'إن كنت تريد إعادةَ التثبيت عمدًا: أسقِط قاعدةَ البيانات، واحذف <code>.installed</code>، ثمّ عُد.'
    );
    exit;
}

// ═══ الحارس ③ — ملفُّ الرمز ═══
$tokenPath = __DIR__ . '/install.token';
if (!is_file($tokenPath)) {
    http_response_code(403);
    render_gate(
        'ملفُّ الرمز مفقود',
        'المُثبِّتُ عبر المتصفّح لا يعمل قبل إثبات الوصول إلى نظام الملفّات على الخادم.',
        'أنشئ الملفَّ <code>install/install.token</code> بمحتوًى عشوائي، ثمّ أدخل المحتوى نفسه هنا:<br>'
        . '<code dir="ltr">php -r "echo bin2hex(random_bytes(16));" &gt; install/install.token</code>'
    );
    exit;
}
$expectedToken = trim((string) file_get_contents($tokenPath));
if ($expectedToken === '') {
    http_response_code(403);
    render_gate('ملفُّ الرمز فارغ', 'الملفُّ <code>install/install.token</code> موجودٌ لكنه فارغ.', 'اكتب فيه محتوًى عشوائيًّا ثمّ أعِد المحاولة.');
    exit;
}

// ═══ المدخلات ═══
$posted = ($_SERVER['REQUEST_METHOD'] === 'POST');
$fields = array(
    'install_token'    => '',
    'db_host'          => 'localhost',
    'db_name'          => '',
    'db_user'          => 'root',
    'db_pass'          => '',
    'db_create'        => '',
    'company_name'     => '',
    'company_email'    => '',
    'company_currency' => 'SDG',
    'company_timezone' => 'Africa/Khartoum',
    'admin_name'       => '',
    'admin_username'   => '',
    'admin_email'      => '',
    'admin_phone'      => '',
    'admin_password'   => '',
);
$in = array();
foreach ($fields as $k => $default) {
    $in[$k] = $posted && isset($_POST[$k]) ? (string) $_POST[$k] : $default;
}

$tokenOk = true;
$checks = array();
$result = null;

if ($posted) {
    // مقارنةٌ ثابتةُ الزمن — الرمزُ سرٌّ.
    $tokenOk = hash_equals($expectedToken, trim($in['install_token']));

    if ($tokenOk) {
        $cfg = $in;
        unset($cfg['install_token']);
        $cfg['db_create'] = ($in['db_create'] !== '');
        $cfg['write_env'] = true;

        $installer = new Installer($cfg, $ROOT);
        $action = isset($_POST['action']) ? $_POST['action'] : 'check';

        if ($action === 'install') {
            $result = $installer->install();
            $checks = $result['ok'] ? array() : $installer->preflight();
        } else {
            $checks = $installer->preflight();
        }
    }
}

// ═══ العرض ═══
function h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function render_gate($title, $body, $hint)
{
    ?><!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo h($title); ?> — مُثبِّت EMS</title>
<?php render_style(); ?>
</head><body><div class="wrap"><div class="card gate">
<h1><?php echo h($title); ?></h1>
<p><?php echo $body; ?></p>
<p class="hint"><?php echo $hint; ?></p>
</div></div></body></html><?php
}

function render_style()
{
    ?><style>
*{box-sizing:border-box}
body{margin:0;background:#0f1115;color:#e8eaed;font:15px/1.7 "Segoe UI",Tahoma,sans-serif}
.wrap{max-width:940px;margin:0 auto;padding:32px 20px 64px}
.card{background:#171a21;border:1px solid #262b36;border-radius:14px;padding:26px 28px;margin-bottom:20px}
.gate{border-color:#5a3030;background:#1d1618}
h1{margin:0 0 6px;font-size:22px;color:#f5c451}
h2{margin:0 0 14px;font-size:17px;color:#f5c451;font-weight:600}
p{margin:8px 0}
.hint{color:#9aa3b2;font-size:14px}
code{background:#0d0f13;border:1px solid #2a3040;border-radius:5px;padding:2px 6px;font-size:13px;color:#7fd1a8}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px}
label{display:block;font-size:13px;color:#9aa3b2;margin-bottom:5px}
input[type=text],input[type=password],input[type=email]{width:100%;padding:9px 11px;border-radius:8px;
  border:1px solid #2a3040;background:#0d0f13;color:#e8eaed;font:14px inherit}
input:focus{outline:none;border-color:#f5c451}
.row{margin-bottom:14px}
.chk{display:flex;align-items:center;gap:8px;color:#c3c9d4;font-size:14px}
button{background:#f5c451;color:#14161b;border:0;border-radius:9px;padding:11px 26px;
  font:600 15px inherit;cursor:pointer;margin-left:10px}
button.ghost{background:transparent;color:#f5c451;border:1px solid #4a4433}
table{width:100%;border-collapse:collapse;font-size:14px}
td{padding:7px 6px;vertical-align:top;}
td.st{width:26px;text-align:center;}
td.lb{width:230px;}
.ok{color:#5ec27f}.no{color:#e5706b}
.banner{border-radius:10px;padding:13px 16px;margin-bottom:18px;font-size:14px}
.b-ok{background:#12291b;border:1px solid #2c5c3c;color:#8fe0aa}
.b-no{background:#2a1618;border:1px solid #5c2f31;color:#f0a6a2}
dl{display:grid;grid-template-columns:150px 1fr;gap:6px 14px;margin:14px 0 0;font-size:14px}
dt{color:#9aa3b2}dd{margin:0;color:#e8eaed}
</style><?php
}

// صفحةُ النجاح
if ($result !== null && $result['ok']) {
    $s = $result['summary'];
    ?><!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>اكتمل التثبيت — EMS</title><?php render_style(); ?></head>
<body><div class="wrap">
<div class="card">
  <h1>✔ اكتمل التثبيت</h1>
  <div class="banner b-no" style="margin-top:14px">
    <b>افعل هذا الآن:</b> احذف مجلَّد <code>install/</code> بالكامل من الخادم.
    بقاؤه يترك مسارَ تثبيتٍ مكشوفًا وإن كان محروسًا.
  </div>
  <dl>
    <dt>القاعدة</dt><dd><?php echo h($s['database']); ?> — <?php echo (int) $s['objects']; ?> كائنًا</dd>
    <dt>الشركة</dt><dd>id=<?php echo (int) $s['company_id']; ?></dd>
    <dt>الموظّف</dt><dd>id=<?php echo (int) $s['employee_id']; ?></dd>
    <dt>الحساب</dt><dd><?php echo h($s['username']); ?> (id=<?php echo (int) $s['user_id']; ?>)</dd>
  </dl>
</div>
<div class="card">
  <h2>ما نُفِّذ</h2>
  <table><?php foreach ($result['steps'] as $st) { ?>
    <tr><td class="st ok">✔</td><td class="lb"><?php echo h($st['label']); ?></td>
        <td class="dt"><?php echo h($st['detail']); ?></td></tr>
  <?php } ?></table>
  <p class="hint" style="margin-top:16px">
    راجع <code>.env</code> ثمّ ادخل من <code>login.php</code>.
    تغييراتُ المخطَّط لاحقًا عبر <code>php database/migrate.php up</code>.
  </p>
</div>
</div></body></html><?php
    exit;
}

// صفحةُ النموذج
$passed = !empty($checks) && Installer::passed($checks);
?><!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>مُثبِّت EMS</title><?php render_style(); ?></head>
<body><div class="wrap">

<div class="card">
  <h1>مُثبِّت EMS</h1>
  <p class="hint">يُنصَب النظامُ على قاعدةٍ <b>فارغة</b>: المخطَّطُ الكامل ثمّ البذرةُ المرجعية
     ثمّ شركةٌ وموظّفٌ وحسابُ دخولٍ مترابطة. لا يعمل فوق قاعدةٍ عامرة.</p>
</div>

<?php if ($posted && !$tokenOk) { ?>
  <div class="banner b-no">رمزُ التثبيت غير مطابق لمحتوى <code>install/install.token</code>.</div>
<?php } ?>

<?php if ($result !== null && !$result['ok']) { ?>
  <div class="banner b-no"><b>فشل التثبيت:</b> <?php echo h($result['error']); ?><br>
  القاعدةُ قد تكون نصفَ مبنيّة — أسقِطها وأعِد التثبيت من قاعدةٍ فارغة.</div>
<?php } ?>

<?php if (!empty($checks)) { ?>
  <div class="card">
    <h2>الفحصُ القبلي</h2>
    <div class="banner <?php echo $passed ? 'b-ok' : 'b-no'; ?>">
      <?php echo $passed
        ? 'اجتاز الفحصُ بالكامل — يمكنك التثبيت الآن.'
        : 'لم يجتز — عالج ما عليه ✘ ثمّ أعِد الفحص.'; ?>
    </div>
    <table><?php foreach ($checks as $c) { ?>
      <tr><td class="st <?php echo $c['ok'] ? 'ok' : 'no'; ?>"><?php echo $c['ok'] ? '✔' : '✘'; ?></td>
          <td class="lb"><?php echo h($c['label']); ?></td>
          <td class="dt"><?php echo h($c['detail']); ?></td></tr>
    <?php } ?></table>
  </div>
<?php } ?>

<form method="post" autocomplete="off">
<div class="card">
  <h2>رمزُ التثبيت</h2>
  <div class="row">
    <label for="emsf_724_15ce6">محتوى <code>install/install.token</code></label>
    <input type="password" name="install_token" value="<?php echo h($in['install_token']); ?>" required id="emsf_724_15ce6">
  </div>
</div>

<div class="card">
  <h2>قاعدة البيانات</h2>
  <div class="grid">
    <div class="row"><label for="emsf_725_5e32f">المضيف</label><input type="text" name="db_host" value="<?php echo h($in['db_host']); ?>" required id="emsf_725_5e32f"></div>
    <div class="row"><label for="emsf_726_2751d">اسم القاعدة</label><input type="text" name="db_name" value="<?php echo h($in['db_name']); ?>" required id="emsf_726_2751d"></div>
    <div class="row"><label for="emsf_727_72e47">المستخدم</label><input type="text" name="db_user" value="<?php echo h($in['db_user']); ?>" required id="emsf_727_72e47"></div>
    <div class="row"><label for="emsf_728_73c78">كلمة المرور</label><input type="password" name="db_pass" value="<?php echo h($in['db_pass']); ?>" id="emsf_728_73c78"></div>
  </div>
  <label class="chk"><input type="checkbox" name="db_create" value="1" <?php echo $in['db_create'] !== '' ? 'checked' : ''; ?>>
    أنشئ القاعدة إن لم تكن موجودة</label>
</div>

<div class="card">
  <h2>الشركة</h2>
  <div class="grid">
    <div class="row"><label>الاسم</label><input type="text" name="company_name" value="<?php echo h($in['company_name']); ?>" required></div>
    <div class="row"><label for="emsf_729_0857a">البريد</label><input type="email" name="company_email" value="<?php echo h($in['company_email']); ?>" required id="emsf_729_0857a"></div>
    <div class="row"><label for="emsf_730_a3355">العملة</label><input type="text" name="company_currency" value="<?php echo h($in['company_currency']); ?>" id="emsf_730_a3355"></div>
    <div class="row"><label for="emsf_731_f7978">المنطقة الزمنية</label><input type="text" name="company_timezone" value="<?php echo h($in['company_timezone']); ?>" id="emsf_731_f7978"></div>
  </div>
</div>

<div class="card">
  <h2>حسابُ الدخول الأوّل</h2>
  <p class="hint">يُنشأ موظّفٌ مربوطٌ بالحساب — النظامُ يرفض أيَّ حسابٍ بلا موظّفٍ مُسنَد.</p>
  <div class="grid">
    <div class="row"><label for="emsf_732_bc9b8">الاسم الكامل</label><input type="text" name="admin_name" value="<?php echo h($in['admin_name']); ?>" required id="emsf_732_bc9b8"></div>
    <div class="row"><label for="emsf_733_27206">اسم الدخول</label><input type="text" name="admin_username" value="<?php echo h($in['admin_username']); ?>" required id="emsf_733_27206"></div>
    <div class="row"><label for="emsf_734_57491">البريد</label><input type="email" name="admin_email" value="<?php echo h($in['admin_email']); ?>" id="emsf_734_57491"></div>
    <div class="row"><label for="emsf_735_e9c3e">الهاتف</label><input type="text" name="admin_phone" value="<?php echo h($in['admin_phone']); ?>" id="emsf_735_e9c3e"></div>
    <div class="row"><label for="emsf_736_f787a">كلمة المرور (٨ محارف فأكثر)</label><input type="password" name="admin_password" value="<?php echo h($in['admin_password']); ?>" required id="emsf_736_f787a"></div>
  </div>
</div>

<div class="card">
  <button type="submit" name="action" value="check" class="ghost">افحص فقط</button>
  <?php if ($passed) { ?>
    <button type="submit" name="action" value="install">ثبِّت الآن</button>
  <?php } ?>
  <p class="hint" style="margin-top:12px">زرُّ التثبيت لا يظهر قبل اجتياز الفحص القبلي كاملًا.</p>
</div>
</form>

</div></body></html>
