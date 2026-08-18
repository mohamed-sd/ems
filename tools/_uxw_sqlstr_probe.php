<?php
/**
 * tools/_uxw_sqlstr_probe.php — رصدُ وصلٍ نصّيٍّ ميتٍ داخلَ سلسلةٍ مزدوجةِ الاقتباس
 * ═══════════════════════════════════════════════════════════════════════════
 * الشكلُ المعطوب:
 *
 *     $sql = "SELECT … WHERE t.company_id = ? AND (reporter_user_id = ' . $uid . ')";
 *
 * السلسلةُ **مزدوجةُ** الاقتباس، فعلاماتُ الاقتباسِ المفردةُ لا تُنهي شيئًا:
 * الكاتبُ ظنَّ أنه يصلُ متغيّرًا كما يُفعل بسلسلةٍ مفردة، والناتجُ نصٌّ مثل
 * `reporter_user_id = ' . 42 . '` — أي مقارنةُ عمودٍ رقميٍّ بسلسلةٍ تؤول إلى 0.
 * فالشرطُ يصير `= 0` ولا يُطابق شيئًا. **لا خطأَ ولا تحذيرَ يظهر.**
 *
 * الكشفُ بمُعجِمِ PHP نفسِه لا بـregex: كلُّ سلسلةٍ مزدوجةٍ (مُقحَمةً أو ثابتة)
 * يُفحَص نصُّها الخام — فإن حوى وصلًا بعلامةِ اقتباسٍ مفردةٍ حولَ متغيّرٍ أو
 * حولَ نقطةِ وصلٍ، فهو نصٌّ ميتٌ لا تعبير. وهذا يستبعد تلقائيًّا الوصلَ
 * المشروعَ `" . $where . "` لأنه خارجُ السلسلةِ لا داخلَها.
 *
 * مسبارُ قراءةٍ فقط.
 */
$ROOT = str_replace(chr(92), '/', dirname(__DIR__));
$hits = 0; $files = array();
$SQLWORD = '/\b(SELECT|WHERE|FROM|INSERT|UPDATE|DELETE|AND|OR|JOIN|SET)\b/i';

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $f) {
    $p = str_replace(chr(92), '/', $f->getPathname());
    if (!preg_match('/\.php$/', $p)) continue;
    if (preg_match('#/(vendor|node_modules|\.git|\.claude|storage/backups|tools/)#', $p)) continue;
    $src = @file_get_contents($f->getPathname());
    if ($src === false || strpos($src, "' . $") === false) continue;
    $rel = str_replace($ROOT . '/', '', $p);

    $tokens = @token_get_all($src);
    if (!is_array($tokens)) continue;
    $inDouble = false; $buf = ''; $bufLine = 0;
    foreach ($tokens as $t) {
        if ($t === '"') { // حدُّ سلسلةٍ مزدوجةٍ مُقحَمة
            if ($inDouble) {
                if (preg_match("/'\s*\.\s*\\\$/", $buf) && preg_match($SQLWORD, $buf)) {
                    echo $rel, ':', $bufLine, "\n    ", trim(preg_replace('/\s+/', ' ', mb_substr($buf, 0, 170))), "\n";
                    $hits++; $files[$rel] = true;
                }
                $inDouble = false; $buf = '';
            } else { $inDouble = true; $buf = ''; }
            continue;
        }
        if (is_array($t)) {
            if ($inDouble) {
                if ($bufLine === 0) { $bufLine = $t[2]; }
                $buf .= $t[1];
                continue;
            }
            // سلسلةٌ مزدوجةٌ ثابتةٌ بلا إقحام
            if ($t[0] === T_CONSTANT_ENCAPSED_STRING && $t[1] !== '' && $t[1][0] === '"') {
                if (preg_match("/'\s*\.\s*\\\$/", $t[1]) && preg_match($SQLWORD, $t[1])) {
                    echo $rel, ':', $t[2], "\n    ", trim(preg_replace('/\s+/', ' ', mb_substr($t[1], 0, 170))), "\n";
                    $hits++; $files[$rel] = true;
                }
            }
        } elseif ($inDouble) {
            $buf .= $t;
        }
        if (!$inDouble) { $bufLine = 0; }
    }
}
echo "\n═══ وصلٌ نصّيٌّ ميتٌ داخلَ سلسلةِ SQL مزدوجة: ", $hits, " في ", count($files), " ملفًّا ═══\n";
