<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>ログイン</title>

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
        ログイン
      </h1>


      <p class="auth-description">
        もう一度、あの春へ。
      </p>


      <form action="login.php" method="post" class="auth-form">

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
          ログイン
        </button>

      </form>


      <div class="auth-divider">
        <span>✿</span>
      </div>


      <p class="auth-switch">
        アカウントをお持ちでない方は
        <a href="register.php" class="auth-link">
          新規登録
        </a>
      </p>

    </section>

  </main>

</body>

</html>