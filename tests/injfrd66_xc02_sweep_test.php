<?php
/**
 * tests/injfrd66_xc02_sweep_test.php — شاهدُ XC-02 ②: كنسُ المصدر
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «صفرُ صيغةٍ ممنوعةٍ في دفتر الدورة · **لا في المخرَجِ وحده**»
 *   — فالقياسُ على النصِّ المخزونِ في الطبقاتِ الأربعِ التي تُرسم منها القائمة.
 *
 * ◆ **إيجابيٌّ** (①–④): صفرُ خرقٍ في كلِّ سطحٍ مخزون.
 * ◆ **سالبٌ**   (⑤): الكاشفُ **يرصد** خرقًا مزروعًا في نسخةٍ مؤقتةٍ ثم يُزال —
 *   فلا يُقبل «صفرٌ» من كاشفٍ عاجزٍ عن الرصدِ أصلًا. وهذا هو الفرقُ بين
 *   «لا خرقَ» و«لا أرى» — وقد كلّف خلطُهما جولاتٍ سابقة.
 * ◆ **⑥ سالبٌ بنيويّ**: بوابةُ الحفظِ ما تزال تُصادق على إعادةِ التسمية —
 *   فكنسُ `old_names` لم يُضعِفها. وهو الشرطُ الذي بلا استيفائِه يُرسِّب
 *   الكنسُ كلَّ التزامٍ في كلِّ الجلسات.
 *
 * ◆ **والمحجوزُ يُعلَن ولا يُبتلع**: صفٌّ واحدٌ بحالة PENDING_OWNER لا مصدرَ
 *   معتمَدًا يُشتقُّ منه — يُطبع بسببِه ولا يُعدُّ نجاحًا ولا رسوبًا.
 *
 * التشغيل: php tests/injfrd66_xc02_sweep_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/conv_form_detect.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$FB = 'خارج الوثيقة|بانتظار المالك|بانتظار قرار المالك|إضافات المالك|إضافاتُ المالك'
    . '|إضافات للمالك|Activation Pattern|Visibility Guard';

$pass = 0; $fail = 0; $held = 0;
$one = static function (string $title, string $sql, int $expect) use ($conn, &$pass, &$fail): int {
    $r = @mysqli_query($conn, $sql);
    $n = $r ? (int) mysqli_fetch_row($r)[0] : -1;
    if ($n === $expect) { $pass++; printf("   ✔ %-52s = %d\n", $title, $n); }
    else { $fail++; printf("   ✘ %-52s = %d (توقّعتُ %d)\n", $title, $n, $expect); }
    return $n;
};

echo "① إيجابيٌّ — الطبقاتُ المخزونةُ التي تُرسم منها القائمة:\n";
$one('nav_canonical_current · مصطلحٌ ممنوع',
    "SELECT COUNT(*) FROM nav_canonical_current WHERE cur_group REGEXP '{$FB}' OR cur_label REGEXP '{$FB}'", 0);
$one('nav_canonical.old_names · صيغةٌ محادثية',
    "SELECT COUNT(*) FROM nav_canonical WHERE old_names REGEXP 'نبدأ|نسجّل|نوزّع|نوقّع|نحاسب|نتفاوض|نفوتر|نتابع|نراجع|نراقب'", 0);
$one('gov_screen_cycle · لفظٌ متقاعد «حاوية»',
    "SELECT COUNT(*) FROM gov_screen_cycle WHERE CONCAT_WS('|',stage_name,group_name,screen_title,output_doc,inputs_note) LIKE BINARY '%حاوي%'", 0);
$one('gov_screen_cycle · مصطلحٌ ممنوع',
    "SELECT COUNT(*) FROM gov_screen_cycle WHERE CONCAT_WS('|',stage_name,group_name,screen_title,output_doc,inputs_note,next_state,consumers) REGEXP '{$FB}'", 0);

/* ── ② الصيغةُ المحادثيةُ في الدفترِ بالكاشفِ الحيِّ لا بـREGEXP يدويّ ──── */
echo "\n② إيجابيٌّ — دفترُ الدورةِ بالكاشفِ المشترك:\n";
$hits = array();
$res = @mysqli_query($conn, "SELECT id, stage_name, group_name, screen_title FROM gov_screen_cycle");
while ($res && ($x = mysqli_fetch_assoc($res))) {
    foreach (array('stage_name', 'group_name', 'screen_title') as $c) {
        if (ems_is_conversational((string) $x[$c])) { $hits[] = "#{$x['id']} {$c}=«{$x[$c]}»"; }
    }
}
if ($hits) { $fail++; printf("   ✘ %d إصابةً محادثيةً في الدفتر\n", count($hits)); foreach ($hits as $h) { echo "      {$h}\n"; } }
else { $pass++; echo "   ✔ صفرُ صيغةٍ محادثيةٍ في دفتر الدورة\n"; }

/* ── ③ المحجوزُ يُعلَن ───────────────────────────────────────────────── */
echo "\n③ محجوزٌ بسببٍ مكتوب:\n";
$res = @mysqli_query($conn, "SELECT route, status, owner_dept, group_name FROM nav_canonical WHERE group_name REGEXP '{$FB}'");
while ($res && ($x = mysqli_fetch_assoc($res))) {
    $held++;
    printf("   ⏸ %s — %s · مالكُه «%s» · لا مجموعةَ معتمَدةً يُشتقُّ منها\n",
        $x['route'], $x['status'], $x['owner_dept']);
}
if ($held === 0) { echo "   (لا محجوز)\n"; }

/* ── ④ سالبٌ: الكاشفُ يرصد خرقًا مزروعًا ──────────────────────────────── */
echo "\n④ سالبٌ — الكاشفُ يجب أن يرصدَ خرقًا مزروعًا (وإلا فصفرُه عمًى لا نظافة):\n";
$probe = array(
    'إضافاتُ المالك'   => 'مصطلحٌ ممنوع',
    'نبدأ من العميل'   => 'صيغةٌ محادثية',
    'حاوياتها'          => 'لفظٌ متقاعد',
);
foreach ($probe as $needle => $kind) {
    $seen = ($kind === 'صيغةٌ محادثية')
        ? ems_is_conversational($needle)
        : (bool) preg_match($kind === 'مصطلحٌ ممنوع' ? '/' . $FB . '/u' : '/حاوي/u', $needle);
    if ($seen) { $pass++; printf("   ✔ رُصد %-16s «%s»\n", $kind, $needle); }
    else { $fail++; printf("   ✘ أفلتَ %-16s «%s» — الكاشفُ أعمى\n", $kind, $needle); }
}

/* ── ⑤ سالبٌ بنيويّ: بوابةُ الحفظِ لم تضعُف بكنسِ old_names ────────────── */
echo "\n⑤ سالبٌ بنيويّ — بوابةُ الحفظِ بعدَ كنسِ old_names:\n";
$out = array(); $rc = 0;
exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/uxui_preserve_check.php') . ' --gate 2>&1', $out, $rc);
$txt = implode("\n", $out);
/* الإجماليُّ من سطرِ الإجماليِّ وحدَه — فالسطورُ الدوريةُ تحمل الرقمَ نفسَه
   اسمًا فيُقرأ أوّلُها إجماليًّا وهو ليس به (خطأُ «رقمٍ منقولٍ» بعينِه) */
$renamed = preg_match('/الإجمالي:.*?أُعيدت تسميتُه بالسجل=(\d+)/us', $txt, $m) ? (int) $m[1] : -1;
if ($rc === 0 && mb_strpos($txt, 'صفرُ فقدٍ غيرِ مصرَّح') !== false && $renamed > 0) {
    $pass++; printf("   ✔ البوابةُ تعبر · وإعادةُ التسميةِ ما تزال مُصادَقةً بالسجل (%d اسمًا)\n", $renamed);
} else {
    $fail++; printf("   ✘ البوابةُ رسبت (rc=%d · مُصادَقٌ=%d) — كنسُ old_names أضعفها\n", $rc, $renamed);
}

printf("\n%s  ناجح %d · راسب %d · محجوز %d\n",
    $fail === 0 ? '✔ XC-02②' : '✘ XC-02②', $pass, $fail, $held);
exit($fail === 0 ? 0 : 1);
