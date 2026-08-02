<?php
/**
 * main/global_search.php — البحثُ الموحَّد (NAV-01 v6 §13-⑤)
 * ───────────────────────────────────────────────────────────────────────────
 * «بحثٌ موحَّدٌ واحدٌ يجد الكيانَ أيًّا كان نوعُه بالكود أو الاسم —
 * فلا يُسأل المتدربُ عن الشاشة.» يبحث تسعةَ كياناتٍ ويصل كلَّ نتيجةٍ بملفّها.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$q = mb_substr(trim($_GET['q'] ?? ''), 0, 80);
$eq = mysqli_real_escape_string($conn, $q);
$like = "'%$eq%'";

$sections = array();
if (mb_strlen($q) >= 2) {
    $probe = function ($label, $sql, $urlFn) use ($conn, &$sections) {
        $r = mysqli_query($conn, $sql);
        $rows = array();
        if ($r) while ($x = mysqli_fetch_assoc($r)) $rows[] = $x;
        if ($rows) $sections[] = array($label, $rows, $urlFn);
    };
    $probe('المعدات', "SELECT id, name, '' extra FROM equipments WHERE company_id = $company_id
            AND (name LIKE $like OR id = '$eq') LIMIT 8",
        function ($x) { return '../Equipments/equipment_profile.php?id=' . intval($x['id']); });
    $probe('الموظفون', "SELECT id, name, '' extra FROM employees WHERE company_id = $company_id
            AND name LIKE $like LIMIT 8",
        function ($x) { return '../Workforce/employee_profile.php?id=' . intval($x['id']); });
    $probe('الموردون', "SELECT id, name, '' extra FROM suppliers WHERE company_id = $company_id
            AND name LIKE $like LIMIT 8",
        function ($x) { return '../Suppliers/supplier_profile.php?id=' . intval($x['id']); });
    $probe('العملاء', "SELECT id, name, '' extra FROM clients WHERE company_id = $company_id
            AND name LIKE $like LIMIT 8",
        function ($x) { return '../Clients/client_profile.php?id=' . intval($x['id']); });
    $probe('المشاريع', "SELECT id, name, '' extra FROM project WHERE company_id = $company_id
            AND name LIKE $like LIMIT 8",
        function ($x) { return '../Projects/project_profile.php?id=' . intval($x['id']); });
    $probe('العقود', "SELECT id, contract_no name, state extra FROM contracts WHERE company_id = $company_id
            AND (contract_no LIKE $like OR contract_name LIKE $like) LIMIT 8",
        function ($x) { return '../Contracts/contract_profile.php?id=' . intval($x['id']); });
    $probe('البلاغات', "SELECT id, ticket_no name, stage extra FROM tickets WHERE company_id = $company_id
            AND (ticket_no LIKE $like OR complaint LIKE $like) LIMIT 8",
        function ($x) { return '../Tickets/tickets_list.php?open=' . intval($x['id']); });
    $probe('الوحدات (التايم شيت)', "SELECT id, entry_no name, state extra FROM unit_entries WHERE company_id = $company_id
            AND entry_no LIKE $like LIMIT 8",
        function ($x) { return '../Operations/unit_entries.php?open=' . intval($x['id']); });
    $probe('عمليات التمويل', "SELECT op_id id, op_code name, state extra FROM financing_operations
            WHERE company_id = $company_id AND op_code LIKE $like LIMIT 8",
        function ($x) { return '../Financing/operation_profile.php?id=' . intval($x['id']); });
}

$page_title = 'البحث الموحد';
include '../insidebar.php';
?>
<div class="main" dir="rtl">
  <div class="ems-topbar"><h4><i class="fa fa-search"></i> البحثُ الموحَّد</h4></div>
  <form method="get" class="ems-form" style="display:flex;gap:8px;max-width:520px;margin-bottom:16px">
    <input type="text" name="q" class="form-control" placeholder="اسمٌ أو كودٌ — معدةٌ · موظفٌ · عقدٌ · بلاغ…"
           value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" autofocus>
    <button class="btn btn-primary">ابحث</button>
  </form>
  <?php if (mb_strlen($q) >= 2 && empty($sections)): ?>
    <div class="alert alert-warning">لا نتيجةَ لـ«<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>» في الكيانات التسعة.</div>
  <?php endif; ?>
  <?php foreach ($sections as $sec): list($label, $rows, $urlFn) = $sec; ?>
    <h5 style="margin-top:14px"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?> <span class="badge" style="background:#6c757d"><?= count($rows) ?></span></h5>
    <table class="table table-sm table-striped" data-no-dt style="max-width:700px">
      <tbody>
      <?php foreach ($rows as $x): ?>
        <tr><td><a href="<?= htmlspecialchars($urlFn($x), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($x['name'], ENT_QUOTES, 'UTF-8') ?></a></td>
            <td class="text-muted"><?= htmlspecialchars($x['extra'] ?: '', ENT_QUOTES, 'UTF-8') ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endforeach; ?>
</div>
