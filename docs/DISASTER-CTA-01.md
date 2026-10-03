# DISASTER-CTA-01 — CTA共通基盤 / Phase 1 `disaster_info_inline`

## 目的
全国 `/disaster-info/` と47都道府県 `/disaster-info/{prefecture}/` の計48ページで、重要な災害情報の確認後に `/check/` へ自然に遷移できる本文内CTAを提供する。

## Phase 1 LOCK
- 公開スイッチ: 初期OFF。管理者は `?bousai_cta_preview=1` で確認可能。公開ONは人間承認後のみ
- surface: `disaster_info_inline`
- 対象: 全国＋47都道府県の48ページ
- 位置: current section直後の root-level stable slot `[data-bousai-slot="post-current-actions"]` の末尾側。CTAは自分自身だけをslot末尾へ維持し、SNS・天気・current sectionは移動しない
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
latest-disaster-info layout ownerがcurrent section直後に提供する
`[data-bousai-slot="post-current-actions"]` を唯一の配置契約とする。

- CTAはfooterのinert `<template>`から自分自身だけをslotへmountする
- CTAは `slot.appendChild(cta)` により自分自身だけをslot末尾へ置く
- SNSや天気が後からmountされた場合に備え、CTAはslot直下のchildListだけを監視する
- 修正が必要な場合もCTA自身だけを末尾へ移し、SNS・天気nodeには触れない
- current sectionやroot-level page orderをCTA側から変更しない
- placement timerは使用しない
- slotが解決できなければfail closedで、災害情報本体へ介入しない

期待順序:
`current full content -> post-current-actions[SNS, weather, CTA] -> downstream`

天気が未配置の場合:
`current full content -> post-current-actions[SNS, CTA] -> downstream`

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
1. 全国ページでroot-level stable slotがcurrent section直後に維持される
2. 都道府県ページでも同じslot contractが成立する
3. 天気ありではslot内が `SNS → weather → CTA` となる
4. 天気なしではslot内が `SNS → CTA` となる
5. CTAがSNS・天気・current section・後続sectionを移動しない
6. 警報0件ページでもCTA表示契約が成立する
7. `/check/` 同一タブ遷移
8. PC/SPで既存承認UIを維持
9. 既存PC追随/SP固定/TOPカード/記事末CTAに回帰なし
10. CTA 50% visibilityでimpression 1回
11. CTA clickが `location=disaster_info_inline` で1回
12. `/check/` のstart/result/affiliate既存Journeyに回帰なし
13. Priority View / SEO P0 root-level placement / Content Bridge / Social Growth / Readiness / 災害情報取得本体にコード変更なし
14. merge/deploy/production変更はユーザー明示承認後のみ

## 実装前残件
- production同版 `bousai-check-cta` v1.3.6 sourceを専用repoへbaseline import
- baseline SHA記録
- Control Issue作成
- main直書き禁止、feature branch→PR→CI

## RC4 placement guard
- Server-sideではCTAを現在情報sectionの閉じタグ直前にseedする。
- 本番のアコーディオン初期化がsection内の子要素を後段で移動するため、`bcc-disaster-info-inline.js` がCTAを常に当該sectionの最終子要素へ戻す。
- SNS共有は既存snippetの仕様どおり現在情報sectionの直後に置かれるため、最終表示順は `current full content -> CTA -> SNS share` になる。


## RC5 root architecture

RC2〜RC4のsection内挿入/placement guard方式は廃止する。

正式な配置契約:

1. latest-disaster-info layout owner が current section直後に
   `[data-bousai-slot="post-current-actions"]` を direct child として作成・配置する。
2. layout reorderは current と slot を一組として移動し、その後にprimary/basic/newsを並べる。
3. CTA pluginはfooter templateからCTAだけをslot先頭へmountする。
4. SNS共有v1.4.5はslot末尾へmountする。slotがなければ従来配置へfallback。
5. CTA/SNSはcurrent sectionやroot-level page orderを所有しない。

最終DOM invariant:
`current full content -> post-current-actions[CTA, SNS] -> downstream content`

CTA公開スイッチOFF時:
`current full content -> post-current-actions[SNS] -> downstream content`


## v1.4.1-rc1 weather insertion contract

RC5/v1.4.0の「CTAをslot先頭へ固定する」consumer contractは、本項でSUPERSEDED。

- layout owner: root-level stable slot位置だけを所有
- SNS: 自分自身だけをslotへ配置
- weather: 自分自身だけをSNSの後ろへ配置
- CTA: 自分自身だけをslot末尾へ配置・維持
- CTAは他componentのnodeを移動しない
- final target: `current -> SNS -> weather -> CTA -> downstream`

この変更ではCTAのコピー、UI、`/check/`、GA4 impression/click契約は変更しない。
