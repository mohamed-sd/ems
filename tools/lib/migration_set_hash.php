<?php
/**
 * tools/lib/migration_set_hash.php — بصمةُ مجموعةِ الهجراتِ المطبَّقة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا**: «تمرينُ تثبيتٍ غيرُ متقادم» تعني **أنَّه تحقّق من مجموعةِ
 *   الهجراتِ التي عندك الآن** — والتعبيرُ عنها بساعةِ الحائطِ ضعيفٌ ويكذب:
 *   الساعةُ ترجع (قيست رجعةُ 11.4 ساعةً في جولةِ `GOV_UI_EXEC`)، والختمُ
 *   الأكبرُ لا يُثبت أنَّ المخطَّطَ المفحوصَ هو مخطَّطُك.
 *
 * ◆ **والبصمةُ تُحسب من الاسمِ والبصمةِ معًا** — فهجرةٌ تغيّر محتواها بعدَ
 *   تطبيقِها (وقد جرى) تُغيّر البصمةَ ولو بقي الاسمُ والعدد.
 *
 * ⛔ **ويُستثنى سكربتُ التراجع**: حالتُه `baseline` تعني «ليس نافذًا»، ودخولُه
 *   يجعل البصمةَ تتحرّك بتسويةٍ إداريّةٍ لا بتغيُّرِ مخطَّط.
 *
 * ◆ **مصبٌّ واحدٌ** يقرؤه مُنشئُ المحضرِ (`gov_exec_fresh_install_drill.php`)
 *   وحاكمُه (`tests/injfrd01_gov014_clean_clone_fingerprint.php`) — فلا
 *   حسابان يتفرّقان.
 */

if (!function_exists('ems_migration_set_hash')) {

/**
 * @param  mysqli $conn
 * @return array{hash:string,count:int} البصمةُ وعددُ الهجراتِ الداخلةِ فيها
 */
function ems_migration_set_hash(mysqli $conn)
{
    $rows = array();
    $q = @$conn->query("SELECT filename, checksum FROM schema_migrations
                         WHERE status = 'applied' ORDER BY filename");
    while ($q && ($r = $q->fetch_assoc())) {
        $rows[] = $r['filename'] . ':' . $r['checksum'];
    }
    return array('hash' => sha1(implode("\n", $rows)), 'count' => count($rows));
}

}
