# post-current-actions DOM Contract

Status: RC5 integration contract  
Owner during DISASTER-CTA-01 integration: DEV-15 (temporary narrow ownership)  
Normal owner after acceptance: latest-disaster-info layout stream (DEV-03)

## Goal

Provide one stable root-level location immediately after the complete
「現在発表されている警報・災害情報」 section.

Required final structure:

```html
<div class="bousai-official-info">
  ...
  <section>現在発表されている警報・災害情報 ... all current items ...</section>
  <div class="bousai-post-current-actions" data-bousai-slot="post-current-actions">
    <!-- 1. disaster_info_inline CTA when enabled/previewed -->
    <!-- 2. SNS share block -->
  </div>
  <!-- prefecture primary recommendation OR national basic guide -->
  ...
</div>
```

## Ownership rules

- Layout owner owns only the root-level slot position.
- CTA plugin owns only `[data-bcc-surface="disaster_info_inline"]`.
- SNS snippet owns only `#bousai-disaster-share`.
- Consumers must not move the current-information section.
- Consumers must not create competing root-level MutationObservers to enforce page order.
- The slot itself moves together with the current-information section inside the existing layout reorder.

## Layout owner behavior

During the existing reorder:

1. Find `current`.
2. Ensure exactly one direct-child slot:
   `:scope > [data-bousai-slot="post-current-actions"]`.
3. Move `current` to its intended position.
4. Immediately move the slot after `current`.
5. Continue existing primary/basic/news ordering using the slot as the anchor.

If `current` is absent, do not create the slot.

The slot is present even when the current-warning count is 0, because the CTA must
remain available on zero-warning pages.

## Consumer behavior

### CTA
- Renders an inert `<template>` in the footer.
- Waits for the stable slot.
- Mounts its own CTA as `slot.firstChild`.
- Disconnects the slot-wait observer after mount.
- Does not inspect/reorder current-section children.

### SNS
- If the slot exists, append SNS block to the slot.
- If the slot does not exist, fallback to v1.4.1 behavior (current.nextSibling).
- Does not move the slot or current section.

## Acceptance invariant

With CTA enabled:
`current full content -> CTA -> SNS -> downstream content`

With CTA disabled:
`current full content -> SNS -> downstream content`

No race-condition timers are allowed to decide the final root-level order.
