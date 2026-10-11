<?php
session_start();

if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('アクセス権限がありません。');
}

require_once __DIR__ . '/config/db.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('DB接続の設定を確認してください。');
}

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$users = [];
$scenes = [];
$error = '';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = 'SELECT id, username, role, created_at FROM users ORDER BY id ASC';
    $users = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    $sql = 'SELECT id, title, speaker, content_text, background_image,
                   character_image, scene_type, display_type, next_scene_id
            FROM scenes ORDER BY id ASC';
    $scenes = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    $error = '一覧を取得できませんでした。DBの設定とテーブルを確認してください。';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>管理者ページ | ファーストキスおばあちゃん</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <h1>管理者ページ</h1>
        <p>管理者：<?= h($_SESSION['username'] ?? '') ?></p>
        <p><a href="index.php">トップページへ戻る</a></p>
    </header>

    <main>
        <?php if ($error !== ''): ?>
            <p role="alert"><?= h($error) ?></p>
        <?php else: ?>
            <section aria-labelledby="users-heading">
                <h2 id="users-heading">登録ユーザー一覧（<?= count($users) ?>件）</h2>
                <?php if ($users === []): ?>
                    <p>登録ユーザーはいません。</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr><th scope="col">ID</th><th scope="col">ユーザー名</th><th scope="col">権限</th><th scope="col">登録日時</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= h($user['id']) ?></td>
                                    <td><?= h($user['username']) ?></td>
                                    <td><?= h($user['role']) ?></td>
                                    <td><?= h($user['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

            <section aria-labelledby="scenes-heading">
                <h2 id="scenes-heading">シナリオ一覧（<?= count($scenes) ?>件）</h2>
                <?php if ($scenes === []): ?>
                    <p>シナリオはありません。</p>
                <?php else: ?>
                    <?php foreach ($scenes as $scene): ?>
                        <article>
                            <h3>#<?= h($scene['id']) ?> <?= h($scene['title']) ?></h3>
                            <p>話者：<?= $scene['speaker'] === null ? '地の文' : h($scene['speaker']) ?></p>
                            <p><?= nl2br(h($scene['content_text']), false) ?></p>
                            <dl>
                                <dt>シーン種別</dt><dd><?= h($scene['scene_type']) ?></dd>
                                <dt>表示形式</dt><dd><?= h($scene['display_type']) ?></dd>
                                <dt>次のシーンID</dt><dd><?= $scene['next_scene_id'] === null ? 'なし' : h($scene['next_scene_id']) ?></dd>
                                <dt>背景画像</dt><dd><?= h($scene['background_image'] ?? '') ?></dd>
                                <dt>キャラクター画像</dt><dd><?= h($scene['character_image'] ?? '') ?></dd>
                            </dl>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
