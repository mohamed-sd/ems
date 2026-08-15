<?php
/**
 * 2027_04_13_swap_request_repairable.php
 * ═══════════════════════════════════════════════════════════════════════════
 * طلبُ التبديلِ يصير قابلًا للإنشاءِ ولا يتضاعف — ⇐ INJ-0140
 *
 * نصُّ القبول: «إعادةُ إرسال الفورم نفسِه لا تنشئ صفًّا ثانيًا وتُعيد رسالةً
 * تشير إلى الصف القائم».
 *
 * ── والمقيسُ أخطرُ ممّا وُصف ───────────────────────────────────────────────
 * وُصف العيبُ بأنَّ المعالجَ «يُدرج صفًّا في كل POST بلا مفتاحِ عطالة». والقياسُ
 * الحيُّ لجملةِ الشاشةِ حرفًا:
 *     execute = false · errno = 1048 · Column 'covering_supplier_id' cannot be null
 * فالجملةُ تمرّر `NULL` صراحةً لعمودٍ `NOT NULL` — **فلم تُنشأ ولا مرةً واحدة**.
 * ولا فرعَ `else` في المعالج، فالفشلُ يمضي صامتًا: يضغط المستخدمُ «أرسل» فلا
 * يظهر خطأٌ ولا صفّ. **والعطالةُ لا تُبنى فوق فعلٍ لا يقع.**
 *
 * ── والحكمُ في العمود ─────────────────────────────────────────────────────
 * ◆ **الجهةُ المغطّيةُ لا تُعرف عند الطلب**: يطلب مسؤولُ الموقعِ بديلًا، وتُحسم
 *   جهةُ التغطيةِ عند الاعتماد بحسبِ الدرجة. فالعمودُ صار `NULL`-قابلًا —
 *   وهذا **تصحيحُ نموذجٍ** لا تليينُ قيد: قيدٌ يمنع الميلادَ ليس حارسًا.
 * ◆ **ولا يتضاعف الطلب**: قيدٌ فريدٌ على (الكيان · المقعد · السبب · المدة ·
 *   الدرجة) — فإعادةُ الإرسالِ يردُّها **القاعدة** لا فحصٌ في PHP يُهزَم بطلبين
 *   متزامنين (CS-11).
 * ◆ ويُقاس التكرارُ القائمُ قبلَ فرضِ القيد، ولا يُفرض فوق بيانةٍ تخالفه.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_USER') : ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_PASS') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

echo "══ طلبُ التبديلِ يصير قابلًا للإنشاءِ ولا يتضاعف ══\n\n";

/* ① الجهةُ المغطّيةُ تُحسم عند الاعتمادِ لا عند الطلب */
$r = $conn->query("SHOW COLUMNS FROM substitute_coverages LIKE 'covering_supplier_id'");
$col = $r ? $r->fetch_assoc() : null;
if ($col && $col['Null'] === 'NO') {
    if ($conn->query("ALTER TABLE substitute_coverages
                      MODIFY `covering_supplier_id` INT NULL
                      COMMENT 'INJ-0140: تُحسم عند الاعتماد بحسب الدرجة — لا تُعرف عند الطلب'")) {
        echo "  ✔ covering_supplier_id صار NULL-قابلًا — فالطلبُ يُنشأ قبلَ حسمِ المغطّي\n";
    } else {
        echo "  ✘ تعذّر التعديل: {$conn->error}\n";
    }
} else {
    echo "  · covering_supplier_id NULL-قابلٌ سلفًا\n";
}

/* ② ولا يتضاعف الطلبُ نفسُه */
$r = $conn->query("SHOW INDEX FROM substitute_coverages WHERE Key_name = 'uq_cov_request'");
if ($r && $r->num_rows > 0) {
    echo "  · uq_cov_request قائمٌ سلفًا\n";
} else {
    $d = $conn->query("SELECT COUNT(*) FROM (
            SELECT company_id, covered_seat_id, reason_code, valid_from, valid_to, level
              FROM substitute_coverages GROUP BY 1,2,3,4,5,6 HAVING COUNT(*) > 1) x");
    $dups = ($d && ($y = $d->fetch_row())) ? (int) $y[0] : -1;
    if ($dups !== 0) {
        echo "  ⚠ {$dups} مجموعةً مكرَّرةً في البيانةِ القائمة — لا يُفرض القيدُ فوقها ويُعلَن\n";
    } elseif ($conn->query("ALTER TABLE substitute_coverages ADD UNIQUE KEY `uq_cov_request`
                            (`company_id`, `covered_seat_id`, `reason_code`, `valid_from`, `valid_to`, `level`)")) {
        echo "  ✔ UNIQUE(الكيان · المقعد · السبب · المدة · الدرجة) — «إعادةُ الإرسالِ لا تنشئ صفًّا ثانيًا»\n";
    } else {
        echo "  ✘ تعذّر القيد: {$conn->error}\n";
    }
}

echo "\n✔ تمّت\n";
