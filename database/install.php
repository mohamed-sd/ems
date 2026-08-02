<?php
/**
 * database/install.php — مدخلُ التثبيت من سطر الأوامر (EMS Installer · CLI)
 * ═══════════════════════════════════════════════════════════════════════════
 * المسارُ الأساسي للتثبيت. المنطقُ كلُّه في App\Install\Installer — هذا الملفّ
 * يجمع المدخلاتِ ويعرض النتيجة، لا أكثر.
 *
 * الاستخدام:
 *   php database/install.php --check --config=install.json
 *   php database/install.php --config=install.json
 *   php database/install.php --db-name=ems --company-name="..." ...
 *
 * ملفُّ الإعداد (JSON) أوثقُ من الأعلام حين تحوي القيمُ عربيّةً — بعضُ الصدفات
 * تُفسد ترميزَ argv. مفاتيحُه هي أسماءُ الأعلام بلا `--` وبشرطاتٍ سفلية.
 *
 * لا يقرأ config.php: التثبيتُ يسبق وجودَ .env، فالمُثبِّتُ يفتح اتصالَه بنفسه.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('403 Forbidden — installer CLI entry is CLI-only. استعمل install/index.php للويب.');
}

error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/app/Install/SchemaDumper.php';
require_once dirname(__DIR__) . '/app/Install/Installer.php';

use App\Install\Installer;

// ── تحليل الأعلام ───────────────────────────────────────────────────────────
$flags = array();
$checkOnly = false;
$assumeYes = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--check') {
        $checkOnly = true;
        continue;
    }
    if ($arg === '--yes' || $arg === '-y') {
        $assumeYes = true;
        continue;
    }
    if (preg_match('/^--([a-z0-9\-]+)(?:=(.*))?$/is', $arg, $m)) {
        $key = str_replace('-', '_', strtolower($m[1]));
        $flags[$key] = isset($m[2]) ? $m[2] : '1';
        continue;
    }
    fwrite(STDERR, "[install] وسيطٌ غير مفهوم: {$arg}\n");
    exit(1);
}

// ── ملفّ الإعداد (إن وُجد) — الأعلامُ الصريحةُ تغلبه ─────────────────────────
$cfg = array();
if (isset($flags['config'])) {
    $path = $flags['config'];
    if (!is_file($path)) {
        fwrite(STDERR, "[install] ملفّ الإعداد غير موجود: {$path}\n");
        exit(1);
    }
    $json = json_decode((string) file_get_contents($path), true);
    if (!is_array($json)) {
        fwrite(STDERR, "[install] ملفّ الإعداد ليس JSON صالحًا: {$path}\n");
        exit(1);
    }
    $cfg = $json;
    unset($flags['config']);
}
$cfg = array_merge($cfg, $flags);

// تطبيعُ القيم المنطقية
foreach (array('db_create', 'write_env') as $b) {
    if (isset($cfg[$b])) {
        $cfg[$b] = !in_array(strtolower((string) $cfg[$b]), array('0', 'false', 'no', ''), true);
    }
}
if (isset($cfg['no_env'])) {
    $cfg['write_env'] = false;
    unset($cfg['no_env']);
}

$installer = new Installer($cfg, dirname(__DIR__));

// ── الفحصُ القبلي ───────────────────────────────────────────────────────────
echo "\n";
echo "═══ مُثبِّت EMS — الفحصُ القبلي ═══\n";
echo str_repeat('─', 78) . "\n";

$checks = $installer->preflight();
foreach ($checks as $c) {
    printf("  %s  %-32s %s\n", $c['ok'] ? '✔' : '✘', $c['label'], $c['detail']);
}
echo str_repeat('─', 78) . "\n";

$passed = Installer::passed($checks);
if (!$passed) {
    fwrite(STDERR, "الفحصُ القبليُّ لم يجتز — عالج ما عليه ✘ ثم أعِد المحاولة.\n\n");
    exit(1);
}
echo "اجتاز الفحصُ القبليُّ بالكامل.\n";

if ($checkOnly) {
    echo "(--check: فحصٌ فقط، لم يُنفَّذ شيء.)\n\n";
    exit(0);
}

// ── تأكيدٌ قبل الكتابة ──────────────────────────────────────────────────────
if (!$assumeYes) {
    echo "\nسيُنشأ الآن على القاعدة `" . (isset($cfg['db_name']) ? $cfg['db_name'] : '؟') . "`:\n";
    echo "  • المخطّطُ الكامل ثمّ البذرةُ المرجعية\n";
    echo "  • الشركة: " . (isset($cfg['company_name']) ? $cfg['company_name'] : '؟') . "\n";
    echo "  • حسابُ الدخول: " . (isset($cfg['admin_username']) ? $cfg['admin_username'] : '؟') . "\n";
    echo "\nاكتب `نعم` أو `yes` للمتابعة: ";
    $answer = trim((string) fgets(STDIN));
    if ($answer !== 'نعم' && strtolower($answer) !== 'yes') {
        echo "أُلغي التثبيت. لم يُكتب شيء.\n\n";
        exit(0);
    }
}

// ── التنفيذ ─────────────────────────────────────────────────────────────────
echo "\n═══ التنفيذ ═══\n";
echo str_repeat('─', 78) . "\n";

$result = $installer->install();

foreach ($result['steps'] as $s) {
    printf("  ✔ %-38s %s\n", $s['label'], $s['detail']);
}
echo str_repeat('─', 78) . "\n";

if (!$result['ok']) {
    fwrite(STDERR, "\n✘ فشل التثبيت: {$result['error']}\n");
    fwrite(STDERR, "القاعدةُ قد تكون نصفَ مبنيّة — أسقِطها وأعِد التثبيت من قاعدةٍ فارغة.\n\n");
    exit(1);
}

$s = $result['summary'];
echo "\n✔ اكتمل التثبيت.\n\n";
echo "  القاعدة      : {$s['database']} ({$s['objects']} كائنًا)\n";
echo "  الشركة       : id={$s['company_id']}\n";
echo "  الموظّف       : id={$s['employee_id']}\n";
echo "  الحساب       : {$s['username']} (id={$s['user_id']})\n";
echo "\nالخطوةُ التالية: راجع `.env` ثمّ سجّل الدخول من login.php.\n";
echo "لتغييراتِ المخطَّط لاحقًا: php database/migrate.php up\n\n";
exit(0);
