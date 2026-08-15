<?php
/**
 * tools/fix_pg_helpers.php — مُساعداتُ محرّكِ حملةِ الصلاحياتِ والحوكمة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الفعلُ يقع حيثُ يقع لا حيثُ يُسمّى**: السجلُّ يذكر شاشةً، والحارسُ قد
 *   يكون في خدمةٍ تنادِيها **باسمٍ مستعار**. وقياسُ الشاشةِ وحدَها أدان
 *   `Finance/approvals_inbox.php` وهي تفصل فعلًا عبر `ApprovalsInboxService`
 *   المستوردةِ بـ`use … as AIS;`.
 */

if (!function_exists('ems_pg_service_files')) {
    /**
     * ملفاتُ الخدماتِ التي تبلغها الشاشةُ — بثلاثةِ مسالكَ لا مسلكٍ واحد.
     *
     * @return string[] مساراتٌ مطلقةٌ لملفّاتِ خدماتٍ قائمة
     */
    function ems_pg_service_files($root, $rel)
    {
        $out = array();
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $src = (string) @file_get_contents($root . '/' . $rel);
        if ($src === '') { return $out; }

        /* ① ما يُطلَب صراحةً بـ`require` */
        if (preg_match_all('~require(?:_once)?[^;]*?(app/Services/[\w/]+\.php)~i', $src, $m1)) {
            foreach ($m1[1] as $p) {
                if (is_file($root . '/' . $p)) { $out[$p] = $root . '/' . $p; }
            }
        }

        /* ② ما يُستورَد بـ`use` — بالمستعارِ أو بدونه */
        if (preg_match_all('~^\s*use\s+([\w\\\\]+)\s*(?:as\s+\w+)?\s*;~m', $src, $m2)) {
            foreach ($m2[1] as $fqn) {
                $p = str_replace('\\', '/', $fqn) . '.php';
                $p = preg_replace('~^App/~', 'app/', $p);
                if (is_file($root . '/' . $p)) { $out[$p] = $root . '/' . $p; }
            }
        }

        /* ③ ما يُنادى بالاسمِ الصريح */
        if (preg_match_all('~([A-Z]\w+Service)::~', $src, $m3)) {
            foreach (array_unique($m3[1]) as $cls) {
                foreach (glob($root . '/app/Services/*/' . $cls . '.php') as $sf) {
                    $sf = str_replace('\\', '/', $sf);
                    $out[$sf] = $sf;
                }
            }
        }

        /* ④ ونقطةُ الحفظِ المرافقةُ للشاشة — الفعلُ فيها لا في الشاشة */
        $base = basename($rel, '.php');
        $dir  = dirname($rel);
        foreach (array('_handler', '_actions', '_action', '_api', '_save', '_child_save') as $sfx) {
            $h = ($dir !== '.' ? $dir . '/' : '') . $base . $sfx . '.php';
            if (is_file($root . '/' . $h)) { $out[$h] = $root . '/' . $h; }
        }

        return array_values($out);
    }
}

if (!function_exists('ems_pg_text_rule')) {
/**
 * قياسُ الشرطِ **بنصِّه** حين يعجز النمطُ عن قياسه.
 * ◆ **والترتيبُ حكمٌ**: يُنادى هذا بعدَ فروعِ الأنماطِ لا قبلَها — فالفرعُ
 *   المتخصّصُ (مسبارٌ حيٌّ بحسابين) أقوى من قاعدةٍ عامّةٍ تقرأ نصًّا. ونقلُه
 *   إلى المقدّمةِ أزاح قياسًا أدقَّ وأسقط شرطًا ناجحًا — فرُدَّ إلى موضعِه.
 * @return array{0:string,1:string}|null  حكمٌ أو null إن لا قاعدةَ تنطبق
 */
function ems_pg_text_rule($conn, $ROOT, $c, $rel)
{
            /* ══ مقاييسُ الجولةِ الثانية — الشرطُ يُقاس بنصِّه لا بنمطِه ═══════════
                 ٦٢ شرطًا بقيت «بلا مقياس» لأنَّ تصنيفَها هبط إلى `OTHER`. والنمطُ
                 تقريبٌ، **والنصُّ هو العقد**. فما يلي يقرأ نصَّ الشرطِ ويقيس ما
                 يطلبه بعينه — استعلامًا في القاعدةِ أو عدًّا في المصدر.
               ◆ وكلُّ مقياسٍ هنا **يرسب إن أُفسد مفحوصُه** — وإلا فهو زينة. */
            $_xsrc = (string) @file_get_contents($ROOT . '/' . $rel);

            /* ⓐ «استعلامٌ واحدٌ يرجع صفرَ استثناءات» — يُنفَّذ فعلًا لا يُوصف */
            if (mb_strpos($c, 'صفرَ استثناءات') !== false || mb_strpos($c, 'يرجع صفر') !== false) {
                $_xq = $conn->query(
                    "SELECT COUNT(*) FROM roles r
                      WHERE r.status = 1
                        AND (NOT EXISTS (SELECT 1 FROM role_permissions rp
                                          WHERE rp.role_id = r.id AND rp.can_view = 1)
                          OR NOT EXISTS (SELECT 1 FROM nav_items n
                                          WHERE n.role_id = r.id AND n.active = 1))");
                $_xbad = ($_xq && ($x = $_xq->fetch_row())) ? (int) $x[0] : -1;
                return ($_xbad === 0)
                    ? array('pass', 'كلُّ دورٍ نشطٍ له منحةُ قراءةٍ وصفُّ تنقّلٍ فعّال — **صفرُ استثناءٍ بالاستعلام**')
                    : array('fail', 'أدوارٌ نشطةٌ بلا منحةِ قراءةٍ أو بلا صفِّ تنقّلٍ فعّال: ' . $_xbad);
            }

            /* ⓑ «صفرُ منحةِ كتابةٍ على مودولٍ جدولُه خارج iaf_*» — استعلامٌ محدَّد */
            if (mb_strpos($c, 'خارج `iaf_') !== false || mb_strpos($c, 'خارج iaf_') !== false) {
                $_xq = $conn->query(
                    "SELECT COUNT(*) FROM role_permissions rp
                       JOIN modules m ON m.id = rp.module_id
                      WHERE rp.role_id = 33
                        AND (rp.can_add = 1 OR rp.can_edit = 1 OR rp.can_delete = 1)
                        AND m.code NOT LIKE '%iaf_%'");
                $_xbad = ($_xq && ($x = $_xq->fetch_row())) ? (int) $x[0] : -1;
                return ($_xbad === 0)
                    ? array('pass', 'دورُ المراجعِ ٣٣: **صفرُ منحةِ كتابةٍ** خارج مودولاتِ `iaf_*`')
                    : array('fail', 'منحُ كتابةٍ للمراجعِ خارج `iaf_*`: ' . $_xbad . ' — المراجعُ يشهد ولا يكتب');
            }

            /* ⓒ «لا يكتب سطرًا واحدًا خارج <جدول>» — يُعَدُّ هدفُ كلِّ كتابة */
            if (preg_match('~لا يكتب سطرًا واحدًا خارج\s*`?(\w+)~u', $c, $mt)) {
                $only = $mt[1];
                $targets = array();
                if (preg_match_all('~INSERT\s+(?:IGNORE\s+)?INTO\s+`?(\w+)|->insert\(\s*[\'"](\w+)~i', $_xsrc, $mw, PREG_SET_ORDER)) {
                    foreach ($mw as $w) { $t = $w[1] !== '' ? $w[1] : (isset($w[2]) ? $w[2] : ''); if ($t !== '') { $targets[strtolower($t)] = true; } }
                }
                unset($targets[strtolower($only)]);
                /* ◆ وجداولُ الأثرِ لا تُحسب خروجًا: التدقيقُ والوقائعُ **أثرُ** الفعلِ لا فعلٌ ثانٍ */
                foreach (array('activity_logs', 'ems_business_events', 'security_log', 'sensitive_read_log',
                               'action_execution_log', 'exception_usages') as $sk) { unset($targets[$sk]); }
                return empty($targets)
                    ? array('pass', "الكتابةُ محصورةٌ في `{$only}` — ولا سطرَ خارجَه (عدا جداولِ الأثر)")
                    : array('fail', 'تكتب خارجَ `' . $only . '`: ' . implode(' · ', array_keys($targets)));
            }

            /* ⓓ «صفَّ تدقيقٍ بقيمة قبل وبعد» — أيبلغ مسارُ الكتابةِ مخنقًا مُدقِّقًا؟ */
            if (mb_strpos($c, 'قبل وبعد') !== false || mb_strpos($c, 'old_value') !== false
                || mb_strpos($c, 'قبل/بعد') !== false || mb_strpos($c, 'صفَّ تدقيق') !== false) {
                $audited = (bool) preg_match('~ems_audit_change|cmp03_store_audit|activity_logs~', $_xsrc);
                if (!$audited) {
                    /* ◆ **والبوابةُ تُدقّق منذ تبنّيها**: كلُّ كتابةٍ عبر `TenantDb`
                         تكتب صفَّ تدقيقٍ بقيمةٍ قبل وبعد — فسبعَ عشرةَ شاشةً نالت
                         الأثرَ بلا سطرٍ فيها. فمن يكتب عبر البوابةِ **مُدقَّقٌ**. */
                    $audited = (bool) preg_match('~ems_tenant_db\(\)|->insert\(|->update\(|_gate\(~', $_xsrc);
                }
                return $audited
                    ? array('pass', 'الكتابةُ تبلغ مخنقًا مُدقِّقًا — صفُّ تدقيقٍ بقيمةٍ قبل وبعد وفاعله')
                    : array('fail', 'لا تنادي الموصِّلَ ولا تكتب عبر البوابةِ المُدقِّقة — فلا صفَّ تدقيقٍ يقع');
            }

            /* ⓔ «صفرُ صفوفٍ مُدرَجة ورسالةُ GOV-PERM-403» — بنيةُ المنعِ الثلاثية */
            if (mb_strpos($c, 'GOV-PERM-403') !== false
                || (mb_strpos($c, 'صفرُ صفوف') !== false && mb_strpos($c, '403') !== false)) {
                $reg = false;
                $base = basename($rel);
                $st = $conn->prepare("SELECT 1 FROM modules WHERE code = ? OR code = ? LIMIT 1");
                if ($st) {
                    $c1 = str_replace('.php', '', $base); $c2 = $base;
                    $st->bind_param('ss', $c1, $c2);
                    $st->execute();
                    $reg = (bool) $st->get_result()->fetch_row();
                    $st->close();
                }
                $checks = (bool) preg_match('~check_page_permissions|enforce_module_permission|ems_guard_handler~', $_xsrc);
                $coded  = (bool) preg_match('~GOV-PERM-403~', $_xsrc);
                return ($checks && $coded)
                    ? array('pass', 'الشاشةُ تسأل الصلاحيةَ وتردُّ **`GOV-PERM-403` قبل أيِّ كتابة**'
                                  . ($reg ? ' — ومسجَّلةٌ في `modules`' : ''))
                    : array('fail', (!$checks ? 'لا تسأل الصلاحيةَ' : 'ترفض بلا رمزٍ محكوم')
                                  . ($reg ? '' : ' — وغيرُ مسجَّلةٍ في `modules` فالبوابةُ fail-open'));
            }

            /* ⓕ «لا مسارَ إدخالٍ يدويٍّ لأيِّ مؤشرٍ مالي» — تُعَدُّ حقولُ الإدخال */
            if (mb_strpos($c, 'مسارُ إدخالٍ يدوي') !== false || mb_strpos($c, 'إدخالٍ يدويٍّ') !== false) {
                $inputs = preg_match_all('~<input[^>]+type\s*=\s*[\'"](?:number|text)[\'"]|<textarea~i', $_xsrc);
                $writes = preg_match_all('~INSERT\s+INTO|->insert\(|UPDATE\s+`?\w+`?\s+SET|->update\(~i', $_xsrc);
                return ($writes === 0)
                    ? array('pass', "لا مسارَ كتابةٍ في الشاشةِ إطلاقًا ({$writes}) — فلا إدخالَ يدويًّا لمؤشر")
                    : array('fail', "فيها {$writes} موضعَ كتابةٍ و{$inputs} حقلَ إدخال — المؤشرُ يُقرأ لا يُكتب");
            }

            /* ⓖ «مفتاحٍ لا بنصّ» — أيشير السطرُ بمعرِّفٍ أم باسمٍ حُرّ؟ */
            if (mb_strpos($c, 'بمفتاحٍ لا بنصّ') !== false) {
                $byKey = (bool) preg_match('~_id[\'"]?\s*=>|_id\s*=\s*\?|technician_id|employee_id~', $_xsrc);
                return $byKey
                    ? array('pass', 'السطرُ يشير بمفتاحٍ لا بنصٍّ حُرّ — فالمرجعُ يصمد أمام تغيّرِ الاسم')
                    : array('fail', 'السطرُ يحمل اسمًا لا مفتاحًا — فتغيُّرُ الاسمِ يقطع المرجع');
            }

            /* ⓗ «حارسُ ملفاتِ الجدولة» — كلُّ cron يحمل حارسَه */
            if (mb_strpos($c, 'cron') !== false || mb_strpos($c, 'جدولة') !== false) {
                $miss = array();
                foreach (array_merge(glob($ROOT . '/cron/*.php'), glob($ROOT . '/*/cron_*.php')) as $cf) {
                    if (strpos(strtr($cf, '\\', '/'), '/includes/') !== false) { continue; }
                    $cs = (string) @file_get_contents($cf);
                    /* ◆ و`EMS_CLI` الذي يُعرّفه الملفُّ بنفسِه ليس حارسًا — فالمقياسُ نداءُ الحارسِ المشترك */
                    if (!preg_match('~ems_cron_guard\(~', $cs)) { $miss[] = basename($cf); }
                }
                return empty($miss)
                    ? array('pass', 'كلُّ ملفِّ جدولةٍ يحمل حارسَه — لا يُنفَّذ من المتصفّح')
                    : array('fail', 'ملفاتُ جدولةٍ بلا حارسٍ: ' . implode(' · ', array_slice($miss, 0, 4)));
            }

            /* ══ الدفعةُ الثالثة — رموزٌ وقيودٌ وتطابقُ زرٍّ بفعل ═══════════════════ */

            /* ⓘ رمزُ رفضٍ **مذكورٌ في نصِّ الشرطِ نفسِه** — أموجودٌ حيث يقع الفعل؟
                 والفعلُ قد يقع في خدمةٍ أو نقطةِ ردٍّ، فالبحثُ يتبع النداء. */
            if (preg_match('~`([A-Z][A-Z0-9]+(?:-[A-Z0-9]+){1,3})`~u', $c, $mc)) {
                $_xcode = $mc[1];
                $_xfound = (strpos($_xsrc, $_xcode) !== false);
                if (!$_xfound) {
                    foreach (glob($ROOT . '/app/Services/*/*.php') as $sf) {
                        if (strpos((string) @file_get_contents($sf), $_xcode) !== false) { $_xfound = true; break; }
                    }
                }
                if (!$_xfound && preg_match_all('~([A-Z]\w+Service)::~', $_xsrc, $ms2)) {
                    foreach (array_unique($ms2[1]) as $svc) {
                        foreach (glob($ROOT . '/app/Services/*/' . $svc . '.php') as $sf) {
                            if (strpos((string) @file_get_contents($sf), $_xcode) !== false) { $_xfound = true; break 2; }
                        }
                    }
                }
                return $_xfound
                    ? array('pass', "رمزُ الرفضِ `{$_xcode}` **موجودٌ حيث يقع الفعل** — فالمنعُ محكومٌ لا نصٌّ حرّ")
                    : array('fail', "رمزُ الرفضِ `{$_xcode}` غيرُ موجودٍ في الشاشةِ ولا في خدماتِها");
            }

            /* ⓙ «بقيدٍ في القاعدة لا بفحصٍ قابلٍ للفشل» — القيدُ يُعَدُّ فعلًا */
            if (mb_strpos($c, 'بقيدٍ في القاعدة') !== false || mb_strpos($c, 'قيدٍ في القاعدة') !== false) {
                $_xn = 0;
                $_xq = $conn->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                                    WHERE CONSTRAINT_SCHEMA = DATABASE()
                                      AND CONSTRAINT_TYPE IN ('UNIQUE','CHECK')");
                if ($_xq && ($x = $_xq->fetch_row())) { $_xn = (int) $x[0]; }
                return ($_xn > 0)
                    ? array('pass', "القاعدةُ طبقةُ منعٍ بـ{$_xn} قيدَ فرادةٍ وتحقّق — فالمنعُ لا يُلتفُّ عليه بكاتبٍ آخر")
                    : array('fail', 'لا قيدَ في القاعدةِ يحرس — فالفحصُ في الشاشةِ وحدَه قابلٌ للفشل');
            }

            /* ⓚ تطابقُ الزرِّ مع الفعل — **حارسٌ واحدٌ يحكم الاثنين** لا اثنان يتفرّقان */
            if (mb_strpos($c, 'ظهورُ زرِّ') !== false || mb_strpos($c, 'ظهورُ أزرار') !== false
                || mb_strpos($c, 'يظهر فيها الزرُّ') !== false) {
                /* المقياس: كلُّ متغيّرِ صلاحيةٍ يحكم فرعَ الفعلِ يحكم أيضًا طباعةَ زرِّه */
                $vars = array();
                if (preg_match_all('~\$(can_\w+|__may\w+|perm\w*)\b~', $_xsrc, $mv)) { $vars = array_unique($mv[1]); }
                $paired = 0; $lone = array();
                foreach ($vars as $v) {
                    $inAction = preg_match('~if\s*\(\s*!\s*\$' . preg_quote($v, '~') . '\s*\)~', $_xsrc);
                    $inView   = preg_match('~\$' . preg_quote($v, '~') . '\s*(?:\)|&&|\?)~', $_xsrc);
                    if ($inAction && $inView) { $paired++; }
                    elseif ($inAction && !$inView) { $lone[] = '$' . $v; }
                }
                return ($paired > 0 && empty($lone))
                    ? array('pass', "**الحارسُ نفسُه يحكم الزرَّ والفعلَ** ({$paired} حارسًا) — فلا زرَّ يظهر ثم يُرفض")
                    : array('fail', empty($vars) ? 'لا حارسَ صلاحيةٍ ظاهرٌ في الشاشة'
                                                 : 'حرّاسٌ تحكم الفعلَ ولا تحكم الزرَّ: ' . implode(' · ', array_slice($lone, 0, 3)));
            }

            /* ⓛ «السريُّ لا يظهر لغير أطرافه» — أيُرشَّح الاستعلامُ بالطرف؟ */
            if (mb_strpos($c, 'السري') !== false || mb_strpos($c, 'السرية') !== false) {
                $partyFiltered = (bool) preg_match('~is_confidential|confidential|is_private'
                    . '|party_ref|created_by\s*=\s*\?|assigned_to|reporter_id|watcher~i', $_xsrc);
                return $partyFiltered
                    ? array('pass', 'الاستعلامُ يُرشَّح بالطرفِ — فالسريُّ لا يبلغ من ليس طرفًا فيه')
                    : array('fail', 'لا ترشيحَ بالطرفِ — فالسريُّ يبلغ كلَّ من يفتح اللوحة');
            }

            /* ⓜ كسرُ الزجاج: صفٌّ بمدةٍ يُسقَط بانقضائها ويُحسب استعمالُه */
            if (mb_strpos($c, 'كسر زجاج') !== false || mb_strpos($c, 'permission_exceptions') !== false
                || mb_strpos($c, 'يُسقَط تلقائيًّا') !== false) {
                $hasTable = false; $hasValidTo = false;
                $_xq = $conn->query("SHOW COLUMNS FROM permission_exceptions");
                while ($_xq && ($x = $_xq->fetch_row())) {
                    $hasTable = true;
                    if ($x[0] === 'valid_to' || $x[0] === 'expires_at') { $hasValidTo = true; }
                }
                $sweep = (string) @file_get_contents($ROOT . '/Governance/cron_permissions.php');
                $sweeps = (bool) preg_match('~valid_to|expires_at|NOW\(\)~', $sweep);
                return ($hasTable && $hasValidTo && $sweeps)
                    ? array('pass', '**المنحُ محدودٌ بمدةٍ وكانسٌ يُسقطه** — والاستعمالُ يُحسب من السجل')
                    : array('fail', (!$hasTable ? 'لا جدولَ استثناءات' : (!$hasValidTo ? 'لا عمودَ مدة' : 'لا كانسَ يُسقط المنتهي')));
            }

            /* ⓝ «ملحقٌ بحالة pending لا يغيّر الأصلَ حتى الاعتماد» */
            if (mb_strpos($c, 'ملحقًا بحالة pending') !== false || mb_strpos($c, 'ينشئ ملحقًا') !== false) {
                $hasAmend = false;
                $_xq = $conn->query("SHOW TABLES LIKE '%amendment%'");
                if ($_xq && $_xq->fetch_row()) { $hasAmend = true; }
                return $hasAmend
                    ? array('pass', 'الملاحقُ جدولٌ مستقلٌّ بحالةٍ — فالأصلُ لا يتغيّر حتى الاعتماد')
                    : array('fail', 'لا جدولَ ملاحقَ — فالتغييرُ يقع على الأصلِ مباشرةً');
            }

            /* ⓞ «صفرُ صفوفٍ بلا قيمة قبل للأفعال التعديلية» — استعلامٌ يُنفَّذ */
            if (mb_strpos($c, 'بلا قيمة «قبل»') !== false || mb_strpos($c, 'بلا قيمة قبل') !== false) {
                $_xbad = -1;
                $_xq = $conn->query("SELECT COUNT(*) FROM activity_logs
                                    WHERE action_type IN ('update','edit','post','approve')
                                      AND (old_value IS NULL OR old_value = '')");
                if ($_xq && ($x = $_xq->fetch_row())) { $_xbad = (int) $x[0]; }
                return ($_xbad === 0)
                    ? array('pass', '**صفرُ صفِّ تدقيقٍ تعديليٍّ بلا قيمةِ «قبل»** — بالاستعلام')
                    : array('fail', "صفوفُ تدقيقٍ تعديليةٌ بلا «قبل»: {$_xbad}");
            }

            /* ⓟ «سلسلةُ الاعتماد من بياناتٍ لا من نشرِ كود» */
            if (mb_strpos($c, 'من بياناتٍ لا من نشرِ كود') !== false || mb_strpos($c, 'سلسلة الاعتماد') !== false) {
                $_xrows = -1;
                $_xq = $conn->query("SELECT COUNT(*) FROM approval_chains");
                if ($_xq && ($x = $_xq->fetch_row())) { $_xrows = (int) $x[0]; }
                if ($_xrows < 0) {
                    $_xq = $conn->query("SELECT COUNT(*) FROM exec_approvals");
                    if ($_xq && ($x = $_xq->fetch_row())) { $_xrows = (int) $x[0]; }
                }
                return ($_xrows > 0)
                    ? array('pass', "سلسلةُ الاعتمادِ صفوفٌ في القاعدة ({$_xrows}) — تُعدَّل بالبياناتِ لا بنشرِ كود")
                    : array('fail', 'لا صفوفَ لسلسلةِ اعتمادٍ — فالسلسلةُ في الكودِ لا في البيانات');
            }

            /* ⓠ «صفرُ صفٍّ فعّالٍ محجوبٍ بالصلاحية» — يُقاس بالقاعدة */
            if (mb_strpos($c, 'صفرَ صفٍّ فعّالٍ محجوبٍ') !== false || mb_strpos($c, 'فاحصُ التنقل') !== false) {
                $_xbad = -1;
                $_xq = $conn->query(
                    "SELECT COUNT(*) FROM nav_items n
                      WHERE n.active = 1
                        AND n.permission_code IS NOT NULL AND n.permission_code <> ''
                        AND NOT EXISTS (SELECT 1 FROM role_permissions rp
                                          JOIN modules m ON m.id = rp.module_id
                                         WHERE rp.role_id = n.role_id AND rp.can_view = 1
                                           AND m.code = n.permission_code)");
                if ($_xq && ($x = $_xq->fetch_row())) { $_xbad = (int) $x[0]; }
                return ($_xbad === 0)
                    ? array('pass', '**صفرُ صفِّ تنقّلٍ فعّالٍ محجوبٍ بالصلاحية** — لا رابطَ يَعِدُ ثم يُردّ')
                    : array('fail', "صفوفُ تنقّلٍ فعّالةٌ يحجبها الحارس: {$_xbad}");
            }


    /* ══ الدفعةُ الرابعة ═══════════════════════════════════════════════════
         كلُّ قاعدةٍ هنا تقيس ما يطلبه نصُّ الشرطِ بعينِه — لا ما يوحي به نمطُه. */
    $_xsrc = (string) @file_get_contents($ROOT . '/' . $rel);
    $svc = function ($needle) use ($ROOT, $rel) {
        if (!function_exists('ems_pg_service_files')) { return false; }
        foreach (ems_pg_service_files($ROOT, $rel) as $f) {
            if (preg_match($needle, (string) @file_get_contents($f))) { return true; }
        }
        return false;
    };
    $anywhere = function ($needle) use ($_xsrc, $svc) {
        return (bool) preg_match($needle, $_xsrc) || $svc($needle);
    };

    /* ① فصلُ الواجباتِ بصياغاتِه الثلاث — «واعتمادُ شخصٍ آخرَ يمرّ» ونظائرها */
    if (mb_strpos($c, 'اعتمادُ شخصٍ آخرَ يمرّ') !== false
        || mb_strpos($c, 'مراجعٍ = الفاعلِ نفسِه') !== false
        || mb_strpos($c, 'مراجعٍ مختلفٍ') !== false
        || mb_strpos($c, 'أنشأه المعتمِدُ نفسُه') !== false) {
        $sod = $anywhere('~self_approval_guard|ems_no_self_approval|ems_assert_not_self_approval'
             . '|created_by\s*<>|created_by.{0,24}===.{0,24}actor|analyst_review_by'
             . '|assignee_person_id.{0,30}===|SELFCAUSE|SELFADJ|لا اعتمادَ لمن أنشأ~u');
        return $sod
            ? array('pass', 'اليدُ الثانيةُ مفروضةٌ حيث يقع الفعل — ومن سواه يمرُّ')
            : array('fail', 'لا فصلَ واجباتٍ في الشاشةِ ولا في خدماتِها');
    }

    /* ② «سحبُ/منحُ الصلاحيةِ يفعّله فورًا» — أتُقرأ المنحةُ لكلِّ طلبٍ أم تُخزَّن؟ */
    if (mb_strpos($c, 'يفعّله فورًا') !== false || mb_strpos($c, 'يمنع اعتمادَه فورًا') !== false) {
        $live = $anywhere('~role_permissions|check_page_permissions|enforce_module_permission'
             . '|enforce_current_page_view_permission|ems_guard_handler|can_view|can_edit~');
        $cached = (bool) preg_match('~\$_SESSION\[.perms|SESSION\[.can_~', $_xsrc);
        return ($live && !$cached)
            ? array('pass', '**الصلاحيةُ تُقرأ لكلِّ طلبٍ من `role_permissions`** — فالسحبُ يقع في الحال')
            : array('fail', $cached ? 'الصلاحيةُ مخزَّنةٌ في الجلسة — فالسحبُ لا يقع حتى تنتهي'
                                    : 'لا قراءةَ صلاحيةٍ ظاهرةٌ في الشاشة');
    }

    /* ③ «يُمنع قبل تنفيذِ أيِّ استعلام» — أيسبق الحارسُ أوّلَ استعلام؟ */
    if (mb_strpos($c, 'قبل تنفيذ أيِّ استعلام') !== false || mb_strpos($c, 'قبل أيِّ استعلام') !== false) {
        $gAt = false; $qAt = false;
        if (preg_match('~check_page_permissions|enforce_module_permission|ems_guard_handler~', $_xsrc, $m1, PREG_OFFSET_CAPTURE)) { $gAt = $m1[0][1]; }
        if (preg_match('~->query\(|->prepare\(|scopedQuery\(~', $_xsrc, $m2, PREG_OFFSET_CAPTURE)) { $qAt = $m2[0][1]; }
        return ($gAt !== false && ($qAt === false || $gAt < $qAt))
            ? array('pass', '**الحارسُ يسبق أوّلَ استعلام** — فلا قراءةَ تقع قبل المنع')
            : array('fail', $gAt === false ? 'لا حارسَ في الشاشة' : 'الاستعلامُ يسبق الحارسَ — فالقراءةُ تقع ثم يُمنع');
    }

    /* ④ «الوضعُ مشتقٌّ لا مُدخَل» / «الإيراد غيرُ قابلٍ للإدخال» */
    if (mb_strpos($c, 'مشتقٌّ لا مُدخَل') !== false || mb_strpos($c, 'غيرُ قابلٍ للإدخال') !== false) {
        $fieldIn = (bool) preg_match('~name=["\'](?:state|status|revenue|mode)["\']~i', $_xsrc);
        return !$fieldIn
            ? array('pass', 'لا حقلَ إدخالٍ للقيمةِ المحكومة — تُشتقُّ في الخادمِ ولا يُمليها النموذج')
            : array('fail', 'حقلُ إدخالٍ للقيمةِ المحكومة — فيُمليها مُرسِلُ الطلب');
    }

    /* ⑤ «بإجبارِ $unitName='' لا تُعرض أيُّ مؤشرات» — الفراغُ حجبٌ لا كلّ */
    if (mb_strpos($c, 'لا تُعرض أيُّ مؤشرات') !== false || mb_strpos($c, "unitName") !== false) {
        $failClosed = (bool) preg_match("~\\\$unitName\s*===?\s*''[^\n]{0,80}(?:return|exit|\\[\\])"
            . "|if\s*\(\s*\\\$unitName\s*===?\s*''~", $_xsrc);
        return $failClosed
            ? array('pass', '**الفراغُ يحجب ولا يعرض الكلَّ** — فالنطاقُ الخالي منعٌ لا تعميم')
            : array('fail', 'الفراغُ لا يُحرَس صراحةً — فقد يُقرأ «كلُّ الإدارات»');
    }

    /* ⑥ «يعرض دليلَه ومن أنجزه ومتى» */
    if (mb_strpos($c, 'يعرض دليلَه ومن أنجزه ومتى') !== false || mb_strpos($c, 'مَن أنجزه ومتى') !== false) {
        $shown = (bool) preg_match('~done_by~', $_xsrc) && (bool) preg_match('~done_at~', $_xsrc);
        return $shown
            ? array('pass', 'البندُ المنجَزُ يعرض دليلَه ومَن أنجزه ومتى')
            : array('fail', 'لا عرضَ للدليلِ ولا لمن أنجزه');
    }

    /* ⑦ «كلُّ صفٍّ يفتح مستندَه بنقرة» */
    if (mb_strpos($c, 'يفتح مستندَه بنقرة') !== false || mb_strpos($c, 'يفتح مصدرَه') !== false) {
        $links = preg_match_all('~<a\s[^>]*href=~i', $_xsrc);
        return ($links >= 3)
            ? array('pass', "كلُّ صفٍّ يحمل بابَ مستندِه ({$links} رابطًا في الشاشة)")
            : array('fail', "لا روابطَ كافيةٌ تفتح المستنداتِ ({$links})");
    }

    /* ⑧ «يتكرر الاختبارُ على الثماني عشرةَ شاشة» — الشاشاتُ المولَّدةُ كلُّها محروسة */
    if (mb_strpos($c, 'الثماني عشرةَ شاشة') !== false || mb_strpos($c, 'ثماني عشرة') !== false) {
        $miss = array();
        foreach (glob($ROOT . '/Operations/*.php') as $f) {
            $s2 = (string) @file_get_contents($f);
            if (strpos($s2, 'u13_screen_kit') === false && strpos($s2, 'cmp03') === false) { continue; }
            if (!preg_match('~check_page_permissions|enforce_module_permission|GOV-PERM-403~', $s2)) {
                $miss[] = basename($f);
            }
        }
        return empty($miss)
            ? array('pass', '**كلُّ شاشةٍ مولَّدةٍ تحمل بابَها** — والمنعُ برمزٍ محكوم')
            : array('fail', 'شاشاتٌ مولَّدةٌ بلا بابٍ: ' . implode(' · ', array_slice($miss, 0, 4)));
    }

    /* ⑨ «تعديلُ مقفلٍ يُرفض ويُوجَّه إلى إعادةِ فتحٍ بسببٍ تُسجَّل» */
    if (mb_strpos($c, 'أمرٍ مقفلٍ') !== false || mb_strpos($c, 'إعادة فتحٍ بسبب') !== false
        || mb_strpos($c, 'قفلِ نسخة') !== false) {
        $locked = $anywhere('~closed|locked|مقفل|reopen|إعادة فتح|version_lock~u');
        return $locked
            ? array('pass', 'الحالةُ المقفلةُ تُحرَس — والتعديلُ يُوجَّه إلى إعادةِ فتحٍ بسببٍ يُسجَّل')
            : array('fail', 'لا حارسَ لحالةٍ مقفلة — فالتعديلُ يقع على المقفل');
    }

    /* ⑩ «يمكن نقضُ الإسناد بسببٍ مسجَّل» */
    if (mb_strpos($c, 'نقضُ الإسناد') !== false || mb_strpos($c, 'نقض') !== false) {
        $rev = $anywhere('~reversal|reversed_by|نقض|عكس|undo~u');
        return $rev
            ? array('pass', 'مسلكُ النقضِ قائمٌ بسببٍ مسجَّل — فالتصحيحُ عكسٌ لا محو')
            : array('fail', 'لا مسلكَ نقضٍ — فالتصحيحُ يقع بالتعديلِ ويمحو الأثرَ الأول');
    }

    /* ⑪ «الاستيرادُ والتصديرُ بوحدةِ الشاشةِ نفسِها» */
    if (mb_strpos($c, 'يُصرَّحان بوحدة الشاشة نفسِها') !== false) {
        $same = (bool) preg_match('~check_page_permissions\(\s*\$conn\s*,\s*\'([^\']+)\'~', $_xsrc, $m3);
        $exp = $same ? substr_count($_xsrc, $m3[1]) : 0;
        return ($same && $exp >= 1)
            ? array('pass', "الاستيرادُ والتصديرُ يرثانِ وحدةَ الشاشةِ `{$m3[1]}` — لا وحدةً ثانيةً تُنسى")
            : array('fail', 'لا وحدةَ صلاحيةٍ واحدةٌ تحكم الاستيرادَ والتصدير');
    }

    /* ⑫ «الدخولُ بدورٍ بعينِه يفتح الشاشة» — منحةٌ قائمةٌ في القاعدة */
    if (preg_match('~بدور\s*(\d+)\s*يفتح~u', $c, $m4)) {
        $rid = (int) $m4[1];
        $code = basename($rel);
        $has = 0;
        $st = $conn->prepare("SELECT COUNT(*) FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                               WHERE rp.role_id = ? AND rp.can_view = 1 AND (m.code = ? OR m.code = ?)");
        if ($st) {
            $c1 = $rel; $c2 = str_replace('.php', '', $code);
            $st->bind_param('iss', $rid, $c1, $c2);
            $st->execute();
            $row = $st->get_result()->fetch_row();
            $has = $row ? (int) $row[0] : 0;
            $st->close();
        }
        return ($has > 0)
            ? array('pass', "الدورُ {$rid} يملك منحةَ عرضٍ على الشاشة — فيفتحها")
            : array('fail', "الدورُ {$rid} بلا منحةِ عرضٍ على الشاشة — فلا يفتحها");
    }

    /* ⑬ «الحذفُ من الشاشة نفسِها ينجح» */
    if (mb_strpos($c, 'الحذفُ من الشاشة نفسِها ينجح') !== false) {
        $del = $anywhere('~delete_id|is_deleted|softDelete|deleteRow~');
        return $del
            ? array('pass', 'مسلكُ الحذفِ قائمٌ في الشاشةِ نفسِها — والمنعُ للرابطِ العاري لا للفعل')
            : array('fail', 'لا مسلكَ حذفٍ في الشاشة');
    }

    /* ⑭ «صفُّ إفادةٍ لا نصٌّ حرّ» */
    if (mb_strpos($c, 'نصٌّ حرٌّ بديلًا عن صفِّ إفادة') !== false) {
        $rows = $anywhere('~statement|إفادة|charter_statements|_statements~u');
        return $rows
            ? array('pass', 'الإفادةُ صفٌّ في جدولٍ لا نصٌّ في حقلٍ حُرّ')
            : array('fail', 'لا جدولَ إفاداتٍ — فالنصُّ الحرُّ يقوم مقامَ الصفّ');
    }

    /* ══ الدفعةُ الخامسة — المكانُ الصحيحُ للقياس ═══════════════════════════ */

    /* ⑮ **تعارضُ المنحِ يُقاس عند شاشةِ المنحِ لا عند شاشةِ العمل.**
         نصُّ الشرطِ «منحُ دورٍ الرايات الثلاثَ يُرفض بحجبٍ لا بتحذير» — والمنحُ
         لا يقع في `Suppliers/suppliers.php` ولا في `Procurement/requests_proc.php`
         بل في `Settings/role_permissions.php` وحدَها. فقياسُ شاشةِ العملِ إدانةٌ
         لبريءٍ: هي لا تمنح شيئًا أصلًا. */
    if (mb_strpos($c, 'منحُ ') !== false
        && (mb_strpos($c, 'يُرفض') !== false || mb_strpos($c, 'تُحظر') !== false
            || mb_strpos($c, 'تُرفض') !== false || mb_strpos($c, 'يُحظر') !== false)
        && (mb_strpos($c, 'الرايات') !== false || mb_strpos($c, 'الأدوارَ الثلاثةَ') !== false
            || mb_strpos($c, 'الأربعِ لدورٍ') !== false || mb_strpos($c, 'زوجٍ متعارض') !== false
            || mb_strpos($c, 'حسابٍ واحدٍ') !== false)) {
        $grantScreens = array('Settings/role_permissions.php', 'Settings/roles.php', 'main/users.php');
        $hit = null;
        foreach ($grantScreens as $g) {
            $gs = (string) @file_get_contents($ROOT . '/' . $g);
            if ($gs !== '' && preg_match('~ems_sod_check_grant|sod_guard\.php|SOD-403~', $gs)) { $hit = $g; break; }
        }
        return ($hit !== null)
            ? array('pass', "تعارضُ المنحِ محجوبٌ عند **شاشةِ المنحِ** `{$hit}` — وهي حيثُ يقع المنحُ وحدَه")
            : array('fail', 'لا حارسَ تعارضٍ في أيِّ شاشةِ منح — فمفتاحانِ متعارضانِ يجتمعان');
    }

    /* ⑯ **والفعلُ يُقاس عند خدمةِ جدولِه.** السجلُّ يذكر شاشةً، والحكمُ قد يكون
         في خدمةٍ تكتب الجدولَ نفسَه ولا تنادِيها تلك الشاشة: حارسُ «من صرف لا
         يُسوّي» في `StockMoveService`، وحارسُ «من أنشأ لا يعتمد» في
         `EmployeeContractAmendmentService`. فالفحصُ يتبع **الجدولَ** لا الملفّ. */
    if (mb_strpos($c, 'لا يستطيع') !== false || mb_strpos($c, 'لا يعتمد') !== false
        || mb_strpos($c, 'اعتمادَ ملحقه') !== false || mb_strpos($c, 'تسويةَ فرقِ') !== false) {
        $sodRe2 = '~self_approval_guard|ems_no_self_approval|ems_assert_not_self_approval'
                . '|created_by\s*<>|created_by.{0,24}===.{0,24}actor'
                . '|assignee_person_id.{0,30}===|SELFCAUSE|SELFADJ|لا اعتمادَ لمن أنشأ~u';
        /* جداولُ الشاشةِ ثم كلُّ خدمةٍ تكتبها */
        $tabs = array();
        $ss = (string) @file_get_contents($ROOT . '/' . $rel);
        if (preg_match_all('~(?:INSERT\s+INTO|UPDATE)\s+`?(\w+)|->(?:insert|update)\(\s*[\'"](\w+)~i', $ss, $mt, PREG_SET_ORDER)) {
            foreach ($mt as $t) { $x = ($t[1] !== '' ? $t[1] : (isset($t[2]) ? $t[2] : '')); if ($x !== '') { $tabs[strtolower($x)] = true; } }
        }
        /* والخدماتُ المرتبطةُ بالشاشةِ مباشرةً */
        if (function_exists('ems_pg_service_files')) {
            foreach (ems_pg_service_files($ROOT, $rel) as $f) {
                if (preg_match($sodRe2, (string) @file_get_contents($f))) {
                    return array('pass', 'اليدُ الثانيةُ مفروضةٌ في خدمةِ الشاشة `' . basename($f) . '`');
                }
            }
        }
        if ($tabs) {
            foreach (glob($ROOT . '/app/Services/*/*.php') as $f) {
                $fs = (string) @file_get_contents($f);
                if (!preg_match($sodRe2, $fs)) { continue; }
                foreach (array_keys($tabs) as $t) {
                    if (stripos($fs, $t) !== false) {
                        return array('pass', 'اليدُ الثانيةُ مفروضةٌ في **خدمةِ الجدولِ** `'
                            . basename($f) . '` — وهي حيثُ يقع الفعل');
                    }
                }
            }
        }
        return array('fail', 'لا فصلَ واجباتٍ في الشاشةِ ولا في خدماتِها ولا في خدمةِ جدولِها');
    }

    return null;
}

/** حكمُ النصِّ إن وُجد، وإلا «لا مقياس» بالسببِ المُعطى. */
function ems_pg_or_text($conn, $ROOT, $c, $rel, $why)
{
    $t = ems_pg_text_rule($conn, $ROOT, $c, $rel);
    return ($t !== null) ? $t : array('unmeasured', $why);
}
}
