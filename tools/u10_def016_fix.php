<?php
/**
 * tools/u10_def016_fix.php — تصحيح المسميات القيادية في تصميم الأعمدة (DEF-016)
 * ───────────────────────────────────────────────────────────────────────────
 * الملف الحي tmp_SCRDES.xlsx يحمل خلايا أعمدةٍ بمسمى «المدير التنفيذي» —
 * والهيكل النافذ (CORE-01 §6): «الرئيس التنفيذي». التصحيح عبر مسار التغيير:
 * نسخة احتياطية ثم استبدال الخلايا الكاملة المطابقة حرفيًّا فقط (لا نصوص أطول).
 * php tools/u10_def016_fix.php [--apply]
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
require_once dirname(__DIR__) . '/vendor/autoload.php';
$ROOT = dirname(__DIR__);
$APPLY = in_array('--apply', $argv, true);
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };

$F = $ROOT . '/tmp_SCRDES.xlsx';
$BAK = $ROOT . '/docs/update0010/tmp_SCRDES-backup-def016-20260806.xlsx';
$FROM = 'المدير التنفيذي';
$TO = 'الرئيس التنفيذي';

$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($F);
$wb = $reader->load($F);
$hits = array();
foreach ($wb->getSheetNames() as $sn) {
    $sh = $wb->getSheetByName($sn);
    foreach ($sh->getRowIterator() as $row) {
        foreach ($row->getCellIterator() as $cell) {
            $v = $cell->getValue();
            $txt = $v instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText ? $v->getPlainText() : (is_string($v) ? $v : null);
            if ($txt !== null && trim($txt) === $FROM) {
                $hits[] = "[$sn] " . $cell->getCoordinate();
                if ($APPLY) { $cell->setValue($TO); }
            }
        }
    }
}
$o('══ DEF-016 — ' . ($APPLY ? 'APPLY' : 'DRY-RUN') . ' · خلايا كاملة مطابقة: ' . count($hits) . ' ══');
foreach ($hits as $h) { $o('  ' . $h); }
if ($APPLY && $hits) {
    if (!is_file($BAK)) { copy($F, $BAK); $o('⓪ نسخة احتياطية: ' . basename($BAK)); }
    \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($wb, 'Xlsx')->save($F);
    $o('✔ حُفظ الملف الحي مصححًا — أعد تشغيل cmp03_gov_check للتحقق');
}
