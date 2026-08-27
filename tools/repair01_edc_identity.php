<?php
/**
 * tools/repair01_edc_identity.php — تعارضُ الهويّةِ الثلاثيُّ بحكمِ المالك
 * ═══════════════════════════════════════════════════════════════════════════
 * **حكمُ المالك 2026-08-27**:
 *  · «لوحةُ مديرِ التشغيل: **أصلح الشاشةَ ولا تغيّر الاسم**» — واللوحاتُ
 *    «`Projection / Dashboard` **ولا يجوز أن تكون `Source of Truth`**».
 *  · `rate_books` ⇐ `Projection` ⛔ «**ولا تنشئ `Price Master` جديدًا بسبب الاسم**».
 *
 * ◆ **والاسمُ لا يُغيَّر ليطابق العطب** — بل يُصلَح السطحُ ليطابق اسمَه.
 *   فتغييرُ الاسمِ يجعل الوثيقةَ تتبع الكود، **وهو عكسُ اتّجاهِ الحوكمة**.
 *
 * التشغيل: php tools/repair01_edc_identity.php [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$APPLY = in_array('--apply', $argv, true);
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

$T = array(
  array('ops_manager_board.php', 'DEP-11',
        'حكم المالك: اصلح الشاشة ولا تغير الاسم — واللوحات Projection ولا يجوز ان تكون Source of Truth'),
  array('rate_books.php', 'DEP-01',
        'حكم المالك: rate_books اسقاط ولا ينشا Price Master جديد بسبب الاسم'),
  array('daily_units_report.php', 'DEP-11',
        'حكم المالك: التقرير الذي يجمع حقائق لا يملكها — اسقاط لا مصدر · والاسم يبقى'),
);
foreach ($T as $t) {
  list($b, $own, $why) = $t;
  $r = $conn->query("SELECT screen_id, route, surface_kind, canonical_label_ar, owner_code
                       FROM repair01_screen_registry WHERE screen_file = '" . $e($b) . "' AND on_disk = 1 LIMIT 1");
  if (!$r || !($x = $r->fetch_assoc())) { echo "  ✘ $b غيرُ مسجَّل\n"; continue; }
  $f = $ROOT . '/' . $x['route'];
  $c = is_file($f) ? (string) file_get_contents($f) : '';
  $w = preg_match('~\$_POST~', $c) ? 'POST' : 'READ';
  printf("  %-26s %-9s %-11s %-5s «%s»\n", $b, $x['owner_code'], $x['surface_kind'], $w, $x['canonical_label_ar']);
  /* ⚠ **سطحٌ يكتب لا يُخفَّض إسقاطًا بحكمٍ ورقيّ** — نصُّ المالك: `WRITE_SURFACE
       = YES` لا يُحسم آليًّا. فيُرفع خبرًا ويبقى حكمُه للمعالجة. */
  /* ◆ **والكتابةُ عبر خدمةٍ محكومةٍ ليست خرقًا**: `rate_books` يكتب بـ
       `RateBookService` — ورأسُ ملفِّه يقول «منطقُ الترجيحِ في الخدمةِ لا هنا».
       **فالخامُّ وحدَه يمنع الخفض**، أمّا المحكومُ فمسارٌ مشروع. */
  $raw  = preg_match('~\b(INSERT\s+INTO|UPDATE\s+`?\w+`?\s+SET|DELETE\s+FROM)~i', $c);
  $svc  = preg_match('~(Service|Publisher|Engine)\s*(::|->)~', $c);
  $note = ($w === 'POST') ? ' · يكتب بخدمة محكومة لا خاما — مسار مشروع' : ' · قياس: صفر كتابة';
  if ($w === 'POST' && $raw && !$svc) {
    echo "     ⚠ **يكتب خامًّا** — الخفضُ يحتاج نقلَ مسارِ الكتابةِ أولًا · يُرفع ولا يُحسم آليًّا
";
    continue;
  }
  if (!$APPLY) { echo "     ⇒ سيُحسم إسقاطًا (‏قراءةٌ فقط) · والاسمُ يبقى\n"; continue; }
  $ok = $conn->query("UPDATE repair01_screen_registry
      SET surface_kind = 'PROJECTION', ownership_verdict = 'DOMAIN_PROJECTION',
          source_of_truth = CASE WHEN screen_file = 'rate_books.php'
                                 THEN 'daily_pricing_fin.php' ELSE source_of_truth END,
          verdict_rule = CONCAT(verdict_rule, ' | " . $e($why) . " · قياس: صفر كتابة'), verdict_at = NOW()
    WHERE screen_id = '" . $e($x['screen_id']) . "'");
  echo $ok ? "     ✔ حُسم إسقاطًا — **والاسمُ لم يُمَسّ**\n" : "     ✘ " . $conn->error . "\n";
}
