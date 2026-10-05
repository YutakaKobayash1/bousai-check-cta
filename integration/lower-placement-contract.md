# Latest disaster information: lower placement (v1.4.2-rc1)

Owner: DEV-15 / 災害情報#2. Repository: YutakaKobayash1/bousai-check-cta.
The user explicitly transferred ownership on 2026-10-05 and archived the old CTA chat.
Other repositories remain read only.

The user reselected the lower placement on 2026-10-05:

この状況で確認しておきたいこと → 防災チェックCTA → SNS → このページについて

Weather remains in the existing post-current-actions slot near the current
information. Nationwide weather remains excluded. This supersedes the previous
slot[SNS, weather, CTA] goal for this change; the old integration files describe
the historical baseline, not the new acceptance target.

## Component boundaries

- Layout owner keeps its stable slot and foreign root sections unchanged.
- CTA renders the same inert footer template and moves only its own node.
- CTA uses a direct-root primary recommendation as its first anchor. Without
  primary, it mounts before direct-root SNS or the direct-root About block.
  Without any anchor it waits; it does not fabricate content.
- SNS19 candidate moves only SNS immediately after a direct-root CTA, otherwise
  before About. It reattaches its direct-root observer on pageshow if root changed.
- Weather22 candidate changes both payload and loading placement branches to
  depend only on its original slot. It does not require upper SNS or CTA.
- No current section, slot, foreign node, canonical, Priority View, Content
  Bridge, disaster retrieval, cache, warning classification or GA4 Journey change.

## Analytics and asynchronous behavior

CTA root childList observation remains active for late primary mount/removal,
CTA deletion/remount and layout-owner changes. Broad subtree observation is
only an initial input wait, then disconnects. Position checks prevent observer
loops. pageshow and repeated script evaluation keep a single CTA.
Impression is sent at 50% visibility, once per document even across CTA remounts.
The unchanged core bcc.js owns click tracking; the inline script adds no click
handler. SPA root replacement is recovered on pageshow.

## Validation and release boundary

Run tests/test-lower-dom.js with native jsdom MutationObservers.
The separate frozen public-page harness replays existing production scripts for
nationwide/Tokyo/Osaka/Saitama, replacing only the three candidate components.
Loading-weather tests are fixtures; frozen weather payloads are historical data.
Neither is live warning/forecast accuracy verification.

No staging for this iteration. TinyFish is prohibited.
PC/SP browser rendering must be inspected before production approval.
PR and CI are authorized; merge requires separate explicit approval.
Production plugin/snippet installation is performed by the user.

Coordinated installation after approval: back up all three current components,
then replace weather22, SNS19, and the CTA plugin in that order. Retain enabled
states and existing settings. Only after all three are installed is lower
placement considered complete. Rollback all three, CTA then SNS then weather.
Any source drift from the pre-install recorded hashes requires a fresh comparison.
