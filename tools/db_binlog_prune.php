<?php
/**
 * db_binlog_prune.php — تنظيفُ السجلّاتِ الثنائيةِ وضبطُ استبقائها
 * ═══════════════════════════════════════════════════════════════════════════
 * لماذا وُجد:
 *   my.ini يفعّل `log_bin=mysql_bin` بإعدادِ خادمِ نسخٍ متماثل:
 *     binlog_format=ROW · binlog_row_image=FULL · expire=1209600 (١٤ يومًا)
 *   وجهازُ التطويرِ لا تابعَ له. فتراكم 1060 م.ب في سبعةِ أيام — أضخمُ بندٍ
 *   في القرصِ كلِّه، وهو **خارجَ القاعدة**: تنظيفُه لا يمسُّ صفًّا واحدًا.
 *
 * حارسٌ قبلَ الفعل: لو وُجد تابعُ نسخٍ متماثلٍ متصل، تتوقّف الأداةُ ولا تُنظّف —
 *   لأنّ السجلّاتِ حينئذٍ ليست فائضةً بل قناةُ النسخ.
 *
 * التشغيل:
 *   php tools/db_binlog_prune.php              # عرضٌ فقط (الافتراضي)
 *   php tools/db_binlog_prune.php --apply      # تنظيفٌ فعليّ
 *   php tools/db_binlog_prune.php --apply --expire-days=1
 *
 * ملاحظة: `SET GLOBAL` فعّالٌ فورًا لكنه لا ينجو من إعادةِ تشغيلِ الخادم.
 *   للثبات، عدِّلْ binlog_expire_logs_seconds في my.ini (يحتاج إعادةَ تشغيل).
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

$ROOT = dirname(__DIR__);

$apply      = in_array('--apply', $argv, true);
$expireDays = 1;
foreach ($argv as $a) {
    if (preg_match('/^--expire-days=(\d+)$/', $a, $m)) {
        $expireDays = max(1, (int) $m[1]);
    }
}

// نقرأ .env مباشرةً — config.php يبتلع مخرَجَ CLI.
$env = array();
foreach (file($ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || $line[0] === ';' || !str_contains($line, '=')) {
        continue;
    }
    list($k, $v) = explode('=', $line, 2);
    $env[trim($k)] = trim($v, " \t\"'");
}

list($host, $port) = array_pad(explode(':', $env['DB_HOST'] ?? 'localhost'), 2, 3306);

$m = @new mysqli($host, $env['DB_ADMIN_USER'] ?? 'root', $env['DB_ADMIN_PASS'] ?? '', $env['DB_NAME'] ?? '', (int) $port);
if ($m->connect_errno) {
    fwrite(STDERR, "تعذَّر الاتصال بحسابِ الإدارة: {$m->connect_error}\n");
    exit(2);
}

$measure = static function (mysqli $m): array {
    $r = $m->query('SHOW BINARY LOGS');
    if (!$r) {
        return array(0, 0);
    }
    $n = 0;
    $t = 0;
    while ($x = $r->fetch_assoc()) {
        $n++;
        $t += (int) $x['File_size'];
    }
    return array($n, $t);
};

list($n0, $t0) = $measure($m);

$cfg = $m->query('SELECT @@log_bin AS lb, @@server_id AS sid, @@binlog_expire_logs_seconds AS s')->fetch_assoc();
printf("log_bin        : %s\n", $cfg['lb'] ? 'مفعَّل' : 'معطَّل');
printf("server_id      : %s\n", $cfg['sid']);
printf("الاستبقاء الحالي: %.0f يومًا\n", $cfg['s'] / 86400);
printf("السجلّات        : %d ملفًّا · %.1f م.ب\n", $n0, $t0 / 1048576);

// ◆ الحارس: تابعٌ متصلٌ يعني أنّ السجلّاتِ قناةُ نسخٍ لا فائض.
$sl = $m->query('SHOW REPLICA HOSTS');
$slaves = $sl ? $sl->num_rows : 0;
printf("توابعُ متصلة    : %d\n", $slaves);
if ($slaves > 0) {
    fwrite(STDERR, "\n⛔ يوجد تابعُ نسخٍ متماثلٍ متصل — التنظيفُ يكسر قناتَه. توقّفتُ.\n");
    exit(3);
}

if (!$apply) {
    printf("\nعرضٌ فقط — سيُحرَّر نحو %.1f م.ب. أضِفْ --apply للتنفيذ.\n", $t0 / 1048576);
    exit(0);
}

/* ◆ التدويرُ قبلَ التنظيف — وإلا حُرِّر صفر:
   `PURGE … BEFORE NOW()` لا يحذف السجلَّ **النشط** مهما كبُر. و`FLUSH BINARY LOGS`
   يفتح سجلًّا جديدًا فيصير القديمُ (بكلِّ حجمِه) قابلًا للتنظيف. */
if (!$m->query('FLUSH BINARY LOGS')) {
    fwrite(STDERR, "تحذير — تعذَّر التدوير: {$m->error}\n");
}
/* ◆ ولا يُنظَّف بالوقت: `BEFORE NOW()` يقارن بطابعِ تعديلِ الملف، وملفٌّ كُتب
   قبلَ ثوانٍ لا يسبق NOW() بدقّةِ الثانية — فيبقى. الصيغةُ الحتميةُ هي
   `PURGE … TO '<اسم الملفِ النشطِ الآن>'`: تحذف كلَّ ما قبلَه بلا اعتمادٍ
   على ساعةِ نظامِ الملفّات. */
$last = null;
$r = $m->query('SHOW BINARY LOGS');
while ($r && ($x = $r->fetch_assoc())) {
    $last = $x['Log_name'];
}
if ($last === null) {
    fwrite(STDERR, "لا سجلّاتٍ لتُنظَّف\n");
    exit(0);
}
if (!$m->query("PURGE BINARY LOGS TO '" . $m->real_escape_string($last) . "'")) {
    fwrite(STDERR, "فشل التنظيف: {$m->error}\n");
    exit(4);
}

$secs = $expireDays * 86400;
if (!$m->query("SET GLOBAL binlog_expire_logs_seconds = {$secs}")) {
    fwrite(STDERR, "تحذير — تعذَّر ضبطُ الاستبقاء: {$m->error}\n");
}

list($n1, $t1) = $measure($m);
printf("\n✔ بقي %d ملفًّا · %.1f م.ب  ⇐  حُرِّر %.1f م.ب\n", $n1, $t1 / 1048576, ($t0 - $t1) / 1048576);
printf("✔ الاستبقاءُ صار %d يومًا (فعّالٌ فورًا · غيرُ ثابتٍ عبر إعادةِ التشغيل)\n", $expireDays);
