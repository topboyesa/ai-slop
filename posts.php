<?php
// Gives the pages the posts that were written from the site (write.php),
// and says whether the visitor is an admin so the menu can show the "write" link.
require __DIR__ . '/config.php';
start_session();

header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    $pdo   = db();
    $posts = [];
    foreach ($pdo->query('SELECT id, title, body, UNIX_TIMESTAMP(created_at) AS ts FROM posts ORDER BY id ASC')->fetchAll() as $r) {
        $ts = (int) $r['ts'];
        $posts[] = [
            'id'       => 'post-' . $r['id'],
            'title'    => $r['title'],
            'body'     => $r['body'],
            'ts'       => $ts,
            'datetime' => date('Y-m-d\TH:i', $ts),
            'label'    => date('l jS F, Y. Hi', $ts) . ' HRS',   // e.g. Thursday 15th October, 2026. 0049 HRS
        ];
    }

    $admin = false;
    if (!empty($_SESSION['user_id'])) {
        $stmt = $pdo->prepare('SELECT 1 FROM admins WHERE user_id = ?');
        $stmt->execute([(int) $_SESSION['user_id']]);
        $admin = (bool) $stmt->fetchColumn();
    }

    echo json_encode(['posts' => $posts, 'admin' => $admin]);
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error.']);
}
