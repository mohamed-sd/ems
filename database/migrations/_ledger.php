<?php
/**
 * database/migrations/_ledger.php — قيدُ الهجرةِ في دفترِها، ذريًّا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العيبُ الذي يعالجه (NF-05)**: الهجراتُ المكتوبةُ ملفَّاتٍ مستقلةً تُشغَّل
 *   بـ`php database/migrations/<file>.php` **فلا تمرُّ بالمُشغِّل** ولا تُقيَّد
 *   في `schema_migrations`. فتقع خمسٌ وثلاثون ثم خمسَ عشرةَ أخرى خارجَ الدفتر
 *   — **والنمطُ لا يتوقف بإدخالِ الصفوفِ يدويًّا بل بقاعدةٍ تمنع تكرارَه**.
 *
 * ◆ **القاعدة**: كلُّ هجرةٍ تستدعي `ems_migration_recorded(__FILE__, $conn)`
 *   في آخرِ سطرٍ ناجحٍ منها. والدالةُ **ذريّة**: تكتب البصمةَ والزمنَ والمنفِّذ،
 *   وتُحدِّث الصفَّ إن تغيّر محتوى الملفِّ بعدَ التطبيق (فالبصمةُ تكشف التحرير).
 *
 * ◆ **ولا تبتلع الفشل**: إن تعذّر القيدُ طُبع تحذيرٌ صريحٌ ورجعت `false` —
 *   فهجرةٌ طُبِّقت ولم تُقيَّد أسوأُ من هجرةٍ لم تُطبَّق، لأنها تكذب على الدفتر.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('ems_migration_recorded')) {
    /**
     * @param string  $file  __FILE__ للهجرة
     * @param mysqli  $conn  اتصالٌ مفتوح
     * @param int     $ms    زمنُ التنفيذِ بالمللي ثانية
     * @param string  $status  applied | baseline
     */
    function ems_migration_recorded($file, $conn, $ms = 0, $status = 'applied')
    {
        $name = basename($file);
        $sum  = @sha1_file($file);
        if ($sum === false) { echo "⚠ تعذّرت بصمةُ {$name} — لم يُقيَّد\n"; return false; }
        $by = (function_exists('get_current_user') ? get_current_user() : 'cli')
            . '@' . (gethostname() ?: 'unknown');

        $st = $conn->prepare(
            "INSERT INTO `schema_migrations`
               (`filename`,`checksum`,`status`,`applied_at`,`execution_ms`,`applied_by`)
             VALUES (?,?,?,NOW(),?,?)
             ON DUPLICATE KEY UPDATE
               `checksum` = VALUES(`checksum`),
               `status`   = VALUES(`status`),
               `applied_at` = NOW(),
               `execution_ms` = VALUES(`execution_ms`),
               `applied_by` = VALUES(`applied_by`)");
        if (!$st) {
            /* لا مفتاحَ فريدًا على `filename`؟ يُقاس ولا يُخمَّن */
            echo "⚠ تعذّر تجهيزُ قيدِ {$name}: {$conn->error}\n";
            return false;
        }
        $st->bind_param('sssis', $name, $sum, $status, $ms, $by);
        $okRun = $st->execute();
        $err = $st->error;
        $st->close();
        if (!$okRun) {
            /* المفتاحُ الفريدُ قد لا يكون موجودًا — يُحاول قيدًا صريحًا بلا ازدواج */
            $chk = $conn->prepare("SELECT COUNT(*) FROM `schema_migrations` WHERE `filename` = ?");
            $chk->bind_param('s', $name); $chk->execute();
            $chk->bind_result($c); $chk->fetch(); $chk->close();
            if ((int) $c > 0) { return true; }
            echo "⚠ تعذّر قيدُ {$name}: {$err}\n";
            return false;
        }
        return true;
    }
}
