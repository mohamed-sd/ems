<?php
/**
 * database/seeds/con02_activate.php
 * ═══════════════════════════════════════════════════════════════════════════
 * التفعيلُ الكامل بأمرٍ واحد — يشغّل المراحلَ الأربعَ بالترتيب:
 *   ① البذر (con02_seed)      ② رفعُ العَلَم EMS_ATTRIBUTION_MATRIX=on
 *   ③ الواقعةُ الحية          ④ أولُ مستخلص
 *
 * التشغيل: php database/seeds/con02_activate.php
 * الرجوع : php database/seeds/con02_rollback.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

$here = __DIR__;
$env  = dirname(__DIR__, 2) . '/.env';
$php  = PHP_BINARY;

function step($label, $cmd) {
    fwrite(STDOUT, "\n══════ {$label}\n");
    $out = array(); $rc = 0;
    exec($cmd . ' 2>&1', $out, $rc);
    foreach ($out as $l) { fwrite(STDOUT, $l . "\n"); }
    if ($rc !== 0) { fwrite(STDERR, "\n✘ توقّف عند: {$label} (rc={$rc})\n"); exit($rc); }
}

step('① البذر', escapeshellarg($php) . ' ' . escapeshellarg($here . '/con02_seed.php'));

fwrite(STDOUT, "\n══════ ② رفعُ العَلَم\n");
$txt = file_get_contents($env);
$new = preg_replace('/^EMS_ATTRIBUTION_MATRIX\s*=.*$/m', 'EMS_ATTRIBUTION_MATRIX=on', $txt);
file_put_contents($env, $new);
fwrite(STDOUT, "   EMS_ATTRIBUTION_MATRIX=on\n");

step('③ الواقعةُ الحية', escapeshellarg($php) . ' ' . escapeshellarg($here . '/con02_live_event.php'));
step('④ أولُ مستخلص',   escapeshellarg($php) . ' ' . escapeshellarg($here . '/con02_claim.php'));

fwrite(STDOUT, "\n✔ التفعيلُ تامّ. الرجوع: php database/seeds/con02_rollback.php\n");
