<?php
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';
start_session();

if (!empty($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $email    = substr(strtolower(trim((string) ($_POST['email'] ?? ''))), 0, 254);
        $password = (string) ($_POST['password'] ?? '');
        $ip       = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        try {
            $pdo = db();

            // Slow down password guessing: 5 failures per email or 20 per IP in 15 minutes.
            $byEmail = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)');
            $byEmail->execute([$email]);
            $byIp = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)');
            $byIp->execute([$ip]);

            if ((int) $byEmail->fetchColumn() >= 5 || (int) $byIp->fetchColumn() >= 20) {
                $errors[] = 'Too many failed attempts. Please try again in 15 minutes.';
            } else {
                $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE email = ?');
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    $pdo->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$email]);
                    session_regenerate_id(true);
                    $_SESSION['user_id']  = (int) $user['id'];
                    $_SESSION['username'] = $user['username'];
                    header('Location: index.html');
                    exit;
                }

                $pdo->prepare('INSERT INTO login_attempts (email, ip) VALUES (?, ?)')->execute([$email, $ip]);
                $errors[] = 'Invalid email or password.';
            }
        } catch (PDOException $ex) {
            error_log($ex->getMessage());
            $errors[] = 'Something went wrong. Please try again.';
        }
    }
}

page_head('Log in');
?>
<?php if ($errors): ?>
<ul class="errors">
<?php foreach ($errors as $err): ?>
    <li><?= e($err) ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<form class="auth" method="post" action="login.php">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <label for="email">Email</label>
    <input id="email" name="email" type="email" value="<?= e($email) ?>" required maxlength="254" autocomplete="email">

    <label for="password">Password</label>
    <input id="password" name="password" type="password" required maxlength="72" autocomplete="current-password">

    <button type="submit">Log in</button>
</form>

<p style="text-align: center;">No account yet? <a href="signup.php">Sign up</a></p>
<?php page_foot();
