INSERT INTO scenes
(
    id,
    title,
    speaker,
    content_text,
    background_image,
    character_image,
    scene_type,
    display_type,
    next_scene_id
)
VALUES
(
    1,
    'プロローグ',
    NULL,
    '幼い頃、両親を亡くした。

両親との思い出は、ほとんど残っていない。

俺にとって父さんと母さんは、写真の中でしか会えない家族だ。

そんな俺を、ずっと育ててくれたのがおばあちゃんだった。

だから俺にとって、本当の意味で家族と呼べる人は――

おばあちゃんだけだった。',
    'images/background/おばあちゃんち.png',
    NULL,
    'normal',
    'narration',
    2
);

<!-- シーン2：おばあちゃんが倒れる -->

INSERT INTO scenes
(
    id,
    title,
    speaker,
    content_text,
    background_image,
    character_image,
    scene_type,
    display_type,
    next_scene_id
)
VALUES
(
    2,
    'おばあちゃんが倒れる',
    NULL,
    'いつもの朝。

主人公「ばあちゃん、おはよう」

ばあちゃん「おはよ……」

主人公「……ばあちゃん？」

ドサッ！！

主人公「ばあちゃん！？」

突然、ばあちゃんが倒れた。

主人公「ばあちゃん！ しっかりして！」

返事がない。主人公は必死に救命処置を行う。そして――人工呼吸。

主人公「頼む……戻ってきてくれ……！」

ばあちゃん「……ん」

主人公「ばあちゃん！よかった……本当によかった……！」',
    'images/background/おばあちゃんち.png',
    NULL,
    'normal',
    'narration',
    3
);

<!-- シーン3：タイムリープとみゆとの出会い --> 

INSERT INTO scenes
(
    id,
    title,
    speaker,
    content_text,
    background_image,
    character_image,
    scene_type,
    display_type,
    next_scene_id
)
VALUES
(
    3,
    'タイムリープ・みゆとの出会い',
    NULL,
    '主人公「…………ん？」

主人公（今の……人工呼吸……）

主人公（俺、まだ誰ともキスしたことない……）

主人公「俺のファーストキス……いや、そんなことよりばあちゃん――」

パチィィィィン！！

目の前が真っ暗になった。

目を覚ますと、自分の部屋だった。

主人公「なんだ……夢か……ん？」

スマホを見る。

主人公「これ……数か月前じゃん！！」

主人公「天が俺に、ファーストキスをやり直すチャンスをくれたのか！？」

主人公「今度こそ……好きな人とファーストキスしてやる！」

こうして俺の「ファーストキスやり直し生活」が始まった。

――数日後。

学校で、俺は一人の女の子に目を奪われた。

主人公（……かわいい）

彼女の名前は――みゆ。

俺は、みゆに一目惚れした。

【ゲームスタート】',
    'images/background/学校.png',
    'images/characters/miyu.png',
    'normal',
    'narration',
    4
);

<!-- シーン4：相合傘イベント-->

INSERT INTO scenes
(
    id,
    title,
    speaker,
    content_text,
    background_image,
    character_image,
    scene_type,
    display_type,
    next_scene_id
)
VALUES
(
    4,
    'EVENT 1：相合傘',
    'みゆ',
    'それからしばらく経った放課後。

突然、大雨が降ってきた。

みゆ「うわ……傘忘れた……」

主人公（これは……）

主人公（ファーストキスへの第一歩なのでは！？）

どうする？',
    'images/background/雨の日.jpg',
    'images/characters/miyu.png',
    'choice',
    'narration',
    NULL
);

<!-- シーン4：相合傘イベントの選択肢--->

INSERT INTO choices
(
    scene_id,
    choice_text,
    love_change,
    next_scene_id,
    display_order
)
VALUES
(
    4,
    'A「一緒に入る？」',
    40,
    5,
    1
),
(
    4,
    'B「この傘使っていいよ」',
    25,
    6,
    2
),
(
    4,
    'C「雨が止むまで待てば？」',
    0,
    7,
    3
);

-- シーン5・6・7：相合傘の選択結果

INSERT INTO scenes
(
    id,
    title,
    speaker,
    content_text,
    background_image,
    character_image,
    scene_type,
    display_type,
    next_scene_id
)
VALUES
(
    5,
    '相合傘・Aの結果',
    NULL,
    'みゆ「……近くない？」

主人公「傘が小さいんだよ！」

みゆ「これ誰の傘？」

主人公「ばあちゃん」

みゆ「おばあちゃん！？」',
    'images/background/雨の日.jpg',
    'images/characters/miyu.png',
    'normal',
    'narration',
    8
),
(
    6,
    '相合傘・Bの結果',
    NULL,
    'みゆ「主人公は？」

主人公「走って帰る！」

みゆ「この雨の中！？」',
    'images/background/雨の日.jpg',
    'images/characters/miyu.png',
    'normal',
    'narration',
    8
),
(
    7,
    '相合傘・Cの結果',
    NULL,
    'みゆ「じゃあ、雨が止むまで待つ」

主人公「じゃ！」

みゆ「帰るんだ……」',
    'images/background/雨の日.jpg',
    'images/characters/miyu.png',
    'normal',
    'narration',
    8
);

-- シーン8：文化祭イベント

INSERT INTO scenes
(
    id,
    title,
    speaker,
    content_text,
    background_image,
    character_image,
    scene_type,
    display_type,
    next_scene_id
)
VALUES
(
    8,
    'EVENT 2：文化祭',
    'みゆ',
    '文化祭の片付け。

主人公とみゆは、一緒に荷物を運ぶことになった。

みゆ「これ重い……」

主人公（ここは……どうする？）',
    'images/background/文化祭荷物倉庫.png',
    'images/characters/miyu.png',
    'choice',
    'narration',
    NULL
);

-- シーン8：文化祭イベントの選択肢

INSERT INTO choices
(
    scene_id,
    choice_text,
    love_change,
    next_scene_id,
    display_order
)
VALUES
(
    8,
    'A「俺が持つよ」',
    40,
    9,
    1
),
(
    8,
    'B「半分持つよ」',
    20,
    9,
    2
),
(
    8,
    'C「頑張れ！」',
    0,
    9,
    3
);

-- シーン9：文化祭・共通ルート

INSERT INTO scenes
(
    id,
    title,
    speaker,
    content_text,
    background_image,
    character_image,
    scene_type,
    display_type,
    next_scene_id
)
VALUES
(
    9,
    '文化祭・共通ルート',
    NULL,
    'なんとか荷物を倉庫まで運び終えた。

みゆ「これで片付け終わりだね」

主人公「おう」

みゆ「じゃあ、また明日！」

主人公「……みゆ！」

みゆ「ん？」

主人公（今しかない……！）',
    'images/background/文化祭荷物倉庫.png',
    'images/characters/miyu.png',
    'normal',
    'narration',
    10
);
-- シーン10：好感度判定

INSERT INTO scenes
(
    id,
    title,
    speaker,
    content_text,
    background_image,
    character_image,
    scene_type,
    display_type,
    next_scene_id
)
VALUES
(
    10,
    '好感度判定',
    NULL,
    '恋の結末は……？',
    'images/background/文化祭荷物倉庫.png',
    NULL,
    'judge',
    'narration',
    NULL
);


-- シーン11：TRUE END

INSERT INTO scenes
(
    id,
    title,
    speaker,
    content_text,
    background_image,
    character_image,
    scene_type,
    display_type,
    next_scene_id
)
VALUES
(
    11,
    'TRUE END：ファーストキスは、みゆ',
    NULL,
    '主人公「待って！」

みゆ「ん？」

主人公「俺……みゆが好きだ」

みゆ「……私も好き」

みゆがそっと近づき――

チュッ。

主人公（やった……）

主人公（俺のファーストキスは、みゆだ！）

そして、あの日。なぜか――ばあちゃんは元気だった。

TRUE END

「ファーストキスは、みゆ」',
    'images/background/クライマックスキス.jpg',
    'images/characters/miyu.png',
    'ending_true',
    'narration',
    NULL
);

-- シーン12：BAD END

INSERT INTO scenes
(
    id,
    title,
    speaker,
    content_text,
    background_image,
    character_image,
    scene_type,
    display_type,
    next_scene_id
)
VALUES
(
    12,
    'BAD END：ファーストキスは、おばあちゃん',
    NULL,
    'みゆに想いを伝えられないまま、あの日を迎えた。

ドサッ！

主人公「ばあちゃん！」

主人公は必死に救命処置をする。そして――人工呼吸。

ばあちゃん「……ん」

主人公「よかった……！」

主人公「…………」

主人公「待て」

主人公「またファーストキス、おばあちゃんじゃん！！」

BAD END

「ファーストキスは、おばあちゃん」',
    'images/background/おばあちゃんち.png',
    'images/characters/おばあちゃん.png',
    'ending_bad',
    'narration',
    NULL
);
