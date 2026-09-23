# DISASTER-CTA-01 — CTA共通基盤 / Phase 1 `disaster_info_inline`

## 目的
全国 `/disaster-info/` と47都道府県 `/disaster-info/{prefecture}/` の計48ページで、重要な災害情報の確認後に `/check/` へ自然に遷移できる本文内CTAを提供する。

## Phase 1 LOCK
- 公開スイッチ: 初期OFF。管理者は `?bousai_cta_preview=1` で確認可能。公開ONは人間承認後のみ
- surface: `disaster_info_inline`
- 対象: 全国＋47都道府県の48ページ
- 位置: 「現在発表されている警報・災害情報」セクション**終了直後**
- 既存の後続ブロック（「この状況で確認しておきたいこと」「都道府県の公式防災情報」「最近発表された…」等）は並び替えない
- 警報0件でも表示
- 遷移先: `/check/`
- 同一タブ（`target="_blank"` を付けない）
- 災害種別連動なし
- 追随・固定表示なし
- 外部画像アセットなし。承認済みモック相当の家＋盾は軽量なinline SVG UIアイコンで表現
- 時間表記なし

## コピー
- Title: `災害情報を確認したら、自宅の備えもチェック`
- Description: `家族構成や住まいに合わせて、水・食料・トイレ・停電対策・避難など、必要な備えを確認できます。`
- Button: `わが家の防災チェックを始める`
- Note: `チェックに名前・住所などの入力は必要ありません。`

## デザイン Golden Reference
2026-09-23に承認されたPC/SP比較モック。
- 淡い水色〜ミントの背景
- 緑の主ボタン
- PC: 左アイコン＋右コンテンツの横長カード
- SP: 1カラム、アイコン→見出し→説明→全幅ボタン→補足
- 警報色（赤・オレンジ）をCTAの主色にしない

## 所有境界
既存 `bousai-check-cta` をCTA共通基盤として専用repo化し、DEV-15が単独write ownerとなる。

Phase 1で変更しないもの:
- DEV-03 最新災害情報 renderer / Code Snippets
- DEV-05 Content Bridge
- DEV-07 Social Growth / GA4 Journey
- Bousai Readiness Check本体
- 既存の災害情報本文・公的情報ロジック

## 実装方式
`do_shortcode_tag` の `bousai_official_info` 出力に対し、既存の災害情報post-processが完了した後のlate priorityで、見出し `現在発表されている警報・災害情報` を含む `<section>` の対応する閉じタグ直後へCTAを挿入する。

- 同じHTMLに `data-bcc-surface="disaster_info_inline"` が既にあれば再挿入しない
- 見出し/section構造を解決できなければfail closed（CTAを出さず災害情報HTMLをそのまま返す）
- 下流コンテンツは移動・書換しない

## GA4
CTA固有のperformanceのみ追加計測する。

### impression
- event: `bousai_check_cta_impression`
- `location=disaster_info_inline`
- `link_url=/check/` の実URL
- CTAの50%以上がviewportに入った時点
- 1 pageviewにつき1回

### click
既存 `bousai-check-cta` の `.bcc-track` / `data-bcc-location` クリック計測を再利用する想定。
- event: `bousai_check_cta_click`
- `location=disaster_info_inline`
- 新しい重複click handlerは追加しない

### downstream
`/check/` 到達後の以下は既存Journeyをそのまま利用し、CTA pluginでは再実装しない。
- `bousai_check_start`
- `bousai_check_result_view`
- `affiliate_click`

同一タブ＋同一origin遷移により既存の `check_entry_route=disaster_info` / `check_entry_path` attributionを維持する。

## 将来拡張（Phase 1では実装しない）
共通surface registryを想定する。
- `article_bottom`
- `related_inline`
- `manual`
- 将来 `[bousai_check_cta type="related_inline"]` 等のショートコード生成

## Phase 1 Acceptance
1. 全国ページでcurrentセクション直後に1件だけ表示
2. 都道府県ページでcurrentセクション直後に1件だけ表示
3. 警報0件ページでも表示
4. 既存の後続セクション順序を変更しない
5. `/check/` 同一タブ遷移
6. PC/SPで承認モック相当のUI
7. 既存PC追随/SP固定/TOPカード/記事末CTAに回帰なし
8. CTA 50% visibilityでimpression 1回
9. CTA clickが `location=disaster_info_inline` で1回
10. `/check/` のstart/result/affiliate既存Journeyに回帰なし
11. 災害情報renderer/Content Bridge/Social Growth/Readinessにコード変更なし
12. merge/deploy/production変更はユーザー明示承認後のみ

## 実装前残件
- production同版 `bousai-check-cta` v1.3.6 sourceを専用repoへbaseline import
- baseline SHA記録
- Control Issue作成
- main直書き禁止、feature branch→PR→CI