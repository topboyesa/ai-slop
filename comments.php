<?php
// Reads, adds and deletes comments.
//   GET  comments.php             -> how many comments each article has
//   GET  comments.php?post=post-1 -> the comments on one article
//   POST comments.php             -> action=add (post, body) or action=delete (id). Members only.
require __DIR__ . '/config.php';
start_session();

header('Content-Type: application/json');
header('Cache-Control: no-store');

function out(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function is_admin(PDO $pdo, int $userId): bool
{
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM admins WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $ex) {
        return false;
    }
}

function valid_post(string $post): bool
{
    return (bool) preg_match('/^post-[0-9]{1,4}$/', $post);
}

function comment_count(PDO $pdo, string $post): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM comments WHERE post_id = ?');
    $stmt->execute([$post]);
    return (int) $stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $pdo  = db();
        $post = (string) ($_GET['post'] ?? '');

        if ($post === '') {
            $counts = [];
            foreach ($pdo->query('SELECT post_id, COUNT(*) AS n FROM comments GROUP BY post_id')->fetchAll() as $r) {
                $counts[$r['post_id']] = (int) $r['n'];
            }
            out(['counts' => (object) $counts]);
        }

        if (!valid_post($post)) {
            out(['error' => 'Bad post.'], 400);
        }

        $me    = (int) ($_SESSION['user_id'] ?? 0);
        $admin = $me > 0 && is_admin($pdo, $me);

        $stmt = $pdo->prepare(
            'SELECT c.id, c.user_id, c.body, UNIX_TIMESTAMP(c.created_at) AS ts, u.username
             FROM comments c JOIN users u ON u.id = c.user_id
             WHERE c.post_id = ?
             ORDER BY c.created_at ASC, c.id ASC
             LIMIT 200'
        );
        $stmt->execute([$post]);

        $comments = [];
        foreach ($stmt->fetchAll() as $r) {
            $comments[] = [
                'id'        => (int) $r['id'],
                'user'      => $r['username'],
                'body'      => $r['body'],
                'ts'        => (int) $r['ts'],
                'canDelete' => $me > 0 && ((int) $r['user_id'] === $me || $admin),
            ];
        }
        out(['comments' => $comments]);
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        out(['error' => 'Server error.'], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(['error' => 'Method not allowed.'], 405);
}
if (empty($_SESSION['user_id'])) {
    out(['error' => 'Log in to comment.'], 401);
}
if (!csrf_ok()) {
    out(['error' => 'Session expired. Reload the page.'], 403);
}

$userId = (int) $_SESSION['user_id'];
$action = (string) ($_POST['action'] ?? '');

try {
    $pdo = db();

    if ($action === 'add') {
        $post = (string) ($_POST['post'] ?? '');
        $body = trim((string) ($_POST['body'] ?? ''));

        if (!valid_post($post)) {
            out(['error' => 'Bad post.'], 400);
        }
        $length = preg_match_all('/./us', $body);
        if ($length < 1 || $length > 1000) {
            out(['error' => 'A comment must be 1 to 1000 characters.'], 400);
        }

        // Slow down spam: one comment every 10 seconds per member.
        $recent = $pdo->prepare('SELECT COUNT(*) FROM comments WHERE user_id = ? AND created_at > (NOW() - INTERVAL 10 SECOND)');
        $recent->execute([$userId]);
        if ((int) $recent->fetchColumn() > 0) {
            out(['error' => 'Please wait a few seconds before commenting again.'], 429);
        }

        $pdo->prepare('INSERT INTO comments (post_id, user_id, body) VALUES (?, ?, ?)')
            ->execute([$post, $userId, $body]);
    } elseif ($action === 'delete') {
        $id   = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT post_id, user_id FROM comments WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            out(['error' => 'Comment not found.'], 404);
        }
        if ((int) $row['user_id'] !== $userId && !is_admin($pdo, $userId)) {
            out(['error' => 'You can only delete your own comments.'], 403);
        }

        $pdo->prepare('DELETE FROM comments WHERE id = ?')->execute([$id]);
        $post = $row['post_id'];
    } else {
        out(['error' => 'Bad action.'], 400);
    }

    out(['count' => comment_count($pdo, $post)]);
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    out(['error' => 'Server error.'], 500);
}
