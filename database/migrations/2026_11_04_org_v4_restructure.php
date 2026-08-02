<?php
/**
 * update0007 · الموجة ① · G-01→G-03 — إعادةُ الهيكلة التنظيمية (ORG-01 v4)
 * ───────────────────────────────────────────────────────────────────────────
 * G-01 الطبقتان الجديدتان: تسعُ جهاتٍ تحت التنفيذي (الموردون يُنشأون موازيةً ·
 *      الموارد البشرية تُقلب موازيةً · الحوكمة تصير «الحوكمة والالتزام») ·
 *      وستٌّ تشغيلية («الحركة والتشغيل» تصير «إدارات المواقع» · «المشغّلون
 *      والقوى» تصير «القوى التشغيلية»).
 * G-02 التسميةُ النافذة: الدور 6 → «إدارة الموقع» · نوعُ التكليف
 *      site_movement_mgr → «مدير الموقع».
 * G-03 دمجُ الدور 5 في 6 نهائيًّا (الترتيبُ المستهدف ورقةٌ واحدة):
 *      المستخدمون يُرحَّلون · وقائمةُ 5 تُطفأ (المرايا من update0004).
 * idempotent — كلُّ خطوةٍ تفحص حالتها قبل الفعل.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/includes/env.php';
$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل\n"); exit(1); }
$conn->set_charset('utf8mb4');
$log = function ($m) { echo "  $m\n"; };

/* ═══ G-01 · org_units — الطبقتان الجديدتان ═══════════════════════════════ */
// ① الموارد البشرية (14): تشغيلية → موازية (ORG-01 v4 §1.1-⑦)
$conn->query("UPDATE org_units SET layer='parallel', parent_unit_id=NULL
              WHERE unit_id=14 AND layer='operational'");
$log('HR → موازية: ' . $conn->affected_rows);

// ② الموردون: وحدةٌ موازيةٌ جديدة (v4 §1.1-③) — لم تكن وحدةً أصلًا
$r = $conn->query("SELECT unit_id FROM org_units WHERE name_ar LIKE '%الموردون%' OR name_ar LIKE '%الموردين%' LIMIT 1");
if (!$r->num_rows) {
    $conn->query("INSERT INTO org_units (name_ar, layer, parent_unit_id, owner_doc)
                  VALUES ('الموردون', 'parallel', NULL, 'ORG-01v4')");
    $log('وحدةُ الموردين أُنشئت: #' . $conn->insert_id);
} else { $log('وحدةُ الموردين قائمة'); }

// ③ «الحوكمة والصلاحيات» → «الحوكمة والالتزام» (v4 §1.1-⑧ — مجالٌ موحَّد)
$conn->query("UPDATE org_units SET name_ar='الحوكمة والالتزام' WHERE unit_id=6 AND name_ar<>'الحوكمة والالتزام'");
$log('الحوكمة والالتزام: ' . $conn->affected_rows);

// ④ «الحركة والتشغيل» → «إدارات المواقع» (v4 الطبقة الثانية ①)
$conn->query("UPDATE org_units SET name_ar='إدارات المواقع' WHERE unit_id=8 AND name_ar<>'إدارات المواقع'");
$log('إدارات المواقع: ' . $conn->affected_rows);

// ⑤ «المشغّلون والقوى التشغيلية» → «القوى التشغيلية» (v4 الطبقة الثانية ③)
$conn->query("UPDATE org_units SET name_ar='القوى التشغيلية' WHERE unit_id=10 AND name_ar<>'القوى التشغيلية'");
$log('القوى التشغيلية: ' . $conn->affected_rows);

// ⑥ «المشتريات التشغيلية» → «المشتريات» (v4 الطبقة الثانية ④)
$conn->query("UPDATE org_units SET name_ar='المشتريات' WHERE unit_id=11 AND name_ar<>'المشتريات'");
$log('المشتريات: ' . $conn->affected_rows);

/* ═══ G-02 · التسميةُ النافذة ═════════════════════════════════════════════ */
// الدور 6 → «إدارة الموقع» (v4 §4: الاسمُ المعتمدُ بالصفات نفسِها بلا نقص)
$conn->query("UPDATE roles SET name='إدارة الموقع' WHERE id=6 AND name<>'إدارة الموقع'");
$log('اسم الدور 6: ' . $conn->affected_rows);
// الدور 5 يُعلَّم قديمًا (لا حذف — العرف: تعطيلٌ لا إسقاط)
$conn->query("UPDATE roles SET name='إدارة الموقع (قديم — مدمج في 6)' WHERE id=5 AND name NOT LIKE '%قديم%'");
$log('اسم الدور 5 المدمج: ' . $conn->affected_rows);
// نوعُ التكليف الموقعي
$conn->query("UPDATE org_assignment_types SET name_ar='مدير الموقع' WHERE type_code='site_movement_mgr' AND name_ar<>'مدير الموقع'");
$log('نوع التكليف: ' . $conn->affected_rows);
// نصوصُ القوائم الحية الحاملةُ للاسم القديم
$conn->query("UPDATE nav_items SET label_ar=REPLACE(label_ar,'الحركة والتشغيل','إدارة الموقع') WHERE label_ar LIKE '%الحركة والتشغيل%'");
$log('نصوص القوائم: ' . $conn->affected_rows);

/* ═══ G-03 · دمجُ الدور 5 في 6 — نهائيًّا (قرارُ الترتيب المستهدف) ═══════ */
// المستخدمون الستة يُرحَّلون
$conn->query("UPDATE users SET role=6 WHERE role=5");
$log('مستخدمون رُحّلوا 5→6: ' . $conn->affected_rows);
// قائمةُ الدور 5 (المرايا) تُطفأ — رجوعُها قلبُ صف
$conn->query("UPDATE nav_items SET active=0 WHERE role_id=5 AND active=1");
$log('مرايا 5 أُطفئت: ' . $conn->affected_rows);
// مجموعاتُ الدور 5 تُطفأ
$conn->query("UPDATE link_groups SET is_active=0 WHERE owner_role_id=5 AND is_active=1");
$log('مجموعات 5 أُطفئت: ' . $conn->affected_rows);

echo "اكتملت هجرةُ الهيكلة v4\n";
exit(0);
