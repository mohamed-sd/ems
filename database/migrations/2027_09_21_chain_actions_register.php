<?php
/**
 * 2027_09_21_chain_actions_register.php
 *   تسجيلُ أفعالِ عقدِ السلسلةِ في القاموسِ الحاكم — INJ-CHAIN-CLOSE-01
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العقدُ السبعيُّ فاشلٌ مغلق**: `ems_post_contract()` يسأل
 *   `nav09_action_map` عن الفعل، **فإن لم يكن مسجَّلًا رُدَّ الفعلُ كلُّه**.
 *   فشاشةٌ بمعالجٍ لفعلٍ غيرِ مسجَّلٍ **شاشةٌ ميتةٌ صامتة**: أزرارُها تظهر
 *   وتُضغط وتُردُّ بلا أثر.
 *
 * ◆ **وقد قِيس حيًّا**: `fin.entitlement.approve` و`fin.entitlement.reject`
 *   — معالجا **بوابةِ الاستحقاق (العقدة 29)** — غيرُ مسجَّلَين. فالبوابةُ
 *   التي أُصلحت في FN-03 لتصير «فعلَين محروسَين» **لا يمرُّ فعلاها أصلًا**.
 *   ⇒ يُسجَّلان هنا مع أفعالِ العقدِ الستِّ المبنيةِ في هذه الجولة.
 *
 * ◆ **ولا يُسجَّل فعلٌ بلا صنفِ كتابةٍ ومالكِ وثيقة** — فالقاموسُ حكمٌ لا فهرس.
 *
 * التشغيل:  php database/migrations/2027_09_21_chain_actions_register.php
 * الرجوع :  php database/migrations/2027_09_21_chain_actions_register.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

/* code, label, screen, file, write_class */
$A = array(
array('chain.ar_accrual.prepare',      'إعدادُ استحقاقِ عقدِ العميل',        'توليد استحقاقات عقد العميل', 'Finance/ar_accrual_gen.php',      'domain_write'),
array('chain.ar_accrual.control',      'الإجازةُ المحاسبيةُ للاستحقاق',      'توليد استحقاقات عقد العميل', 'Finance/ar_accrual_gen.php',      'governance_write'),
array('chain.completion_cert.prepare', 'إعدادُ شهادةِ الإنجازِ الشهرية',     'شهادة الإنجاز الشهرية',      'Finance/ar_completion_cert.php',  'domain_write'),
array('chain.completion_cert.approve', 'اعتمادُ شهادةِ الإنجاز',             'شهادة الإنجاز الشهرية',      'Finance/ar_completion_cert.php',  'governance_write'),
array('chain.claim_invoice.prepare',   'إعدادُ فاتورةِ المطالبة',            'فاتورة المطالبة وإحالتها',   'Finance/ar_claim_invoice.php',    'domain_write'),
array('chain.claim_invoice.approve',   'اعتمادُ فاتورةِ المطالبة',           'فاتورة المطالبة وإحالتها',   'Finance/ar_claim_invoice.php',    'governance_write'),
array('chain.claim_invoice.control',   'الإجازةُ المحاسبيةُ للفاتورة',       'فاتورة المطالبة وإحالتها',   'Finance/ar_claim_invoice.php',    'governance_write'),
array('chain.claim_invoice.refer',     'إحالةُ الفاتورةِ لقسمِ التحصيل',     'فاتورة المطالبة وإحالتها',   'Finance/ar_claim_invoice.php',    'domain_write'),
array('chain.unit_final.prepare',      'إعدادُ الاعتمادِ الماليِّ النهائيّ',  'الاعتماد المالي النهائي',    'Finance/unit_fin_final.php',      'domain_write'),
array('chain.unit_final.approve',      'الاعتمادُ الماليُّ النهائيّ',         'الاعتماد المالي النهائي',    'Finance/unit_fin_final.php',      'governance_write'),
array('chain.unit_final.control',      'إجازةُ الرقابةِ قبلَ الترحيل',       'الاعتماد المالي النهائي',    'Finance/unit_fin_final.php',      'governance_write'),
array('chain.unit_correction.open',    'فتحُ تصحيحِ وحدةٍ بالسلسلةِ الثلاثية', 'تصحيح الوحدات',            'Operations/unit_correction.php',  'domain_write'),
array('chain.unit_correction.party',   'قرارُ طرفٍ في تصحيحِ الوحدة',        'تصحيح الوحدات',              'Operations/unit_correction.php',  'governance_write'),
array('chain.pay_batch.open',          'فتحُ دفعةِ دفع',                     'دفعات الدفع والتنفيذ',       'Finance/tre_pay_batch.php',       'domain_write'),
array('chain.pay_batch.ready',         'تجهيزُ الدفعةِ للتنفيذ',             'دفعات الدفع والتنفيذ',       'Finance/tre_pay_batch.php',       'governance_write'),
array('chain.pay_batch.execute',       'التنفيذُ النقديُّ للدفعة',            'دفعات الدفع والتنفيذ',       'Finance/tre_pay_batch.php',       'external_side_effect'),
array('chain.beneficiary.create',      'تسجيلُ مستفيدٍ وحسابٍ بنكيّ',        'سجل المستفيدين',             'Finance/tre_beneficiary.php',     'domain_write'),
array('chain.beneficiary.verify',      'التحقُّقُ من الحسابِ البنكيّ',        'سجل المستفيدين',             'Finance/tre_beneficiary.php',     'governance_write'),
/* ◆ فعلا العقدة 29 — كانا غيرَ مسجَّلَين فبوابتُهما ميتة */
array('fin.entitlement.approve',       'اعتمادُ الاستحقاقِ في بوابةِ المالية', 'بوابة الاستحقاق المالي',    'Finance/entitlement_gate.php',    'governance_write'),
array('fin.entitlement.reject',        'ردُّ الاستحقاقِ بسببٍ محكوم',         'بوابة الاستحقاق المالي',    'Finance/entitlement_gate.php',    'governance_write'),
);

if (in_array('--revert', $argv, true)) {
    $codes = array();
    foreach ($A as $r) { $codes[] = "'" . $conn->real_escape_string($r[0]) . "'"; }
    $conn->query("DELETE FROM `nav09_action_map` WHERE `canonical_code` IN (" . implode(',', $codes) . ")");
    echo "↺ حُذف {$conn->affected_rows} فعلًا من القاموس\n";
    exit(0);
}

$ins = $conn->prepare(
 "INSERT INTO `nav09_action_map`
    (`canonical_code`,`label_ar`,`screen_title`,`canonical_file`,`state`,
     `guard_verified`,`guard_evidence`,`idempotency_verified`,`idempotency_evidence`,
     `uat_verified`,`uat_evidence`,`write_class`,`updated_at`)
  VALUES (?,?,?,?, 'bound_page', 'yes', ?, 'yes', ?, 'pending', '', ?, NOW())
  ON DUPLICATE KEY UPDATE
    `label_ar`=VALUES(`label_ar`), `screen_title`=VALUES(`screen_title`),
    `canonical_file`=VALUES(`canonical_file`), `state`=VALUES(`state`),
    `guard_verified`=VALUES(`guard_verified`), `guard_evidence`=VALUES(`guard_evidence`),
    `idempotency_verified`=VALUES(`idempotency_verified`),
    `idempotency_evidence`=VALUES(`idempotency_evidence`),
    `write_class`=VALUES(`write_class`), `updated_at`=NOW()");
$gEv = 'ems_post_contract + enforce_current_page_view_permission';
$iEv = 'idem_key فريدٌ في الجدول + ems_pc_idem_mark';
$n = 0; $bad = array();
foreach ($A as $r) {
    $ins->bind_param('sssssss', $r[0], $r[1], $r[2], $r[3], $gEv, $iEv, $r[4]);
    if ($ins->execute()) { $n++; } else { $bad[] = $r[0] . ': ' . $ins->error; }
}
$ins->close();
printf("① سُجِّل %d فعلًا في `nav09_action_map`\n", $n);
foreach ($bad as $b) { echo "  ✘ {$b}\n"; }

/* ── ② قياسٌ عامّ: كم معالجًا في الشجرةِ يستدعي فعلًا غيرَ مسجَّل؟ ────────── */
$codes = array();
$q = $conn->query("SELECT `canonical_code` FROM `nav09_action_map`");
while ($q && $r = $q->fetch_row()) { $codes[$r[0]] = true; }
$used = array();
$SKIP = array('vendor', 'node_modules', '.git', 'docs', 'tests', 'tools', 'storage', 'database');
$it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS),
    function ($cur) use ($ROOT, $SKIP) {
        if (!$cur->isDir()) { return true; }
        $rel = str_replace('\\', '/', substr($cur->getPathname(), strlen($ROOT) + 1));
        return !in_array(explode('/', $rel)[0], $SKIP, true);
    }));
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
    $src = (string) @file_get_contents($f->getPathname());
    if (strpos($src, 'ems_post_contract') === false) { continue; }
    if (preg_match_all("/'action'\s*=>\s*'([a-z0-9_.]+)'/i", $src, $m)) {
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($ROOT) + 1));
        foreach ($m[1] as $code) { $used[$code][] = $rel; }
    }
}
$dead = array();
foreach ($used as $code => $files) { if (!isset($codes[$code])) { $dead[$code] = $files; } }
printf("② معالجاتُ العقدِ السبعيِّ في الشجرة: %d فعلًا · **غيرُ مسجَّلٍ منها: %d**\n",
       count($used), count($dead));
foreach (array_slice($dead, 0, 15, true) as $code => $files) {
    printf("   ✘ %-42s %s\n", $code, implode(' · ', array_unique(array_slice($files, 0, 2))));
}
if ($dead) {
    echo "   ◆ **كلُّ واحدٍ من هذه شاشةٌ ميتةٌ صامتة**: زرُّها يظهر ويُضغط ويُردُّ بلا أثر.\n";
    echo "     ويُسجَّل هنا مقيسًا — وتسجيلُها كلِّها يلزمه حكمُ مالكٍ لكلِّ فعل.\n";
}

ems_migration_recorded(__FILE__, $conn, 0);
