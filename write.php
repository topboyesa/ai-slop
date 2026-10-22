<?php
// Admin-only page: write, edit and delete posts.
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';
start_session();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = db();

$stmt = $pdo->prepare('SELECT 1 FROM admins WHERE user_id = ?');
$stmt->execute([(int) $_SESSION['user_id']]);
if (!$stmt->fetchColumn()) {
    http_response_code(403);
    page_head('Not allowed');
    echo '<p style="text-align: center;">Only the site owner can write posts.</p>';
    page_foot();
    exit;
}

$errors = [];
$editId = 0;
$title  = '';
$body   = '';

// Opening write.php?id=4 loads post 4 into the form for editing.
if (isset($_GET['id']) && ctype_digit((string) $_GET['id'])) {
    $stmt = $pdo->prepare('SELECT id, title, body FROM posts WHERE id = ?');
    $stmt->execute([(int) $_GET['id']]);
    if ($row = $stmt->fetch()) {
        $editId = (int) $row['id'];
        $title  = $row['title'];
        $body   = $row['body'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $id     = (int) ($_POST['id'] ?? 0);

        if ($action === 'delete') {
            // Remove the post and everything attached to it.
            $key = 'post-' . $id;
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);
            foreach (['post_votes', 'post_views', 'comments'] as $table) {
                $pdo->prepare("DELETE FROM $table WHERE post_id = ?")->execute([$key]);
            }
            $pdo->commit();
            header('Location: write.php?done=deleted');
            exit;
        }

        if ($action === 'save') {
            $title  = trim((string) ($_POST['title'] ?? ''));
            $body   = trim((string) ($_POST['body'] ?? ''));
            $editId = $id;

            $titleLength = preg_match_all('/./us', $title);
            $bodyLength  = preg_match_all('/./us', $body);
            if ($titleLength < 1 || $titleLength > 200) {
                $errors[] = 'The title must be 1 to 200 characters.';
            }
            if ($bodyLength < 1 || $bodyLength > 16000) {
                $errors[] = 'The post must be 1 to 16000 characters.';
            }

            if (!$errors) {
                if ($id > 0) {
                    $pdo->prepare('UPDATE posts SET title = ?, body = ? WHERE id = ?')->execute([$title, $body, $id]);
                } else {
                    $pdo->prepare('INSERT INTO posts (title, body) VALUES (?, ?)')->execute([$title, $body]);
                }
                header('Location: write.php?done=saved');
                exit;
            }
        }
    }
}

$posts = $pdo->query('SELECT id, title, UNIX_TIMESTAMP(created_at) AS ts FROM posts ORDER BY id DESC')->fetchAll();
$done  = (string) ($_GET['done'] ?? '');

page_head($editId ? 'Edit post' : 'Write a post');
?>
<?php if ($done === 'saved'): ?>
<p style="text-align: center;">Post saved. <a href="index.html">See it on the site</a></p>
<?php elseif ($done === 'deleted'): ?>
<p style="text-align: center;">Post deleted.</p>
<?php endif; ?>

<?php if ($errors): ?>
<ul class="errors">
<?php foreach ($errors as $err): ?>
    <li><?= e($err) ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<form class="auth" method="post" action="write.php<?= $editId ? '?id=' . $editId : '' ?>" style="max-width: 720px;">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $editId ?>">

    <label for="title">Title</label>
    <input id="title" name="title" type="text" value="<?= e($title) ?>" required maxlength="200">

    <label for="body">Post (a blank line starts a new paragraph)</label>
    <textarea id="body" name="body" rows="14" required maxlength="16000" style="display: block; width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid var(--line); border-radius: 8px; background: var(--bg); color: var(--fg); font: inherit;"><?= e($body) ?></textarea>

    <button type="submit"><?= $editId ? 'Save changes' : 'Publish post' ?></button>
</form>
<?php if ($editId): ?>
<p style="text-align: center;"><a href="write.php">Write a new post instead</a></p>
<?php endif; ?>

<h2>Posts written here</h2>
<?php if (!$posts): ?>
<p style="text-align: center;">Nothing yet. Your first three posts live in index.html.</p>
<?php else: ?>
<ul style="max-width: 720px; margin: 0 auto 1.5rem; padding: 0 12px; list-style: none;">
<?php foreach ($posts as $p): ?>
    <li style="padding: 8px 0; border-bottom: 1px solid var(--line);">
        <?= e($p['title']) ?>
        <span style="opacity: 0.7;"> &middot; <?= e(date('j M Y', (int) $p['ts'])) ?></span>
        &middot; <a href="write.php?id=<?= (int) $p['id'] ?>">edit</a>
        &middot;
        <form method="post" action="write.php" style="display: inline;" onsubmit="return confirm('Delete this post and its comments, likes and views?');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button type="submit" style="background: none; border: none; padding: 0; color: inherit; font: inherit; text-decoration: underline; cursor: pointer;">delete</button>
        </form>
    </li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?php page_foot();
