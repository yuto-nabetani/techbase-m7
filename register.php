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