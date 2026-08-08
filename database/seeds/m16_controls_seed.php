<?php
/**
 * database/seeds/m16_controls_seed.php — بذرةُ الضوابطِ الحرجةِ (M-16 §13-3/13-4)
 * ───────────────────────────────────────────────────────────────────────────
 * منهجُ ICMM: «ضوابطُ قليلةٌ محدَّدةٌ تمنع الأحداثَ عاليةَ العواقب — لا مئاتٌ
 * متساوية». فالبذرةُ هنا سبعةُ ضوابطَ فقط: خمسةٌ حرجةٌ بحقولها الخمسةِ كاملةً
 * (الحدثُ عاليُ العواقب · معيارُ الأداء · طريقةُ التحقق · المتحقِّقُ المستقل ·
 * إجراءُ الفشل) واثنان عاديان — والقلّةُ شرطُ المعنى لا نقصُ بذرة.
 *
 * المتحقِّقُ ≠ المالكُ في كلِّ ضابطٍ حرجٍ بنيويًّا (RK-07) — والبذرةُ تحترم الحارسَ
 * ولا تتجاوزه، وإلا بذرت خرقَ فصلِ واجباتٍ يرصده gov_dept_rsk فورًا.
 *
 * بياناتٌ فقط — لا DDL (الأعلامُ تقول EMS_DDL_FREEZE=true).
 * idempotent بـcontrol_code — يُعاد تشغيلُها بلا أثرٍ مزدوج.
 *
 * التشغيل: php database/seeds/m16_controls_seed.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

$db = new mysqli('127.0.0.1', 'root', '', 'equipation_manage', 3307);
if ($db->connect_error) { fwrite(STDERR, $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');
$CO = 4;

/* المالكُ والمتحقِّقُ حسابانِ حيّان مختلفان — لا معرّفانِ مخترعان.
   ◆ ولا يُسنَد ضابطٌ إلى «مشرف المخاطر» (الدور 30): دورُه قراءةٌ خالصةٌ على
   العشرين، ومِلكيةُ ضابطٍ تمنحه حقَّ كتابةِ دليلِ تنفيذِه — فيُنقض دورُه من حيث
   لا يُنقض في جدولِ الصلاحيات. «المخاطرُ تُملك حيث تنشأ» (RK-01): مالكُ الضابطِ
   مَن يشغّله فعلًا في إدارةِ العمل، لا مراقبٌ في مكتبِ المخاطر. */
$mgr = (int) ($db->query("SELECT id FROM users WHERE username='مخاطر' AND company_id=$CO")->fetch_row()[0] ?? 0);
$anl = (int) ($db->query("SELECT id FROM users WHERE username='محلل مخاطر' AND company_id=$CO")->fetch_row()[0] ?? 0);
if ($mgr === 0 || $anl === 0) {
    fwrite(STDERR, "حسابا المخاطر غائبان — شغّل tools/m16_complete_setup.php أولًا\n");
    exit(1);
}
/* مالكٌ تشغيليٌّ حقيقيٌّ إن وُجد (مديرُ موقعٍ أو تشغيل) وإلا فمديرُ المخاطرِ مؤقتًا
   بوسمٍ معلَنٍ في اسمِ العملية — والبذرةُ لا تخترع حسابًا. */
$ops = (int) ($db->query("SELECT id FROM users WHERE company_id=$CO AND role IN (1,5,6)
                           AND status='active' ORDER BY id LIMIT 1")->fetch_row()[0] ?? 0);
$fieldOwner = $ops ?: $mgr;

/* code, name, ctype, owner, process, freq, evidence, critical, hico, perf, method, verifier, fail */
$CONTROLS = array(
    array('CTL-0001', 'عزل الطاقة قبل أي صيانة على معدة ثقيلة', 'وقائي', $fieldOwner,
        'أمر الصيانة — قبل بدء العمل', 'عند الحدث',
        'بطاقة عزل موقَّعة بالرقم التسلسلي وصورة القفل مركَّبًا', 1,
        'صعقة أو حركة معدة أثناء الصيانة تؤدي إلى إصابة قاتلة',
        'صفر أمر صيانة يبدأ بلا بطاقة عزل موقَّعة — يُقاس أسبوعيًّا على أوامر الفترة',
        'مشاهدة ميدانية + مطابقة سجل بطاقات العزل بأوامر الصيانة',
        $anl, 'إيقاف العمل فورًا · إبلاغ مدير الموقع في الساعة · تصعيد للرئيس في اليوم نفسه'),
    array('CTL-0002', 'حارس الرجوع للخلف ومراقب الحركة في مناطق الاختلاط', 'وقائي', $fieldOwner,
        'حركة المعدات في الموقع', 'كل وردية',
        'سجل وردية بأسماء المراقبين وتوقيعهم وزمن التغطية', 1,
        'اصطدام معدة بشخص أثناء الرجوع للخلف',
        'صفر حركة رجوعٍ بلا مراقب معلَن في مناطق الاختلاط',
        'مشاهدة ميدانية عشوائية مرتين في الوردية + مراجعة سجل الوردية',
        $anl, 'إيقاف حركة المعدة · إعادة تأهيل المشغّل · تصعيد فوري'),
    array('CTL-0003', 'فحص الفرامل والإطارات قبل تحرك المعدة', 'كاشف', $fieldOwner,
        'بداية الوردية — قبل التحرك', 'كل وردية',
        'قائمة فحص ما قبل التشغيل موقَّعة بالقراءات', 1,
        'انقلاب أو فقد سيطرة على منحدر بسبب فرامل معطلة',
        'صفر تحرك بلا قائمة فحص مكتملة — والقراءة داخل حدود المصنِّع',
        'مطابقة قوائم الفحص بسجل ساعات التشغيل + فحص فني شهري',
        $anl, 'حجز المعدة عن العمل · أمر صيانة عاجل · لا عودة بلا تحقق فني'),
    array('CTL-0004', 'ضبط صرف الوقود والحد من مخاطر الحرائق', 'وقائي', $mgr,
        'صرف الوقود في المخزن والموقع', 'يومي',
        'إيصال صرف بالكمية والمعدة وقراءة العداد + شهادة سلامة نقطة الصرف', 1,
        'حريق وقود في نقطة الصرف أو أثناء النقل',
        'صفر صرف خارج نقطة معتمدة · طفاية سارية الصلاحية في كل نقطة',
        'جولة سلامة أسبوعية + مطابقة إيصالات الصرف بحركة المخزن',
        $anl, 'إغلاق نقطة الصرف · إبلاغ السلامة · تصعيد فوري إن غابت وسائل الإطفاء'),
    array('CTL-0005', 'التحقق من أهلية المشغّل ورخصته قبل الإسناد', 'وقائي', $mgr,
        'إسناد مشغّل إلى معدة', 'عند الحدث',
        'رخصة سارية مربوطة بنوع المعدة + سجل تأهيل موقَّع', 1,
        'تشغيل معدة ثقيلة بمشغّل غير مؤهَّل يؤدي إلى حدث كارثي',
        'صفر إسناد برخصة منتهية أو غير مطابقة لنوع المعدة',
        'مطابقة آلية بين سجل الإسناد ووثائق المشغّل شهريًّا',
        $anl, 'إلغاء الإسناد فورًا · وقف المشغّل عن المعدة · تصعيد للقوى التشغيلية والمخاطر'),
    /* عاديان — للتمييز: «لا مئاتٌ متساوية» */
    array('CTL-0006', 'مطابقة ساعات التشغيل بسجل الوردية', 'كاشف', $mgr,
        'الإقفال التشغيلي اليومي', 'يومي',
        'تقرير فروق الساعات بين العداد وسجل الوردية', 0, null, null, null, null, null),
    array('CTL-0007', 'مراجعة أعمار الذمم أسبوعيًّا', 'كاشف', $mgr,
        'دورة التحصيل', 'أسبوعي',
        'تقرير أعمار الذمم موقَّعًا من المحصِّل', 0, null, null, null, null, null),
);

$added = 0; $exists = 0;
foreach ($CONTROLS as $c) {
    list($code, $name, $ctype, $owner, $proc, $freq, $ev, $crit, $hico, $perf, $method, $verifier, $fail) = $c;
    $r = $db->query("SELECT id FROM risk_controls WHERE company_id=$CO AND control_code='"
        . $db->real_escape_string($code) . "'");
    if ($r && $r->num_rows > 0) { $exists++; continue; }
    // RK-07 بنيويًّا في البذرة: المتحقِّقُ الحرجُ ≠ المالك
    if ($crit && (int) $verifier === (int) $owner) {
        fwrite(STDERR, "بذرةٌ مرفوضة: $code متحققه هو مالكه (RK-07)\n");
        continue;
    }
    $st = $db->prepare("INSERT INTO risk_controls
        (company_id, control_code, name_ar, ctype, owner_user_id, process_ref, frequency, evidence_spec,
         effectiveness, is_critical, hico_event, perf_criterion, verify_method, verifier_user_id,
         fail_action, active, created_by)
        VALUES (?,?,?,?,?,?,?,?,'غير مثبت',?,?,?,?,?,?,1,?)");
    $st->bind_param('isssisssisssisi', $CO, $code, $name, $ctype, $owner, $proc, $freq, $ev,
        $crit, $hico, $perf, $method, $verifier, $fail, $mgr);
    $st->execute();
    $st->close();
    $added++;
}

/* ربطُ الضوابطِ الحرجةِ بمخاطرِ وحدةِ السلامةِ RU-10 إن وُجدت — «الضابطُ مرتبطٌ
   ولا يخفض الدرجةَ قبل إثبات فعاليته» (RK-07)، فالربطُ خريطةٌ لا خفضُ درجة. */
$linked = 0;
$ru10Risks = array();
$r = $db->query("SELECT rr.id FROM risk_register rr
                  JOIN risk_units ru ON ru.id = rr.ru_id AND ru.company_id = rr.company_id
                 WHERE rr.company_id=$CO AND ru.ru_code='RU-10' AND rr.merged_into_id IS NULL");
while ($x = $r->fetch_row()) { $ru10Risks[] = (int) $x[0]; }
if ($ru10Risks) {
    $crits = array();
    $r = $db->query("SELECT id FROM risk_controls WHERE company_id=$CO AND is_critical=1 AND active=1");
    while ($x = $r->fetch_row()) { $crits[] = (int) $x[0]; }
    foreach ($ru10Risks as $rid) {
        foreach ($crits as $cid) {
            $db->query("INSERT IGNORE INTO risk_control_links (company_id, risk_id, control_id, linked_by)
                        VALUES ($CO, $rid, $cid, $mgr)");
            $linked += $db->affected_rows > 0 ? 1 : 0;
        }
    }
}

$tot = (int) $db->query("SELECT COUNT(*) FROM risk_controls WHERE company_id=$CO AND active=1")->fetch_row()[0];
$totC = (int) $db->query("SELECT COUNT(*) FROM risk_controls WHERE company_id=$CO AND active=1 AND is_critical=1")->fetch_row()[0];
$sod = (int) $db->query("SELECT COUNT(*) FROM risk_controls WHERE company_id=$CO AND active=1
                          AND is_critical=1 AND verifier_user_id = owner_user_id")->fetch_row()[0];
echo "ضوابط أُضيفت: $added · قائمة سلفًا: $exists\n";
echo "الإجمالي النشط: $tot · الحرجة: $totC (ICMM: قليلة ومحدَّدة)\n";
echo "ارتباطات بمخاطر RU-10: $linked\n";
echo "خرق RK-07 في البذرة (متحقق = مالك): $sod (المطلوب 0)\n";
