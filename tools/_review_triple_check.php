<?php
/* القيدُ الجدوليُّ بديلًا عن القادحِ — لا يلزمه SUPER وهو أصلبُ منه */
if (PHP_SAPI !== 'cli') { exit("CLI\n"); }
require_once dirname(__DIR__) . '/includes/env.php';
mysqli_report(MYSQLI_REPORT_OFF);
$h = ems_env('DB_HOST'); $p = 3306;
if (strpos($h, ':') !== false) { list($h, $p) = explode(':', $h); $p = (int) $p; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$w = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$c = new mysqli($h, $u, $w, ems_env('DB_NAME'), $p);
$c->set_charset('utf8mb4');
$c->query("ALTER TABLE gov_independent_reviews DROP CONSTRAINT chk_review_version_triple");
$ok = $c->query("ALTER TABLE gov_independent_reviews
    ADD CONSTRAINT chk_review_version_triple CHECK (
        component_version <> '' AND visual_baseline_version <> ''
        AND CHAR_LENGTH(commit_hash) >= 7
    )");
echo $ok ? "✔ القيدُ الجدوليُّ مضافٌ — شهادةٌ بلا ثلاثيٍّ كاملٍ مرفوضةٌ بنيويًّا\n"
         : ("✗ {$c->error}\n");
