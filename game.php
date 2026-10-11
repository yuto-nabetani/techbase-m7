<?php
session_start();

if (isset($_GET['reset'])) {
    unset($_SESSION['scene_id']);
    unset($_SESSION['love']);
    unset($_SESSION['text_index']);

    header('Location: game.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

// ログインしていない場合
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// ゲームの初期状態を設定
if (!isset($_SESSION['scene_id'])) {
    $_SESSION['scene_id'] = 1;
}

if (!isset($_SESSION['love'])) {
    $_SESSION['love'] = 0;
}

if (!isset($_SESSION['text_index'])) {
    $_SESSION['text_index'] = 0;
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

            if (($_POST['action'] ?? '') === 'next') {

                // プロローグ（scene_id=1）は全文表示して1回で次へ
                if ((int)$currentScene['id'] === 1) {

                    if ($currentScene['next_scene_id'] !== null) {
                        $_SESSION['scene_id'] = $currentScene['next_scene_id'];
                        $_SESSION['text_index'] = 0;
                    }

                } else {

                    // 文章を空行ごとに分割
                    $chunks = preg_split(
                        "/\R{2,}/u",
                        trim($currentScene['content_text'])
                    );

                    if (!$chunks || count($chunks) === 0) {
                        $chunks = [$currentScene['content_text']];
                    }

                    // まだ同じシーン内に次の文章があるなら進める
                    if ($_SESSION['text_index'] < count($chunks) - 1) {
                        $_SESSION['text_index']++;
                    } else {
                        // もう最後まで読んだら次のシーンへ
                        if ($currentScene['next_scene_id'] !== null) {
                            $_SESSION['scene_id'] = $currentScene['next_scene_id'];
                            $_SESSION['text_index'] = 0;
                        }
                    }
                }
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

                    $_SESSION['text_index'] = 0;
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
$trueEndThreshold = 75;

if ($scene && $scene['scene_type'] === 'judge') {

    if ($_SESSION['love'] >= $trueEndThreshold) {
        // TRUE END
        $_SESSION['scene_id'] = 11;
    } else {
        // BAD END
        $_SESSION['scene_id'] = 12;
    }

    $_SESSION['text_index'] = 0;    

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

$isPrologue = ((int)$scene['id'] === 1);
$displayText = $scene['content_text'];

if (!$isPrologue && $scene['scene_type'] === 'normal') {

    $chunks = preg_split(
        "/\R{2,}/u",
        trim($scene['content_text'])
    );

    if (!$chunks || count($chunks) === 0) {
        $chunks = [$scene['content_text']];
    }

    $index = $_SESSION['text_index'] ?? 0;

    if ($index > count($chunks) - 1) {
        $index = count($chunks) - 1;
    }

    $displayText = $chunks[$index];
}

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>ファーストキスおばあちゃん</title>

    <link rel="stylesheet" href="css/game.css">
</head>

<body>

<div class="love-meter">
    💗 恋愛メーター：
    <?= (int)$_SESSION['love'] ?>%
</div>


    <main
        class="game-screen"
        style="
            background-image: url('<?= htmlspecialchars(
                $scene['background_image'] ?? '',
                ENT_QUOTES,
                'UTF-8'
            ) ?>');
        "
    >



<?php if (!empty($scene['character_image'])): ?>

    <img
        class="character-image"
        src="<?= htmlspecialchars(
            $scene['character_image'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
        alt="キャラクター"
    >

<?php endif; ?>


<div class="dialogue-box <?= $isPrologue ? 'prologue-box' : '' ?>">

    <?php if ($scene['speaker'] !== null): ?>

        <div class="speaker-name">
            <?= htmlspecialchars($scene['speaker']) ?>
        </div>

    <?php endif; ?>


    <div class="story-text">
        <?= nl2br(
            htmlspecialchars(
                $displayText,
                ENT_QUOTES,
                'UTF-8'
            )
        ) ?>
    </div>


    <?php if (
        $scene['scene_type'] === 'ending_true' ||
        $scene['scene_type'] === 'ending_bad'
    ): ?>

        <form method="GET" action="game.php">
            <button
                type="submit"
                name="reset"
                value="1"
                class="game-button"
            >
                もう一度プレイ
            </button>
        </form>

    <?php endif; ?>


    <?php if ($scene['scene_type'] === 'choice'): ?>

        <div class="choices">

            <?php foreach ($choices as $choice): ?>

                <form method="POST">

                    <button
                        type="submit"
                        name="choice_id"
                        value="<?= (int)$choice['id'] ?>"
                        class="choice-button"
                    >
                        <?= htmlspecialchars(
                            $choice['choice_text'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </button>

                </form>

            <?php endforeach; ?>

        </div>


    <?php elseif (
        $scene['scene_type'] === 'normal' &&
        $scene['next_scene_id'] !== null
    ): ?>

        <form method="POST" class="next-form">

            <button
                type="submit"
                name="action"
                value="next"
                class="game-button"
            >
                次へ
                <span>›</span>
            </button>

        </form>

    <?php endif; ?>

    </div>

    </main>

</body>

</html>
