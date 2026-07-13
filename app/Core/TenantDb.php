<?php
/**
 * بوابة العزل / DAO الإلزامية — TenantDb (ADR-02 · المرحلة 1)
 * ───────────────────────────────────────────────────────────────────────────
 * الطبقة الوحيدة المعتمدة لوصول الشاشات المُهاجَرة إلى قاعدة البيانات.
 * العقد الكامل: docs/TENANT_GATE_CONTRACT_ar.md (المخرَج ⑥ من حزمة المراجعة).
 *
 * الضمانات البنيوية:
 *   • حقن عزل الشركة آليًا قراءةً وكتابةً — المطوّر لا يكتب company_id أبدًا.
 *   • Fail-Closed: جدول غير مسجَّل في TenantRegistry أو سياق بلا شركة أو
 *     محاولة تمرير company_id مغاير = رفض + tenant_gate_violation في السجل.
 *   • الهوية من TenantContext حصريًا (جلسة/خادم) — لا مدخل يقبل هوية عميل.
 *   • prepared statements دائمًا؛ كل المعرّفات تُتحقق ضد نمطٍ صارمٍ والسجل.
 *   • الحذف عبر البوابة ناعمٌ حصريًا (معيار القبول §4 بند ⑧) — لا DELETE خام.
 *   • تجاوز العزل (قراءة عابرة للشركات) صريحٌ فقط عبر forAllTenants() —
 *     للمدير الأعلى وحده، وكل استدعاءٍ يُسجَّل (tenant_gate_cross_tenant).
 *
 * ملاحظة نطاق: البوابة تخدم الشاشات المُهاجَرة والوحدات الجديدة. الشاشات
 * القديمة تبقى على mysqli الخام حتى دور هجرتها (خارطة R02 §3.4) — المؤشر:
 * «100% من استعلامات الشاشة المُهاجَرة عبر البوابة».
 *
 * وضع المراقبة log-only (K1 · خطة المرحلة 1 §7-2 — نمط CSRF حرفيًا):
 *   • غياب EMS_TENANT_GATE من .env (أو enforce) = fail-closed صافٍ (الافتراض الآمن).
 *   • EMS_TENANT_GATE=monitor = حالات سياق العزل الثلاث (جدول غير مسجَّل، جدول
 *     مقيَّد، لا شركة في السياق) تُسجَّل tenant_gate_would_deny ثم تُمرَّر بسلوك
 *     الشاشة قبل الهجرة — قراءةً وإدراجًا حصرًا؛ المسارات المسجَّلة في
 *     TENANT_ENFORCE_PATHS تبقى fail-closed (إنفاذ شاشةً شاشة).
 *   • المسار السوي المعزول مطابقٌ تمامًا في الوضعين — monitor لا يلمسه.
 *   • لا تمرير أبدًا لأخطاء البرمجة والهجمات والكتابات التعديلية: تزوير الهوية،
 *     update/softDelete عبر التمرير، update بلا شرط، الحذف الصلب، كتابة مرجعٍ
 *     عام، ابنٌ لأبٍ غير مملوك، company_id في whereRaw، معرّفات فاسدة — تُرمى دائمًا.
 */

namespace App\Core;

class TenantDb
{
    /** نوع تعريفٍ داخلي لعبور المراقبة (ليس نوع سجل) — قراءة/إدراج بلا حقن عزل. */
    const T_MONITOR_PASS = 'monitor_pass';

    /** حقن قائمة مسارات الإنفاذ في الاختبارات CLI حصرًا (بديل .env). */
    public static $enforcePathsOverride = null;

    /** @var \mysqli */
    private $conn;
    /** @var TenantContext */
    private $ctx;
    /** @var bool وضع القراءة العابرة للشركات (super admin، مُسجَّل) */
    private $crossTenant;
    /** @var string 'enforce' | 'monitor' — من .env أو صريحًا للاختبارات */
    private $mode;
    /** @var bool داخل معاملة مُدارة (runInTransaction) — القنوات الذرية الداخلية تتبنّاها بدل فتح معاملتها */
    private $managedTx = false;

    public function __construct(\mysqli $conn, TenantContext $ctx, $crossTenant = false, $mode = null)
    {
        $this->conn = $conn;
        $this->ctx = $ctx;
        $this->crossTenant = (bool) $crossTenant;
        if ($mode === null) {
            $mode = (function_exists('ems_env') && ems_env('EMS_TENANT_GATE') === 'monitor')
                ? 'monitor' : 'enforce';
        }
        $this->mode = ($mode === 'monitor') ? 'monitor' : 'enforce';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // العمليات العامة
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * قراءة صفوف. $opts:
     *   columns  string[] أعمدة (افتراضي *)
     *   where    array    شروط تساوٍ مرتبطة بمعاملات (col => value)
     *   whereRaw string   جزء شرطٍ حر بعلامات ? (يكتبه المطوّر لا العميل)
     *   params   array    قيم علامات whereRaw
     *   orderBy  string   "col ASC, col2 DESC"
     *   limit/offset int
     *   includeDeleted bool  ضم المحذوف ناعمًا (افتراضي: لا)
     */
    public function select($table, array $opts = array())
    {
        list($sql, $params) = $this->buildSelect($table, $opts, false);
        return $this->run($sql, $params)->fetch_all(MYSQLI_ASSOC);
    }

    /** صفٌّ واحد أو null. */
    public function selectOne($table, array $opts = array())
    {
        $opts['limit'] = 1;
        $rows = $this->select($table, $opts);
        return empty($rows) ? null : $rows[0];
    }

    /** عدد الصفوف ضمن نطاق العزل. */
    public function count($table, array $opts = array())
    {
        list($sql, $params) = $this->buildSelect($table, $opts, true);
        $row = $this->run($sql, $params)->fetch_row();
        return intval($row[0]);
    }

    /** إدراج صف — العزل يُحقن؛ تمرير company_id مغايرٍ = محاولة تزوير (رفض). */
    public function insert($table, array $data)
    {
        $def = $this->requireTable($table);
        $this->assertWritable($table, $def);

        if ($def['type'] === TenantRegistry::T_TENANT || $def['type'] === TenantRegistry::T_CATALOG) {
            // كتالوج: الإدراج عبر البوابة يُنشئ صفَّ شركة السياق (لا صفًّا عامًّا — الصفوف
            // العامّة تُبذَر بالترحيل/المدير الأعلى)، فيُحقن company_id كـT_TENANT تمامًا.
            if (array_key_exists('company_id', $data)
                && intval($data['company_id']) !== $this->ctx->companyId()
                && !$this->crossTenant) {
                $this->deny('identity forgery attempt', $table . ' insert company_id=' . intval($data['company_id']));
            }
            $data['company_id'] = $this->crossTenant && array_key_exists('company_id', $data)
                ? intval($data['company_id'])
                : $this->ctx->companyId();
        } elseif ($def['type'] === TenantRegistry::T_CHILD) {
            $this->assertParentOwned($table, $def, $data);
        }

        if (empty($data)) {
            $this->deny('empty insert', $table);
        }

        $cols = array();
        $marks = array();
        $params = array();
        foreach ($data as $col => $val) {
            $this->assertIdent($col);
            $cols[] = '`' . $col . '`';
            $marks[] = '?';
            $params[] = $val;
        }
        $sql = 'INSERT INTO `' . $table . '` (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $marks) . ')';
        $this->run($sql, $params);
        return $this->conn->insert_id;
    }

    /** تحديث صفوف ضمن نطاق العزل. تغيير company_id ممنوع (إلا crossTenant).
     *  الكتابة التعديلية لا يشملها تمرير المراقبة أبدًا (strict). */
    public function update($table, array $data, array $where, $whereRaw = '', array $rawParams = array())
    {
        $def = $this->requireTable($table, true /* strict: لا عبور مراقبةٍ للتعديل */);
        $this->assertWritable($table, $def);

        if (array_key_exists('company_id', $data) && !$this->crossTenant) {
            $this->deny('identity forgery attempt', $table . ' update company_id');
        }
        if (empty($data)) {
            $this->deny('empty update', $table);
        }
        if (empty($where) && trim($whereRaw) === '') {
            $this->deny('update without where', $table);
        }

        $sets = array();
        $params = array();
        foreach ($data as $col => $val) {
            $this->assertIdent($col);
            $sets[] = '`' . $col . '` = ?';
            $params[] = $val;
        }

        list($cond, $condParams) = $this->buildWhere($table, $def, array(
            'where' => $where, 'whereRaw' => $whereRaw, 'params' => $rawParams,
            'includeDeleted' => true, // التحديث لا يستثني المحذوف ضمنيًا (قد نحدّث حالة الحذف نفسها)
            '__strictTenant' => true, // كتابة تعديلية: لا تمرير مراقبةٍ لغياب الشركة أبدًا
        ));

        // حارس الحصانة (§12 · A0): جدولٌ ذو immutable_key = دفترُ أحداثٍ — أيُّ صفٍّ
        // يحمل المفتاح (حدثٌ منشورٌ على الناقل) لا يُعدَّل ولا يُحذَف عبر البوابة
        // (عقيدة اللاتعديل؛ يحرس كلَّ كاتبٍ يعبر البوابة اليوم ومستقبلًا). يسري
        // حتى على السوبر — الحصانة عن التعديل لا عن العزل. softDelete يمرّ بهذا لأنه يستدعي update.
        if (!empty($def['immutable_key'])) {
            $ik = $def['immutable_key'];
            $this->assertIdent($ik);
            $guardSql = 'SELECT COUNT(*) FROM `' . $table . '` WHERE ' . $cond
                      . ' AND `' . $ik . '` IS NOT NULL AND `' . $ik . '` <> ?';
            $guardParams = array_merge($condParams, array(''));
            $hit = intval($this->run($guardSql, $guardParams)->fetch_row()[0]);
            if ($hit > 0) {
                $this->deny('immutable published-event mutation refused', $table . ' :: ' . $ik . ' present');
            }
        }

        $sql = 'UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE ' . $cond;
        return (int) $this->run($sql, array_merge($params, $condParams));
    }

    /**
     * الحذف عبر البوابة ناعمٌ حصريًا (is_deleted/deleted_at/deleted_by).
     * جدول بلا أعمدة حذفٍ ناعم = العملية مرفوضة (سجّل الجدول أو أضف الأعمدة
     * بترحيل — لا DELETE خام عبر البوابة أبدًا).
     */
    public function softDelete($table, $id)
    {
        $def = $this->requireTable($table, true /* strict: كتابة تعديلية — لا عبور مراقبة */);
        if (empty($def['soft'])) {
            $this->deny('hard delete refused (no soft-delete columns)', $table);
        }
        return $this->update($table, array(
            'is_deleted' => 1,
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $this->ctx->userId(),
        ), array('id' => intval($id)));
    }

    /**
     * حذف صفِّ كيانٍ مستأجَرٍ بلا أبٍ إلزاميّ — deleteRow (القدرة الثامنة · 2026-07-13)
     * ───────────────────────────────────────────────────────────────────────
     * توأم deleteChild للكيانات العليا التي لا أبَ إلزاميًّا لها (مثل employees:
     * supplier_id/project_id كلاهما اختياري فلا يصلح نطاقًا مزدوجًا). الحذف صلبٌ
     * على صفٍّ واحدٍ بمعرّفه، مقيَّدٌ **بشركة السياق إلزامًا** (نفس ضمانة عزل
     * update/softDelete)، strict (لا عبور مراقبة)، ويُرفَض حيث يتوفر حذفٌ ناعم
     * (سجلّات الأعمال تُؤرشَف — هذه للكيانات الصلبة حصرًا كالأصل التاريخي).
     * كل استدعاءٍ يُسجَّل (أثرٌ إلزامي — يحذف بيانات). للسوبر (crossTenant):
     * الحذف بالمعرّف وحده — عابرٌ مُسجَّل كسلوك الأصل.
     *
     * @return int عدد الصفوف المحذوفة (0 إن لم يطابق النطاق)
     */
    public function deleteRow($table, $id, $note = '')
    {
        $id = intval($id);
        $def = $this->requireTable($table, true /* strict — لا عبور مراقبةٍ للحذف أبدًا */);
        if (!empty($def['soft'])) {
            $this->deny('deleteRow refused (table has soft delete — use softDelete)', $table);
        }
        if ($def['type'] !== TenantRegistry::T_TENANT) {
            $this->deny('deleteRow requires a tenant-scoped table', $table . ':' . $def['type']);
        }

        if ($this->crossTenant) {
            $stmt = $this->conn->prepare('DELETE FROM `' . $table . '` WHERE `id` = ?');
            if (!$stmt) {
                throw new TenantGateException('deleteRow prepare failed: ' . $this->conn->error);
            }
            $stmt->bind_param('i', $id);
        } else {
            $this->requireTenant($table);
            $cid = $this->ctx->companyId();
            $stmt = $this->conn->prepare('DELETE FROM `' . $table . '` WHERE `id` = ? AND `company_id` = ?');
            if (!$stmt) {
                throw new TenantGateException('deleteRow prepare failed: ' . $this->conn->error);
            }
            $stmt->bind_param('ii', $id, $cid);
        }
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new TenantGateException('deleteRow delete failed: ' . $err);
        }
        $deleted = $stmt->affected_rows;
        $stmt->close();

        if (function_exists('log_security_event')) {
            log_security_event('tenant_gate_delete_row',
                $table . '#' . $id . ' deleted=' . $deleted
                . ' | user=' . $this->ctx->userId() . ' company=' . $this->ctx->companyId()
                . ($this->crossTenant ? ' cross=1' : '')
                . ($note !== '' ? ' note=' . substr($note, 0, 120) : ''));
        }
        return (int) $deleted;
    }

    /**
     * قناة إعادة كتابة الأبناء — replaceChildren (K9-M1 · قرار المستخدم جـ 2026-07-08)
     * ───────────────────────────────────────────────────────────────────────
     * قناة استثنائية معرَّفة بدقة لنمط «الاستبدال الكامل» التقني (تحرير أبٍ
     * يعيد كتابة كل سطوره): حذفٌ مقيَّدٌ بنطاقٍ مزدوجٍ إلزامي (الشركة **و**
     * الأبِ المملوكِ المتحقَّق — لا يكفي أحدهما) + إدراج البديل، **ذرّيًّا في
     * معاملةٍ واحدة** (انقطاعٌ = لا شيء؛ لا تضيع السطور القديمة دون الجديدة).
     * ليست بديلًا عن softDelete: سجلات الأعمال تُؤرشف؛ هذه لأسطر التفاصيل
     * القابلة لإعادة الكتابة حصرًا، وكل استدعاءٍ يُسجَّل (أثر إلزامي — تحذف
     * بيانات). أي شاشةٍ تستعملها تُبرّر أنها نمط أب-أبناء فعلًا (العقد §8).
     * تدير معاملتها بنفسها — لا تُستدعى داخل معاملةٍ مفتوحة.
     *
     * @param string $parentTable جدول الأب (مستأجَر — تُتحقق ملكيته للشركة)
     * @param int    $parentId
     * @param string $childTable  جدول الأبناء (مستأجَر بcompany_id)
     * @param string $parentCol   عمود إشارة الابن لأبيه
     * @param array  $rows        صفوف الإدراج الجديدة (قد تكون فارغة = محوٌ مشروع للسطور)
     * @return array{deleted:int, inserted:int}
     */
    public function replaceChildren($parentTable, $parentId, $childTable, $parentCol, array $rows, $note = '')
    {
        $parentId = intval($parentId);
        $this->assertIdent($parentCol);
        $pDef = $this->requireTable($parentTable, true); // strict — لا عبور مراقبةٍ للحذف أبدًا
        $cDef = $this->requireTable($childTable, true);
        if ($pDef['type'] !== TenantRegistry::T_TENANT || $cDef['type'] !== TenantRegistry::T_TENANT) {
            $this->deny('replaceChildren requires tenant-scoped parent and child', $parentTable . '/' . $childTable);
        }
        $this->requireTenant($parentTable);

        // النطاق 1: الأب مملوكٌ لشركة السياق (تحقُّق صريح — يُرفض غير المملوك)
        $owned = $this->selectOne($parentTable, array('columns' => array('id'), 'where' => array('id' => $parentId), 'includeDeleted' => true));
        if ($owned === null) {
            $this->deny('replaceChildren: parent not owned by tenant', $parentTable . '#' . $parentId);
        }

        // داخل معاملة مُدارة (runInTransaction): تُنفَّذ العمليات ضمنها ولا تُفتح
        // معاملة متداخلة (begin متداخل في MySQL = commit ضمني يكسر ذرّية المدير).
        $ownTx = !$this->managedTx;
        if ($ownTx) {
            $this->conn->begin_transaction();
        }
        try {
            // النطاق 2: حذف الأبناء بالشرطين معًا إلزامًا (الأب + الشركة)
            $stmt = $this->conn->prepare(
                'DELETE FROM `' . $childTable . '` WHERE `' . $parentCol . '` = ? AND `company_id` = ?'
            );
            if (!$stmt) {
                throw new TenantGateException('replaceChildren prepare failed: ' . $this->conn->error);
            }
            $cid = $this->ctx->companyId();
            $stmt->bind_param('ii', $parentId, $cid);
            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                throw new TenantGateException('replaceChildren delete failed: ' . $err);
            }
            $deleted = $stmt->affected_rows;
            $stmt->close();

            $inserted = 0;
            foreach ($rows as $row) {
                $row[$parentCol] = $parentId; // ربط الابن بأبيه المتحقَّق حصرًا
                $this->insert($childTable, $row); // حقن الشركة وحراس التزوير كالمعتاد
                $inserted++;
            }
            if ($ownTx) {
                $this->conn->commit();
            }
        } catch (\Throwable $t) {
            if ($ownTx) {
                $this->conn->rollback(); // ذرّية: لا شيء يتغير عند أي فشل
            }
            // داخل معاملة مُدارة: الرمي يكفي — المدير الخارجي يتراجع بالكل
            if ($t instanceof TenantGateException) {
                throw $t;
            }
            throw new TenantGateException('replaceChildren aborted (rolled back): ' . $t->getMessage());
        }

        if (function_exists('log_security_event')) {
            log_security_event('tenant_gate_replace_children',
                $parentTable . '#' . $parentId . ' -> ' . $childTable
                . ' deleted=' . $deleted . ' inserted=' . $inserted
                . ' | user=' . $this->ctx->userId() . ' company=' . $this->ctx->companyId()
                . ($note !== '' ? ' note=' . substr($note, 0, 120) : ''));
        }
        return array('deleted' => $deleted, 'inserted' => $inserted);
    }

    /**
     * حذف صفِّ ابنٍ واحد صلبًا — deleteChild (توأم replaceChildren لصفٍّ مفرد · 2026-07-13)
     * ───────────────────────────────────────────────────────────────────────
     * لجداول السطور التزايُدية (soft=false) التي تُحرَّر صفًّا صفًّا لا استبدالًا كاملًا
     * (مهام الخطة الوقائية، عمالة/قطع أمر الصيانة، سطور الفحص). الحذف مقيَّدٌ بنطاقٍ
     * مزدوجٍ إلزامي — الشركة **و** الأبِ المملوكِ المتحقَّق (لا يكفي أحدهما) — تمامًا
     * كـreplaceChildren؛ لكن على صفٍّ واحد بمعرّفه (لا استبدال، فلا تتغيّر معرّفات
     * الأشقّاء). عبارةٌ ذرّية واحدة (تشارك معاملة المستدعي إن كان داخل runInTransaction).
     * كل استدعاءٍ يُسجَّل (أثرٌ إلزامي — يحذف بيانات). ليست بديلًا عن softDelete: سجلات
     * الأعمال تُؤرشَف، وهذه لأسطر التفاصيل الصلبة حصرًا.
     *
     * @param string $childTable  جدول الأبناء (مستأجَر بcompany_id)
     * @param int    $childId
     * @param string $parentTable جدول الأب (تُتحقق ملكيته لشركة السياق)
     * @param int    $parentId
     * @param string $parentCol   عمود إشارة الابن لأبيه
     * @return int عدد الصفوف المحذوفة (0 إن لم يطابق النطاق المزدوج)
     */
    public function deleteChild($childTable, $childId, $parentTable, $parentId, $parentCol, $note = '')
    {
        $childId = intval($childId);
        $parentId = intval($parentId);
        $this->assertIdent($parentCol);
        $cDef = $this->requireTable($childTable, true); // strict — لا عبور مراقبةٍ للحذف أبدًا
        $pDef = $this->requireTable($parentTable, true);
        if ($cDef['type'] !== TenantRegistry::T_TENANT || $pDef['type'] !== TenantRegistry::T_TENANT) {
            $this->deny('deleteChild requires tenant-scoped parent and child', $parentTable . '/' . $childTable);
        }
        $this->requireTenant($parentTable);
        $cid = $this->ctx->companyId();

        // النطاق 1: الأب مملوكٌ لشركة السياق (تحقُّق صريح — يُرفض غير المملوك)
        $owned = $this->selectOne($parentTable, array('columns' => array('id'), 'where' => array('id' => $parentId), 'includeDeleted' => true));
        if ($owned === null) {
            $this->deny('deleteChild: parent not owned by tenant', $parentTable . '#' . $parentId);
        }

        // النطاق 2: حذف الابن بالشروط الثلاثة معًا إلزامًا (الصف + الأب + الشركة)
        $stmt = $this->conn->prepare(
            'DELETE FROM `' . $childTable . '` WHERE `id` = ? AND `' . $parentCol . '` = ? AND `company_id` = ?'
        );
        if (!$stmt) {
            throw new TenantGateException('deleteChild prepare failed: ' . $this->conn->error);
        }
        $stmt->bind_param('iii', $childId, $parentId, $cid);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new TenantGateException('deleteChild delete failed: ' . $err);
        }
        $deleted = $stmt->affected_rows;
        $stmt->close();

        if (function_exists('log_security_event')) {
            log_security_event('tenant_gate_delete_child',
                $childTable . '#' . $childId . ' of ' . $parentTable . '#' . $parentId
                . ' deleted=' . $deleted
                . ' | user=' . $this->ctx->userId() . ' company=' . $cid
                . ($note !== '' ? ' note=' . substr($note, 0, 120) : ''));
        }
        return (int) $deleted;
    }

    /**
     * الاستعلام المركّب المعزول — scopedQuery (K9-D · قرار المستخدم ب · 2026-07-08)
     * ───────────────────────────────────────────────────────────────────────
     * للقراءات التجميعية/المركّبة (GROUP BY/JOINs) التي تتجاوز CRUD والترطيب:
     * المطوّر **يعلن** الجداول ويضع رمز {TENANT_SCOPE} — **البوابة تبني شروط
     * العزل بنفسها** وتحقنها مكان الرمز؛ لا عزل يدويًا أبدًا.
     *
     * ضمانة «العزل النافذ لا النصي» (تدقيق المستخدم): لا يُكتفى بوجود الرمز —
     * يُتحقق بنيويًا (مسحٌ بعمق الأقواس بعد تجريد النصوص المقتبسة) أن الرمز:
     * مرةً واحدة، على العمق 0، بعد WHERE العلوية الوحيدة، وقبل أي
     * GROUP BY/HAVING/ORDER BY/LIMIT علوي — أي في موضع ترشيح مصادر الصفوف
     * قبل التجميع، لا بعده. UNION مرفوض كليًا (كتلٌ متعددة = استدعاءات متعددة).
     * والبرهان التجريبي المكمِّل: اختبار تسرّبٍ لكل شاشةٍ مستهلِكة (§7).
     *
     * إعلان الجداول:
     *   'scope'  => array('m' => 'proc_stock_move')   ← يُحقن m.company_id = ?
     *   'enrich' => array('it' => 'proc_item', ...)   ← إثراء LEFT JOIN بمفتاحٍ من
     *      صفوفٍ معزولة: لا شرط له (شرط WHERE يقلب LEFT إلى INNER)، ويُتحقق أن
     *      كل ظهوره مسبوقٌ بLEFT JOIN حصرًا. عزله الفعلي عبر مفاتيح الصفوف
     *      المعزولة + اختبار §7 للشاشة (قيد النسخة موثَّق في العقد §10).
     * أي جدولٍ مستأجرٍ مسجَّلٍ يظهر كمصدر FROM/JOIN دون إعلانٍ = رفض.
     * ذكر company_id في النص = رفض. SELECT فقط.
     */
    public function scopedQuery(array $decl, $sql, array $params = array())
    {
        $scope  = isset($decl['scope'])  && is_array($decl['scope'])  ? $decl['scope']  : array();
        $enrich = isset($decl['enrich']) && is_array($decl['enrich']) ? $decl['enrich'] : array();
        if (empty($scope)) {
            $this->deny('scopedQuery: scope declaration required', substr($sql, 0, 120));
        }
        foreach (array_merge($scope, $enrich) as $alias => $table) {
            $this->assertIdent($alias);
            $this->requireTable($table, true); // مسجَّل، وبلا عبور مراقبة — قناة كودٍ جديد
        }
        if (stripos($sql, 'company_id') !== false) {
            $this->deny('scopedQuery: manual company_id refused', 'العزل مسؤولية البوابة وحدها');
        }

        // ── تجريد النصوص المقتبسة ثم بناء نسخة عليا للمسح البنيوي ──
        $stripped = preg_replace("/'(?:[^'\\\\]|\\\\.)*'/s", "''", $sql);
        $upper = strtoupper($stripped);
        if (!preg_match('/^\s*SELECT\b/', $upper)) {
            $this->deny('scopedQuery: SELECT only', substr($sql, 0, 80));
        }
        if (substr_count($stripped, '{TENANT_SCOPE}') !== 1) {
            $this->deny('scopedQuery: {TENANT_SCOPE} مطلوب مرةً واحدة بالضبط', substr($sql, 0, 120));
        }

        // مواضع العمق 0 للكلمات الحاكمة
        $depth0 = function ($needle) use ($upper) {
            $pos = array(); $d = 0; $len = strlen($upper); $nlen = strlen($needle);
            for ($i = 0; $i < $len; $i++) {
                $ch = $upper[$i];
                if ($ch === '(') { $d++; } elseif ($ch === ')') { $d--; }
                elseif ($d === 0 && $ch === $needle[0] && substr($upper, $i, $nlen) === $needle) { $pos[] = $i; }
            }
            return $pos;
        };
        if (!empty($depth0('UNION'))) {
            $this->deny('scopedQuery: UNION refused (استدعاءات منفصلة)', '');
        }
        $wherePos = $depth0('WHERE');
        if (count($wherePos) !== 1) {
            $this->deny('scopedQuery: يلزم WHERE علوية واحدة بالضبط', 'found=' . count($wherePos));
        }
        $tokenPos = strpos($upper, '{TENANT_SCOPE}');
        if ($tokenPos < $wherePos[0]) {
            $this->deny('scopedQuery: الرمز قبل WHERE — موضعٌ لا يعزل', '');
        }
        foreach (array('GROUP BY', 'HAVING', 'ORDER BY', 'LIMIT') as $kw) {
            $kp = $depth0($kw);
            if (!empty($kp) && $tokenPos > $kp[0]) {
                $this->deny('scopedQuery: الرمز بعد ' . $kw . ' — العزل غير نافذٍ على مصادر الصفوف', '');
            }
        }

        // كل جدولٍ مستأجرٍ مسجَّلٍ يظهر مصدرًا يجب إعلانه؛ وenrich = LEFT JOIN حصرًا
        $declaredTables = array_map('strtoupper', array_values(array_merge($scope, $enrich)));
        if (preg_match_all('/\b(FROM|JOIN)\s+`?([A-Za-z0-9_]+)`?/', $upper, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                $tname = $m[2];
                $def = TenantRegistry::get(strtolower($tname));
                if ($def !== null && $def['type'] !== TenantRegistry::T_GLOBAL
                    && !in_array($tname, $declaredTables, true)) {
                    $this->deny('scopedQuery: جدولٌ مستأجرٌ غير معلَن', strtolower($tname));
                }
            }
        }
        foreach ($enrich as $alias => $table) {
            $tU = strtoupper($table);
            if (preg_match_all('/(\S+\s+)?JOIN\s+`?' . preg_quote($tU, '/') . '`?/', $upper, $jm, PREG_SET_ORDER)) {
                foreach ($jm as $j) {
                    if (stripos($j[0], 'LEFT') === false) {
                        $this->deny('scopedQuery: جدول الإثراء يُربط LEFT JOIN حصرًا', $table);
                    }
                }
            }
        }

        // ── بناء شروط العزل وحقنها + ترتيب المعاملات حول الرمز ──
        $conds = array(); $scopeParams = array();
        foreach ($scope as $alias => $table) {
            if ($this->crossTenant) {
                $conds[] = '1=1';
            } else {
                $this->requireTenant($table);
                $conds[] = '`' . $alias . '`.`company_id` = ?';
                $scopeParams[] = $this->ctx->companyId();
            }
        }
        $scopeSqlFrag = '(' . implode(' AND ', $conds) . ')';
        $rawToken = strpos($sql, '{TENANT_SCOPE}');
        $before = substr($sql, 0, $rawToken);
        $after  = substr($sql, $rawToken + strlen('{TENANT_SCOPE}'));
        $nBefore = substr_count(preg_replace("/'(?:[^'\\\\]|\\\\.)*'/s", "''", $before), '?');
        $finalSql = $before . $scopeSqlFrag . $after;
        $finalParams = array_merge(
            array_slice($params, 0, $nBefore),
            $scopeParams,
            array_slice($params, $nBefore)
        );
        $res = $this->run($finalSql, $finalParams);
        if (!($res instanceof \mysqli_result)) {
            $this->deny('scopedQuery: non-result outcome', '');
        }
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * معاملة مُدارة متعددة العمليات — runInTransaction (قرار المستخدم 2 · 2026-07-08)
     * ───────────────────────────────────────────────────────────────────────
     * للعمليات النطاقية المترابطة (مثل صرف المخزون: سطور ← حركات ← عهدة
     * بترابط line_id الوليد) التي تتطلب ذرّيةً مشتركة: معاملةٌ واحدة تجري
     * داخلها عمليات بوابةٍ متعددة.
     *
     * **شروط المستخدم الحاكمة**:
     *   • الحراسة لا تتعلق: كل عمليةٍ داخل الـcallable هي نفس دوال البوابة
     *     بحُرّاسها كاملةً (عزل الشركة/الملكية/التزوير) — فتحُ المعاملة لا
     *     يمنح أي إعفاء، وأي رفضٍ داخلي = rollback الكل.
     *   • الذرّية المشتركة: أي فشلٍ (استثناء) في أي نقطة = تراجعُ كل ما سبق.
     *   • الترابط: insert() يعيد المعرّف الوليد فورًا — مرئيٌّ للعمليات
     *     التالية داخل المعاملة نفسها (نفس الاتصال).
     * القنوات الذرّية الداخلية (replaceChildren) تتبنّى هذه المعاملة تلقائيًا
     * (لا begin متداخل). لا تداخل: استدعاءٌ داخل معاملةٍ مُدارة قائمة يُرفض.
     * كل معاملةٍ تُسجَّل (tenant_gate_transaction) بنتيجتها.
     *
     * @param callable $work function(TenantDb $gate): mixed — عمليات البوابة حصرًا
     * @return mixed ما يعيده الـcallable
     */
    public function runInTransaction(callable $work, $note = '')
    {
        if ($this->managedTx) {
            $this->deny('nested runInTransaction refused', $note);
        }
        $this->conn->begin_transaction();
        $this->managedTx = true;
        try {
            $result = $work($this);
            $this->conn->commit();
            $this->managedTx = false;
            if (function_exists('log_security_event')) {
                log_security_event('tenant_gate_transaction',
                    'committed | user=' . $this->ctx->userId() . ' company=' . $this->ctx->companyId()
                    . ($note !== '' ? ' note=' . substr($note, 0, 120) : ''));
            }
            return $result;
        } catch (\Throwable $t) {
            $this->conn->rollback();
            $this->managedTx = false;
            if (function_exists('log_security_event')) {
                log_security_event('tenant_gate_transaction',
                    'ROLLED BACK :: ' . substr($t->getMessage(), 0, 200)
                    . ' | user=' . $this->ctx->userId() . ' company=' . $this->ctx->companyId()
                    . ($note !== '' ? ' note=' . substr($note, 0, 120) : ''));
            }
            if ($t instanceof TenantGateException) {
                throw $t;
            }
            throw new TenantGateException('transaction rolled back: ' . $t->getMessage());
        }
    }

    /**
     * القراءة العابرة للشركات — للمدير الأعلى حصرًا، وكل استدعاءٍ يُسجَّل.
     * تعيد نسخةً منفصلة؛ النسخة الأصلية تبقى معزولة.
     */
    public function forAllTenants($reason = '')
    {
        if (!$this->ctx->isSuperAdmin()) {
            $this->deny('cross-tenant escape refused (not super admin)', 'role=' . $this->ctx->role());
        }
        if (function_exists('log_security_event')) {
            $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
            $caller = isset($bt[0]['file']) ? basename($bt[0]['file']) . ':' . $bt[0]['line'] : 'unknown';
            log_security_event('tenant_gate_cross_tenant',
                'user=' . $this->ctx->userId() . ' caller=' . $caller . ($reason !== '' ? ' reason=' . $reason : ''));
        }
        return new self($this->conn, $this->ctx, true, $this->mode);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // البناء الداخلي
    // ─────────────────────────────────────────────────────────────────────────

    private function buildSelect($table, array $opts, $countOnly)
    {
        $def = $this->requireTable($table);

        $columns = '*';
        if (!empty($opts['columns']) && is_array($opts['columns'])) {
            $safe = array();
            foreach ($opts['columns'] as $c) {
                $this->assertIdent($c);
                $safe[] = '`' . $c . '`';
            }
            $columns = implode(', ', $safe);
        }

        list($cond, $params) = $this->buildWhere($table, $def, $opts);

        $sql = 'SELECT ' . ($countOnly ? 'COUNT(*)' : $columns) . ' FROM `' . $table . '` WHERE ' . $cond;

        if (!$countOnly && !empty($opts['orderBy'])) {
            $this->assertOrderBy($opts['orderBy']);
            $sql .= ' ORDER BY ' . $opts['orderBy'];
        }
        if (!$countOnly && isset($opts['limit'])) {
            $sql .= ' LIMIT ' . intval($opts['limit']);
            if (isset($opts['offset'])) {
                $sql .= ' OFFSET ' . intval($opts['offset']);
            }
        }
        return array($sql, $params);
    }

    /** شرط WHERE الكامل: حقن العزل + الحذف الناعم + شروط المطوّر. */
    private function buildWhere($table, array $def, array $opts)
    {
        $conds = array();
        $params = array();

        // 1) حقن العزل — القلب.
        if (!$this->crossTenant) {
            if ($def['type'] === TenantRegistry::T_TENANT) {
                if ($this->tenantScopeRequired($table, $opts)) {
                    $conds[] = '`' . $table . '`.`company_id` = ?';
                    $params[] = $this->ctx->companyId();
                }
            } elseif ($def['type'] === TenantRegistry::T_CATALOG) {
                // كتالوج مشترك: القراءة «العامّ (NULL) أو مِلكي»؛ الكتابة التعديلية
                // (__strictTenant) «مِلكي حصرًا» — فلا تُعدَّل/تُحذَف الصفوف العامّة.
                if ($this->tenantScopeRequired($table, $opts)) {
                    if (empty($opts['__strictTenant'])) {
                        $conds[] = '(`' . $table . '`.`company_id` IS NULL OR `' . $table . '`.`company_id` = ?)';
                    } else {
                        $conds[] = '`' . $table . '`.`company_id` = ?';
                    }
                    $params[] = $this->ctx->companyId();
                }
            } elseif ($def['type'] === TenantRegistry::T_CHILD) {
                if ($this->tenantScopeRequired($table, $opts)) {
                    $parent = $def['parent'];
                    $fk = $def['fk'];
                    $this->assertIdent($parent);
                    $this->assertIdent($fk);
                    // إن كان الأب كتالوجًا: العزل عبره «عامّ أو مِلكي» قراءةً (الكتابة strict = مِلكي).
                    $pDef = TenantRegistry::get($parent);
                    if ($pDef !== null && $pDef['type'] === TenantRegistry::T_CATALOG && empty($opts['__strictTenant'])) {
                        $conds[] = 'EXISTS (SELECT 1 FROM `' . $parent . '` __p WHERE __p.`id` = `' . $table . '`.`' . $fk . '` AND (__p.`company_id` IS NULL OR __p.`company_id` = ?))';
                    } else {
                        $conds[] = 'EXISTS (SELECT 1 FROM `' . $parent . '` __p WHERE __p.`id` = `' . $table . '`.`' . $fk . '` AND __p.`company_id` = ?)';
                    }
                    $params[] = $this->ctx->companyId();
                }
            }
            // T_GLOBAL: قراءة بلا نطاق. · T_MONITOR_PASS: عبور مراقبةٍ مُسجَّل بلا حقن.
        }

        // 2) استبعاد المحذوف ناعمًا (افتراضيًا).
        if (!empty($def['soft']) && empty($opts['includeDeleted'])) {
            $conds[] = 'COALESCE(`' . $table . '`.`is_deleted`, 0) = 0';
        }

        // 3) شروط تساوٍ مرتبطة.
        if (!empty($opts['where']) && is_array($opts['where'])) {
            foreach ($opts['where'] as $col => $val) {
                $this->assertIdent($col);
                if ($val === null) {
                    $conds[] = '`' . $table . '`.`' . $col . '` IS NULL';
                } else {
                    $conds[] = '`' . $table . '`.`' . $col . '` = ?';
                    $params[] = $val;
                }
            }
        }

        // 4) جزء حر (يكتبه المطوّر؛ القيم عبر ? حصريًا).
        if (!empty($opts['whereRaw'])) {
            $raw = trim($opts['whereRaw']);
            if (stripos($raw, 'company_id') !== false && !$this->crossTenant) {
                // منع التلاعب بنطاق العزل من الجزء الحر — العزل مسؤولية البوابة وحدها.
                $this->deny('company_id in whereRaw refused', $table);
            }
            $conds[] = '(' . $raw . ')';
            if (!empty($opts['params']) && is_array($opts['params'])) {
                foreach ($opts['params'] as $p) {
                    $params[] = $p;
                }
            }
        }

        if (empty($conds)) {
            $conds[] = '1=1';
        }
        return array(implode(' AND ', $conds), $params);
    }

    /** تنفيذ prepared statement وإرجاع النتيجة. */
    private function run($sql, array $params)
    {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            $this->deny('prepare failed: ' . $this->conn->error, $sql);
        }
        if (!empty($params)) {
            $types = '';
            foreach ($params as $p) {
                $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
            }
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            $this->deny('execute failed: ' . $err, $sql);
        }
        $result = $stmt->get_result();
        if ($result === false) {
            // DML (INSERT/UPDATE): نلتقط affected_rows من الـ statement قبل
            // إغلاقه — قراءته من الاتصال بعد الإغلاق غير موثوقة.
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected;
        }
        $stmt->close();
        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // الحرّاس
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param bool $strict كتابة تعديلية (update/softDelete): لا عبور مراقبةٍ أبدًا.
     */
    private function requireTable($table, $strict = false)
    {
        $this->assertIdent($table);
        $def = TenantRegistry::get($table);
        if ($def === null) {
            if (!$strict && $this->monitorPass('unregistered table', $table)) {
                // عبور مراقبة: قراءة/إدراج بسلوك ما قبل الهجرة (بلا حقن ولا مرشّح حذفٍ ناعم
                // — أعمدته مجهولة لجدولٍ خارج السجل). أخطاء SQL الحقيقية تظهر كما هي.
                return array('type' => self::T_MONITOR_PASS, 'soft' => false);
            }
            $this->deny('unregistered table', $table);
        }
        if ($def['type'] === TenantRegistry::T_RESTRICTED) {
            if (!$strict && $this->monitorPass('restricted table (pending its module migration)', $table)) {
                return array('type' => self::T_MONITOR_PASS, 'soft' => !empty($def['soft']));
            }
            $this->deny('restricted table (pending its module migration)', $table);
        }
        return $def;
    }

    private function requireTenant($table)
    {
        if (!$this->ctx->hasTenant()) {
            $this->deny('no tenant in context (fail-closed)', $table);
        }
    }

    /**
     * هل يُحقن شرط العزل؟ true = احقن (المسار السوي). عند غياب شركة السياق:
     * قراءةٌ في وضع المراقبة خارج مسارات الإنفاذ → تُسجَّل وتُمرَّر بلا شرط (false)؛
     * وإلا (enforce، أو مسار إنفاذ، أو كتابة strict) → رفضٌ مغلق.
     */
    private function tenantScopeRequired($table, array $opts)
    {
        if ($this->ctx->hasTenant()) {
            return true;
        }
        if (empty($opts['__strictTenant']) && $this->monitorPass('no tenant in context', $table)) {
            return false; // عبور مراقبةٍ مُسجَّل — سلوك الشاشة قبل الهجرة
        }
        $this->deny('no tenant in context (fail-closed)', $table);
    }

    private function assertWritable($table, array $def)
    {
        if ($def['type'] === self::T_MONITOR_PASS) {
            return; // إدراجٌ عابرٌ مُسجَّل (سلوك ما قبل الهجرة) — التعديل لا يصل هنا (strict)
        }
        if ($def['type'] === TenantRegistry::T_GLOBAL && !$this->ctx->isSuperAdmin()) {
            // كتالوجٌ عامٌّ مُدار من التطبيق (managed): يديره مستخدمو الأدوار المخوَّلة
            // (كأنواع المعدات/تصنيف الأعطال) بحُكم صلاحية الصفحة — لا عزل شركة (عامّ محض،
            // بلا company_id)، فالكتابة مسموحة والحوكمة على الشاشة. أما المراجع المجمَّدة
            // (roles/modules/RBAC) فتبقى للسوبر حصرًا.
            if (!empty($def['managed'])) {
                return;
            }
            $this->deny('write to global reference refused', $table);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // آلية المراقبة log-only (K1)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * true = مسموح العبور: الوضع monitor والمسار الحالي خارج TENANT_ENFORCE_PATHS —
     * ويُسجَّل tenant_gate_would_deny (ما كان الإنفاذ سيحجبه). غير ذلك false.
     */
    private function monitorPass($reason, $detail)
    {
        if ($this->mode !== 'monitor' || $this->isEnforcedPath()) {
            return false;
        }
        if (function_exists('log_security_event')) {
            $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : 'cli';
            log_security_event('tenant_gate_would_deny',
                $reason . ($detail !== '' ? ' :: ' . substr($detail, 0, 200) : '')
                . ' | script=' . $script
                . ' | user=' . $this->ctx->userId() . ' company=' . $this->ctx->companyId());
        }
        return true;
    }

    /** مطابقة مسار السكربت الحالي ضد TENANT_ENFORCE_PATHS (نمط CSRF_ENFORCE_PATHS). */
    private function isEnforcedPath()
    {
        $paths = self::$enforcePathsOverride;
        if ($paths === null) {
            $raw = function_exists('ems_env') ? ems_env('TENANT_ENFORCE_PATHS', '') : '';
            $paths = array_filter(array_map('trim', explode(',', (string) $raw)));
        }
        if (empty($paths)) {
            return false;
        }
        $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
        if ($script === '') {
            return false; // CLI: الوضع يُضبط صراحةً في الاختبارات
        }
        foreach ($paths as $p) {
            if ($p !== '' && stripos($script, $p) !== false) {
                return true;
            }
        }
        return false;
    }

    /** لإدراج ابنٍ: الأب المُشار إليه يجب أن يملكه مستأجر السياق. */
    private function assertParentOwned($table, array $def, array $data)
    {
        $fk = $def['fk'];
        $parent = $def['parent'];
        if (!isset($data[$fk])) {
            $this->deny('child insert without parent fk', $table);
        }
        if ($this->crossTenant) {
            return;
        }
        $this->requireTenant($table);
        $res = $this->run(
            'SELECT 1 FROM `' . $parent . '` WHERE `id` = ? AND `company_id` = ? LIMIT 1',
            array(intval($data[$fk]), $this->ctx->companyId())
        );
        if (!($res instanceof \mysqli_result) || $res->num_rows === 0) {
            $this->deny('child insert: parent not owned by tenant', $table . '.' . $fk . '=' . intval($data[$fk]));
        }
    }

    private function assertIdent($name)
    {
        if (!is_string($name) || !preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            $this->deny('invalid identifier', var_export($name, true));
        }
    }

    private function assertOrderBy($orderBy)
    {
        if (!preg_match('/^[A-Za-z0-9_`\.\s,]+(ASC|DESC|asc|desc)?[A-Za-z0-9_`\.\s,]*$/', $orderBy)
            || !preg_match('/^[A-Za-z0-9_`\.\s,]+$/', str_ireplace(array('ASC', 'DESC'), '', $orderBy))) {
            $this->deny('invalid orderBy', $orderBy);
        }
    }

    /** تسجيل المخالفة ثم الرفض — لا مسار فشلٍ صامت. */
    private function deny($msg, $detail = '')
    {
        if (function_exists('log_security_event')) {
            log_security_event('tenant_gate_violation',
                $msg . ($detail !== '' ? ' :: ' . substr($detail, 0, 200) : '')
                . ' | user=' . $this->ctx->userId() . ' company=' . $this->ctx->companyId());
        }
        throw new TenantGateException('TenantGate: ' . $msg);
    }
}
