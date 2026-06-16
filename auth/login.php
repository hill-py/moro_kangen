<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

if (isLoggedIn()) {
    header('Location: ../dashboard/index.php');
    exit;
}
//opotoh
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id_user, username, password, role FROM user WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            setUserSession($user);
            header('Location: ../dashboard/index.php');
            exit;
        }

        $error = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Moro Kangen</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="login-page">

<div class="login-wrapper">
  <div class="login-box">
    <div class="login-header">
      <h1>Moro Kangen</h1>
      <p>Mie Ayam Bakso - Karanggede, Boyolali</p>
    </div>

    <?php if ($error !== ''): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label for="username">Username</label>
        <input
          type="text"
          id="username"
          name="username"
          value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
          placeholder="Masukkan username"
          autocomplete="username"
          required
        >
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input
          type="password"
          id="password"
          name="password"
          placeholder="Masukkan password"
          autocomplete="current-password"
          required
        >
      </div>

      <button type="submit" class="btn btn-primary btn-full">Masuk</button>
    </form>
  </div>
</div>

</body>
</html>
