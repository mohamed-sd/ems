<?php
/**
 * tools/baseline_reconcile.php — BL-20260821: توفيق مصادر الشاشات السبعة في سجل واحد
 * قراءة فقط من extract/*.json — لا يمس القاعدة ولا الكود.
 * الإخراج: screen_registry.json · field_registry.json · reconcile_stats.json
 *
 * هوية الشاشة: المسار النسبي الموحَّد (فواصل أمامية، بلا ../ بادئة). متغيرات ?view= شاشات فرعية.
 * قاعدة: لا تخمين — التعارض يُدوَّن في conflicts والغائب UNKNOWN.
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
$D = $ROOT . '/docs/baseline_20260821/extract/';
function j($f) { global $D; return json_decode((string) file_get_contents($D . $f . '.json'), true) ?: array(); }
/* مفتاح المطابقة غير حساس للحالة (نظام ملفات ويندوز) — والعرض بالمسار الحقيقي على القرص */
$CASEMAP = array();
function norm_route($r)
{
    global $CASEMAP;
    $r = str_replace('\\', '/', trim((string) $r));
    $r = preg_replace('#^(\./|\.\./|/)+#', '', $r);
    $k = mb_strtolower($r);
    if (isset($CASEMAP[$k])) { return $CASEMAP[$k]; }
    return $r;
}

$disk = j('disk_surfaces');
$ledger = j('gov_migration_ledger');
$cycle = j('gov_screen_cycle');
$apps = j('gov_space_appearances');
$nav = j('nav_items');
$nfm = j('nav09_file_map');
$fields = j('fields_by_screen');
$fclass = j('gov_field_class');
$sens = j('scr_sensitive_fields');
$rulings = j('gov_ownership_rulings');

$REG = array();          /* route => row */
$srcCount = array();

function &row(&$REG, $route)
{
    if (!isset($REG[$route])) {
        $REG[$route] = array(
            'route' => $route,
            'file' => preg_replace('/\?.*$/', '', $route),
            'sources' => array(),
            'surface_type' => 'UNKNOWN',
            'on_disk' => 0,
            'name_ar' => array(),
            'owner_candidates' => array(),
            'workspaces' => array(),
            'roles_nav' => array(),
            'profiles' => array(),
            'stage' => null,
            'ledger' => null,
            'nav09' => null,
            'deprecated' => 0,
            'known_issue' => array(),
        );
    }
    return $REG[$route];
}

/* ① القرص — أولًا خريطة الحالة الحقيقية للمسارات */
foreach ($disk as $d) {
    $p = str_replace('\\', '/', $d['path']);
    $CASEMAP[mb_strtolower($p)] = $p;
}
$diskByPath = array();
foreach ($disk as $d) {
    $diskByPath[norm_route($d['path'])] = $d;
    if (in_array($d['class'], array('SCREEN', 'HANDLER', 'ENTRY', 'CRON'), true)) {
        $r = &row($REG, norm_route($d['path']));
        $r['sources'][] = 'disk';
        $r['surface_type'] = $d['class'];
        $r['on_disk'] = 1;
        $r['generator'] = $d['generator'] ?? '';
        if ($d['deprecated_mark']) { $r['deprecated'] = 1; }
        unset($r);
    }
}

/* ② دفتر الترحيل (663) */
foreach ($ledger as $L) {
    $route = norm_route($L['route'] !== '' && $L['route'] !== '—' ? $L['route'] : $L['file_base']);
    if ($route === '' || $route === '—') { continue; }
    $r = &row($REG, $route);
    $r['sources'][] = 'ledger';
    $r['name_ar'][] = $L['screen_label'];
    $r['owner_candidates']['ledger:' . $L['dept']] = ($r['owner_candidates']['ledger:' . $L['dept']] ?? 0) + 1;
    $r['ledger'] = array(
        'dept' => $L['dept'], 'layer' => $L['layer'], 'stage' => $L['stage'],
        'label' => $L['screen_label'], 'entity' => $L['entity'], 'nature' => $L['nature'],
        'target_type' => $L['target_type'], 'decision' => $L['decision'],
        'resolve_state' => $L['resolve_state'], 'is_duplicate' => $L['is_duplicate'],
        'official_doc' => $L['official_doc'], 'problems' => $L['problems'],
        'migration_state' => $L['migration_state'], 'parent_file' => $L['parent_file'],
    );
    if ($L['problems'] !== '' && $L['problems'] !== '—') { $r['known_issue'][] = $L['problems']; }
    unset($r);
}

/* ③ دورة الشاشات (663) — تناظر معرّفات مثبَت 663/663 مع الدفتر: الربط بالمعرّف.
 * ملاحظة: الشاشة الواحدة قد تظهر في أكثر من إدارة/مرحلة — تُجمع كل الإصابات. */
$cycleById = array();
foreach ($cycle as $C) { $cycleById[$C['id']] = $C; }
$ledgerIdByRoute = array();
foreach ($ledger as $L) {
    $route = norm_route($L['route'] !== '' && $L['route'] !== '—' ? $L['route'] : $L['file_base']);
    if ($route === '' || $route === '—') { continue; }
    $ledgerIdByRoute[$route][] = $L['id'];
}
foreach ($REG as $route => &$r) {
    $hit = null;
    foreach ($ledgerIdByRoute[$route] ?? array() as $lid) {
        if (isset($cycleById[$lid])) { $hit = $cycleById[$lid]; break; }
    }
    if ($hit) {
        $r['sources'][] = 'cycle';
        $r['stage'] = array(
            'stage_order' => (int) $hit['stage_order'], 'stage_name' => $hit['stage_name'],
            'layer' => $hit['layer_name'], 'group' => $hit['group_name'],
            'inputs' => $hit['inputs_note'], 'output_doc' => $hit['output_doc'],
            'resp_role' => $hit['resp_role'], 'next_state' => $hit['next_state'],
            'consumers' => $hit['consumers'], 'fin_impact' => $hit['fin_impact'],
        );
    }
}
unset($r);

/* ④ ظهورات المساحات (888) */
foreach ($apps as $A) {
    $route = norm_route($A['route']);
    if ($route === '' || $route === '—') { continue; }
    $r = &row($REG, $route);
    $r['sources'][] = 'apps';
    $r['name_ar'][] = $A['screen_ar'];
    if ($A['owner_dept_ar'] !== '' && $A['owner_dept_ar'] !== '—') {
        $r['owner_candidates']['apps:' . $A['owner_dept_ar']] = ($r['owner_candidates']['apps:' . $A['owner_dept_ar']] ?? 0) + 1;
    }
    $r['workspaces'][] = array(
        'space' => $A['space_ar'], 'kind' => $A['space_kind'], 'tab' => $A['tab_ar'],
        'cls' => $A['cls'], 'decision' => $A['decision'], 'owner_kind' => $A['owner_kind'],
    );
    unset($r);
}

/* ⑤ التنقّل الحي (1646 بندًا نشطًا) */
foreach ($nav as $N) {
    $route = norm_route($N['route']);
    if ($route === '') { continue; }
    $r = &row($REG, $route);
    $r['sources'][] = 'nav';
    $r['name_ar'][] = $N['label_ar'];
    $r['roles_nav'][$N['role_name'] ?: ('role#' . $N['role_id'])][] = array(
        'group' => $N['group_name'], 'order' => (int) $N['sort_order'], 'perm' => $N['permission_code'],
    );
    unset($r);
}

/* ⑥ nav09_file_map (258) */
foreach ($nfm as $F) {
    $route = norm_route($F['real_path'] !== '' ? $F['real_path'] : $F['canonical_file']);
    if ($route === '') { continue; }
    $r = &row($REG, $route);
    $r['sources'][] = 'nav09';
    $r['name_ar'][] = $F['title_ar'];
    if ($F['owner_dept'] !== '') {
        $r['owner_candidates']['nav09:' . $F['owner_dept']] = ($r['owner_candidates']['nav09:' . $F['owner_dept']] ?? 0) + 1;
    }
    $r['nav09'] = array('state' => $F['state'], 'canonical' => $F['canonical_file']);
    if (stripos((string) $F['state'], 'retired') !== false || stripos((string) $F['state'], 'DEPRECATED') !== false) { $r['deprecated'] = 1; }
    unset($r);
}

/* ⑦ أحكام الملكية (46) — الحكم الأعلى */
$rulingByRoute = array();
foreach ($rulings as $RU) { $rulingByRoute[norm_route($RU['route'])] = $RU; }

/* ⑧ بنود الملفات الشخصية gov_profile_items (2526 شاشة) */
$gpi = j('gov_profile_items');
$profiles = array();
foreach (j('gov_role_profiles') as $P) { $profiles[$P['profile_id']] = $P; }
foreach ($gpi as $G) {
    $route = norm_route($G['item_ref']);
    if (!isset($REG[$route])) { continue; /* بند لمسار غير معروف — يُحصى أدناه */ }
    $p = $profiles[$G['profile_id']] ?? null;
    $REG[$route]['profiles'][] = array(
        'profile' => $p ? $p['profile_code'] : ('profile#' . $G['profile_id']),
        'dept' => $p ? $p['dept_code'] : '?',
        'allow' => (int) $G['allow'], 'add' => (int) $G['can_add'],
        'edit' => (int) $G['can_edit'], 'del' => (int) $G['can_delete'],
    );
}
$gpiUnknownRoute = 0;
foreach ($gpi as $G) { if (!isset($REG[norm_route($G['item_ref'])])) { $gpiUnknownRoute++; } }

/* ── التلخيص والحكم ─────────────────────────────────────────────── */
$fieldsByRoute = array();
foreach ($fields as $FE) { $fieldsByRoute[norm_route($FE['route'])] = $FE; }
$fclassByCode = array();
foreach ($fclass as $FC) { $fclassByCode[$FC['screen_code']][$FC['field_key']] = $FC; }

$out = array();
$conflicts = array();
$i = 0;
ksort($REG);
foreach ($REG as $route => $r) {
    $i++;
    $sid = 'SCR-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
    /* ملفٌّ صنَّفه المسح INCLUDE لكن السجلات (nav/ledger/apps) تعرفه شاشةً:
     * يُصيَّر عبر عُدّة/غلاف — يُرقّى شاشةً مع بيان الأساس */
    if ($r['surface_type'] === 'UNKNOWN' && isset($diskByPath[$route])) {
        $r['surface_type'] = 'SCREEN_VIA_KIT';
        $r['on_disk'] = 1;
    }
    /* نظام فرعي خارج نطاق المسح (chats/emsreports) — الملف قائم فعلًا */
    if (!$r['on_disk'] && is_file(dirname(__DIR__) . '/' . preg_replace('/[?#].*$/', '', $route))) {
        $r['on_disk'] = 1;
        $r['surface_type'] = 'SCREEN_SUBSYSTEM';
    }
    /* مسار بمَعلمة ?view= أو مرساة #: شاشة فرعية لملف أم قائم */
    if (!$r['on_disk'] && preg_match('/^([^?#]+)[?#]/', $route, $vm)) {
        $parent = norm_route($vm[1]);
        if (isset($diskByPath[$parent])) {
            $r['surface_type'] = 'VIEW_VARIANT';
            $r['on_disk'] = 1;
            $r['parent_file'] = $parent;
        }
    }
    /* المالك: حكم ملكية صريح > إجماع المصادر > NEEDS_REVIEW */
    $owner = 'UNKNOWN';
    $ownerBasis = 'NONE';
    if (isset($rulingByRoute[$route])) {
        $owner = $rulingByRoute[$route]['owner_after'];
        $ownerBasis = 'RULING';
    } else {
        $names = array();
        foreach ($r['owner_candidates'] as $k => $n) {
            $names[preg_replace('/^[a-z0-9]+:/', '', $k)] = ($names[preg_replace('/^[a-z0-9]+:/', '', $k)] ?? 0) + $n;
        }
        if (count($names) === 1) { $owner = array_key_first($names); $ownerBasis = 'CONSENSUS'; }
        elseif (count($names) > 1) {
            arsort($names);
            $owner = array_key_first($names);
            $ownerBasis = 'MAJORITY';
            $conflicts[] = array('route' => $route, 'kind' => 'owner', 'values' => $names);
        }
    }
    $nameCounts = array_count_values(array_filter($r['name_ar']));
    arsort($nameCounts);
    $fe = $fieldsByRoute[$route] ?? null;
    $tblCols = 0; $frmFlds = 0;
    if ($fe) {
        foreach ($fe['tables'] as $t) { $tblCols += count($t['columns']); }
        $frmFlds = count($fe['form_fields']);
    }
    $out[] = array(
        'screen_id' => $sid,
        'route' => $route,
        'file' => $r['file'],
        'surface_type' => $r['surface_type'],
        'on_disk' => $r['on_disk'],
        'sources' => array_values(array_unique($r['sources'])),
        'name_ar' => $nameCounts ? array_key_first($nameCounts) : 'UNKNOWN',
        'names_all' => array_keys($nameCounts),
        'owner_dept' => $owner,
        'owner_basis' => $ownerBasis,
        'workspaces' => $r['workspaces'],
        'roles_nav' => $r['roles_nav'],
        'profiles_count' => count($r['profiles']),
        'profiles' => $r['profiles'],
        'stage' => $r['stage'],
        'ledger' => $r['ledger'],
        'nav09' => $r['nav09'],
        'deprecated' => $r['deprecated'],
        'known_issue' => array_values(array_unique($r['known_issue'])),
        'table_columns' => $tblCols,
        'form_fields' => $frmFlds,
    );
}

/* سجل الحقول المسطَّح */
$fieldReg = array();
$fid = 0;
$sidByRoute = array();
foreach ($out as $o) { $sidByRoute[$o['route']] = $o['screen_id']; }
foreach ($fields as $FE) {
    $route = norm_route($FE['route']);
    $sid = $sidByRoute[$route] ?? null;
    if (!$sid) { continue; }
    $code = preg_replace('/\.php$/', '', basename($route));
    foreach ($FE['tables'] as $t) {
        foreach ($t['columns'] as $c) {
            $fid++;
            $fc = null;
            if ($c['technical'] !== null && isset($fclassByCode[$code][$c['technical']])) { $fc = $fclassByCode[$code][$c['technical']]; }
            $fieldReg[] = array(
                'field_id' => 'FLD-' . str_pad((string) $fid, 5, '0', STR_PAD_LEFT),
                'screen_id' => $sid, 'route' => $route,
                'kind' => 'table_column', 'table_index' => $t['table_index'],
                'label_ar' => $c['label_ar'], 'technical' => $c['technical'] ?? 'NEEDS_REVIEW',
                'col_group' => $c['col_group'], 'hidden_default' => $c['hidden_default'],
                'input_type' => '', 'required' => '', 'readonly' => '', 'section' => '',
                'dc_code' => $fc ? $fc['dc_code'] : '', 'is_sensitive' => $fc ? (int) $fc['is_sensitive'] : '',
            );
        }
    }
    /* شاشات U13 المولَّدة: أعمدتها من gov_field_class حصرًا (OBL-0052: الحقل بلا صنف لا يُصيَّر) */
    $dk = $diskByPath[$route] ?? null;
    if ($dk && ($dk['generator'] ?? '') === 'U13_MANIFEST' && ($dk['u13_screen'] ?? '') !== ''
        && isset($fclassByCode[$dk['u13_screen']])) {
        foreach ($fclassByCode[$dk['u13_screen']] as $fk => $fc) {
            $fid++;
            $fieldReg[] = array(
                'field_id' => 'FLD-' . str_pad((string) $fid, 5, '0', STR_PAD_LEFT),
                'screen_id' => $sid, 'route' => $route,
                'kind' => 'u13_column', 'table_index' => 0,
                'label_ar' => $fc['label_ar'], 'technical' => $fk,
                'col_group' => '', 'hidden_default' => '',
                'input_type' => '', 'required' => '', 'readonly' => '', 'section' => '',
                'dc_code' => $fc['dc_code'], 'is_sensitive' => (int) $fc['is_sensitive'],
            );
        }
    }
    foreach ($FE['form_fields'] as $f) {
        $fid++;
        $fc = isset($fclassByCode[$code][$f['name']]) ? $fclassByCode[$code][$f['name']] : null;
        $fieldReg[] = array(
            'field_id' => 'FLD-' . str_pad((string) $fid, 5, '0', STR_PAD_LEFT),
            'screen_id' => $sid, 'route' => $route,
            'kind' => $f['is_system'] ? 'form_field_system' : 'form_field',
            'table_index' => '',
            'label_ar' => $f['label_ar'], 'technical' => $f['name'],
            'col_group' => '', 'hidden_default' => '',
            'input_type' => $f['input_type'], 'required' => $f['required'],
            'readonly' => $f['readonly'], 'section' => $f['section'],
            'dc_code' => $fc ? $fc['dc_code'] : '', 'is_sensitive' => $fc ? (int) $fc['is_sensitive'] : '',
        );
    }
}

/* إحصاءات التوفيق */
$stats = array(
    'registry_rows' => count($out),
    'on_disk_screens' => count(array_filter($out, fn($o) => $o['surface_type'] === 'SCREEN')),
    'handlers' => count(array_filter($out, fn($o) => $o['surface_type'] === 'HANDLER')),
    'cron' => count(array_filter($out, fn($o) => $o['surface_type'] === 'CRON')),
    'registry_no_disk' => count(array_filter($out, fn($o) => !$o['on_disk'])),
    'disk_not_in_any_registry' => count(array_filter($out, fn($o) => $o['sources'] === array('disk') && $o['surface_type'] === 'SCREEN')),
    'owner_unknown' => count(array_filter($out, fn($o) => $o['owner_dept'] === 'UNKNOWN')),
    'owner_conflicts' => count($conflicts),
    'with_stage' => count(array_filter($out, fn($o) => $o['stage'] !== null)),
    'fields_total' => count($fieldReg),
    'gpi_unknown_route' => $gpiUnknownRoute,
);
file_put_contents($D . 'screen_registry.json', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
file_put_contents($D . 'field_registry.json', json_encode($fieldReg, JSON_UNESCAPED_UNICODE));
file_put_contents($D . 'reconcile_conflicts.json', json_encode($conflicts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
file_put_contents($D . 'reconcile_stats.json', json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
foreach ($stats as $k => $v) { echo str_pad($k, 28) . $v . "\n"; }
