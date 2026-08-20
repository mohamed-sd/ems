<?php
/**
 * 2027_08_09_restricted_views.php — سجلُّ المناظرِ المقيَّدةِ (سادسًا + ٢٠-٢)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ المواصفة (٢٠-٢): «لكلِّ منظرٍ مقيَّد: **مُعرِّفُه · مالكُ مصدرِه · الدورُ
 *   المستهلِكُ · غرضُه · نطاقُ الصفوفِ · قائمةُ الحقولِ · أيُسمح بتصديرِه · وهل
 *   يلزمه أثرٌ في السجل**».
 *
 * ◆ **والمنظرُ بديلُ الإزالةِ لا مانعُها**: الشاشةُ الماليةُ في مساحةٍ لا تملكها
 *   تُزال — **ويُستبدَل بها منظرٌ مقيَّدٌ إن كان العملُ يتوقف عليها وإلا فلا بديل**.
 *   فلا يُنشأ منظرٌ لكلِّ ممنوع، بل لما ثبتَ توقّفُ العملِ عليه.
 *
 * ◆ **والمناظرُ الأربعةُ هنا تُشتقُّ من الاستهلاكِ المقيس** لا من طلبٍ شفويّ:
 *   هي الحالاتُ التي أثبتت العقدةُ الخامسةُ فيها أن شاشاتِ المساحةِ **تقرأ ما
 *   تكتبه** الشاشةُ الممنوعة — فحاجتُها إلى **حقولٍ منها** ثابتةٌ بالقياس،
 *   وحاجتُها إلى **الشاشةِ نفسِها** غيرُ ثابتة.
 *
 * ◆ **ولا حقلَ حساسٌ في أيِّ قائمة**: السعرُ والتكلفةُ والهامشُ والأجرُ خارجَ
 *   الحدود — والخدمةُ تطرحها **بنيويًّا** حتى لو أُدرجت هنا سهوًا.
 * ═══════════════════════════════════════════════════════════════════════════
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
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

if (!$conn->query("CREATE TABLE IF NOT EXISTS `gov_restricted_views` (
    `view_key`        VARCHAR(60)  NOT NULL COMMENT 'مُعرِّفُ المنظر',
    `source_table`    VARCHAR(64)  NOT NULL COMMENT 'جدولُ المصدر',
    `owner_dept_ar`   VARCHAR(120) NOT NULL COMMENT 'مالكُ المصدر',
    `consumer_space`  VARCHAR(80)  NOT NULL COMMENT 'المساحةُ المستهلِكة',
    `purpose_ar`      VARCHAR(255) NOT NULL COMMENT 'غرضُه — ولا منظرَ بلا غرضٍ مكتوب',
    `row_scope_col`   VARCHAR(64)  NOT NULL COMMENT 'عمودُ نطاقِ الصفِّ — يُحقن في الشرطِ لا يُرشَّح بعدَه',
    `field_allowlist` VARCHAR(500) NOT NULL COMMENT 'الحقولُ المسموحةُ حصرًا',
    `allow_export`    TINYINT(1)   NOT NULL DEFAULT 0,
    `needs_audit`     TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'أيلزمه أثرٌ في سجلِّ الاطّلاع',
    `replaces_route`  VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'الشاشةُ الممنوعةُ التي يحلُّ محلَّها',
    `active`          TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`      DATETIME NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`view_key`),
    KEY `ix_grv_space` (`consumer_space`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='سادسًا — المنظرُ المقيَّدُ: row_scope × field_allowlist'")) {
    exit("✘ تعذّر إنشاءُ الجدول: {$conn->error}\n");
}

/* الحقولُ تُختار من أعمدةِ الجدولِ الحقيقيةِ — ولا يُكتب اسمٌ لا وجودَ له */
function cols_of(mysqli $c, $t) {
    $o = array();
    $st = $c->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    if (!$st) { return $o; }
    $st->bind_param('s', $t); $st->execute();
    $r = $st->get_result();
    while ($r && ($x = $r->fetch_row())) { $o[mb_strtolower($x[0])] = 1; }
    $st->close();
    return $o;
}
function pick(mysqli $c, $t, array $want) {
    $have = cols_of($c, $t);
    $out = array();
    foreach ($want as $w) { if (isset($have[mb_strtolower($w)])) { $out[] = $w; } }
    return $out;
}

$PLAN = array(
    array('key' => 'rv.site.contract_ref', 'table' => 'contracts',
          'owner' => 'المبيعات والعقود', 'space' => 'إدارة الموقع',
          'purpose' => 'تسجيلُ وحدةٍ على عقدٍ يلزمه رقمُ العقدِ وسريانُه — لا شروطُه المالية',
          'scope' => 'project_id',
          /* ◆ الأسماءُ صُحِّحت من مخططِ `contracts` الحقيقيِّ بعدَ أن سقطت أربعةٌ
               منها لعدمِ وجودِها — **وحقلٌ يُكتب من الذاكرةِ يسقط صامتًا فيصير
               المنظرُ ثلاثةَ أعمدةٍ بلا معنى**. ولا سعرَ ولا مدةَ تعاقدٍ ماليةً. */
          'fields' => array('id', 'quotation_id', 'contract_signing_date', 'actual_start',
                            'actual_end', 'contract_duration_months', 'project_id'),
          'export' => 0, 'replaces' => 'Contracts/price_terms.php'),
    array('key' => 'rv.ops.contract_ref', 'table' => 'contracts',
          'owner' => 'المبيعات والعقود', 'space' => 'ادارة التشغيل',
          'purpose' => 'ربطُ الوحدةِ التشغيليةِ بعقدِها — المرجعُ والسريانُ فقط',
          'scope' => 'project_id',
          /* ◆ الأسماءُ صُحِّحت من مخططِ `contracts` الحقيقيِّ بعدَ أن سقطت أربعةٌ
               منها لعدمِ وجودِها — **وحقلٌ يُكتب من الذاكرةِ يسقط صامتًا فيصير
               المنظرُ ثلاثةَ أعمدةٍ بلا معنى**. ولا سعرَ ولا مدةَ تعاقدٍ ماليةً. */
          'fields' => array('id', 'quotation_id', 'contract_signing_date', 'actual_start',
                            'actual_end', 'contract_duration_months', 'project_id'),
          'export' => 0, 'replaces' => 'Contracts/price_terms.php'),
    array('key' => 'rv.hr.payment_state', 'table' => 'fin_payments',
          'owner' => 'المالية والخزينة', 'space' => 'ادارة الموارد البشرية',
          'purpose' => 'متابعةُ حالةِ طلبِ صرفٍ لموظفٍ — الحالةُ والتاريخُ لا المبلغ',
          'scope' => 'company_id',
          'fields' => array('id', 'status', 'created_at'),
          'export' => 0, 'replaces' => 'Finance/payments_fin.php'),
    array('key' => 'rv.fleet.asset_state', 'table' => 'equipments',
          'owner' => 'إدارة الأسطول', 'space' => 'ادارة الصيانة',
          'purpose' => 'حالةُ المعدةِ وموقعُها لجدولةِ الصيانة — لا قيمتُها الدفترية',
          'scope' => 'company_id',
          'fields' => array('id', 'code', 'name', 'equipment_condition', 'operating_hours'),
          'export' => 0, 'replaces' => 'Finance/assets_fin.php'),
);

$ins = $conn->prepare(
    "INSERT INTO gov_restricted_views
        (view_key, source_table, owner_dept_ar, consumer_space, purpose_ar,
         row_scope_col, field_allowlist, allow_export, needs_audit, replaces_route)
     VALUES (?,?,?,?,?,?,?,?,1,?)
     ON DUPLICATE KEY UPDATE source_table=VALUES(source_table), owner_dept_ar=VALUES(owner_dept_ar),
        consumer_space=VALUES(consumer_space), purpose_ar=VALUES(purpose_ar),
        row_scope_col=VALUES(row_scope_col), field_allowlist=VALUES(field_allowlist),
        allow_export=VALUES(allow_export), replaces_route=VALUES(replaces_route)");
if (!$ins) { exit("تعذّر التحضير: {$conn->error}\n"); }

echo "══ سجلُّ المناظرِ المقيَّدة ══\n";
$n = 0; $skipped = 0;
foreach ($PLAN as $v) {
    $have = cols_of($conn, $v['table']);
    if (!$have) { echo "  ◆ {$v['key']}: جدولُ `{$v['table']}` غيرُ موجودٍ — **لا يُسجَّل منظرٌ لمصدرٍ وهميّ**\n"; $skipped++; continue; }
    if (!isset($have[mb_strtolower($v['scope'])])) {
        echo "  ◆ {$v['key']}: لا عمودَ نطاقِ صفٍّ `{$v['scope']}` — **ولا منظرَ بلا نطاقِ صفّ**\n"; $skipped++; continue;
    }
    $f = pick($conn, $v['table'], $v['fields']);
    if (!$f) { echo "  ◆ {$v['key']}: لا حقلَ موجودًا من القائمة\n"; $skipped++; continue; }
    $fl = implode(',', $f);
    $ins->bind_param('sssssssis', $v['key'], $v['table'], $v['owner'], $v['space'],
                     $v['purpose'], $v['scope'], $fl, $v['export'], $v['replaces']);
    if ($ins->execute()) {
        $n++;
        printf("  ✔ %-24s %-22s ⇐ %-16s نطاق=%-12s حقول=%d\n",
            $v['key'], mb_substr($v['space'], 0, 22), $v['table'], $v['scope'], count($f));
        $dropped = array_diff($v['fields'], $f);
        if ($dropped) { echo "      ◆ سقطَ لعدمِ وجودِه: " . implode(' · ', $dropped) . "\n"; }
    } else { echo "  ✘ {$v['key']}: {$ins->error}\n"; }
}
$ins->close();

echo "  سُجِّل: {$n}" . ($skipped ? " · تُخطّي: {$skipped} (**بسببِه المكتوبِ لا صامتًا**)" : '') . "\n";
$q = $conn->query("SELECT COUNT(*) FROM gov_restricted_views WHERE active=1");
echo "  المناظرُ النشِطةُ إجمالًا: " . ($q ? $q->fetch_row()[0] : 0) . "\n";
exit($n > 0 ? 0 : 1);
