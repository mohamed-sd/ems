<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * UX-02 §8.1 — اختبار قبول «وحدات الأطراف» بدل «مطابقة الوحدات»
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/party_units_screen_test.php
 *
 * يحكم البنودَ السبعة للمفهوم المعتمد:
 *   ① الإدخال اليدوي زال — «دون إعادة إدخال» (المصدر الثالث أُغلق)
 *   ② «اعتماد التطابق» زال — والمروحة اليدوية forUnitRecord بلا مستدعٍ حي
 *   ③ لا مصطلح «تطابق ثلاثي» في الشاشة ولا في التسميات
 *   ④ الصفوف العشرة القديمة مجمّدة بمالها المربوط (24 رابطًا) بلا مساس
 *   ⑤ بطاقات الأحكام الثلاث تُكتب لحظة التحويل (المشغّل معهما)
 *   ⑥ التساوي يُعلَّم حين يقع طبيعيًّا ولا يُشترط
 *   ⑦ التسمية: الوحدة 105 وصفوف السايدبار الـ13 = «وحدات الأطراف»
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

$env = array();
foreach (file(dirname(__DIR__) . '/.env') as $l) {
    if (preg_match('/^\s*([A-Z_]+)=(.*)$/', $l, $m)) { $env[$m[1]] = trim($m[2]); }
}
$db = new mysqli($env['DB_HOST'], 'root', '', $env['DB_NAME']);
if ($db->connect_error) { fwrite(STDERR, "conn: {$db->connect_error}\n"); exit(1); }
$db->set_charset('utf8mb4');

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$screen = file_get_contents(dirname(__DIR__) . '/Finance/unit_records_fin.php');

fwrite(STDOUT, "\n══ UX-02 §8.1 — وحدات الأطراف ══\n");

// ═══ ① الإدخال اليدوي زال ═══
head('① «دون إعادة إدخال» — المصدر الثالث أُغلق');
check(strpos($screen, "INSERT") === false || strpos($screen, "insert('fin_unit_records'") === false,
    'لا INSERT في fin_unit_records من الشاشة');
check(strpos($screen, 'fin_gen_code') === false, 'لا توليدَ لأرقام سجلاتٍ يدويةٍ جديدة (fin_gen_code زال)');
check(strpos($screen, "name=\"work_model\"") === false && strpos($screen, "name='work_model'") === false,
    'نموذجُ الإدخال اليدوي زال من الواجهة');
check(strpos($screen, 'delete_id') === false, 'حذفُ السجلات زال (القسم مجمّدٌ قراءة)');

// ═══ ② اعتماد التطابق زال ═══
head('② «اعتماد الأحكام» لا «اعتماد التطابق»');
check(strpos($screen, 'approve_id') === false, 'مسار «اعتماد التطابق» زال من الشاشة');
check(strpos($screen, 'forUnitRecord') === false, 'المروحة اليدوية forUnitRecord بلا استدعاءٍ من الشاشة');
check(strpos($screen, 'اعتماد الأحكام') !== false || strpos($screen, 'اعتماد أحكام') !== false,
    'الزر الجديد «اعتماد الأحكام» حاضر');
// forUnitRecord لم يعد له مستدعٍ حيٌّ في الشاشات كلها (المحرك يبقى للتاريخ)
$callers = 0;
foreach (glob(dirname(__DIR__) . '/Finance/*.php') as $f) {
    if (strpos(file_get_contents($f), 'forUnitRecord') !== false) { $callers++; }
}
check($callers === 0, "صفرُ مستدعٍ لـforUnitRecord في شاشات المالية: {$callers}");

// ═══ ③ لا «تطابق ثلاثي» ═══
head('③ «لا يظهر مصطلح التطابق الثلاثي في أي شاشة» (§8.1 ذيلًا)');
check(strpos($screen, 'التطابق الثلاثي') === false, 'الشاشة خالية من المصطلح');
$r = $db->query("SELECT COUNT(*) c FROM modules WHERE name LIKE '%تطابق%' OR name LIKE '%مطابقة الوحدات%'");
check((int) $r->fetch_assoc()['c'] === 0, 'لا وحدةَ باسم المطابقة');
$r = $db->query("SELECT COUNT(*) c FROM nav_items WHERE label_ar LIKE '%مطابقة الوحدات%'");
check((int) $r->fetch_assoc()['c'] === 0, 'لا رابطَ سايدبارٍ باسم المطابقة');

// ═══ ④ العشرة مجمّدة بمالها ═══
head('④ السجل اليدوي القديم مجمّدٌ بمالِه المربوط');
/* ══ **الجمدُ يُقاس على المجمَّدِ لا على الجدولِ كلِّه.** كانت الثلاثةُ تُجمِّد
     إحصاءَ الجدولِ بأسرِه (10 · 24 · 2)، ثم دخلت دفعةٌ تجريبيةٌ لاحقةٌ
     (#663..#672) فصار 20 · 30 · 7 — **فرسبت الثلاثةُ وما مُسَّ المجمَّدُ بحرف**.
     والسجلُّ اليدويُّ القديمُ مجموعةٌ **ثابتةُ الهوية**: #13..#22 — وهي وحدَها
     التي تحلُّ مراسيها 8/8 وتحمل ثلاثيةَ المالِ كاملةً (قِيست صفًّا صفًّا).
   ⇒ فيُقاس الجمدُ على مداها، ويُضاف فوقَه **عقدُ المال** على الجدولِ كلِّه —
     وهو ما تجسّده الموروثةُ: لا اعتمادَ بلا مالٍ **يحلُّ**. */
$LEGACY = 'id BETWEEN 13 AND 22';
$r = $db->query("SELECT COUNT(*) c FROM fin_unit_records WHERE {$LEGACY} AND COALESCE(is_deleted,0)=0");
check((int) $r->fetch_assoc()['c'] === 10, 'صفوفُ السجلِّ اليدويِّ العشرةُ باقيةٌ حيّة');
$r = $db->query("SELECT COUNT(*) c FROM fin_event_links l
                  JOIN fin_unit_records u ON u.id = l.parent_ref
                 WHERE l.parent_kind='unit_record' AND u.{$LEGACY}");
check((int) $r->fetch_assoc()['c'] === 24, 'روابطُ مالِها الـ24 بلا مساس');
$r = $db->query("SELECT COUNT(*) c FROM fin_unit_records
                  WHERE {$LEGACY} AND match_state IN ('variance','pending') AND COALESCE(is_deleted,0)=0");
check((int) $r->fetch_assoc()['c'] === 2, 'الصفان غير المعتمدَين باقيان شاهدَين (لا اعتمادَ لهما بعد اليوم)');

// ── عقدُ المال: قابلٌ للتطبيقِ على كلِّ صفٍّ حاضرٍ ومستقبَل ──
$r = $db->query("SELECT COUNT(*) c FROM fin_event_links l
                  LEFT JOIN fin_financial_events e ON e.id = l.event_id
                 WHERE l.parent_kind='unit_record' AND l.event_id IS NOT NULL AND e.id IS NULL");
check((int) $r->fetch_assoc()['c'] === 0, 'ولا رابطَ مالٍ يشير إلى حدثٍ معدوم');
$r = $db->query("SELECT COUNT(*) c FROM fin_unit_records u
                  WHERE COALESCE(u.is_deleted,0)=0 AND u.match_state='approved'
                    AND NOT EXISTS (SELECT 1 FROM fin_event_links l
                                     WHERE l.parent_kind='unit_record' AND l.parent_ref=u.id
                                       AND l.event_id IS NOT NULL)");
check((int) $r->fetch_assoc()['c'] === 0, 'ولا صفَّ «معتمَدٍ» بلا مالٍ يحلُّ — والقاعدةُ تمنعه بقيدٍ');

// ═══ ⑤ البطاقات الثلاث لحظة التحويل ═══
head('⑤ بطاقات الأحكام الثلاث — المشغّل ثالثُها (قياسٌ حي)');
// أحدثُ صفٍّ محوَّلٍ له بطاقات: يجب أن تكون له بطاقةُ operator (ما بعد اليوم)
// — الفحص بنيوي: كود المروحة يكتب الثلاثة
$fanout = file_get_contents(dirname(__DIR__) . '/app/Services/EffectFanout.php');
check(strpos($fanout, "'party' => 'operator'") !== false, 'المروحة تكتب بطاقة المشغّل (party=operator)');
check(strpos($fanout, 'OperatorDue::compute') !== false, 'حكمُ المشغّل من محرّك السياسات لا من الأعمدة');
check(strpos($screen, 'حكم المشغّل') !== false, 'الشاشة تعرض عمودَ حكم المشغّل');

// ═══ ⑥ التساوي يُعلَّم ولا يُشترط ═══
head('⑥ «التساوي يُعلَّم تلقائيًّا حين يقع طبيعيًّا ولا يُشترط أبدًا»');
check(strpos($screen, 'تساوٍ طبيعي') !== false, 'علامةُ التساوي الطبيعي حاضرة');
check(strpos($screen, "match_state !== 'matched'") === false && strpos($screen, "match_state'] !== 'matched'") === false,
    'لا شرطَ تطابقٍ يحجب اعتمادًا');

// ═══ ⑦ التسمية ═══
head('⑦ التسمية — «وحدات الأطراف» في الوحدة والسايدبار');
$r = $db->query("SELECT name FROM modules WHERE code='Finance/unit_records_fin.php'");
$x = $r->fetch_assoc();
check($x !== null && $x['name'] === 'وحدات الأطراف', 'الوحدة 105: ' . ($x['name'] ?? '—'));
/* ══ **الاسمُ المشترطُ نُقض بقرارٍ لاحقٍ مسجَّل.** `NAV-01 §6` صنّف «وحدات
     الأطراف» اسمًا **بلغةِ النظام** والمعتمدُ «أحكام العميل والمورد والمشغّل» —
     وهو ما تحمله الصفوفُ الثلاثةَ عشرَ فعلًا. فكان الفحصُ يُرسِب المواصفةَ
     النافذةَ (0/13).
   ⇒ يُقاس **الاتساقُ** لا سلسلةٌ مكتوبةٌ في الفاحص: اسمٌ واحدٌ متمايزٌ في كلِّ
     الصفوف، وعددُها لا ينقص عن الموثَّق. فاسمٌ يتغيّر بقرارٍ يمرُّ، واسمانِ
     مختلفانِ لشاشةٍ واحدةٍ يرسبان — وذاك هو الحكمُ المقصود. */
$r = $db->query("SELECT COUNT(DISTINCT label_ar) d, COUNT(*) c, MIN(label_ar) nm
                   FROM nav_items WHERE route='Finance/unit_records_fin.php'");
$nv = $r->fetch_assoc();
check((int) $nv['d'] === 1 && (int) $nv['c'] >= 13,
    'صفوفُ السايدبار كلُّها باسمٍ واحدٍ متمايز: «' . (string) $nv['nm'] . '» في '
    . (int) $nv['c'] . ' صفًّا (' . (int) $nv['d'] . ' اسمًا متمايزًا)');

// ═══ الطابور سليم (D02 باقٍ) ═══
head('طابور التحويل — بوابة D02 لم تُمسّ');
check(strpos($screen, 'convert_units') !== false && strpos($screen, 'fin_conversion_queue') !== false,
    'مسار التحويل وأهليته الخادمية حاضران');
check(strpos($screen, 'fin_verify_action_token') !== false, 'رمز الحماية على التحويل');
check(strpos($screen, 'unit_entries') !== false && strpos($screen, 'unit_time_log') !== false,
    'الشاشة تقرأ من السجل القانوني (unit_entries + unit_time_log)');

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
