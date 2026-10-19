<?php
// Counts one view per visitor session for an article.
require __DIR__ . '/config.php';
start_session();

header('Content-Type: application/json');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only.']);
    exit;
}

$post = (string) ($_POST['post'] ?? '');
if (!preg_match('/^post-[0-9]{1,4}$/', $post)) {
    http_response_code(400);
    echo json_encode(['error' => 'Bad post.']);
    exit;
}

try {
    $pdo = db();

    // Refreshing the page does not add more views: each session counts once per article.
    if (empty($_SESSION['viewed'][$post])) {
        $_SESSION['viewed'][$post] = true;
        $pdo->prepare('INSERT INTO post_views (post_id, views) VALUES (?, 1) ON DUPLICATE KEY UPDATE views = views + 1')
            ->execute([$post]);
    }

    $stmt = $pdo->prepare('SELECT views FROM post_views WHERE post_id = ?');
    $stmt->execute([$post]);
    echo json_encode(['views' => (int) $stmt->fetchColumn()]);
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error.']);
}
