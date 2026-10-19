<?php
// Saves a like (1), a dislike (-1) or removes the vote (0). Members only.
require __DIR__ . '/config.php';
start_session();

header('Content-Type: application/json');
header('Cache-Control: no-store');

function fail(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, 'POST only.');
}
if (empty($_SESSION['user_id'])) {
    fail(401, 'Log in to vote.');
}
if (!csrf_ok()) {
    fail(403, 'Session expired. Reload the page.');
}

$post = (string) ($_POST['post'] ?? '');
$vote = (int) ($_POST['vote'] ?? 0);

if (!preg_match('/^post-[0-9]{1,4}$/', $post)) {
    fail(400, 'Bad post.');
}
if (!in_array($vote, [1, -1, 0], true)) {
    fail(400, 'Bad vote.');
}

try {
    $pdo = db();
    $userId = (int) $_SESSION['user_id'];

    if ($vote === 0) {
        $pdo->prepare('DELETE FROM post_votes WHERE user_id = ? AND post_id = ?')
            ->execute([$userId, $post]);
    } else {
        $pdo->prepare('INSERT INTO post_votes (user_id, post_id, vote) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE vote = VALUES(vote)')
            ->execute([$userId, $post, $vote]);
    }

    $stmt = $pdo->prepare('SELECT SUM(vote = 1) AS likes, SUM(vote = -1) AS dislikes FROM post_votes WHERE post_id = ?');
    $stmt->execute([$post]);
    $row = $stmt->fetch();

    echo json_encode([
        'likes'    => (int) ($row['likes'] ?? 0),
        'dislikes' => (int) ($row['dislikes'] ?? 0),
        'mine'     => $vote,
    ]);
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    fail(500, 'Server error.');
}
