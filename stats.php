<?php
// Gives the main page every article's likes, dislikes and views,
// plus which ones the current member has voted on.
require __DIR__ . '/config.php';
start_session();

header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    $pdo = db();
    $posts = [];
    $blank = ['likes' => 0, 'dislikes' => 0, 'views' => 0, 'mine' => 0];

    $rows = $pdo->query('SELECT post_id, SUM(vote = 1) AS likes, SUM(vote = -1) AS dislikes FROM post_votes GROUP BY post_id')->fetchAll();
    foreach ($rows as $r) {
        $posts[$r['post_id']] = array_merge($blank, [
            'likes'    => (int) $r['likes'],
            'dislikes' => (int) $r['dislikes'],
        ]);
    }

    $rows = $pdo->query('SELECT post_id, views FROM post_views')->fetchAll();
    foreach ($rows as $r) {
        if (!isset($posts[$r['post_id']])) {
            $posts[$r['post_id']] = $blank;
        }
        $posts[$r['post_id']]['views'] = (int) $r['views'];
    }

    $loggedIn = !empty($_SESSION['user_id']);
    if ($loggedIn) {
        $stmt = $pdo->prepare('SELECT post_id, vote FROM post_votes WHERE user_id = ?');
        $stmt->execute([(int) $_SESSION['user_id']]);
        foreach ($stmt->fetchAll() as $r) {
            if (isset($posts[$r['post_id']])) {
                $posts[$r['post_id']]['mine'] = (int) $r['vote'];
            }
        }
    }

    echo json_encode([
        'posts'    => (object) $posts,
        'loggedIn' => $loggedIn,
        'csrf'     => $loggedIn ? csrf_token() : null,
    ]);
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error.']);
}
