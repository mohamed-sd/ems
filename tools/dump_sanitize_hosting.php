<?php
/**
 * tools/dump_sanitize_hosting.php — تعقيمُ دمب SQL للاستضافة المشتركة (Hostinger)
 * ───────────────────────────────────────────────────────────────────────────
 * حسابُ الاستضافة لا يملك SET USER فيفشل أي DEFINER ويسقط ما بعده (#1227):
 *   ① ينزع DEFINER=`user`@`host` من المناظير والقوادح والإجراءات والأحداث.
 *   ② يقلب SQL SECURITY DEFINER → INVOKER (المناظير تعمل بهوية المستورِد).
 *   ③ يطبّع ترتيبات MySQL-8 غير المتاحة (utf8mb4_0900_ai_ci → unicode_ci).
 * لا يغيّر شيئًا آخر — البيانات كما هي.
 *
 * php tools/dump_sanitize_hosting.php in.sql [out.sql]   (الافتراض: in.hosting.sql)
 */
if (PHP_SAPI !== 'cli') { die("CLI only\n"); }
$in = $argv[1] ?? null;
if (!$in || !is_file($in)) { fwrite(STDERR, "usage: php tools/dump_sanitize_hosting.php in.sql [out.sql]\n"); exit(1); }
$out = $argv[2] ?? preg_replace('/\.sql$/i', '', $in) . '.hosting.sql';

$src = fopen($in, 'rb');
$dst = fopen($out, 'wb');
$counts = array('definer' => 0, 'security' => 0, 'collate' => 0);
while (($line = fgets($src)) !== false) {
    $n = 0;
    $line = preg_replace('/DEFINER\s*=\s*(`[^`]+`|\'[^\']+\'|\w+)\s*@\s*(`[^`]+`|\'[^\']+\'|[\w.%-]+)\s*/i', '', $line, -1, $n);
    $counts['definer'] += $n;
    $line = preg_replace('/SQL\s+SECURITY\s+DEFINER/i', 'SQL SECURITY INVOKER', $line, -1, $n);
    $counts['security'] += $n;
    $line = str_replace(array('utf8mb4_0900_ai_ci', 'utf8mb4_0900_as_cs'), 'utf8mb4_unicode_ci', $line, $n);
    $counts['collate'] += $n;
    fwrite($dst, $line);
}
fclose($src);
fclose($dst);
fwrite(STDOUT, "✔ عُقّم: {$counts['definer']} DEFINER · {$counts['security']} SQL SECURITY · {$counts['collate']} ترتيب 0900\n");
fwrite(STDOUT, "المخرج: $out — هذا ما يُرفع لهوستينجر\n");
