<?php
/**
 * ra05b_perm_reconcile.php — مضاهاةُ المنحِ المعلَنةِ بالسلوكِ الحيِّ المقيس
 * ═══════════════════════════════════════════════════════════════════════════
 * لكلِّ (دورٍ، شاشةٍ) مسحُها ra05: المتوقَّعُ من role_permissions.can_view،
 * والواقعُ من رمزِ الاستجابة (200 = صُيِّرت · تحويلٌ إلى dashboard = رُفضت).
 *   ✔ ممنوحةٌ وصُيِّرت      ✔ محجوبةٌ ورُفضت
 *   ✘ ممنوحةٌ ورُفضت  ⇒ شاشةٌ مكسورةٌ لمالكِها (عطبُ تسليم)
 *   ✘ محجوبةٌ وصُيِّرت ⇒ ثغرةُ صلاحيات (عطبُ أمن)
 * التحويلاتُ لغيرِ اللوحة (معاملٌ ناقص) تُفصل: «غيرُ قابلةٍ للحكم بلا معامل».
 * المخرَج: evidence/perm_reconcile.json
 */
declare(strict_types=1);
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = 'C:/wamp64/www/ems';
$EV   = $ROOT . '/docs/reverse_audit_2026-08/evidence';
$db = mysqli_connect('127.0.0.1', 'root', '', 'equipation_manage', 3307);
$db->set_charset('utf8mb4');

$roles = ['admin' => 1, 'reader' => 22];
$out = [];

foreach ($roles as $tag => $roleId) {
    /* المنح المعلنة لهذا الدور */
    $grant = [];
    $r = $db->query("SELECT m.code FROM role_permissions rp JOIN modules m ON m.id=rp.module_id
                     WHERE rp.role_id={$roleId} AND rp.can_view=1 AND m.code LIKE '%.php'");
    while ($x = $r->fetch_row()) { $grant[str_replace('\\', '/', $x[0])] = true; }

    $agg = ['grant_render' => 0, 'grant_denied' => [], 'nogranт_denied' => 0,
            'nogrant_render' => [], 'param_redirect' => 0, 'error' => [], 'total' => 0];
    foreach (file($EV . '/live_http_' . $tag . '.jsonl', FILE_IGNORE_NEW_LINES) as $l) {
        $rec = json_decode($l, true);
        if (!$rec) { continue; }
        $p = $rec['path']; $g = isset($grant[$p]);
        $agg['total']++;
        if ($rec['code'] >= 500 || $rec['php_error']) { $agg['error'][] = $p; }
        if ($rec['code'] === 200) {
            if ($g) { $agg['grant_render']++; }
            else { $agg['nogrant_render'][] = $p; }
        } elseif (in_array($rec['code'], [301, 302, 303], true)) {
            $toDash = stripos((string) $rec['redirect'], 'dashboard') !== false
                   || stripos((string) $rec['redirect'], 'login') !== false;
            if ($toDash) {
                if ($g) { $agg['grant_denied'][] = $p; }
                else { $agg['nogranт_denied']++; }
            } else { $agg['param_redirect']++; }
        }
    }
    $out[$tag] = ['role_id' => $roleId, 'declared_grants' => count($grant)] + $agg;
}

file_put_contents($EV . '/perm_reconcile.json', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
foreach ($out as $tag => $a) {
    printf("═ %s (دور %d) — منح معلنة: %d من %d هدفًا\n", $tag, $a['role_id'], $a['declared_grants'], $a['total']);
    printf("  ✔ ممنوحة وصُيِّرت: %d · ✔ محجوبة ورُفضت: %d · تحويل معامل: %d\n",
        $a['grant_render'], $a['nogranт_denied'], $a['param_redirect']);
    printf("  ✘ ممنوحة ورُفضت (مكسورة): %d · ✘ محجوبة وصُيِّرت (ثغرة): %d · أعطال: %d\n",
        count($a['grant_denied']), count($a['nogrant_render']), count($a['error']));
    foreach (array_slice($a['grant_denied'], 0, 10) as $p) { echo "    مكسورة⇐ $p\n"; }
    foreach (array_slice($a['nogrant_render'], 0, 10) as $p) { echo "    ثغرة⇐ $p\n"; }
}
