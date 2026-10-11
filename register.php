<?php

require_once __DIR__ . '/config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // 未入力チェック
    if ($username === '' || $password === '') {

        $error = 'ユーザー名とパスワードを入力してください。';

    } else {

        // 同じユーザー名が存在するか確認
        $stmt = $pdo->prepare(
            'SELECT id FROM users WHERE username = ?'
        );

        $stmt->execute([$username]);

        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {

            $error = 'そのユーザー名はすでに使用されています。';

        } else {

            // パスワードをハッシュ化
            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // usersテーブルに登録
            $stmt = $pdo->prepare(
                'INSERT INTO users
                (username, password_hash)
                VALUES (?, ?)'
            );

            $stmt->execute([
                $username,
                $passwordHash
            ]);

            // 登録後ログイン画面へ
            header('Location: login.php');
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">

  <meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
  >

  <title>新規登録</title>

  <link rel="stylesheet" href="css/top.css">
  <link rel="stylesheet" href="css/auth.css">
</head>


<body>

  <main class="hero">

    <div class="hero-overlay"></div>

    <a href="index.php" class="back-home">
      ← トップへ戻る
    </a>


    <section class="title-panel">

      <div class="auth-game-title">
        <span>✿</span>
        ファーストキスおばあちゃん
        <span>✿</span>
      </div>


      <h1 class="auth-title">
        新規登録
      </h1>

      <?php if ($error !== ''): ?>
        <p class="error-message">
          <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>
      <?php endif; ?>
      
      <p class="auth-description">
        あなたの物語を、ここから始めよう。
      </p>


      <form action="register.php" method="post" class="auth-form">

        <input
          type="text"
          name="username"
          class="auth-input"
          placeholder="ユーザー名"
          required
        >

        <input
          type="password"
          name="password"
          class="auth-input"
          placeholder="パスワード"
          required
        >

        <button type="submit" class="auth-button">
          新規登録
        </button>

      </form>


      <div class="auth-divider">
        <span>✿</span>
      </div>


      <p class="auth-switch">
        すでにアカウントをお持ちの方は
        <a href="login.php" class="auth-link">
          ログイン
        </a>
      </p>

    </section>

  </main>

</body>

</html>