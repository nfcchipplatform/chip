# ASP アフィリエイトアカウント設定ガイド

## FANZA アカウントは必要？

| 用途 | 必要？ | 説明 |
|------|--------|------|
| **Phase 1〜2 の開発・テスト** | 不要 | サンプル CSV / JSON で動作確認可能 |
| **本番運用（収益化）** | **必要** | DMM アフィリエイト（FANZA）の登録が必須 |
| **一般の FANZA 視聴アカウント** | 不要 | 購入用アカウント ≠ アフィリエイトアカウント |

開発段階ではアカウントなしで進められます。本番公開してアフィリエイト収益を得る段階で、ご自身での ASP 登録が必要です。

> **重要**: アフィリエイトアカウントは本人確認・規約同意・審査が必要なため、代理での作成はできません。以下の手順をご自身で実施してください。

---

## DMM アフィリエイト（FANZA）登録手順

1. **DMM アフィリエイトに登録**
   - URL: https://affiliate.dmm.com/
   - メールアドレス・サイト情報・振込口座などを入力
   - 審査完了後、`af_id`（アフィリエイト ID）が発行される

2. **FANZA 商品 CSV / API の取得**
   - DMM アフィリエイト管理画面 → 商品データダウンロード
   - CSV を `backend/data/raw/` に配置
   - CSV 内のアフィリエイト URL に `af_id` が自動付与される

3. **パイプライン実行**
   ```bash
   cd affiliate-media/backend
   python scripts/run_pipeline.py \
     --source fanza \
     --input data/raw/fanza_products.csv
   ```

4. **フロントエンドへデータ同期**
   ```bash
   cd affiliate-media/frontend
   npm run sync-data
   npm run build
   ```

---

## DUGA 登録（任意）

DUGA も利用する場合:

1. DUGA アフィリエイトに登録: https://duga.jp/affiliate/
2. 商品フィード CSV を `backend/data/raw/` に配置
3. `--source duga` でパイプライン実行

---

## 必要な API キー一覧（本番運用時）

| サービス | 用途 | 取得先 |
|---------|------|--------|
| DMM アフィリエイト | 商品 CSV・アフィリエイトリンク | https://affiliate.dmm.com/ |
| OpenAI API | 紹介文自動生成 | https://platform.openai.com/ |
| X API（Phase 3） | 自動投稿 | https://developer.x.com/ |
| Discord Webhook（Phase 3） | エラー通知 | Discord サーバー設定 |

---

## よくある質問

**Q. FANZA の視聴アカウントを作ればアフィリエイトリンクが使えますか？**  
A. いいえ。別途 DMM アフィリエイトへの登録が必要です。

**Q. 個人でも登録できますか？**  
A. はい。個人・法人どちらも登録可能です（規約は ASP 側を確認してください）。

**Q. サイト未公開の状態で審査は通りますか？**  
A. 審査基準は ASP によります。テストサイト URL や運用予定の説明が必要な場合があります。
