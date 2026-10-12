<?php

session_start();


// =======================================
// 最初からやり直す
// =======================================

if (isset($_GET['reset'])) {

    unset($_SESSION['scene_id']);
    unset($_SESSION['love']);
    unset($_SESSION['text_index']);

    header('Location: game.php');
    exit;
}


require_once __DIR__ . '/config/db.php';


// =======================================
// ログイン確認
// =======================================

if (!isset($_SESSION['user_id'])) {

    header('Location: login.php');
    exit;
}


// =======================================
// ゲームの初期状態
// =======================================

if (!isset($_SESSION['scene_id'])) {
    $_SESSION['scene_id'] = 1;
}

if (!isset($_SESSION['love'])) {
    $_SESSION['love'] = 0;
}

if (!isset($_SESSION['text_index'])) {
    $_SESSION['text_index'] = 0;
}


// =======================================
// POST処理
// =======================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 現在のシーンを取得
    $stmt = $pdo->prepare(
        'SELECT * FROM scenes WHERE id = ?'
    );

    $stmt->execute([
        $_SESSION['scene_id']
    ]);

    $currentScene = $stmt->fetch(
        PDO::FETCH_ASSOC
    );


    if ($currentScene) {


        // ===================================
        // normalシーン
        // ===================================

        if ($currentScene['scene_type'] === 'normal') {

            if (
                ($_POST['action'] ?? '') === 'next'
            ) {


                // -------------------------------
                // プロローグ
                // scene_id = 1 は全文表示
                // -------------------------------

                if ((int)$currentScene['id'] === 1) {

                    if (
                        $currentScene['next_scene_id']
                        !== null
                    ) {

                        $_SESSION['scene_id']
                            = $currentScene['next_scene_id'];

                        $_SESSION['text_index'] = 0;
                    }


                // -------------------------------
                // 通常シーン
                // -------------------------------

                } else {

                    // 空行単位で文章を分割
                    $chunks = preg_split(
                        "/\R{2,}/u",
                        trim(
                            $currentScene['content_text']
                        )
                    );


                    if (
                        !$chunks ||
                        count($chunks) === 0
                    ) {

                        $chunks = [
                            $currentScene['content_text']
                        ];
                    }


                    // まだ同じシーンに文章が残っている
                    if (
                        $_SESSION['text_index']
                        < count($chunks) - 1
                    ) {

                        $_SESSION['text_index']++;


                    // 最後まで読んだ
                    } else {

                        if (
                            $currentScene['next_scene_id']
                            !== null
                        ) {

                            $_SESSION['scene_id']
                                = $currentScene['next_scene_id'];

                            $_SESSION['text_index'] = 0;
                        }
                    }
                }
            }
        }


        // ===================================
        // choiceシーン
        // ===================================

        elseif (
            $currentScene['scene_type']
            === 'choice'
        ) {

            // 空行単位で文章を分割
            $chunks = preg_split(
                "/\R{2,}/u",
                trim(
                    $currentScene['content_text']
                )
            );


            if (
                !$chunks ||
                count($chunks) === 0
            ) {

                $chunks = [
                    $currentScene['content_text']
                ];
            }


            // -------------------------------
            // 文章を進める
            // -------------------------------

            if (
                ($_POST['action'] ?? '') === 'next' &&
                $_SESSION['text_index']
                < count($chunks) - 1
            ) {

                $_SESSION['text_index']++;
            }


            // -------------------------------
            // 最後まで読んだ後に選択
            // -------------------------------

            elseif (
                isset($_POST['choice_id']) &&
                $_SESSION['text_index']
                >= count($chunks) - 1
            ) {

                $stmt = $pdo->prepare(
                    'SELECT *
                     FROM choices
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

                    // 恋愛メーターを増減
                    $_SESSION['love']
                        += (int)$choice['love_change'];


                    // 選択肢に対応した次のシーンへ
                    $_SESSION['scene_id']
                        = $choice['next_scene_id'];


                    // 文送りを最初に戻す
                    $_SESSION['text_index'] = 0;
                }
            }
        }


        // ===================================
        // TRUE END / BAD END
        // ===================================

        elseif (
            $currentScene['scene_type']
                === 'ending_true' ||
            $currentScene['scene_type']
                === 'ending_bad'
        ) {

            $chunks = preg_split(
                "/\R{2,}/u",
                trim(
                    $currentScene['content_text']
                )
            );


            if (
                !$chunks ||
                count($chunks) === 0
            ) {

                $chunks = [
                    $currentScene['content_text']
                ];
            }


            // エンディングも1文ずつ進める
            if (
                ($_POST['action'] ?? '') === 'next' &&
                $_SESSION['text_index']
                < count($chunks) - 1
            ) {

                $_SESSION['text_index']++;
            }
        }
    }


    // POST再送信防止
    header('Location: game.php');
    exit;
}


// =======================================
// 現在のシーンを取得
// =======================================

$stmt = $pdo->prepare(
    'SELECT * FROM scenes WHERE id = ?'
);

$stmt->execute([
    $_SESSION['scene_id']
]);

$scene = $stmt->fetch(
    PDO::FETCH_ASSOC
);


// =======================================
// エンディング判定
// =======================================

$trueEndThreshold = 75;


if (
    $scene &&
    $scene['scene_type'] === 'judge'
) {

    if (
        $_SESSION['love']
        >= $trueEndThreshold
    ) {

        // TRUE END
        $_SESSION['scene_id'] = 11;

    } else {

        // BAD END
        $_SESSION['scene_id'] = 12;
    }


    $_SESSION['text_index'] = 0;


    header('Location: game.php');
    exit;
}


// =======================================
// シーンがない場合
// =======================================

if (!$scene) {

    exit('シーンが見つかりません。');
}


// =======================================
// 表示する文章を決定
// =======================================

$choices = [];

$isPrologue = (
    (int)$scene['id'] === 1
);

$displayText = $scene['content_text'];

$chunks = [
    $scene['content_text']
];

$isLastText = true;


// =======================================
// プロローグ
// =======================================

if ($isPrologue) {

    // 空行を通常の改行にする
    // プロローグは全文表示
    $displayText = preg_replace(
        "/\R{2,}/u",
        "\n",
        trim(
            $scene['content_text']
        )
    );


// =======================================
// プロローグ以外
// =======================================

} elseif (
    $scene['scene_type'] === 'normal' ||
    $scene['scene_type'] === 'choice' ||
    $scene['scene_type'] === 'ending_true' ||
    $scene['scene_type'] === 'ending_bad'
) {

    // DB上の空行単位で
    // 1文・1セリフずつ分割
    $chunks = preg_split(
        "/\R{2,}/u",
        trim(
            $scene['content_text']
        )
    );


    if (
        !$chunks ||
        count($chunks) === 0
    ) {

        $chunks = [
            $scene['content_text']
        ];
    }


    $index
        = $_SESSION['text_index'] ?? 0;


    // 念のため範囲外を防止
    if (
        $index > count($chunks) - 1
    ) {

        $index = count($chunks) - 1;
    }


    // 現在表示する文章
    $displayText
        = $chunks[$index];


    // 最後の文章か判定
    $isLastText = (
        $index >= count($chunks) - 1
    );
}


// =======================================
// 選択肢取得
// =======================================

if (
    $scene['scene_type']
    === 'choice'
) {

    $stmt = $pdo->prepare(
        'SELECT *
         FROM choices
         WHERE scene_id = ?
         ORDER BY display_order ASC'
    );

    $stmt->execute([
        $_SESSION['scene_id']
    ]);

    $choices = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );
}

// 管理画面への入口は、ログイン情報と現在のDB権限の両方で判定する。
$showAdminLink = false;
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    try {
        $adminAccess = $pdo->prepare('SELECT role FROM users WHERE id = ?');
        if ($adminAccess && $adminAccess->execute([$_SESSION['user_id']])) {
            $showAdminLink = $adminAccess->fetchColumn() === 'admin';
        }
    } catch (PDOException $e) {
        error_log('game admin access: ' . $e->getMessage());
    }
}

// 表示専用の設定。セッション・文章送り・選択結果は変更しない。
$assetReplacements = [
    'miyu.png' => 'images/characters/miyu-v2.png',
    'miyu_normal_transparent (2).png' => 'images/characters/miyu-v2.png',
    'おばあちゃん.png' => 'images/characters/grandma-v2.png',
    'grandma_normal_transparent (3).png' => 'images/characters/grandma-v2.png',
    'おばあちゃんち.png' => 'images/background/home-v2.png',
    '学校.png' => 'images/background/school-v2.png',
    '雨の日.jpg' => 'images/background/rain-v2.png',
    '文化祭荷物倉庫.png' => 'images/background/storage-v2.png',
];
$resolveGameAsset = static function ($path) use ($assetReplacements) {
    $path = (string)$path;
    $replacement = $assetReplacements[basename($path)] ?? null;
    return ($replacement !== null && is_file(__DIR__ . '/' . $replacement))
        ? $replacement : $path;
};
$backgroundImage = $resolveGameAsset($scene['background_image'] ?? '');
$characterImage = $resolveGameAsset($scene['character_image'] ?? '');
$isTrueEnd = ($scene['scene_type'] === 'ending_true');
$showCharacter = !$isTrueEnd && !empty($scene['character_image']);
$sceneId = (int)$scene['id'];
$chapterLabel = 'STORY';
$chapterTitle = (string)($scene['title'] ?? '');
if ($isPrologue) {
    $chapterLabel = 'PROLOGUE';
    $chapterTitle = 'はじまりの記憶';
} elseif ($sceneId >= 4 && $sceneId <= 7) {
    $chapterLabel = 'EVENT 1';
    $chapterTitle = '相合傘';
} elseif ($sceneId === 8 || $sceneId === 9) {
    $chapterLabel = 'EVENT 2';
    $chapterTitle = '文化祭';
} elseif (in_array($scene['scene_type'], ['ending_true', 'ending_bad'], true)) {
    // 結末の種別は最後の文章まで見出しで明かさない。
    $chapterLabel = $isLastText
        ? ($scene['scene_type'] === 'ending_true' ? 'TRUE END' : 'BAD END')
        : 'EPILOGUE';
    $chapterTitle = $isLastText ? '物語の結末' : 'あの日の、その先へ';
}

?>


<!DOCTYPE html>

<html lang="ja">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0, viewport-fit=cover"
    >

    <title>
        ファーストキスおばあちゃん
    </title>

    <link
        rel="stylesheet"
        href="css/game.css?v=20261012-navigation"
    >

</head>


<body>


<main
    class="game-screen<?= $showAdminLink ? ' has-admin-access' : '' ?>"
    style="
        background-image: url('<?= htmlspecialchars(
            $backgroundImage,
            ENT_QUOTES,
            'UTF-8'
        ) ?>');
    "
>


    <header class="chapter-heading" aria-label="現在の場面">
        <span class="chapter-flower" aria-hidden="true">✿</span>
        <span class="chapter-label"><?= htmlspecialchars($chapterLabel, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="chapter-title"><?= htmlspecialchars($chapterTitle, ENT_QUOTES, 'UTF-8') ?></span>
    </header>

    <!-- ==================================
         中断 / 最初から
    =================================== -->

    <nav class="game-controls" aria-label="ゲーム操作">

        <a
            href="index.php"
            class="control-button"
        >
            <span class="control-icon" aria-hidden="true">Ⅱ</span>
            <span>中断</span>
        </a>

        <a
            href="game.php?reset=1"
            class="control-button restart-button"
        >
            <span class="control-icon" aria-hidden="true">↺</span>
            <span>最初から</span>
        </a>

        <?php if ($showAdminLink): ?>
            <a href="admin.php" class="control-button admin-return-button">管理画面へ</a>
        <?php endif; ?>

    </nav>


    <!-- ==================================
         恋愛メーター
    =================================== -->

    <div class="love-meter">

        💗 恋愛メーター：
        <?= (int)$_SESSION['love'] ?>%

    </div>


    <!-- ==================================
         キャラクター
    =================================== -->

    <?php if ($showCharacter): ?>

        <img
            class="character-image"
            src="<?= htmlspecialchars(
                $characterImage,
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            alt="キャラクター"
        >

    <?php endif; ?>


    <!-- ==================================
         会話ボックス
    =================================== -->

    <div
        class="
            dialogue-box
            <?= $isPrologue
                ? 'prologue-box'
                : ''
            ?>
            <?= (!$isPrologue && $showCharacter)
                ? 'with-character'
                : ''
            ?>
            <?= $isTrueEnd ? 'true-end-box' : '' ?>
        "
    >


        <!-- 話者名 -->

        <?php if (
            $scene['speaker'] !== null &&
            $scene['speaker'] !== ''
        ): ?>

            <div class="speaker-name">

                <?= htmlspecialchars(
                    $scene['speaker'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>


        <!-- 本文 -->

        <div class="story-text">

            <?= nl2br(
                htmlspecialchars(
                    $displayText,
                    ENT_QUOTES,
                    'UTF-8'
                )
            ) ?>

        </div>


        <!-- ==================================
             normal
        =================================== -->

        <?php if (
            $scene['scene_type'] === 'normal' &&
            $scene['next_scene_id'] !== null
        ): ?>

            <form
                method="POST"
                class="next-form"
            >

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


        <!-- ==================================
             choice
        =================================== -->

        <?php elseif (
            $scene['scene_type']
            === 'choice'
        ): ?>


            <?php if (!$isLastText): ?>

                <!-- 文章が残っている -->

                <form
                    method="POST"
                    class="next-form"
                >

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


            <?php else: ?>

                <!--
                    choiceシーンの最後まで来たら
                    選択肢を表示
                -->

                <div class="choices">


                    <?php foreach (
                        $choices as $choice
                    ): ?>

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

            <?php endif; ?>


        <!-- ==================================
             TRUE END / BAD END
        =================================== -->

        <?php elseif (
            $scene['scene_type']
                === 'ending_true' ||
            $scene['scene_type']
                === 'ending_bad'
        ): ?>


            <?php if (!$isLastText): ?>

                <!--
                    エンディング途中
                -->

                <form
                    method="POST"
                    class="next-form"
                >

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


            <?php else: ?>

                <!--
                    エンディング最後
                -->

                <form
                    method="GET"
                    action="game.php"
                    class="next-form"
                >

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


        <?php endif; ?>


    </div>


</main>


</body>

</html>
