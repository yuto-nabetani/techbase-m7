<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ログイン</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <div class="container">
    <h2>ログイン</h2>
    <form action="login.php" method="post">
      <input type="text" name="username" placeholder="ユーザー名" required>
      <input type="password" name="password" placeholder="パスワード" required>
      <button type="submit" class="btn">ログイン</button>
    </form>
    <a href="register.php" class="link">アカウントをお持ちでない方はこちら</a>
  </div>
</body>
</html>