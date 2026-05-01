<?php
require_once __DIR__ . '/functions.php';
requireLogin();
requireRole('manager');
$pageTitle = 'News & Announcements';
$user   = getCurrentUser();
$userId = (int)$user['id'];
$pdo    = getDB();

// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $id      = (int)($_POST['id'] ?? 0);

    if ($action === 'add') {
        if (empty($title) || empty($content)) {
            flashMessage('danger', 'Title and content are required.');
        } else {
            $pdo->prepare("INSERT INTO news_master (title, content, created_by) VALUES (?,?,?)")
                ->execute([$title, $content, $userId]);
            flashMessage('success', 'Announcement published.');
        }
    }
    if ($action === 'edit' && $id) {
        $pdo->prepare("UPDATE news_master SET title=?, content=? WHERE id=?")
            ->execute([$title, $content, $id]);
        flashMessage('success', 'Announcement updated.');
    }
    if ($action === 'delete' && $id) {
        $pdo->prepare("DELETE FROM news_master WHERE id=?")->execute([$id]);
        flashMessage('success', 'Announcement deleted.');
    }
    redirect(APP_URL . '/news_master.php');
}

$stmt = $pdo->query("SELECT n.*, u.username FROM news_master n JOIN users u ON n.created_by=u.id ORDER BY n.created_at DESC");
$newsList = $stmt->fetchAll();
include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h2><i class="ri-megaphone-line me-2"></i>News &amp; Announcements</h2>
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
      <li class="breadcrumb-item active">News</li>
    </ol></nav>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNewsModal">
    <i class="ri-add-line me-1"></i>New Announcement
  </button>
</div>

<!-- News List -->
<?php if (empty($newsList)): ?>
  <div class="card"><div class="card-body text-center text-muted py-5">
    <i class="ri-megaphone-line" style="font-size:3rem;opacity:.3;"></i>
    <p class="mt-2 mb-0">No announcements yet. Create the first one!</p>
  </div></div>
<?php else: ?>
  <?php foreach ($newsList as $item): ?>
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span class="fw-semibold"><?= h($item['title']) ?></span>
      <div class="d-flex align-items-center gap-3">
        <small class="text-muted">By <?= h($item['username']) ?> &mdash; <?= h($item['created_at']) ?></small>
        <div class="d-flex gap-1">
          <button class="btn btn-xs btn-outline-primary"
            onclick="openEdit(<?= (int)$item['id'] ?>, <?= json_encode($item['title']) ?>, <?= json_encode($item['content']) ?>)">
            <i class="ri-edit-line"></i>
          </button>
          <form method="POST" class="d-inline" onsubmit="return confirm('Delete this announcement?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
            <button type="submit" class="btn btn-xs btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
          </form>
        </div>
      </div>
    </div>
    <div class="card-body">
      <p class="mb-0" style="font-size:.875rem;white-space:pre-wrap;"><?= nl2br(h($item['content'])) ?></p>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<!-- Add Modal -->
<div class="modal fade" id="addNewsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="">
        <input type="hidden" name="action" value="add">
        <div class="modal-header"><h5 class="modal-title">New Announcement</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Title *</label>
            <input type="text" name="title" class="form-control" required placeholder="Announcement title">
          </div>
          <div class="mb-3">
            <label class="form-label">Content *</label>
            <textarea name="content" class="form-control" rows="6" required placeholder="Write your announcement…"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="ri-send-plane-line me-1"></i>Publish</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editNewsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="editNewsId">
        <div class="modal-header"><h5 class="modal-title">Edit Announcement</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Title *</label>
            <input type="text" name="title" id="editNewsTitle" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Content *</label>
            <textarea name="content" id="editNewsContent" class="form-control" rows="6" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i>Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$extraJs = <<<'JS'
<script>
function openEdit(id, title, content) {
    document.getElementById('editNewsId').value = id;
    document.getElementById('editNewsTitle').value = title;
    document.getElementById('editNewsContent').value = content;
    new bootstrap.Modal(document.getElementById('editNewsModal')).show();
}
</script>
JS;
include __DIR__ . '/includes/footer.php';
?>
