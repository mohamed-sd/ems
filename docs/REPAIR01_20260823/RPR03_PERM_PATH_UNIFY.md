# `RPR-03` §٦ — توحيدُ مسارِ قرارِ الصلاحية

> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/rpr03_perm_path_unify.php --md`

| المفردة | العدد |
|---|---:|
| ملفّاتٌ تحمل الكتلةَ المستقلّة | 12 |
| **يُوحَّد** | **9** |
| ⛔ موقوفٌ | 3 |

## الملفّاتُ الموحَّدة

| الملفّ | المتغيّراتُ المحفوظة |
|---|---|
| `admin/ops_manager_board.php` | `$can_view` ⇐ `can_view` |
| `admin/org_structure.php` | `$can_view` ⇐ `can_view` |
| `Contracts/client_statement.php` | `$can_view` ⇐ `can_view` |
| `Contracts/commercial_board.php` | `$can_view` ⇐ `can_view` |
| `Finance/approvals_inbox.php` | `$can_view` ⇐ `can_view` |
| `Portal/visibility_audit.php` | `$can_view` ⇐ `can_view` |
| `Portal/visibility_simulator.php` | `$can_view` ⇐ `can_view` |
| `Tickets/ticket_workstreams_board.php` | `$can_view` ⇐ `can_view` |
| `Tickets/watchtower.php` | `$can_view` ⇐ `can_view` |

## ⛔ موقوفٌ — ولا يُلمَس ما لم يُفهَم

| الملفّ | السبب |
|---|---|
| `app/Services/Security/PermSourceService.php` | لا يشتمل `permissions_helper.php` |
| `Operations/containers.php` | بلا `$MODULE_CODE` مُعلَن |
| `user_capacities.php` | لا يشتمل `permissions_helper.php` |
