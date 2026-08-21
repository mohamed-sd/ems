<?php
/**
 * app/Install/SchemaDumper.php — مولّد مصنوعات التثبيت (EMS Installer · المرحلة ①)
 * ═══════════════════════════════════════════════════════════════════════════
 * يقرأ قاعدةً حيّةً (قراءةً خالصة، لا يكتب فيها حرفًا) ويولّد ثلاثة ملفّات:
 *
 *   schema.sql          بنيةُ كلِّ الجداول ثمّ المناظير — بلا بيانات.
 *   seed_reference.sql  البذرةُ المرجعية بطبقتيها (العالمية ثمّ المستأجَرة).
 *   MANIFEST.json       بصمةُ sha1 لكلِّ ملف + عددُ الكائنات + المصدر والتاريخ.
 *
 * لماذا PHP خالصٌ بلا mysqldump: المُثبِّت يجب أن يعمل على استضافةٍ مشتركةٍ
 * لا shell فيها. المنطق مُنتزَعٌ من migrate_export_schema المُجرَّب في migrate.php.
 *
 * ── طبقتا البذر ─────────────────────────────────────────────────────────────
 * ① عالمية (SEED_GLOBAL): جداولٌ بلا company_id — بنيةٌ متنكّرةٌ في هيئة بيانات.
 *    بدونها النظام لا يُقلِع: لا قائمةَ تنقّلٍ ولا صلاحياتٍ ولا خريطةَ أثر.
 *    تُصدَّر حرفيًّا بمعرّفاتها الصريحة (role_permissions يشير إلى roles.id
 *    وmodules.id — إسقاطُ المعرّفات يكسر الإحالة).
 *
 * ② مستأجَرة (SEED_TENANT): جداولٌ مرجعيةٌ لكنّها تحمل company_id، فصفوفُها
 *    تخصُّ شركةَ المصدر لا كلَّ شركة. تُصدَّر قالبًا: قيمةُ company_id تُستبدل
 *    بالعلامة النائبة COMPANY_PLACEHOLDER، والمُثبِّت يحقن معرّف الشركة التي
 *    أنشأها. تُصدَّر صفوفُ شركةٍ واحدةٍ فقط (أصغرَ معرّفٍ له صفوف) تفاديًا
 *    للتكرار حين تتعدّد شركاتُ المصدر.
 *
 * ملاحظة: `admin_companies` و`users` و`employees` ليست بذرةً — بياناتُ مستأجِرٍ
 * يُنشئها المُثبِّتُ من مدخلات المُثبِّت لا من قاعدة المصدر.
 */

namespace App\Install;

use mysqli;

class SchemaDumper
{
    /** العلامة النائبة لمعرّف الشركة داخل البذرة المستأجَرة. */
    const COMPANY_PLACEHOLDER = '{{COMPANY_ID}}';

    /** أقصى عدد صفوف في عبارة INSERT واحدة — تفاديًا لـ max_allowed_packet. */
    const ROWS_PER_INSERT = 200;

    /** ① بذرةٌ عالمية — تُصدَّر حرفيًّا بمعرّفاتها. الترتيب يحترم الإحالة. */
    const SEED_GLOBAL = array(
        'roles',
        'modules',
        'role_permissions',
        'link_groups',
        'nav_items',
        'equipments_types',
        'failure_codes',
    );

    /**
     * جداولٌ نُظر فيها وقُرِّر **عدمُ** بذرها — يُوثَّق القرارُ كي لا يُعاد فتحُه:
     *   ems_sequences  عدّاداتٌ تشغيليةٌ لا مرجعية (scope يحمل معرّف الشركة،
     *                  والقيمُ أرقامُ المصدر). ServerId::nextNo() يُنشئ الصفَّ
     *                  كسولًا عند أوّل استعمال، فالبذرُ يورث عدّادًا غريبًا.
     *   admin_companies · users · employees  بياناتُ مستأجِرٍ يُنشئها المُثبِّت.
     */
    const SEED_EXCLUDED = array(
        'ems_sequences',
        'admin_companies',
        'users',
        'employees',
    );

    /** ② بذرةٌ مستأجَرة — company_id يُستبدل بالعلامة النائبة. */
    const SEED_TENANT = array(
        'fin_chart_of_accounts',
        'fin_approval_matrix',
        'fin_effect_map',
        'job_titles',
        'employee_roles',
        'transfer_types',
        /* ⇐ INJ-0060 · «بعد نشرٍ نظيفٍ من `database/` وحدَه … و`SELECT COUNT(*)
             FROM gov_field_class` > 0». وكان التصنيفُ يُبذر بأداةِ CLI خارجَ مسارِ
             النشر (`tools/u13_seed.php`)، فالنشرُ من `database/` يترك الجدولَ
             خاويًا و**أربعًا وأربعين شاشةً تُصيَّر «لا عمودَ مصنَّفٌ لهذه الشاشة»**.
             والتصنيفُ **شرطُ الظهور** لا زينة — فمكانُه البذرةُ المرجعيةُ لا أداةٌ
             تُنسى: خطوةٌ يدويةٌ خارجَ الخطِّ تُنسى مرةً، والشاشاتُ تخلو أبدًا. */
        'gov_field_class',
    );

    /** @var mysqli */
    private $conn;

    /** @var string */
    private $dbName = '';

    /** @var string وقتُ التوليد — ثابتٌ لكلّ المصنوعات في الجولة الواحدة. */
    private $stamp;

    public function __construct(mysqli $conn, $stamp = null)
    {
        $this->conn = $conn;
        $this->stamp = $stamp !== null ? $stamp : date('Y-m-d H:i:s');
        $res = $conn->query('SELECT DATABASE() AS db');
        if ($res) {
            $row = $res->fetch_assoc();
            $this->dbName = $row['db'] !== null ? $row['db'] : '';
            $res->free();
        }
    }

    public function databaseName()
    {
        return $this->dbName;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // المخطّط
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * لقطةُ بنيةٍ كاملة: كلُّ جداول الأساس ثمّ كلُّ المناظير.
     * @return array [sql|'', err|'', meta]
     */
    public function dumpSchema()
    {
        list($tables, $views, $err) = $this->listObjects();
        if ($err !== '') {
            return array('', $err, array());
        }

        $out = array();
        $out[] = $this->banner('EMS — مخطّط التثبيت الكامل (بنية فقط، بلا بيانات)', array(
            'الجداول: ' . count($tables) . ' · المناظير: ' . count($views),
            'يُستورد على قاعدةٍ فارغة عبر المُثبِّت. FOREIGN_KEY_CHECKS مُطفأٌ داخل',
            'الملف لأن الجداول مرتّبةٌ أبجديًّا لا حسب تبعية المفاتيح الأجنبية.',
        ));
        // COLLATE صريحٌ إلزامًا: `SET NAMES utf8mb4` وحدَه يُصفّر collation_connection
        // إلى افتراض الخادم (utf8mb4_0900_ai_ci في MySQL 8)، فتُولَد أعمدةُ
        // المناظير المشتقّةُ من ثوابتَ نصّيةٍ بترتيبٍ مخالفٍ لقاعدة المصدر.
        $out[] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;";
        $out[] = 'SET FOREIGN_KEY_CHECKS = 0;';
        $out[] = '';

        foreach ($tables as $t) {
            $r = $this->conn->query('SHOW CREATE TABLE ' . $this->qi($t));
            if (!$r) {
                return array('', "فشل SHOW CREATE TABLE {$t}: " . $this->conn->error, array());
            }
            $row = $r->fetch_assoc();
            $r->free();
            // `SHOW CREATE TABLE` يحمل AUTO_INCREMENT=n من قاعدة المصدر — لو بقي
            // لبدأ تنصيبٌ نظيفٌ ترقيمَه من عدّاد قاعدةٍ أخرى (شركةٌ أولى برقم 625).
            $create = preg_replace('/\sAUTO_INCREMENT=\d+/i', '', $row['Create Table']);
            $out[] = '-- ── Table: ' . $t . ' ──';
            $out[] = $create . ';';
            $out[] = '';
        }

        // المناظير أخيرًا: تعريفُها يستعلم من الجداول فيلزم وجودُها أوّلًا.
        foreach ($views as $v) {
            $r = $this->conn->query('SHOW CREATE VIEW ' . $this->qi($v));
            if (!$r) {
                return array('', "فشل SHOW CREATE VIEW {$v}: " . $this->conn->error, array());
            }
            $row = $r->fetch_assoc();
            $r->free();
            // DEFINER يربط المنظور بمستخدمٍ قد لا يوجد على خادم الوجهة — يُنزع.
            $create = preg_replace('/\sDEFINER\s*=\s*`[^`]*`@`[^`]*`/i', '', $row['Create View']);

            // أعمدةُ المنظور المشتقّةُ من ثوابتَ نصّية (CASE/CONCAT) تأخذ ترتيبَ
            // الجلسة وقتَ الإنشاء لا ترتيبَ جدولٍ. فيُضبط الترتيبُ لكلِّ منظورٍ
            // بحسب أصله — نسخٌ أمينٌ لا تصحيحٌ ضمني: المُصدِّر يعكس المصدر كما هو.
            $coll = $this->viewCollation($v);
            $out[] = '-- ── View: ' . $v . ' ──';
            if ($coll !== '') {
                $out[] = "SET collation_connection = '" . $this->conn->real_escape_string($coll) . "';";
            }
            $out[] = $create . ';';
            $out[] = '';
        }

        /* ══ INJ-FIX-01 · الموجة ب · الحاجز ① — القوادحُ صنفٌ ثالثٌ كان يسقط ══
           ◆ **العيبُ الذي يسدُّه — مقيسٌ باستنساخٍ نظيفٍ لا بمراجعةِ كود**:
             بصمةُ المخططِ طابقت حرفًا (628 كائنًا · 10094 عمودًا) **والاستنساخُ
             خالٍ من أربعةٍ وثلاثين قادحًا**. فالمُصدِّرُ كان يُخرج جدولًا ومنظورًا
             ولا يُخرج قادحًا، والبصمةُ المبنيةُ على الأعمدةِ **لا تراه**
             فتُعلن تطابقًا صادقًا على مقامٍ ناقص.
           ◆ **وليست زينة**: منها ما يمنع تداخلَ حصصِ الملكية · ويحفظ عدمَ
             رجعيةِ قراراتِ الإدارةِ التنفيذيةِ واعتماداتِها · ويمنع التفويضَ
             غيرَ القابلِ للتفويضِ وتسلسلَ الإنابة · ويمنع رصيدَ مخزونٍ سالبًا ·
             ويردُّ الوحدةَ المكرَّرة. فاستنساخٌ بلا قوادحَ نظامٌ **بلا حرّاسِ
             قاعدةٍ** يبدو مطابقًا.
           ◆ **ولماذا لم تُرَ قبلًا**: `information_schema.TRIGGERS` تُرشِّح بما
             يملكه الحسابُ الفاحص. فقياسُ الأساسِ أعلن «قوادح=0» وهو يقرأ
             بحسابٍ لا يراها — وهذا نصُّ GAP-33: «قادحٌ لا يُرى قد لا يُعاد بناؤه».
           ◆ **و`DEFINER` يُنزَع** كما يُنزَع من المناظير: يربط القادحَ بمستخدمٍ
             قد لا يوجد على خادمِ الوجهةِ فيُفشل الاستيراد.
           ◆ **و`DROP TRIGGER IF EXISTS` يسبق كلَّ إنشاء**: الاستيرادُ المستأنَفُ
             على قاعدةٍ فيها القادحُ يرفع 1359 ويقف. */
        $triggers = $this->listTriggers();
        foreach ($triggers as $t) {
            $r = $this->conn->query('SHOW CREATE TRIGGER ' . $this->qi($t));
            if (!$r) {
                return array('', "فشل SHOW CREATE TRIGGER {$t}: " . $this->conn->error, array());
            }
            $row = $r->fetch_assoc();
            $r->free();
            $create = isset($row['SQL Original Statement']) ? $row['SQL Original Statement'] : '';
            if ($create === '') { continue; }
            $create = preg_replace('/\sDEFINER\s*=\s*`[^`]*`@`[^`]*`/i', '', $create);
            /* ◆ **ولا `DELIMITER` هنا**: هي تعليمةُ عميلٍ لا عبارةُ SQL، والمُثبِّتُ
                 يستورد بـ`multi_query` — فالخادمُ هو من يفصل العبارات، وهو يفهم
                 الكتلةَ المركَّبةَ `BEGIN … END` ولا يحتاج فاصلًا بديلًا.
                 وإقحامُها كان يكسر المسارَ الحقيقيَّ للتثبيتِ ليُرضيَ عميلَ سطرِ
                 الأوامر — فيُصلَح المُصدِّرُ على المسارِ الذي يُستعمل فعلًا. */
            $out[] = '-- ── Trigger: ' . $t . ' ──';
            $out[] = 'DROP TRIGGER IF EXISTS ' . $this->qi($t) . ';';
            $out[] = $create . ';';
            $out[] = '';
        }

        $out[] = 'SET FOREIGN_KEY_CHECKS = 1;';
        $out[] = '';

        return array(implode("\n", $out), '', array(
            'tables'   => count($tables),
            'views'    => count($views),
            'triggers' => count($triggers),
        ));
    }

    /** أسماءُ قوادحِ القاعدةِ الحالية — مرتَّبةً بجدولِها ثم باسمِها. */
    private function listTriggers()
    {
        $out = array();
        $st = $this->conn->prepare(
            "SELECT TRIGGER_NAME FROM information_schema.TRIGGERS
              WHERE TRIGGER_SCHEMA = ? ORDER BY EVENT_OBJECT_TABLE, ACTION_ORDER, TRIGGER_NAME");
        if (!$st) { return $out; }
        $db = $this->databaseName();
        $st->bind_param('s', $db);
        $st->execute();
        $r = $st->get_result();
        while ($x = $r->fetch_assoc()) { $out[] = $x['TRIGGER_NAME']; }
        $st->close();
        return $out;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // البذرة
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * البذرةُ المرجعية بطبقتيها.
     * @return array [sql|'', err|'', meta]
     */
    public function dumpSeed()
    {
        $counts = array();

        $out = array();
        $out[] = $this->banner('EMS — البذرة المرجعية (طبقتان)', array(
            '① عالمية: بنيةٌ متنكّرةٌ في هيئة بيانات — بدونها لا تنقّلَ ولا صلاحيات.',
            '② مستأجَرة: مرجعيةٌ تحمل company_id — القيمةُ علامةٌ نائبةٌ يحقنها المُثبِّت:',
            '   ' . self::COMPANY_PLACEHOLDER,
            'ليست بذرةً بالتصميم: admin_companies · users · employees (بيانات مُثبِّت).',
        ));
        // COLLATE صريحٌ إلزامًا: `SET NAMES utf8mb4` وحدَه يُصفّر collation_connection
        // إلى افتراض الخادم (utf8mb4_0900_ai_ci في MySQL 8)، فتُولَد أعمدةُ
        // المناظير المشتقّةُ من ثوابتَ نصّيةٍ بترتيبٍ مخالفٍ لقاعدة المصدر.
        $out[] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;";
        $out[] = 'SET FOREIGN_KEY_CHECKS = 0;';
        $out[] = '';

        $out[] = '-- ═══ ① البذرة العالمية ═══';
        $out[] = '';
        foreach (self::SEED_GLOBAL as $t) {
            list($sql, $err, $n) = $this->dumpTableRows($t, null);
            if ($err !== '') {
                return array('', $err, array());
            }
            $counts[$t] = $n;
            $out[] = $sql;
        }

        $out[] = '-- ═══ ② البذرة المستأجَرة — company_id مُستبدَلٌ بالعلامة النائبة ═══';
        $out[] = '';
        foreach (self::SEED_TENANT as $t) {
            $cid = $this->templateCompanyId($t);
            list($sql, $err, $n) = $this->dumpTableRows($t, $cid);
            if ($err !== '') {
                return array('', $err, array());
            }
            $counts[$t] = $n;
            $out[] = $sql;
        }

        $out[] = 'SET FOREIGN_KEY_CHECKS = 1;';
        $out[] = '';

        return array(implode("\n", $out), '', array(
            'rows'   => array_sum($counts),
            'tables' => $counts,
        ));
    }

    /**
     * صفوفُ جدولٍ واحدٍ كعبارات INSERT مجمَّعة.
     * @param string   $table
     * @param int|null $companyId إن مُرِّر: تُصفّى الصفوف عليه ويُستبدل عمودُ
     *                            company_id بالعلامة النائبة (قالبٌ مستأجَر).
     * @return array [sql, err, rowCount]
     */
    private function dumpTableRows($table, $companyId)
    {
        if (!$this->tableExists($table)) {
            // جدولٌ في القائمة وغيرُ موجودٍ في المصدر: يُعلَن ولا يُبتلع صامتًا.
            return array(
                "-- ⚠ تخطّي {$table}: غير موجود في قاعدة المصدر.\n",
                '',
                0
            );
        }

        $cols = $this->columnsOf($table);
        if (empty($cols)) {
            return array('', "تعذّرت قراءة أعمدة {$table}", 0);
        }

        $where = '';
        if ($companyId !== null) {
            $where = ' WHERE `company_id` = ' . (int) $companyId;
        }
        $res = $this->conn->query('SELECT * FROM ' . $this->qi($table) . $where);
        if (!$res) {
            return array('', "فشل قراءة صفوف {$table}: " . $this->conn->error, 0);
        }

        $colList = array();
        foreach ($cols as $c) {
            $colList[] = $this->qi($c);
        }
        $colList = implode(', ', $colList);

        $lines = array();
        $lines[] = '-- ── ' . $table . ($companyId !== null ? ' (قالبُ شركة ' . (int) $companyId . ') ──' : ' ──');
        $lines[] = 'DELETE FROM ' . $this->qi($table) . ';';

        $batch = array();
        $n = 0;
        while ($row = $res->fetch_assoc()) {
            $vals = array();
            foreach ($cols as $c) {
                if ($companyId !== null && $c === 'company_id') {
                    $vals[] = self::COMPANY_PLACEHOLDER;
                    continue;
                }
                $vals[] = $this->literal($row[$c]);
            }
            $batch[] = '(' . implode(',', $vals) . ')';
            $n++;
            if (count($batch) >= self::ROWS_PER_INSERT) {
                $lines[] = 'INSERT INTO ' . $this->qi($table) . " ({$colList}) VALUES\n" . implode(",\n", $batch) . ';';
                $batch = array();
            }
        }
        $res->free();

        if (!empty($batch)) {
            $lines[] = 'INSERT INTO ' . $this->qi($table) . " ({$colList}) VALUES\n" . implode(",\n", $batch) . ';';
        }
        if ($n === 0) {
            $lines[] = '-- (لا صفوف)';
        }
        $lines[] = '';

        return array(implode("\n", $lines), '', $n);
    }

    /**
     * الشركةُ التي تُؤخذ منها صفوفُ القالب: أصغرُ company_id له صفوف.
     * (fin_effect_map مثلًا مكرّرٌ لشركتين بنفس المحتوى — صفٌّ واحدٌ يكفي.)
     */
    private function templateCompanyId($table)
    {
        if (!$this->tableExists($table)) {
            return null;
        }
        $res = $this->conn->query(
            'SELECT `company_id` FROM ' . $this->qi($table) .
            ' WHERE `company_id` IS NOT NULL GROUP BY `company_id` ORDER BY `company_id` LIMIT 1'
        );
        if (!$res || $res->num_rows === 0) {
            return null;
        }
        $row = $res->fetch_row();
        $res->free();
        return (int) $row[0];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // البيان
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @param array $files اسمُ الملف => محتواه
     * @param array $meta  بياناتٌ إضافية تُدمج في البيان
     */
    public function manifest(array $files, array $meta = array())
    {
        $entries = array();
        foreach ($files as $name => $content) {
            $entries[$name] = array(
                'sha1'  => sha1($content),
                'bytes' => strlen($content),
            );
        }
        return array_merge(array(
            'generated_at'   => $this->stamp,
            'source_db'      => $this->dbName,
            'server_version' => $this->conn->server_info,
            'files'          => $entries,
        ), $meta);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // أدوات
    // ═══════════════════════════════════════════════════════════════════════

    /** @return array [tables, views, err] — كلٌّ مرتّبٌ أبجديًّا. */
    public function listObjects()
    {
        $tables = array();
        $views = array();
        $res = $this->conn->query('SHOW FULL TABLES');
        if (!$res) {
            return array(array(), array(), 'فشل SHOW FULL TABLES: ' . $this->conn->error);
        }
        while ($row = $res->fetch_row()) {
            if (strcasecmp($row[1], 'VIEW') === 0) {
                $views[] = $row[0];
            } else {
                $tables[] = $row[0];
            }
        }
        $res->free();
        sort($tables, SORT_STRING);
        sort($views, SORT_STRING);
        return array($tables, $views, '');
    }

    /**
     * ترتيبُ منظورٍ واحد: الترتيبُ الوحيدُ الظاهرُ في أعمدته النصّية.
     * تعدُّدُ الترتيبات داخل منظورٍ واحد لا يُحسم بجلسةٍ واحدة (الأعمدةُ الممرَّرةُ
     * من الجداول تحمل ترتيبَها مهما كانت الجلسة) — فيُترك للافتراض ويُعلَن.
     * @return string '' إن تعذّر الحسم.
     */
    private function viewCollation($view)
    {
        $v = $this->conn->real_escape_string($view);
        $res = $this->conn->query(
            "SELECT DISTINCT COLLATION_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$v}'
               AND COLLATION_NAME IS NOT NULL"
        );
        if (!$res) {
            return '';
        }
        $all = array();
        while ($r = $res->fetch_row()) {
            $all[] = $r[0];
        }
        $res->free();
        return count($all) === 1 ? $all[0] : '';
    }

    private function tableExists($table)
    {
        $t = $this->conn->real_escape_string($table);
        $res = $this->conn->query(
            "SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$t}'"
        );
        return $res && $res->num_rows > 0;
    }

    private function columnsOf($table)
    {
        $t = $this->conn->real_escape_string($table);
        $res = $this->conn->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$t}'
             ORDER BY ORDINAL_POSITION"
        );
        $out = array();
        if ($res) {
            while ($r = $res->fetch_row()) {
                $out[] = $r[0];
            }
            $res->free();
        }
        return $out;
    }

    /** قيمةٌ حرفيةٌ آمنةٌ لإدراجها في SQL. */
    private function literal($v)
    {
        if ($v === null) {
            return 'NULL';
        }
        // الأرقامُ الخالصة تُكتب بلا اقتباس (يبقى المخطَّط مقروءًا).
        if (is_int($v) || is_float($v)) {
            return (string) $v;
        }
        if (is_string($v) && $v !== '' && preg_match('/^-?(0|[1-9][0-9]{0,17})(\.[0-9]+)?$/', $v)) {
            return $v;
        }
        return "'" . $this->conn->real_escape_string((string) $v) . "'";
    }

    /** تسويرُ معرّف (جدولٍ أو عمود). */
    private function qi($ident)
    {
        return '`' . str_replace('`', '``', $ident) . '`';
    }

    private function banner($title, array $notes)
    {
        $line = str_repeat('═', 75);
        $out = array();
        $out[] = '-- ' . $line;
        $out[] = '-- ' . $title;
        $out[] = '-- ' . str_repeat('─', 73);
        $out[] = '-- المصدر: ' . $this->dbName . ' · التوليد: ' . $this->stamp;
        foreach ($notes as $n) {
            $out[] = '-- ' . $n;
        }
        $out[] = '-- مولَّدٌ آليًّا بـ `php database/migrate.php dump-schema` — لا يُحرَّر بيد.';
        $out[] = '-- ' . $line;
        return implode("\n", $out);
    }
}
