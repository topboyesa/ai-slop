<?php
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';
start_session();

if (!empty($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}

$errors = [];
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $email    = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['confirm'] ?? '');

        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
            $errors[] = 'Username must be 3-30 letters, numbers or underscores.';
        }
        if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if (strlen($password) > 72) {
            $errors[] = 'Password must be 72 characters or fewer.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }

        if (!$errors) {
            try {
                $pdo  = db();
                $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
                $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);

                session_regenerate_id(true);
                $_SESSION['user_id']  = (int) $pdo->lastInsertId();
                $_SESSION['username'] = $username;
                header('Location: index.html');
                exit;
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23000') {
                    $errors[] = 'That username or email is already taken.';
                } else {
                    error_log($ex->getMessage());
                    $errors[] = 'Something went wrong. Please try again.';
                }
            }
        }
    }
}

page_head('Sign up');
?>
<?php if ($errors): ?>
<ul class="errors">
<?php foreach ($errors as $err): ?>
    <li><?= e($err) ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<form class="auth" method="post" action="signup.php">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <label for="username">Username</label>
    <input id="username" name="username" type="text" value="<?= e($username) ?>" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_]+" autocomplete="username">

    <label for="email">Email</label>
    <input id="email" name="email" type="email" value="<?= e($email) ?>" required maxlength="254" autocomplete="email">

    <label for="password">Password</label>
    <input id="password" name="password" type="password" required minlength="8" maxlength="72" autocomplete="new-password">

    <label for="confirm">Confirm password</label>
    <input id="confirm" name="confirm" type="password" required minlength="8" maxlength="72" autocomplete="new-password">

    <button type="submit">Sign up</button>
</form>

<p style="text-align: center;">Already have an account? <a href="login.php">Log in</a></p>
<?php page_foot();
