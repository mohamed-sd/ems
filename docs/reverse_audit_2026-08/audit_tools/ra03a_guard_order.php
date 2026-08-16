<?php
/**
 * ra03a_guard_order.php — فاحصٌ مستقلٌّ لترتيبِ «الأثرِ قبل الحارس» (قراءةٌ فقط)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ لا يثق بأدواتِ المستودع: يعيد قياسَ الادعاءِ المركزيِّ لتدقيقِ 2026-08-09
 *   (كتاباتٌ تقع قبل بلوغِ الحارس) على **كلِّ** ملفِّ شاشةٍ حيّ.
 * ◆ الحارسُ أيٌّ من: enforce_current_page_view_permission | تضمين insidebar
 *   (يستدعيه في سطره 13) | ems_post_contract | check_page_permissions.
 * ◆ ويقيس معه مقياسًا ثانيًا: الكتابةُ المباشرةُ في الشاشةِ متجاوزةً خدمةَ
 *   المجال (INSERT/UPDATE/DELETE في ملفِّ واجهة).
 * المخرَج: evidence/guard_order.json + خلاصة.
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = 'C:/wamp64/www/ems';
$EV   = $ROOT . '/docs/reverse_audit_2026-08/evidence';

/* مجلداتُ الواجهة: من مساراتِ modules الحية + main (لا قائمةَ يدوية) */
$db = @mysqli_connect('127.0.0.1', 'root', '', 'equipation_manage', 3307);
$dirs = ['main' => true];
if ($db) {
    $r = $db->query("SELECT DISTINCT SUBSTRING_INDEX(code,'/',1) d FROM modules WHERE code LIKE '%/%'");
    while ($x = $r->fetch_row()) { $dirs[$x[0]] = true; }
}
unset($dirs['includes'], $dirs['app'], $dirs['tools'], $dirs['tests'], $dirs['database'], $dirs['assets'], $dirs['vendor']);

$writeRe = '/\b(INSERT\s+INTO|UPDATE\s+[`a-z_]+\s+SET|DELETE\s+FROM|REPLACE\s+INTO)\b/i';
/* الحراسُ المعتمَدون بعد التحقُّقِ الفرديِّ من كلِّ نمطٍ في الكودِ الحي:
   حارسُ الشاشة · حارسُ المعالج · عقدُ POST · حارسُ السلطةِ الماليةِ (رفضُه
   409 هو ما يسبق كتابةَ التصعيد) · حارسُ super-admin · حارسُ الكرون ·
   الاستعلامُ اليدويُّ عن can_* (نمطُ penalties) */
$guardRe = '/enforce_current_page_view_permission|include\s*[\'"][^\'"]*insidebar\.php|require[^;]*insidebar\.php|ems_post_contract|check_page_permissions|ems_require_permission|AuthorityGuard::sign|ems_guard_handler|super_admin_require_login|ems_cron_guard|rp\.can_(view|add|edit|delete)/';

$out = ['scanned' => 0, 'no_write' => 0, 'guarded_before_write' => 0,
        'write_before_guard' => [], 'write_no_guard' => [],
        'direct_write_screens' => 0, 'central_ajax_only' => 0, 'dirs' => array_keys($dirs)];

foreach (array_keys($dirs) as $d) {
    $dirAbs = $ROOT . '/' . $d;
    if (!is_dir($dirAbs)) { continue; }
    foreach (glob($dirAbs . '/*.php') as $f) {
        $rel = $d . '/' . basename($f);
        $src = file_get_contents($f);
        /* تُنزع التعليقاتُ والنصوصُ حتى لا يشهد تعليقٌ على كتابةٍ لا تقع */
        $tok = token_get_all($src);
        $code = ''; $map = [];   /* خريطة: موضعُ الحرفِ في code ⇒ رقمُ السطر */
        foreach ($tok as $t) {
            if (is_array($t)) {
                [$id, $txt, $line] = $t;
                if (in_array($id, [T_COMMENT, T_DOC_COMMENT], true)) { continue; }
                if ($id === T_CONSTANT_ENCAPSED_STRING || $id === T_ENCAPSED_AND_WHITESPACE) {
                    /* النصوصُ تبقى — كتاباتُ SQL تعيش داخلَها */
                }
                $map[strlen($code)] = $line;
                $code .= $txt;
            } else { $code .= $t; }
        }
        $lineAt = function (int $pos) use ($map): int {
            $best = 1;
            foreach ($map as $p => $ln) { if ($p <= $pos) { $best = $ln; } else { break; } }
            return $best;
        };

        $out['scanned']++;
        /* ملفاتُ "_" مساعداتٌ تُضمَّن داخلَ شاشاتٍ محروسة — كتابتُها داخل دوالَّ
           لا تُنفَّذ بالوصولِ المباشر؛ تُعَدُّ على حدةٍ ولا تُتَّهم */
        if (basename($f)[0] === '_') { $out['helper_files'][] = $rel; continue; }
        $isAjax = (bool) preg_match('/^(get_|ajax_)|_handler\.php$|_ajax\.php$/', basename($f));

        $wPos = preg_match($writeRe, $code, $wm, PREG_OFFSET_CAPTURE) ? $wm[0][1] : null;
        $gPos = preg_match($guardRe, $code, $gm, PREG_OFFSET_CAPTURE) ? $gm[0][1] : null;

        if ($wPos === null) { $out['no_write']++; continue; }
        $out['direct_write_screens']++;

        if ($gPos !== null && $gPos < $wPos) { $out['guarded_before_write']++; continue; }
        $rec = ['file' => $rel, 'write_line' => $lineAt($wPos),
                'guard_line' => $gPos !== null ? $lineAt($gPos) : null,
                'ajax_central' => $isAjax];
        if ($gPos === null) {
            if ($isAjax) { $out['central_ajax_only']++; }
            $out['write_no_guard'][] = $rec;
        } else {
            $out['write_before_guard'][] = $rec;
        }
    }
}

@mkdir($EV, 0777, true);
file_put_contents($EV . '/guard_order.json', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
printf("فُحص: %d ملفًّا في %d مجلدًا\n", $out['scanned'], count($out['dirs']));
printf("بلا كتابة مباشرة: %d · كتابة بعد الحارس: %d\n", $out['no_write'], $out['guarded_before_write']);
printf("شاشات تكتب مباشرة (تجاوز خدمة): %d\n", $out['direct_write_screens']);
printf("⚠ كتابة قبل الحارس: %d · ⚠ كتابة بلا حارس إطلاقًا: %d (منها AJAX مركزي: %d)\n",
    count($out['write_before_guard']), count($out['write_no_guard']), $out['central_ajax_only']);
foreach (array_slice($out['write_before_guard'], 0, 12) as $r) {
    printf("  قبل⇐ %s (كتابة س%d · حارس س%s)\n", $r['file'], $r['write_line'], $r['guard_line'] ?? '—');
}
foreach (array_slice(array_filter($out['write_no_guard'], fn($r) => !$r['ajax_central']), 0, 12) as $r) {
    printf("  بلا⇐ %s (كتابة س%d)\n", $r['file'], $r['write_line']);
}
