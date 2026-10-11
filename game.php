<?php
session_start();

require_once __DIR__ . '/config/db.php';

// ゲームの初期状態を設定
if (!isset($_SESSION['scene_id'])) {
    $_SESSION['scene_id'] = 1;
}

if (!isset($_SESSION['love'])) {
    $_SESSION['love'] = 0;
}


// ボタンが押されたとき
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 現在のシーンを取得
    $stmt = $pdo->prepare(
        'SELECT * FROM scenes WHERE id = ?'
    );

    $stmt->execute([
        $_SESSION['scene_id']
    ]);

    $currentScene = $stmt->fetch(PDO::FETCH_ASSOC);


    if ($currentScene) {

        // normalシーン
     if ($currentScene['scene_type'] === 'normal') {

    if (
        ($_POST['action'] ?? '') === 'next' &&
        $currentScene['next_scene_id'] !== null
    ) {
        $_SESSION['scene_id'] = $currentScene['next_scene_id'];
}
     }
        // choiceシーン
        elseif ($currentScene['scene_type'] === 'choice') {

            if (isset($_POST['choice_id'])) {

                $stmt = $pdo->prepare(
                    'SELECT * FROM choices
                     WHERE id = ?
                     AND scene_id = ?'
                );

                $stmt->execute([
                    $_POST['choice_id'],
                    $_SESSION['scene_id']
                ]);

                $choice = $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


                if ($choice) {

                    $_SESSION['love']
                        += (int)$choice['love_change'];

                    $_SESSION['scene_id']
                        = $choice['next_scene_id'];
                }
            }
        }
    }


    header('Location: game.php');
    exit;
}





// 現在のシーンを取得
$stmt = $pdo->prepare(
    'SELECT * FROM scenes WHERE id = ?'
);

$stmt->execute([$_SESSION['scene_id']]);

$scene = $stmt->fetch(PDO::FETCH_ASSOC);
// エンディング判定
$trueEndThreshold = 81;

if ($scene && $scene['scene_type'] === 'judge') {

    if ($_SESSION['love'] >= $trueEndThreshold) {
        // TRUE END
        $_SESSION['scene_id'] = 11;
    } else {
        // BAD END
        $_SESSION['scene_id'] = 12;
    }

    // エンディング画面へ移動
    header('Location: game.php');
    exit;
}


// シーンが存在しない場合
if (!$scene) {
    exit('シーンが見つかりません。');
}

// 選択肢を入れる配列
$choices = [];

// 選択肢シーンの場合
if ($scene['scene_type'] === 'choice') {

    $stmt = $pdo->prepare(
        'SELECT * FROM choices
         WHERE scene_id = ?
         ORDER BY display_order ASC'
    );

    $stmt->execute([$_SESSION['scene_id']]);

    $choices = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>


<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>ファーストキスおばあちゃん</title>
</head>

<body>

<div class="love-meter">
    💗 恋愛メーター：
    <?= (int)$_SESSION['love'] ?>%
</div>


    <main
    style="
        min-height: 100vh;
        background-image: url('<?= htmlspecialchars($scene['background_image'] ?? '', ENT_QUOTES, 'UTF-8') ?>');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
    "
>

        <h1>
            <?= htmlspecialchars($scene['title']) ?>
        </h1>

<?php if (!empty($scene['character_image'])): ?>
    <img
        src="<?= htmlspecialchars($scene['character_image'], ENT_QUOTES, 'UTF-8') ?>"
        alt="キャラクター"
        style="height: 350px;"
    >

<?php endif; ?>

        <?php if ($scene['speaker'] !== null): ?>

            <p>
                <?= htmlspecialchars($scene['speaker']) ?>
            </p>
            <?php endif; ?>



        <p>
            <?= nl2br(htmlspecialchars($scene['content_text'])) ?>
        </p>


        <?php if ($scene['next_scene_id'] !== null): ?>


<?php if ($scene['scene_type'] === 'choice'): ?>

    <!-- 選択肢を表示 -->
    <?php foreach ($choices as $choice): ?>
              <!-- ↓ここに「もう一度プレイ」を追加 -->

<?php if (
    $scene['scene_type'] === 'ending_true' ||
    $scene['scene_type'] === 'ending_bad'
): ?>

    <form method="GET" action="game.php">
        <button type="submit" name="reset" value="1">
            もう一度プレイ
        </button>
    </form>

<?php endif; ?>

        <form method="POST">
            <button type="submit" name="choice_id"
                    value="<?= (int)$choice['id'] ?>">
                <?= htmlspecialchars($choice['choice_text'], ENT_QUOTES, 'UTF-8') ?>
            </button>
        </form>

    <?php endforeach; ?>

<?php elseif (
    $scene['scene_type'] === 'normal' &&
    $scene['next_scene_id'] !== null
): ?>
    <!-- 通常シーンの次へボタン -->
    <form method="POST">
        <button type="submit" name="action" value="next">
            次へ
        </button>
    </form>

<?php endif; ?>



    </main>

</body>

</html>
