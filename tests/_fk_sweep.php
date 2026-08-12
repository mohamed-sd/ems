<?php
/**
 * tests/_fk_sweep.php — كانسٌ يشتقُّ الأبناءَ من القاعدةِ لا من قائمةٍ بيدٍ
 * ═══════════════════════════════════════════════════════════════════════════
 * **العطبُ الذي أوجدَ هذا الملفَّ**: `tests/seed_unit_reconcile_50.php` كان يُسمّي
 * أبناءَ `unit_entries` بيدِه (`unit_approvals` · `unit_capacity_flags` ·
 * `unit_time_log`) — و`unit_match_overrides` **غائبٌ عن القائمة**. فحين أنشأ
 * `unit_reconcile_test` صفوفَ تجاوزٍ، صار كنسُ البذرةِ يموت بـ
 * «Cannot delete or update a parent row» في منتصفِ التنظيف، فتبقى النافذةُ
 * **نصفَ نظيفةٍ** ⇒ عدّاداتُ الفاحصِ تتضاعف (1⇒3 · 2⇒3 · 3⇒6).
 *
 * وهو **فخٌّ دائريّ**: الفاحصُ يُنشئ ما يمنع بذرتَه من التنظيف، فكلُّ جولةٍ
 * تُفسد التي بعدها. ولا يُحَلُّ بإضافةِ اسمٍ رابعٍ إلى القائمةِ — لأنَّ ابنًا
 * خامسًا سيُضاف غدًا ويعود العطبُ صامتًا.
 *
 * ⇒ فالأبناءُ **يُشتقّون من `information_schema`** وقتَ التشغيل: كلُّ جدولٍ يرجع
 *   إلى الأصلِ يُكنَس قبله، والأعمقُ أولًا، مع حرسِ دورةٍ للمفتاحِ الذاتيّ.
 *
 * ── والقواعدُ التي يلزمُها هذا الملفُّ ─────────────────────────────────────────
 * ◆ **يُفحَص مُرجَعُ كلِّ حذف**: `config.php` يضبط mysqli على عدمِ الرمي، فالحذفُ
 *   الفاشلُ يعود `false` **صامتًا** — وكانسٌ لا يعرف أنه فشل أخطرُ من غيابِه.
 * ◆ **ولا يُحذف شيءٌ بلا معرِّفاتٍ صريحة**: قائمةٌ خاويةٌ ⇒ صفرُ حذفٍ، لا
 *   `WHERE id IN ()` ولا `LIKE '%'`. (ثغرةٌ وقعت فعلًا في هذه الشجرة: متغيّرٌ
 *   خارج `use` المُغلَقةِ صيّر `LIKE '{$MARK}%'` إلى `LIKE '%'` فكنس كلَّ شيء.)
 * ◆ ويُرجَع تقريرٌ بما حُذف من كلِّ جدولٍ — فالكنسُ يُقاس لا يُوعَد.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('ems_fk_q')) {
    /**
     * استعلامٌ **آمنٌ في البيئتين**: في التطبيقِ يضبط `config.php` mysqli على
     * عدمِ الرمي فالفشلُ يعود `false`؛ وفي البذورِ يُبنى الاتصالُ خامًا فيرمي
     * `mysqli_sql_exception` (سلوكُ PHP 8 الافتراضي). فيُلَفُّ الاستعلامُ هنا
     * ليتصرّف واحدًا: `false` عند الفشلِ مع إعلانِ السبب.
     * ◆ وقع فعلًا: فرضتُ عمودَ `id` في كلِّ ابنٍ فرمى «Unknown column 'id'»
     *   فمات البذّارُ — والكانسُ الذي يقتل مستعملَه أسوأُ من غيابِه.
     */
    function ems_fk_q(mysqli $db, $sql, $quiet = false)
    {
        try {
            $r = $db->query($sql);
        } catch (\Throwable $t) {
            if (!$quiet) { fwrite(STDERR, '  ⚠ استعلامٌ فشل: ' . $t->getMessage() . "\n"); }
            return false;
        }
        if ($r === false && !$quiet) {
            fwrite(STDERR, '  ⚠ استعلامٌ فشل: ' . $db->error . "\n");
        }
        return $r;
    }
}

if (!function_exists('ems_fk_pk')) {
    /**
     * عمودُ المفتاحِ الأولِ لجدولٍ — أو `null` إن كان مركّبًا أو غائبًا.
     * ◆ ولا يُفترض `id`: جداولُ الربطِ كثيرًا ما تُمفتَح بعمودين، فالنزولُ إلى
     *   أحفادِها يُتخطّى **ويُعلَن** بدلَ أن يموتَ الكانس.
     */
    function ems_fk_pk(mysqli $db, $table)
    {
        static $cache = array();
        $k = strtolower((string) $table);
        if (array_key_exists($k, $cache)) { return $cache[$k]; }
        $esc = $db->real_escape_string((string) $table);
        $r = ems_fk_q($db, "SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
                             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$esc}'
                               AND CONSTRAINT_NAME = 'PRIMARY'
                             ORDER BY ORDINAL_POSITION", true);
        $cols = array();
        if ($r !== false) { while ($x = $r->fetch_row()) { $cols[] = (string) $x[0]; } }
        $cache[$k] = (count($cols) === 1) ? $cols[0] : null;
        return $cache[$k];
    }
}

if (!function_exists('ems_fk_logical_children')) {
    /**
     * مراجعُ **منطقيةٌ بلا مفتاحٍ أجنبيّ** — تُعلَن هنا في موضعٍ واحدٍ بسببِها.
     * ═══════════════════════════════════════════════════════════════════════
     * الاشتقاقُ من `information_schema` لا يرى ما لا قيدَ له. وقِيس فعلًا:
     * `capacity_consumption_ledger.unit_record_id` يشير إلى `unit_entries`
     * **بلا FK** (قيدُه الوحيدُ ذاتيٌّ على `reverses_led_id`) — فبقيت 50 صفًّا
     * بعد كنسِ الخمسين مرآةً، فصار كلُّ صفٍّ يُبلّغ «تجاوزَ طاقةٍ» (2 ⇒ 50).
     *
     * ◆ **ولا تُلصَق هذه في كلِّ بذرةٍ**: موضعٌ واحدٌ مُعلَنٌ، ومن يزيد مرجعًا
     *   منطقيًّا يزيده هنا. وسجلّان للمراجعِ يتفرَّقان — وهي عينُ العلّةِ التي
     *   أنتجت هذا الملفَّ أصلًا.
     * ◆ والأصوبُ معماريًّا **قيدٌ في القاعدة**؛ فحتى يُضاف يُعلَن النقصُ هنا
     *   صراحةً بدلَ أن يُسكَت عنه.
     * ═══════════════════════════════════════════════════════════════════════
     * @return array<int,array{table:string,column:string,note:string}>
     */
    function ems_fk_logical_children($table)
    {
        $map = array(
            'unit_entries' => array(
                array('table' => 'capacity_consumption_ledger', 'column' => 'unit_record_id',
                      'note' => 'بلا FK — قيدُه الوحيدُ ذاتيٌّ على reverses_led_id'),
            ),
        );
        $k = strtolower((string) $table);
        return isset($map[$k]) ? $map[$k] : array();
    }
}

if (!function_exists('ems_fk_children')) {
    /**
     * أبناءُ جدولٍ من `information_schema` — مستوًى واحد.
     * @return array<int,array{table:string,column:string,constraint:string}>
     */
    function ems_fk_children(mysqli $db, $table)
    {
        static $cache = array();
        $key = strtolower((string) $table);
        if (isset($cache[$key])) { return $cache[$key]; }
        $out = array();
        $esc = $db->real_escape_string((string) $table);
        $r = ems_fk_q($db, "SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME
                           FROM information_schema.KEY_COLUMN_USAGE
                          WHERE TABLE_SCHEMA = DATABASE()
                            AND REFERENCED_TABLE_SCHEMA = DATABASE()
                            AND REFERENCED_TABLE_NAME = '{$esc}'");
        if ($r === false) {
            /* لا يُخفى تعذّرُ القراءةِ: كانسٌ أعمى يُقرأ نجاحًا */
            fwrite(STDERR, "  ⚠ تعذّر قراءةُ أبناءِ {$table}: " . $db->error . "\n");
            $cache[$key] = array();
            return array();
        }
        while ($x = $r->fetch_assoc()) {
            $out[] = array('table' => (string) $x['TABLE_NAME'],
                           'column' => (string) $x['COLUMN_NAME'],
                           'constraint' => (string) $x['CONSTRAINT_NAME']);
        }
        $cache[$key] = $out;
        return $out;
    }
}

if (!function_exists('ems_fk_delete')) {
    /**
     * يحذف صفوفَ `$table` بمعرِّفاتِها **بعد** كنسِ كلِّ ذريّتِها — الأعمقُ أولًا.
     *
     * @param array $ids    معرِّفاتٌ صريحةٌ (فارغةٌ ⇒ صفرُ حذف)
     * @param array $report يُحشى: جدولٌ ⇒ عددُ ما حُذف
     * @param string $pk    عمودُ المفتاحِ في الأصل (افتراضًا `id`)
     * @param array $seen   حرسُ الدورةِ — لا يُمرَّر من الخارج
     * @return bool صحيحٌ إن نجح كلُّ حذفٍ؛ وكاذبٌ إن فشل واحدٌ (ويُعلَن)
     */
    function ems_fk_delete(mysqli $db, $table, array $ids, array &$report, $pk = 'id', array $seen = array())
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids, 'strlen'))));
        if (!$ids) { return true; }

        /* حرسُ الدورة: المفتاحُ الذاتيُّ (أبٌ يرجع إلى نفسِه) لا يُعيد النزول */
        $key = strtolower((string) $table);
        if (isset($seen[$key])) { return true; }
        $seen[$key] = true;

        $in = implode(',', $ids);
        $allOk = true;

        /* الأبناءُ = المشتقُّ من القيودِ **+** المُعلَنُ بلا قيدٍ */
        $children = array_merge(ems_fk_children($db, $table), ems_fk_logical_children($table));

        foreach ($children as $ch) {
            if (strtolower($ch['table']) === $key) {
                /* ◆ **مرجعٌ ذاتيٌّ**: صفٌّ يشير إلى صفٍّ في جدولِه نفسِه (عكسُ قيدٍ
                     يشير إلى أصلِه). فيُحذف المُشيرُ **قبل** المُشارِ إليه، وإلا
                     ردَّ القيدُ الحذفَ. ولا يُعاد النزولُ فلا دورةَ لا تنتهي. */
                $selfOk = ems_fk_q($db, "DELETE FROM `{$table}` WHERE `{$ch['column']}` IN ({$in})");
                if ($selfOk === false) { $allOk = false; continue; }
                $sn = max(0, $db->affected_rows);
                if ($sn > 0) {
                    $report[$table . ' (ذاتيٌّ)'] = (isset($report[$table . ' (ذاتيٌّ)'])
                        ? $report[$table . ' (ذاتيٌّ)'] : 0) + $sn;
                }
                continue;
            }
            /* معرِّفاتُ الأحفادِ تُقرأ **قبل** حذفِ الأبناءِ لأنها تضيع بعده */
            /* ◆ **مفتاحُ الابنِ يُثبَت لا يُفترض `id`**: فرضتُه أوّلَ مرةٍ فرمى
                 «Unknown column 'id'» على جدولٍ بمفتاحٍ آخرَ فمات البذّار. ومن
                 كان مفتاحُه مركّبًا يُتخطّى نزولُه **ويُعلَن** — فالحذفُ المباشرُ
                 عليه يكفي، وإن كان له أحفادٌ ردَّ القيدُ فظهر في التقرير. */
            $cpk = ems_fk_pk($db, $ch['table']);
            if ($cpk !== null) {
                $cq = ems_fk_q($db, "SELECT `{$cpk}` FROM `{$ch['table']}`
                                      WHERE `{$ch['column']}` IN ({$in})", true);
                $grand = array();
                if ($cq !== false) {
                    while ($g = $cq->fetch_row()) { $grand[] = (int) $g[0]; }
                }
                if ($grand) {
                    if (!ems_fk_delete($db, $ch['table'], $grand, $report, $cpk, $seen)) { $allOk = false; }
                }
            }
            $ok = ems_fk_q($db, "DELETE FROM `{$ch['table']}` WHERE `{$ch['column']}` IN ({$in})");
            if ($ok === false) {
                fwrite(STDERR, "  ⚠ حذفٌ فشل على {$ch['table']}.{$ch['column']}: " . $db->error . "\n");
                $allOk = false;
                continue;
            }
            $n = max(0, $db->affected_rows);
            if ($n > 0) { $report[$ch['table']] = (isset($report[$ch['table']]) ? $report[$ch['table']] : 0) + $n; }
        }

        $ok = ems_fk_q($db, "DELETE FROM `{$table}` WHERE `{$pk}` IN ({$in})");
        if ($ok === false) {
            fwrite(STDERR, "  ⚠ حذفٌ فشل على {$table}: " . $db->error . "\n");
            return false;
        }
        $n = max(0, $db->affected_rows);
        if ($n > 0) { $report[$table] = (isset($report[$table]) ? $report[$table] : 0) + $n; }
        return $allOk;
    }
}

if (!function_exists('ems_fk_delete_where')) {
    /**
     * كنسٌ بشرطٍ: تُقرأ المعرِّفاتُ أولًا ثم تُحذف بذريّتِها.
     * ═══════════════════════════════════════════════════════════════════════
     * ◆ **لماذا لا يُحذف بالشرطِ مباشرةً**: `DELETE ... WHERE entity_type='timesheet'`
     *   على `ems_business_events` مات بـ`fk_ffe_root` — لأنَّ
     *   `fin_financial_events.root_event_id` يرجع إلى الحقيقة. أي أنَّ كنسَ
     *   حقيقةٍ منشورةٍ يلزمه كنسُ **إسقاطِها الماليِّ** أولًا (ADR-15: الحقيقةُ
     *   أصلٌ والدفترُ إسقاطُها). فالحذفُ بالشرطِ يُترجَم معرِّفاتٍ ثم يُمرَّر
     *   للكانسِ الذي يشتقُّ الذريّة.
     * ◆ ولا يُنفَّذ شرطٌ خاوٍ: `$whereRaw` فارغةٌ ⇒ صفرُ حذفٍ وتُعلَن — فشرطٌ
     *   انهار إلى «صحيحٌ دائمًا» يكنس الجدولَ كلَّه (وقع في هذه الشجرةِ فعلًا).
     * ═══════════════════════════════════════════════════════════════════════
     */
    function ems_fk_delete_where(mysqli $db, $table, $whereRaw, array &$report, $pk = 'id')
    {
        $whereRaw = trim((string) $whereRaw);
        if ($whereRaw === '') {
            fwrite(STDERR, "  ⚠ شرطٌ خاوٍ على {$table} — صفرُ حذفٍ (ولا يُكنَس الجدولُ كلُّه)\n");
            return true;
        }
        $r = ems_fk_q($db, "SELECT `{$pk}` FROM `{$table}` WHERE {$whereRaw}");
        if ($r === false) {
            fwrite(STDERR, "  ⚠ تعذّر قراءةُ معرِّفاتِ {$table}: " . $db->error . "\n");
            return false;
        }
        $ids = array();
        while ($x = $r->fetch_row()) { $ids[] = (int) $x[0]; }
        if (!$ids) { return true; }
        return ems_fk_delete($db, $table, $ids, $report, $pk);
    }
}

if (!function_exists('ems_fk_sweep_report')) {
    /** سطرٌ واحدٌ يُلخّص ما كُنس — فالكنسُ يُعلَن لا يُخمَّن */
    function ems_fk_sweep_report(array $report)
    {
        if (!$report) { return 'صفرُ صفٍّ'; }
        arsort($report);
        $parts = array();
        foreach ($report as $t => $n) { $parts[] = $t . '=' . $n; }
        return array_sum($report) . ' صفًّا (' . implode(' · ', array_slice($parts, 0, 8)) . ')';
    }
}
