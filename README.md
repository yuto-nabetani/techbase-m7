# Tech-BASE Mission 7

## 1. プロジェクト概要

大学を舞台とした恋愛シミュレーションゲーム  
**「ファーストキスおばあちゃん」**

プレイヤーはストーリーを読みながら選択肢を選び、
選択によって好感度が変化する。

最終的な好感度によってエンディングが分岐する。

---

## 2. 必要機能

以下の機能を実装する。

- 新規登録
- ログイン
- ログアウト
- 管理者ユーザー
- 管理者専用ページ
- ゲーム開始
- シナリオ表示
- 選択肢表示
- 好感度の増減
- エンディング分岐
- 再プレイ

---

## 3. ファイル構成

```text
techbase-m7/
│
├─ index.php              # トップページ
├─ register.php           # 新規登録
├─ login.php              # ログイン
├─ logout.php             # ログアウト
├─ game.php               # ゲーム本体
├─ admin.php              # 管理者ページ
│
├─ config/
│  └─ db.php              # DB接続設定
│
├─ database/
│  ├─ schema.sql          # テーブル作成用SQL
│  └─ seed.sql            # シナリオ・選択肢の初期データ
│
├─ css/
│  └─ style.css           # デザイン
│
├─ js/
│  └─ game.js             # ゲーム画面用JavaScript
│
└─ images/                # 背景・キャラクター画像
```

### ファイル作成ルール

既存ファイルと同じ役割のファイルを勝手に追加しない。

新しいファイルが必要になった場合は、チーム内で共有してから追加する。

例：

- `signin.php` を勝手に追加せず `login.php` を使用する
- `game2.php` などを勝手に追加しない
- 新規登録ファイル名は `register.php` に統一する

---

# 4. DB仕様

使用するテーブルは以下の3つ。

```text
users
scenes
choices
```

ゲーム中の好感度や現在のシーンはDBに保存せず、
PHPのSessionで管理する。

---

## users

ユーザー登録・ログイン・管理者判定に使用する。

| カラム名 | 型 | 用途 |
|---|---|---|
| id | INT | ユーザーID・主キー・自動採番 |
| username | VARCHAR(50) | ユーザー名・重複不可 |
| password_hash | VARCHAR(255) | ハッシュ化したパスワード |
| role | VARCHAR(20) | `user` または `admin` |
| created_at | TIMESTAMP | 登録日時 |

通常の新規登録では、

```text
role = user
```

とする。

管理者のみ、

```text
role = admin
```

とする。

パスワードはそのまま保存せず、

```php
password_hash()
```

を使用する。

---

## scenes

ゲーム内で表示する文章・画像・シーン進行を管理する。

| カラム名 | 型 | 用途 |
|---|---|---|
| id | INT | シーンID・主キー・自動採番 |
| title | VARCHAR(100) | シーン名 |
| speaker | VARCHAR(50) | 話者。地の文の場合はNULL |
| content_text | TEXT | 表示する文章 |
| background_image | VARCHAR(255) | 背景画像のパス |
| character_image | VARCHAR(255) | キャラクター画像のパス |
| scene_type | VARCHAR(20) | シーンの処理方法 |
| display_type | VARCHAR(20) | 文章の表示方法 |
| next_scene_id | INT | 次のシーンID。終端等ではNULL |

### 1レコードの単位

原則として、

**1レコード = 画面に一度に表示する1つの文章**

とする。

例：

```text
speaker = 主人公
content_text = ばあちゃん、おはよう
```

次のレコード：

```text
speaker = ばあちゃん
content_text = おはよ……
```

のようにする。

---

## scene_type

以下の値に統一する。

```text
normal
choice
judge
ending_true
ending_bad
```

### normal

通常のシーン。

「次へ」を押すと、

```text
scenes.next_scene_id
```

へ進む。

### choice

選択肢を表示するシーン。

`choices` テーブルから、
現在の `scene_id` に対応する選択肢を取得する。

### judge

現在の好感度を判定し、
TRUE END または BAD END に分岐する。

### ending_true

TRUE END用のシーン。

### ending_bad

BAD END用のシーン。

---

## display_type

以下の値に統一する。

```text
dialogue
narration
```

### dialogue

キャラクターの会話。

画面下部に会話ウィンドウを表示する。

例：

```text
主人公
「ばあちゃん、おはよう」
```

### narration

プロローグ・地の文など。

背景を少し暗くし、
文章を画面中央付近に表示する。

例：

```text
幼い頃、両親を亡くした。
```

---

## choices

選択肢の内容・好感度変化・次のシーンを管理する。

| カラム名 | 型 | 用途 |
|---|---|---|
| id | INT | 選択肢ID・主キー・自動採番 |
| scene_id | INT | 選択肢を表示するシーン |
| choice_text | VARCHAR(255) | 選択肢の文章 |
| love_change | INT | 選択した際の好感度変化 |
| next_scene_id | INT | 選択後に進むシーン |
| display_order | INT | 選択肢の表示順 |

例：

```text
scene_id = 20

A「一緒に入る？」
love_change = 40

B「この傘使っていいよ」
love_change = 25

C「雨が止むまで待てば？」
love_change = 0
```

`display_order` を使用して、

```text
A
B
C
```

の順番を維持する。

---

# 5. Session仕様

Session名は以下に統一する。

別の名前を勝手に使用しない。

## ログイン関連

```php
$_SESSION['user_id'];
$_SESSION['username'];
$_SESSION['role'];
```

用途：

```text
user_id
→ ログインユーザーID

username
→ ログインユーザー名

role
→ user / admin
```

## ゲーム関連

```php
$_SESSION['scene_id'];
$_SESSION['love'];
```

用途：

```text
scene_id
→ 現在表示しているシーン

love
→ 現在の好感度
```

Sessionを使用するPHPファイルでは、

```php
session_start();
```

を実行する。

---

# 6. DB接続

DB接続は、

```text
config/db.php
```

に統一する。

各PHPファイルから必要に応じて、

```php
require_once __DIR__ . '/config/db.php';
```

で読み込む。

DB接続用変数は、

```php
$pdo
```

に統一する。

---

# 7. 新規登録

担当ファイル：

```text
register.php
```

処理の流れ：

```text
username入力
↓
password入力
↓
入力チェック
↓
同じusernameが存在しないか確認
↓
password_hash()でパスワードをハッシュ化
↓
usersへINSERT
↓
登録完了
↓
login.phpへ
```

通常登録されたユーザーのroleは、

```text
user
```

とする。

---

# 8. ログイン

担当ファイル：

```text
login.php
```

処理の流れ：

```text
username入力
↓
password入力
↓
usersからusernameを検索
↓
password_verify()で照合
↓
成功
↓
Sessionへ保存
```

保存するSession：

```php
$_SESSION['user_id'];
$_SESSION['username'];
$_SESSION['role'];
```

ログイン失敗時はエラーメッセージを表示する。

---

# 9. ログアウト

担当ファイル：

```text
logout.php
```

Sessionを終了し、

```text
index.php
```

へ戻す。

---

# 10. ゲーム開始

ゲーム開始時に、

```php
$_SESSION['love'] = 0;
$_SESSION['scene_id'] = 1;
```

とする。

その後、

```text
game.php
```

でゲームを進行する。

---

# 11. ゲーム進行

ゲーム本体は原則として、

```text
game.php
```

で管理する。

シーンごとに、

```text
scene1.php
scene2.php
scene3.php
```

などのPHPファイルは作らない。

---

## 通常シーン

現在の、

```php
$_SESSION['scene_id']
```

を使用して `scenes` からシーンを取得する。

```text
シーン取得
↓
文章・背景・キャラクター表示
↓
「次へ」
↓
next_scene_idへ移動
```

---

## 選択肢シーン

`scene_type = choice` の場合、

現在のシーンIDに対応するデータを
`choices` から取得する。

```text
選択肢表示
↓
ユーザーが選択
↓
love_changeを好感度へ加算
↓
next_scene_idへ移動
```

好感度更新：

```php
$_SESSION['love'] += $loveChange;
```

---

# 12. 好感度

好感度はDBに保存せず、

```php
$_SESSION['love']
```

で管理する。

初期値：

```text
0
```

選択肢によって加算する。

---

# 13. エンディング判定

判定基準値はコードの複数箇所に直接書かず、
1か所で管理する。

例：

```php
$trueEndThreshold = 80;
```

判定：

```php
if ($_SESSION['love'] >= $trueEndThreshold) {
    // TRUE END
} else {
    // BAD END
}
```

数値はゲームバランスを見ながら後から変更可能とする。

---

# 14. 再プレイ

ゲームを最初から遊ぶ場合は、

```php
$_SESSION['love'] = 0;
$_SESSION['scene_id'] = 1;
```

に戻す。

---

# 15. 管理者ページ

担当ファイル：

```text
admin.php
```

管理者ページには、

```text
role = admin
```

のユーザーだけアクセス可能とする。

例：

```php
if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    exit('アクセス権限がありません。');
}
```

最低限、以下を表示できるようにする。

- 登録ユーザー一覧
- シナリオ一覧

余裕があれば、

- シナリオ追加
- シナリオ編集
- シナリオ削除

を追加する。

---

# 16. 初期データ

テーブル構造は、

```text
database/schema.sql
```

で管理する。

シナリオ・選択肢の初期データは、

```text
database/seed.sql
```

で管理する。

新しい開発環境では、

```text
1. schema.sql を実行
2. seed.sql を実行
```

することで同じゲームDBを再現できる状態にする。

---

# 17. Git運用

原則として `main` に直接コードを書かない。

作業ごとにブランチを作成する。

例：

```text
feature/auth
feature/game
feature/ui
feature/admin
```

基本の流れ：

```text
mainからブランチ作成
↓
作業
↓
git add
↓
git commit
↓
git push
↓
GitHubでPull Request
↓
Files changedで差分確認
↓
Merge
↓
mainをPull
```

Pull Requestで変更内容を確認してから
`main` にマージする。

---

# 18. 実装時の共通ルール

以下はチーム内で統一する。

- DBのテーブル名を勝手に変更しない
- DBのカラム名を勝手に変更しない
- Session名を勝手に変更しない
- `scene_type` の値を勝手に追加・変更しない
- `display_type` の値を勝手に追加・変更しない
- 同じ役割のPHPファイルを勝手に増やさない
- 大きな仕様変更をする場合は事前に共有する
- mainへ直接変更を加えず、原則ブランチ＋PRを使用する

---

# 19. セキュリティ

DBパスワードなどの認証情報は、
GitHubへ公開しない。

実際のDB接続情報を含むファイルを共有する場合は、
GitHub以外の安全な方法を使用する。

ユーザーのパスワードは必ず、

```php
password_hash()
```

で保存し、

```php
password_verify()
```

で確認する。

---

# 20. 担当分担

現時点では以下を基本とする。

```text
① DB・全体統合
- DB構築
- schema.sql
- seed.sql
- DB接続確認
- 全体仕様管理

② 認証
- register.php
- login.php
- logout.php

③ ゲームロジック
- game.php
- シーン取得
- シーン遷移
- 選択肢処理
- 好感度処理

④ UI
- style.css
- game.js
- dialogue表示
- narration表示
- 背景・キャラクター表示

⑤ 管理者
- admin.php
- 管理者判定
- ユーザー一覧
- シナリオ一覧

⑥ エンディング関連
- 好感度判定
- TRUE END
- BAD END
- 再プレイ
```

担当間で同じファイルを編集する場合は、
事前に共有して競合を避ける。
