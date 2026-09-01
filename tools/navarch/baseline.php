<?php
/**
 * tools/navarch/baseline.php — NAV_ARCH_BASELINE (‏أمرُ NAV-ARCH-02 §5)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **نصُّ §5**: «قبل أول تعديل أنشئ NAV_ARCH_BASELINE» بستّةٍ: `Commit Hash`
 *   و`DB Snapshot` و`Navigation Registry Version` و`Role Permission Version`
 *   و`Target Architecture Version` و`Timestamp` — ثمَّ «استخرج Runtime Sidebar
 *   لكل Workspace ولكل Role ممثل»، و⛔ «لا تستخدم قياسات مولدة من Commits
 *   مختلفة».
 *
 * ◆ **ولذلك لقطةٌ واحدة**: هذا الملفُّ يُخرج المعرِّفاتِ الستةَ **والتصييرَ
 *   الحيَّ لكلِّ مساحةٍ في العمليّةِ نفسِها** — فلا يُخلط قياسُ اليومِ بقياسِ
 *   الأمس. ومعرِّفُ الأساسِ يحمل بصمةَ محتواه: من غيّر صفًّا غيّر المعرِّف.
 *
 * ◆ **والبصماتُ بالحقائقِ لا بالساعة** ([[staleness-by-fact-not-clock]]):
 *   `DB Snapshot` بصمةُ مجموعةِ الهجراتِ المطبَّقة، و`Navigation Registry
 *   Version` بصمةُ محتوى سجلَّاتِ الملاحةِ الثلاثة، و`Role Permission Version`
 *   بصمةُ منحِ الأدوار. والختمُ UTC — ويُذكر أنَّه ساعةُ حائطٍ لا ترتيب.
 *
 * ◆ [[render-not-store-rule]]: لقطةُ السايدبارِ **من التصييرِ الحيِّ** بعمليّةٍ
 *   نقيّةٍ لكلِّ دور — ⛔ لا من صفوفِ `nav_items`.
 *
 * التشغيل: php tools/navarch/baseline.php
 *   ⇒ docs/REPAIR01_20260823/navarch/NAV_ARCH_BASELINE.json
 *   ⇒ docs/REPAIR01_20260823/navarch/NAV_ARCH_BASELINE.md
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__, 2));
require_once $ROOT . '/tools/lib/migration_set_hash.php';
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$OUTDIR = $ROOT . '/docs/REPAIR01_20260823/navarch';
if (!is_dir($OUTDIR)) { @mkdir($OUTDIR, 0777, true); }

/* ═══ ① المعرِّفاتُ الستّة ═══════════════════════════════════════════════ */
$commit = trim((string) @shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse HEAD 2>&1'));
$dirty  = trim((string) @shell_exec('git -C ' . escapeshellarg($ROOT) . ' status --porcelain 2>&1'));

$mig = ems_migration_set_hash($conn);

/** بصمةُ محتوى استعلامٍ بترتيبٍ ثابت */
$fp = function ($sql) use ($conn) {
    $r = @$conn->query($sql);
    if (!$r) { return array('hash' => 'ERR', 'count' => 0, 'err' => $conn->error); }
    $lines = array();
    while ($x = $r->fetch_row()) { $lines[] = implode('|', array_map('strval', $x)); }
    return array('hash' => sha1(implode("\n", $lines)), 'count' => count($lines));
};

$navReg = $fp("SELECT k FROM (
      SELECT CONCAT('W|',workspace_id,'|',kind,'|',name_ar,'|',COALESCE(dept_code,''),'|',active) k
        FROM nav_workspaces
      UNION ALL
      SELECT CONCAT('G|',workspace_id,'|',id,'|',group_key,'|',label_ar,'|',sort_no,'|',active)
        FROM nav_lifecycle_groups
      UNION ALL
      SELECT CONCAT('P|',workspace_id,'|',id,'|',COALESCE(screen_id,''),'|',COALESCE(route,''),'|',
                    group_id,'|',sort_no,'|',placement_type,'|',active)
        FROM nav_placements) t ORDER BY k");

$permVer = $fp("SELECT CONCAT(role_id,'|',module_id,'|',can_view,'|',can_add,'|',can_edit,'|',can_delete)
                  FROM role_permissions ORDER BY role_id, module_id");

$navItems = $fp("SELECT CONCAT(role_id,'|',door,'|',COALESCE(group_id,0),'|',label_ar,'|',route,'|',
                               sort_order,'|',COALESCE(permission_code,''),'|',active)
                   FROM nav_items WHERE active = 1 ORDER BY role_id, door, sort_order, id");

/* هدفُ المعماريّة: بصمةُ ورقةِ الدليلِ المعتمَدة */
$guideCand = glob($ROOT . '/docs/sources/*/*guide*.xlsx');
$guideXlsx = $guideCand ? $guideCand[0] : '';
$tgtVer = ($guideXlsx !== '' && is_file($guideXlsx))
    ? substr(sha1_file($guideXlsx), 0, 12) . ' · ' . basename($guideXlsx)
    : 'nav_placements@' . substr($navReg['hash'], 0, 12);

$stamp = gmdate('Y-m-d\TH:i:s\Z');

/* ═══ ② التصييرُ الحيُّ — لقطةٌ واحدةٌ لكلِّ مساحةٍ بدورِها الممثِّل ══════════ */
$rt = function ($s) {
    $s = preg_replace('~^(\.\./)+~', '', (string) $s);
    $s = preg_replace('~[?#].*$~', '', $s);
    return strtolower(trim(preg_replace('~\.php$~i', '', $s), '/'));
};
/* ◆ **ودورُ المساحةِ المُمثِّلُ `PRIMARY` ثمَّ `SECONDARY`** — بالقاعدةِ نفسِها
     التي يفرزُ بها `navarch_role_workspace`.
   ⛔ **والقصرُ على `PRIMARY` يُعمي الأساسَ عن مساحةٍ كلُّ أدوارِها فرعيّة**:
     `WS-PLATFORM` دورُها الوحيدُ 15 «إدارة الصلاحيات» مربوطٌ `SECONDARY`
     (‏لا إدارةَ أمَّ لها بالدليل) — فكان `role_id` يرجع `NULL` فلا تُصيَّر،
     **فلا يكتب لها المُصنِّفُ موضعًا واحدًا**، فيردُّها المُصيِّرُ إلى المسارِ
     القديمِ أبدًا. وهي وحدَها كانت تُبقي `U3` على مخالفتَين: مسارٌ يُقرأ تحتَ
     «مساحتي» في ثمانيةَ عشرَ دورًا مقلوبًا وتحتَ تصنيفِ الإرثِ في دورٍ واحد.
     ⇒ **عطبُ قارئٍ لا عطبُ بناء** [[measure-blind-spots]]. */
$ws = array();
$r = $conn->query("SELECT w.workspace_id, w.kind, w.name_ar, w.dept_code, w.active,
                          wr.role_id, ro.name rname
                     FROM nav_workspaces w
                     LEFT JOIN nav_ws_roles wr
                            ON wr.workspace_id = w.workspace_id
                           AND wr.role_id = (SELECT x.role_id FROM nav_ws_roles x
                                              WHERE x.workspace_id = w.workspace_id
                                              ORDER BY (x.binding = 'PRIMARY') DESC, x.role_id ASC
                                              LIMIT 1)
                     LEFT JOIN roles ro ON ro.id = wr.role_id
                    ORDER BY w.workspace_id");
while ($x = $r->fetch_assoc()) { $ws[$x['workspace_id']] = $x; }

$snap = array(); $tot = 0; $rendered = 0;
foreach ($ws as $id => $w) {
    $base = array('role_id'   => $w['role_id'] === null ? null : (int) $w['role_id'],
                  'role_name' => $w['rname'], 'kind' => $w['kind'],
                  'name_ar'   => $w['name_ar'], 'active' => (int) $w['active'],
                  'rendered'  => null, 'items' => array());
    if ((int) $w['active'] !== 1 || $w['role_id'] === null) { $snap[$id] = $base; continue; }
    $out = array();
    @exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/lib/render_role_cli.php')
        . ' ' . (int) $w['role_id'] . ' 2>NUL', $out);
    $j = json_decode(implode("\n", $out), true);
    if (is_array($j) && isset($j['positions'])) {
        foreach ($j['positions'] as $i => $p) {
            $base['items'][] = array('n' => $i + 1, 'group' => (string) $p['g'],
                                     'label' => (string) $p['l'], 'route' => $rt($p['h']),
                                     'href' => (string) $p['h']);
        }
        $base['rendered'] = count($base['items']);
        $rendered++;
        $tot += $base['rendered'];
    }
    $snap[$id] = $base;
}

/* ═══ ③ معرِّفُ الأساسِ — بصمةُ محتواه لا رقمٌ متسلسل ══════════════════════ */
$idSeed = $commit . '|' . $mig['hash'] . '|' . $navReg['hash'] . '|' . $permVer['hash']
        . '|' . $navItems['hash'] . '|' . $tgtVer;
$BLID = 'NAB-' . strtoupper(substr(sha1($idSeed), 0, 10));

$doc = array(
    'baseline_id'                  => $BLID,
    'commit_hash'                  => $commit,
    'worktree_clean'               => ($dirty === ''),
    'db_snapshot'                  => array('migration_set_hash' => $mig['hash'],
                                            'applied_migrations' => $mig['count']),
    'navigation_registry_version'  => array('hash' => $navReg['hash'], 'rows' => $navReg['count']),
    'nav_items_version'            => array('hash' => $navItems['hash'], 'rows' => $navItems['count']),
    'role_permission_version'      => array('hash' => $permVer['hash'], 'rows' => $permVer['count']),
    'target_architecture_version'  => $tgtVer,
    'timestamp_utc'                => $stamp,
    'timestamp_note'               => 'ساعةُ حائطٍ للتوثيقِ لا لترتيبِ الحقائق — الترتيبُ بالبصمات',
    'workspaces'                   => count($ws),
    'workspaces_rendered'          => $rendered,
    'total_rendered_links'         => $tot,
    'snapshot'                     => $snap,
);
file_put_contents($OUTDIR . '/NAV_ARCH_BASELINE.json',
    json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

/* ═══ ④ المحضرُ المقروء ═══════════════════════════════════════════════════ */
$md   = array();
$md[] = '# `NAV_ARCH_BASELINE` — نقطةُ قياسِ NAV-ARCH-02 (‏§5)';
$md[] = '';
$md[] = '> ⛔ **لقطةٌ واحدة**: المعرِّفاتُ الستّةُ والتصييرُ الحيُّ لكلِّ مساحةٍ';
$md[] = '> أُنتجت في **عمليّةٍ واحدةٍ على التزامٍ واحد** — بنصِّ §5: «لا تستخدم';
$md[] = '> قياسات مولدة من Commits مختلفة».';
$md[] = '>';
$md[] = '> ◆ **ومولِّدُه** `tools/navarch/baseline.php` — يُعاد تشغيلُه فيُعاد';
$md[] = '> إنتاجُ المعرِّفِ نفسِه ما لم يتغيّر صفٌّ أو التزام.';
$md[] = '';
$md[] = '| المعرِّف | القيمة |';
$md[] = '|---|---|';
$md[] = '| **`Baseline ID`** | **`' . $BLID . '`** |';
$md[] = '| `Commit Hash` | `' . $commit . '` |';
$md[] = '| شجرةُ العملِ نظيفة؟ | ' . ($dirty === '' ? '✔ نعم' : '◆ لا — فيها تغييرٌ غيرُ ملتزَم') . ' |';
$md[] = '| `DB Snapshot` (‏بصمةُ مجموعةِ الهجرات) | `' . substr($mig['hash'], 0, 16) . '…` · '
      . $mig['count'] . ' هجرةً مطبَّقة |';
$md[] = '| `Navigation Registry Version` | `' . substr($navReg['hash'], 0, 16) . '…` · '
      . $navReg['count'] . ' صفًّا (‏مساحات + مجموعات + مواضع) |';
$md[] = '| `nav_items` النشطة (‏مصدرُ المُصيِّرِ القديم) | `' . substr($navItems['hash'], 0, 16) . '…` · '
      . $navItems['count'] . ' صفًّا |';
$md[] = '| `Role Permission Version` | `' . substr($permVer['hash'], 0, 16) . '…` · '
      . $permVer['count'] . ' منحةً |';
$md[] = '| `Target Architecture Version` | `' . $tgtVer . '` |';
$md[] = '| `Timestamp` (UTC) | `' . $stamp . '` |';
$md[] = '';
$md[] = '## المساحاتُ ولقطةُ سايدبارِ كلٍّ';
$md[] = '';
$md[] = '| المساحة | النوع | الاسم | الدورُ الممثِّل | روابطُ ظاهرة |';
$md[] = '|---|---|---|---|---:|';
foreach ($snap as $id => $s) {
    $md[] = '| `' . $id . '` | `' . $s['kind'] . '` | ' . $s['name_ar'] . ' | '
          . ($s['role_id'] === null ? '— بلا ربطٍ `PRIMARY`' : $s['role_id'] . ' · ' . $s['role_name'])
          . ' | ' . ($s['rendered'] === null ? '—' : $s['rendered']) . ' |';
}
$md[] = '| **المجموع** | | | **' . $rendered . ' مساحةً مُصيَّرة** | **' . $tot . '** |';
$md[] = '';
file_put_contents($OUTDIR . '/NAV_ARCH_BASELINE.md', implode("\n", $md) . "\n");

printf("%s · مساحات %d (مُصيَّرة %d) · روابط %d · هجرات %d\n=> %s\n",
    $BLID, count($ws), $rendered, $tot, $mig['count'], $OUTDIR . '/NAV_ARCH_BASELINE.md');
