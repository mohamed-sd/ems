<?php
/**
 * 2027_01_26 — INJ-0443 · INJ-0544: تسميةُ مساحةِ مخاطرِ الإدارةِ باسمِ إدارتِها
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ العطل: صفُّ التنقّلِ في كلِّ إدارةٍ يحمل العنوانَ العامَّ «مساحة مخاطر الإدارة»،
 *   فالمستخدمُ في المورّدينَ والنقلِ يرى الاسمَ نفسَه ولا يعرف نطاقَه من القائمة.
 * ◆ اختبارُ القبول (INJ-0443): «سايدبارُ الدور 2 يعرض عنصرَ مخاطرَ **واحدًا**
 *   بعنوان ‹مخاطر إدارة الموردين›» · و(INJ-0544): «‹مخاطر النقل والترحيل›
 *   داخلَ مجموعةٍ من مجموعاتِ الإدارة».
 * ◆ ولا تُنشأ صفوفٌ جديدة: الصفُّ قائمٌ ومجموعتُه قائمة — يُعاد تسميتُه فقط.
 *   إنشاءُ صفٍّ ثانٍ يُنتج «عنصرَي مخاطر» وهو عينُ ما يمنعه اختبارُ القبول.
 *
 * ◆ گوتشا كلّفتني جولةً كاملة: كُتب هذا الملفُّ أولَ مرةٍ بصيغةِ
 *   `return array('up' => …, 'down' => …)`. والمُرحِّلُ هنا يشغّل كلَّ هجرةٍ
 *   **سكربتًا مستقلًّا في عمليةٍ منفصلة** و«النجاحُ = رمزُ خروجٍ صفر» — فالملفُّ
 *   عاد بمصفوفةٍ وخرج بصفرٍ، فسُجِّل ناجحًا **بلا أن ينفّذ حرفًا**. ولم يكشفه
 *   إلا أن المِسبارَ قرأ الصفوفَ على حالِها القديم. فالصيغةُ الصحيحةُ سكربتٌ
 *   يفتح اتصالَه ويُنهي بـ`exit(0)` عند النجاحِ وغيرِه عند الفشل.
 *
 * التشغيل مباشرةً: php database/migrations/2027_01_26_inj0443_0544_dept_risk_labels.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$LBL = array(2 => 'مخاطر إدارة الموردين', 23 => 'مخاطر النقل والترحيل');

/* ◆ مُتحمِّلٌ للتكرار: الشرطُ على المسارِ والدورِ لا على العنوانِ القديم، فإعادةُ
     التشغيلِ على قاعدةٍ مُصلَحةٍ تُصيب صفرَ صفٍّ ولا تفسد شيئًا. */
foreach ($LBL as $roleId => $label) {
    $st = $db->prepare("UPDATE nav_items SET label_ar = ?
                         WHERE role_id = ? AND route = 'Risk/dept_risk_space.php'");
    if (!$st) { fwrite(STDERR, 'prepare: ' . $db->error . "\n"); exit(1); }
    $st->bind_param('si', $label, $roleId);
    if (!$st->execute()) { fwrite(STDERR, 'update: ' . $st->error . "\n"); exit(1); }
    echo "  دور {$roleId} ← «{$label}» ({$st->affected_rows} صفًّا)\n";
    $st->close();
}

/* ══ إثباتٌ وظيفيّ — لا يكفي أن تمضيَ العبارة ═══════════════════════════════
   ① لكلِّ دورٍ عنصرُ مخاطرَ **واحدٌ** بعنوانِ إدارتِه (نصُّ اختبارِ القبول).
   ② وصفرُ صفٍّ باقٍ بالعنوانِ العامِّ في هذين الدورين. */
foreach ($LBL as $roleId => $label) {
    $st = $db->prepare("SELECT COUNT(*) FROM nav_items
                         WHERE role_id = ? AND route = 'Risk/dept_risk_space.php' AND label_ar = ?");
    $st->bind_param('is', $roleId, $label);
    $st->execute();
    $n = (int) $st->get_result()->fetch_row()[0];
    $st->close();
    if ($n !== 1) { fwrite(STDERR, "الدور {$roleId}: عناصرُ «{$label}» = {$n} والمطلوب 1\n"); exit(1); }
}
$rs = $db->query("SELECT COUNT(*) FROM nav_items
                   WHERE role_id IN (2, 23) AND label_ar = 'مساحة مخاطر الإدارة'");
if (!$rs) { fwrite(STDERR, 'تحقق: ' . $db->error . "\n"); exit(1); }
$left = (int) $rs->fetch_row()[0];
if ($left !== 0) { fwrite(STDERR, "بقي {$left} صفًّا بالعنوانِ العامّ\n"); exit(1); }

echo "  ✔ إثبات: عنصرٌ واحدٌ لكلِّ دورٍ بعنوانِ إدارتِه · صفرُ عنوانٍ عامٍّ باقٍ\n";
echo "  الشاهد: php tools/fix_od19_probe.php (INJ-0443 · INJ-0544)\n";
exit(0);
