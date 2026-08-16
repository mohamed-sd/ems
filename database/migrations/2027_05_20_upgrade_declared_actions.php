<?php
/**
 * 2027_05_20_upgrade_declared_actions.php
 * ═══════════════════════════════════════════════════════════════════════════
 * ترقيةُ «المعلَنِ غيرِ المبنيِّ» إلى مكافئِه الحيِّ + تسجيلُ أفعالِ الهرمِ الأربعة
 *
 * ◆ السبعةُ المعلَنةُ لها مكافئاتٌ حيّةٌ مقيسة (خريطةُ TS-01 الاسمية):
 *   cov.define ⇐ contract_coverage.php (حاويةُ النوع = contract_commitments)
 *   terms.set ⇐ price_terms.php · plan.commit ⇐ equipment_plan.php
 *   quota.allocate/consume ⇐ showcontractsuppliers/shares_coverage (الحصةُ حصصُ
 *   op_containers) · quota.post ⇐ container_consumption عبرَ شاشةِ الاستهلاك ·
 *   se.register ⇐ suppliercontractequipments عبرَ showcontractsuppliers.
 *   فتُرقَّى state إلى alias بربطِ live_code — كما رُقّي أمثالُها (31 alias قائمًا).
 * ◆ وأفعالُ الهرمِ الأربعةُ (cnt.annual.open · cnt.types.define · cnt.slots.open
 *   · cnt.slot.allocate) تُسجَّل bound إلى أسطحِ الهرمِ الحيّةِ — الحاوياتُ
 *   تتولد من توقيعِ العقدِ (ContractSignedEffects) وتُدار حصصُها من
 *   shares_coverage/equipment_quota، والتسليمُ من sup_handover.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

echo "══ ترقيةُ المعلَنِ وتسجيلُ الهرم ══\n\n";

/* ① السبعةُ المعلَنة ⇐ alias بمكافئِها الحي */
$UP = array(
    'cov.define'     => array('Contracts/contract_coverage.php',      'التغطيةُ حيّةٌ في contract_commitments وشاشتِها'),
    'terms.set'      => array('Contracts/price_terms.php',            'أحكامُ السعرِ حيّةٌ بشاشتِها'),
    'plan.commit'    => array('Suppliers/equipment_plan.php',         'خطةُ معداتِ الموردِ حيّةٌ بشاشتِها'),
    'quota.allocate' => array('Operations/shares_coverage.php',       'إسنادُ الحصصِ حيٌّ على op_containers'),
    'quota.consume'  => array('Operations/shares_coverage.php',       'الاستهلاكُ حيٌّ في container_consumption/allocated'),
    'quota.post'     => array('Operations/shares_coverage.php',       'ترحيلُ الحصةِ = صفُّ استهلاكٍ بمفتاحِ عطالة'),
    'se.register'    => array('Suppliers/showcontractsuppliers.php',  'معداتُ عقدِ الموردِ حيّةٌ في suppliercontractequipments'),
);
$n1 = 0;
foreach ($UP as $code => [$live, $why]) {
    if (!is_file($ROOT . '/' . $live)) { echo "  ⚠ $code: $live غيرُ موجودٍ — لم يُرقَّ\n"; continue; }
    $st = $conn->prepare("UPDATE nav09_action_map
                          SET state='alias', live_code=?, guard_verified='yes',
                              guard_evidence=CONCAT('مكافئٌ حيٌّ: ', ?),
                              updated_at=NOW()
                          WHERE canonical_code=? AND state='declared_unbuilt'");
    $lc = $code; $ev = $live . ' — ' . $why;
    $st->bind_param('sss', $lc, $ev, $code);
    $st->execute(); $n1 += $conn->affected_rows; $st->close();
}
echo "  ① رُقّي $n1 من 7\n";

/* ② أفعالُ الهرمِ الأربعةُ — bound إلى أسطحِه الحيّة */
$CNT = array(
    array('cnt.annual.open',   'فتحُ الحاويةِ السنوية',    'contract_coverage.php',
          'تتولد آليًّا من توقيعِ العقد (ContractSignedEffects ⇐ op_containers رئيسية) وتُدار من شاشةِ التغطية'),
    array('cnt.types.define',  'تعريفُ حاوياتِ الأنواع',   'contract_coverage.php',
          'مستوى «مورد/نوع» في op_containers من بنودِ العقد — ويستدعي F-04 (الاشتقاقُ الصعوديُّ CapacityRollupService)'),
    array('cnt.slots.open',    'فتحُ خاناتِ الآليات',      'equipment_quota.php',
          'مقاعدُ level=معدة في op_containers — وF-01/F-02 قادحان'),
    array('cnt.slot.allocate', 'إسنادُ خانةٍ لمورد',       'shares_coverage.php',
          'supplier_id على المقعدِ وحصتُه allocated — ويستدعي F-06'),
);
$n2 = 0;
foreach ($CNT as [$code, $label, $file, $effect]) {
    $q = $conn->query("SELECT 1 FROM nav09_action_map WHERE canonical_code='" . $conn->real_escape_string($code) . "'");
    if ($q && $q->num_rows) { continue; }
    $st = $conn->prepare("INSERT INTO nav09_action_map
        (canonical_code, label_ar, screen_title, canonical_file, actor_ar, writes_text, event_name,
         consumers_text, effect_text, reverse_text, live_code, state, guard_verified, guard_evidence,
         idempotency_verified, idempotency_evidence, uat_verified, uat_evidence, write_class, updated_at)
        VALUES (?,?,?,?, 'المبيعاتُ والتشغيل', 'op_containers', 'ContainerOpened',
                'المبيعات · الموردون · التشغيل', ?, 'إقفالُ الحاويةِ بقرارٍ لا حذف',
                ?, 'bound_page', 'yes', 'حارسُ الشاشةِ الحاملةِ + قيودُ CHECK الهرميةُ وقوادحُ F-01/02',
                'yes', 'الحاويةُ بمفتاحِها (contract·level·seat) والتوليدُ من العقدِ عاطل', 'pending', '',
                'domain_write', NOW())");
    $st->bind_param('ssssss', $code, $label, $label, $file, $effect, $code);
    if ($st->execute()) { $n2++; }
    $st->close();
}
echo "  ② سُجِّل $n2 من 4\n";
echo "\n✔ تمّت\n";
