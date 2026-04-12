<?php
// admin/audit.php  –  Security Audit Log (Admin Only)
require_once __DIR__ . '/../includes/config.php';
startSecureSession();
requireAdmin();

$pdo       = getDB();
$pageTitle = 'Audit Log – ' . APP_NAME;
$filter    = sanitiseString($_GET['action_filter'] ?? '', 50);

$sql  = "SELECT a.*, u.name FROM audit_log a LEFT JOIN users u ON a.user_id = u.id";
$params = [];
if ($filter !== '') {
    $sql   .= ' WHERE a.action LIKE ?';
    $params[] = "%$filter%";
}
$sql .= ' ORDER BY a.created_at DESC LIMIT 500';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$actions = $pdo->query("SELECT DISTINCT action FROM audit_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h2><i class="fa fa-shield-alt"></i> Security Audit Log</h2>
</div>

<div class="section-box">
  <form method="GET" class="search-bar">
    <select name="action_filter">
      <option value="">-- All Actions --</option>
      <?php foreach ($actions as $a): ?>
        <option value="<?= e($a) ?>" <?= $filter === $a ? 'selected' : '' ?>><?= e($a) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn-primary"><i class="fa fa-filter"></i> Filter</button>
    <?php if ($filter): ?><a href="<?= e(url('/admin/audit.php')) ?>" class="btn-secondary">Clear</a><?php endif; ?>
  </form>

  <table class="data-table">
    <thead>
      <tr><th>Timestamp</th><th>User</th><th>Action</th><th>Detail</th><th>IP Address</th></tr>
    </thead>
    <tbody>
      <?php foreach ($logs as $log): ?>
      <tr>
        <td><?= e($log['created_at']) ?></td>
        <td><?= e($log['name'] ?? 'Guest') ?></td>
        <td>
          <span class="badge <?=
            str_contains($log['action'],'FAIL')  ||
            str_contains($log['action'],'LOCK')  ||
            str_contains($log['action'],'UNAUTH')
            ? 'badge-danger' : 'badge-info' ?>">
            <?= e($log['action']) ?>
          </span>
        </td>
        <td><?= e($log['detail'] ?? '') ?></td>
        <td><code><?= e($log['ip_address']) ?></code></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($logs)): ?>
        <tr><td colspan="5" class="empty-row">No audit entries found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  <p class="table-note">Showing last 500 entries</p>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>