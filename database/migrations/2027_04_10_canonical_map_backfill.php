<?php
/**
 * 2027_04_10_canonical_map_backfill.php
 * ═══════════════════════════════════════════════════════════════════════════
 * رمزُ الوثيقةِ ⇄ مسارُ القرصِ ⇄ مالكٌ واحد — ⇐ INJ-0457 · INJ-0476
 *
 * نصُّ القبول: «لكل رمزِ شاشةٍ في §٨-٤ صفٌّ يشير إلى **مسارٍ موجودٍ على القرص**؛
 * صفرُ رمزٍ بلا مطابقة» · «ولكلِّ رمزٍ في §١١-٤ … **ومالكٍ واحد**».
 *
 * ── ما كان ────────────────────────────────────────────────────────────────
 * تعلن `INJAZ-FRD-01` رمزَ ملفٍّ لكلِّ شاشة (`equip_models.php`) والمنفَّذُ اسمٌ
 * آخرُ في مجلدٍ آخر (`Equipments/fleet_models.php`). فقياسُ «ربطُ الأفعالِ
 * مكتمل» يحتاج اجتهادًا يدويًّا لكلِّ حكم، ويسهل ازدواجُ الملكيةِ بين إدارتين.
 * والمقيسُ بالفاحصِ `tools/fix_canonical_map_scan.php`: **٢٠٨ رموزٍ في ١٨
 * جدولَ «§N-٤»، منها ١٥٦ مربوطًا و٥٢ بلا صفٍّ أصلًا** — كلُّها شاشاتُ مخاطرَ
 * وحوكمةِ إدارات، **وكلُّها موجودةٌ على القرصِ فعلًا**: النقصُ في السجلِّ لا في البناء.
 *
 * ── ولماذا `nav09_file_map` لا سجلٌّ جديد ─────────────────────────────────
 * أوصت الملاحظةُ بملءِ «جدول canonical_names»، و`nav09_file_map` **هو** ذلك
 * السجلُّ في هذا النظام: `canonical_file` ⇄ `real_path` ⇄ `owner_dept`، وفيه
 * ١٥٦ رمزًا مربوطًا سلفًا. وإنشاءُ سجلٍّ ثانٍ لحقيقةٍ لها سجلٌّ **هو بعينِه
 * عيبُ «مخزنانِ لحقيقةٍ واحدة»** الذي تعالجه هذه الحملةُ — فلا يُنشأ.
 *
 * ── والمالكُ مشتقٌّ لا مُخترَع ─────────────────────────────────────────────
 * مالكُ كلِّ صفٍّ جديدٍ **أغلبيةُ مُلّاكِ قسمِه المربوطين سلفًا** — فشاشةُ مخاطرِ
 * الأسطولِ تتبع «إدارة الأسطول» لأنَّ إحدى عشرةَ شاشةً في §٨-٤ تتبعها.
 * وشاشاتُ §١٢/§٢٣ الأربعَ عشرةَ تتبع **«إدارة المخاطر»**: تُصرّح الوثيقةُ أنَّها
 * إدارةٌ مركزيةٌ تتبع الرئيسَ التنفيذيَّ مباشرةً في طبقةٍ **موازيةٍ** للحوكمةِ
 * والالتزام — فنسبتُها إلى الحوكمةِ تُلغي استقلالَها المنصوصَ عليه.
 *
 * ◆ و`state`: `live` إن طابق اسمُ الملفِّ الرمزَ، و`mapped` إن اختلف الاسمان
 *   (أربعةُ صفوفٍ فقط: `risk_dashboard→risk_board` · `risk_profile→risk_card` ·
 *   `risk_kri→risk_kris` · `risk_treatment→risk_treatments`) — مطابَقةٌ **بالعنوان**
 *   لا بالتخمين: «إجراءات المعالجة» و«ملف الخطر» يتطابقان حرفًا مع عنوانِ الشاشة.
 * ◆ ولا يُكتب صفٌّ لمسارٍ غيرِ موجودٍ على القرص — يُتخطّى ويُعلَن.
 * ◆ وعاطلةٌ: `INSERT ... ON DUPLICATE KEY UPDATE` على مفتاحِ `canonical_file`،
 *   ولا تُلمس الصفوفُ الـ١٥٦ القائمة.
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
$u = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_USER') : ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_PASS') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

echo "══ ردمُ سجلِّ الأسماءِ المعتمدة (canonical ⇄ path ⇄ owner) ══\n\n";

/* canonical_file · real_path · owner_dept · state · title_ar */
$ROWS = array(
    array('risk_dept_ceo.php', 'Risk/risk_dept_ceo.php', 'الإدارة التنفيذية', 'live', 'المخاطر المؤسسية'),
    array('gov_dept_ceo.php', 'Portal/gov_dept_ceo.php', 'الإدارة التنفيذية', 'live', 'حوكمة مكتبُ الرئيس التنفيذي والنواب'),
    array('risk_dept_ops.php', 'Risk/risk_dept_ops.php', 'إدارة التشغيل', 'live', 'المخاطر التشغيلية'),
    array('gov_dept_ops.php', 'Operations/gov_dept_ops.php', 'إدارة التشغيل', 'live', 'حوكمة إدارة التشغيل'),
    array('risk_dept_flt.php', 'Risk/risk_dept_flt.php', 'إدارة الأسطول', 'live', 'مخاطر الأسطول'),
    array('gov_dept_flt.php', 'Equipments/gov_dept_flt.php', 'إدارة الأسطول', 'live', 'حوكمة إدارة الأسطول'),
    array('risk_dept_cap.php', 'Risk/risk_dept_cap.php', 'التمويل والملكية', 'live', 'مخاطر التمويل والملكية'),
    array('gov_dept_cap.php', 'Financing/gov_dept_cap.php', 'التمويل والملكية', 'live', 'حوكمة التمويل والملكية'),
    array('risk_dept_hrm.php', 'Risk/risk_dept_hrm.php', 'الموارد البشرية', 'live', 'مخاطر الموارد البشرية'),
    array('gov_dept_hrm.php', 'Workforce/gov_dept_hrm.php', 'الموارد البشرية', 'live', 'حوكمة الموارد البشرية'),
    array('risk_dashboard.php', 'Risk/risk_board.php', 'إدارة المخاطر', 'mapped', 'لوحة المخاطر المؤسسية'),
    array('risk_register.php', 'Risk/risk_register.php', 'إدارة المخاطر', 'live', 'سجل المخاطر المؤسسي'),
    array('risk_units.php', 'Risk/risk_units.php', 'إدارة المخاطر', 'live', 'وحدات المخاطر والتصنيف'),
    array('risk_profile.php', 'Risk/risk_card.php', 'إدارة المخاطر', 'mapped', 'ملف الخطر'),
    array('risk_assessment.php', 'Risk/risk_assessment.php', 'إدارة المخاطر', 'live', 'تقييم الخطر ونسخُه التاريخية'),
    array('risk_controls.php', 'Risk/risk_controls.php', 'إدارة المخاطر', 'live', 'الضوابط والضوابط الحرجة'),
    array('risk_control_verify.php', 'Risk/risk_control_verify.php', 'إدارة المخاطر', 'live', 'التحقق من الضوابط الحرجة'),
    array('risk_kri.php', 'Risk/risk_kris.php', 'إدارة المخاطر', 'mapped', 'مؤشرات المخاطر والضوابط'),
    array('risk_treatment.php', 'Risk/risk_treatments.php', 'إدارة المخاطر', 'mapped', 'إجراءات المعالجة'),
    array('risk_acceptance.php', 'Risk/risk_acceptance.php', 'إدارة المخاطر', 'live', 'القبول والاستثناءات'),
    array('risk_signals.php', 'Risk/risk_signals.php', 'إدارة المخاطر', 'live', 'إشارات المخاطر والفرز'),
    array('risk_incidents.php', 'Risk/risk_incidents.php', 'إدارة المخاطر', 'live', 'الحوادث والوقائع'),
    array('risk_reviews.php', 'Risk/risk_reviews.php', 'إدارة المخاطر', 'live', 'المراجعات والتصعيدات'),
    array('risk_committee.php', 'Risk/risk_committee.php', 'إدارة المخاطر', 'live', 'لجنة المخاطر'),
    array('risk_dept_crp.php', 'Risk/risk_dept_crp.php', 'مركز البلاغات', 'live', 'مخاطر البلاغات والوقائع'),
    array('gov_dept_crp.php', 'Tickets/gov_dept_crp.php', 'مركز البلاغات', 'live', 'حوكمة مركز البلاغات'),
    array('risk_dept_mnt.php', 'Risk/risk_dept_mnt.php', 'إدارة الصيانة', 'live', 'مخاطر الصيانة'),
    array('gov_dept_mnt.php', 'Maintenance/gov_dept_mnt.php', 'إدارة الصيانة', 'live', 'حوكمة إدارة الصيانة'),
    array('risk_dept_wrk.php', 'Risk/risk_dept_wrk.php', 'القوى التشغيلية', 'live', 'مخاطر القوى التشغيلية'),
    array('gov_dept_wrk.php', 'Workforce/gov_dept_wrk.php', 'القوى التشغيلية', 'live', 'حوكمة القوى التشغيلية'),
    array('risk_dept_trp.php', 'Risk/risk_dept_trp.php', 'النقل والترحيل', 'live', 'مخاطر النقل والترحيل'),
    array('gov_dept_trp.php', 'Transport/gov_dept_trp.php', 'النقل والترحيل', 'live', 'حوكمة النقل والترحيل'),
    array('risk_dept_prc.php', 'Risk/risk_dept_prc.php', 'المشتريات', 'live', 'مخاطر المشتريات التشغيلية'),
    array('gov_dept_prc.php', 'Procurement/gov_dept_prc.php', 'المشتريات', 'live', 'حوكمة إدارة المشتريات التشغيلية'),
    array('risk_dept_inv.php', 'Risk/risk_dept_inv.php', 'المخازن', 'live', 'مخاطر المخازن'),
    array('gov_dept_inv.php', 'Procurement/gov_dept_inv.php', 'المخازن', 'live', 'حوكمة إدارة المخازن'),
    array('risk_dept_sit.php', 'Risk/risk_dept_sit.php', 'إدارة الموقع', 'live', 'مخاطر الموقع'),
    array('gov_dept_sit.php', 'Operations/gov_dept_sit.php', 'إدارة الموقع', 'live', 'حوكمة إدارة الموقع'),
);

$st = $conn->prepare(
    "INSERT INTO nav09_file_map (canonical_file, title_ar, owner_dept, state, real_path, note, updated_at)
          VALUES (?, ?, ?, ?, ?, 'INJ-0457/0476: ردمُ سجلِّ الأسماءِ من FRD §N-٤ — المسارُ محقَّقٌ على القرص', NOW())
     ON DUPLICATE KEY UPDATE
          title_ar   = VALUES(title_ar),
          owner_dept = VALUES(owner_dept),
          state      = VALUES(state),
          real_path  = VALUES(real_path),
          note       = VALUES(note),
          updated_at = NOW()");
if (!$st) { exit("تعذّر التحضير: {$conn->error}\n"); }

$ins = 0; $upd = 0; $skipped = array();
foreach ($ROWS as $r) {
    list($canon, $path, $owner, $state, $title) = $r;
    /* ◆ لا يُكتب صفٌّ لمسارٍ لا وجودَ له — فالسجلُّ يعِد بمسارٍ موجود */
    if (!is_file($ROOT . '/' . $path)) { $skipped[] = $canon . ' ⟵ ' . $path; continue; }
    $st->bind_param('sssss', $canon, $title, $owner, $state, $path);
    if (!$st->execute()) { echo "  ✘ {$canon}: {$conn->error}\n"; continue; }
    /* affected_rows: 1 إدراجٌ · 2 تحديثٌ · 0 لا تغيير */
    if ($conn->affected_rows === 1) { $ins++; } else { $upd++; }
}
$st->close();
echo '  أُدرج: ' . $ins . ' · حُدّث: ' . $upd . ' · تُخُطّي لمسارٍ مفقود: ' . count($skipped) . "\n";
foreach ($skipped as $s) { echo '    ⚠ ' . $s . "\n"; }

$r = $conn->query('SELECT COUNT(*) FROM nav09_file_map');
echo '  إجماليُّ السجلِّ الآن: ' . ($r ? $r->fetch_row()[0] : '?') . " صفًّا\n";
$r = $conn->query("SELECT COUNT(*) FROM nav09_file_map WHERE COALESCE(owner_dept,'') = ''");
echo '  صفوفٌ بلا مالك: ' . ($r ? $r->fetch_row()[0] : '?') . "  (قائمةٌ من قبلُ — خارجَ نطاقِ §N-٤)\n";
echo "\n✔ تمّت\n";
