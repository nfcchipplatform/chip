# ICカード（NFC）書込み運用手順書

## 概要

ICチップ（NFCタグ）に PONNU プロフィールURLを書き込む運用手順です。

---

## 前提条件

| 項目 | 内容 |
|---|---|
| ICチップ規格 | NTAG213 / NTAG215 / NTAG216 推奨 |
| 書込みアプリ | NFC Tools（iOS/Android 無料） |
| 管理画面 | https://ic.ponnu.net/admin/nfc.php |
| 書込みURL形式 | `https://ic.ponnu.net/n/?t={64文字hex token}` |

---

## 手順

### 1. ICチップを発行（管理画面）

1. https://ic.ponnu.net/admin/ にSUPER_ADMINでログイン
2. 「NFC」タブをクリック
3. 「発行枚数」に必要枚数を入力（1〜100）
4. 「メモ」に管理用メモ（例: 「2026年5月ロット」）を入力
5. 「発行する」をクリック
6. 発行されたURL一覧が表示される

### 2. URLを確認

発行後に表示されるURL形式：
```
https://ic.ponnu.net/n/?t=abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890
```

- CSVダウンロード → Excel等で管理
- A4印刷 → QRコード付き印刷物

### 3. ICチップに書込み（NFC Tools アプリ）

#### iOS の場合
1. App Store から「NFC Tools」をインストール
2. アプリを起動 → 「Write」タブ
3. 「Add a record」→ 「URL/URI」を選択
4. 管理画面で発行したURL（`https://ic.ponnu.net/n/?t=...`）を貼り付け
5. 「Write」をタップ
6. ICチップをiPhone上部に近づける
7. 「Write complete」と表示されれば成功

#### Android の場合
1. Google Play から「NFC Tools」をインストール
2. アプリを起動 → 「Write」タブ
3. 「Add a record」→ 「URL」を選択
4. 管理画面で発行したURL（`https://ic.ponnu.net/n/?t=...`）を貼り付け
5. 「Write / XX Bytes」をタップ
6. ICチップをスマホの背面中央に近づける
7. 「Write complete」と表示されれば成功

### 4. 動作確認

1. スマホのNFC機能がONになっていることを確認
2. ICチップにスマホをかざす
3. 以下のいずれかが表示される：
   - **未紐付き**: 「このカードをあなたに紐付けますか？」画面
   - **紐付き済み**: 紐付けユーザーのプロフィール画面
   - **ダイレクトリンク設定済み**: 3秒カウントダウン → 外部URLへ遷移

---

## トラブルシューティング

| 症状 | 対処 |
|---|---|
| 書込みが「Fail」になる | チップが書込み保護（Lock）されていないか確認。新品のNTAGタグを使用 |
| スマホで反応しない | NFC設定がOFFになっている。設定 → NFC → ON |
| iPhone で反応しない | iPhone 7以降が必要。ロック画面でもタッチすれば反応する |
| 「無効なカード」表示 | 管理画面でチップが「revoked」（無効化）されていないか確認 |
| URL が404 | トークンが間違っている。CSVで正しいURLを再確認 |

---

## 運用上の注意

- **1枚のICチップ = 1つのトークン = 1人のユーザー** に紐付く
- 紐付け解除は管理画面（`/admin/nfc.php`）から可能
- チップを紛失した場合は管理画面から「無効化」→ 新しいチップを発行
- 書込み後のICチップは物理的に破壊しない限り半永久的に動作する（電池不要）
- ICチップの書込み内容は上書き可能（別URLに書き換え可能）

---

## 大量発行の効率的な方法

1. 管理画面で100枚一括発行
2. CSVダウンロード
3. NFC Tools Pro（有料版）のバッチ書込み機能を使用
4. または、PC接続のNFCリーダー（ACR122U等）+ nfcpy でスクリプト化

---

## 参考リンク

- NFC Tools: https://www.wakdev.com/en/apps/nfc-tools.html
- NTAG仕様: NXP Semiconductors NTAG21x datasheet
- PONNU管理画面: https://ic.ponnu.net/admin/nfc.php
