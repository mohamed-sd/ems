<?php
/**
 * includes/nav_view.php — تشغيلُ المنظرِ المُعلَن
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ INJ-0512 · INJ-0514
 *
 * يُستدعى من `includes/page_header.php` وحدَه — **مخنقٌ واحدٌ لكلِّ الشاشات**،
 * فلا يُعدَّل خمسةَ عشرَ ملفًّا ولا تُنسى شاشةٌ حين يُضاف منظرٌ جديد.
 *
 * ── قاعدتان ───────────────────────────────────────────────────────────────
 * ① **رمزٌ غيرُ مُعلَنٍ لا يُعرض**: يُردُّ الزائرُ إلى الرابطِ العاري. فلا تبقى
 *    صفحةٌ بمعاملٍ ميتٍ تُوهم بمحتوًى لا يأتي — وهي العلّةُ التي كُشفت بالقياس.
 * ② **والمُعلَنُ يُطبَّق ويُعلن عن نفسِه**: شارةٌ ظاهرةٌ باسمِ المنظرِ وفيها
 *    «عرض الكل»، وعنوانُ الصفحةِ يصير تسميةَ الرابطِ الذي ضُغط — فيتحقّق
 *    «كلُّ رابطٍ يشير إلى وجهةٍ مختلفة» في المرأى لا في الرابطِ وحدَه.
 *
 * ◆ ولا يُطبَّق شيءٌ على نداءِ AJAX ولا على غيرِ GET — الحارسُ للصفحاتِ لا
 *   لنقاطِ الخدمة.
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/nav_views.php';

/** المسارُ النسبيُّ للشاشةِ الجارية من جذرِ المشروع. */
function ems_nav_view_script()
{
    $root = str_replace('\\', '/', dirname(__DIR__));
    $self = isset($_SERVER['SCRIPT_FILENAME']) ? str_replace('\\', '/', (string) $_SERVER['SCRIPT_FILENAME']) : '';
    if ($self !== '' && strpos($self, $root) === 0) { return ltrim(substr($self, strlen($root)), '/'); }
    $sn = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
    return ltrim(preg_replace('~^/ems/~', '', $sn), '/');
}

/**
 * يحلُّ المنظرَ الجاري. يُرجع الإعلانَ مع رمزِه، أو null إن لا منظر.
 * وإن كان الرمزُ غيرَ مُعلَنٍ **حوّل وأنهى** — فلا يعود.
 */
function ems_nav_view_resolve()
{
    static $resolved = false, $view = null;
    if ($resolved) { return $view; }
    $resolved = true;

    if (php_sapi_name() === 'cli') { return null; }
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') { return null; }
    if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') { return null; }

    $code = isset($_GET['view']) ? trim((string) $_GET['view']) : '';
    if ($code === '') { return null; }

    /* ◆ صاحبُ المعاملِ أولى به — والمُخنِقُ يتنحّى لا يحكم.
         شاشةٌ أعلنت ملكيتَها (`ems_nav_view_claim` قبلَ `page_header.php`)
         تتولّى الرمزَ بنفسِها، فلا يُقاس رمزُها بسجلٍّ ليس سجلَّه ولا يُردُّ
         زائرُها. وبدونِ هذا كانت تاباتُ «مهامي» العشرُ تُصيَّر ولا تفتح —
         الشرحُ الكاملُ والقياسُ في ذيلِ `nav_views.php`. */
    if (ems_nav_view_claimed()) { return null; }

    $file = ems_nav_view_script();
    $decl = ems_nav_view_declared($file, $code);

    if ($decl === null) {
        /* ① رمزٌ غيرُ مُعلَن: إلى الرابطِ العاري — وبقيةُ المعاملاتِ تبقى */
        if (headers_sent()) { return null; }
        $q = $_GET; unset($q['view']);
        $to = basename($file) . ($q ? '?' . http_build_query($q) : '');
        header('Location: ' . $to, true, 302);
        exit();
    }

    $decl['code'] = $code;
    $decl['bare'] = basename($file);
    $view = $decl;
    $GLOBALS['ems_nav_view'] = $view;
    return $view;
}

/** شارةُ المنظرِ + المُطبِّقُ — تُطبع مرةً واحدةً داخلَ جسمِ الصفحة. */
function ems_nav_view_chip()
{
    static $printed = false;
    if ($printed) { return ''; }
    $v = ems_nav_view_resolve();
    if ($v === null) { return ''; }
    $printed = true;

    $h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
    $cfg = json_encode(array(
        'section' => isset($v['section']) ? $v['section'] : null,
        'col'     => isset($v['col']) ? $v['col'] : null,
        'val'     => isset($v['val']) ? $v['val'] : null,
    ), JSON_UNESCAPED_UNICODE);

    ob_start(); ?>
<div class="ems-view-chip" data-ems-view="<?php echo $h($v['code']); ?>"
     data-ems-view-cfg='<?php echo $h($cfg); ?>'
     style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0 0 10px;
            padding:8px 12px;border:1px solid #e0d7bd;border-radius:8px;background:#fffdf3">
  <strong style="font-weight:800">المنظر:</strong>
  <span><?php echo $h($v['label']); ?></span>
  <span class="ems-view-state" style="color:#8a6d00;font-size:.86rem"></span>
  <a href="<?php echo $h($v['bare']); ?>" style="margin-inline-start:auto;font-weight:700">عرض الكل ▸</a>
</div>
<script>
(function () {
  var chip = document.currentScript && document.currentScript.previousElementSibling;
  function apply() {
    var box = document.querySelector('.ems-view-chip'); if (!box) { return; }
    var cfg; try { cfg = JSON.parse(box.getAttribute('data-ems-view-cfg') || '{}'); } catch (e) { return; }
    var state = box.querySelector('.ems-view-state'), applied = 0;

    /* ⓐ تركيزٌ على قسم: يبقى القسمُ ويُطوى ما عداه */
    if (cfg.section) {
      var host = document.querySelector('.main') || document.body;
      var cards = host.querySelectorAll('.card, .ems-card, section'), hit = null;
      for (var i = 0; i < cards.length; i++) {
        if ((cards[i].textContent || '').indexOf(cfg.section) !== -1
            && !cards[i].querySelector('.ems-view-chip')) { hit = cards[i]; break; }
      }
      if (hit) {
        for (var j = 0; j < cards.length; j++) {
          if (cards[j] !== hit && !cards[j].contains(hit) && !hit.contains(cards[j])
              && !cards[j].querySelector('.ems-view-chip')) { cards[j].style.display = 'none'; }
        }
        applied = 1;
      }
    }

    /* ⓑ ترشيحٌ بعمودٍ وقيمة: تبقى الصفوفُ المطابقةُ وحدَها */
    if (cfg.col && cfg.val) {
      var tables = document.querySelectorAll('table');
      for (var t = 0; t < tables.length; t++) {
        var ths = tables[t].querySelectorAll('thead th'), idx = -1;
        for (var k = 0; k < ths.length; k++) {
          if ((ths[k].textContent || '').trim().indexOf(cfg.col) !== -1) { idx = k; break; }
        }
        if (idx < 0) { continue; }
        var rows = tables[t].querySelectorAll('tbody tr'), kept = 0;
        for (var r = 0; r < rows.length; r++) {
          var cell = rows[r].cells && rows[r].cells[idx];
          var txt = cell ? (cell.textContent || '') : '';
          if (txt.indexOf(cfg.val) === -1) { rows[r].style.display = 'none'; } else { kept++; }
        }
        applied = 1;
        if (state) { state.textContent = '— ' + kept + ' صفًّا'; }
      }
    }

    box.setAttribute('data-ems-view-applied', applied ? '1' : '0');
    if (!applied && state) { state.textContent = '— لم يُطبَّق: القسمُ غيرُ ظاهرٍ لصلاحيتك'; }
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', apply); }
  else { apply(); }
})();
</script>
<?php
    return ob_get_clean();
}
