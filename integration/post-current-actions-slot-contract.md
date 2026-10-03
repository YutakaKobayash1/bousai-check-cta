# post-current-actions DOM Contract

Status: v1.4.1-rc1 weather-insertion contract  
Owner of CTA consumer behavior: DEV-15  
Owner of root-level slot position: latest-disaster-info layout stream

## Goal

Keep one stable root-level location immediately after the complete
「現在発表されている警報・災害情報」 section while allowing independent
consumers to compose themselves without moving one another.

Required conceptual structure when weather is present:

```html
<div class="bousai-official-info">
  ...
  <section>現在発表されている警報・災害情報 ... all current items ...</section>
  <div class="bousai-post-current-actions" data-bousai-slot="post-current-actions">
    <!-- SNS share: owns only its own node -->
    <!-- weather forecast: owns only its own node -->
    <!-- disaster_info_inline CTA: owns only its own node and stays at slot tail -->
  </div>
  <!-- prefecture primary recommendation OR national basic guide -->
  ...
</div>
```

## Ownership rules

- Layout owner owns only the root-level slot position.
- SNS owns only `#bousai-disaster-share`.
- Weather owns only its own forecast node.
- CTA plugin owns only `[data-bcc-surface="disaster_info_inline"]`.
- No consumer moves the current-information section.
- No consumer moves another consumer's node to manufacture the final order.
- The slot itself moves together with the current-information section inside the existing layout reorder.

## Layout owner behavior

The existing SEO P0/root layout contract remains unchanged:

1. Find `current`.
2. Ensure exactly one direct-child slot:
   `:scope > [data-bousai-slot="post-current-actions"]`.
3. Move `current` to its intended root-level position.
4. Immediately move the slot after `current`.
5. Continue existing primary/basic/news ordering using the slot as the anchor.

If `current` is absent, do not create the slot.

The slot remains present even when the current-warning count is 0.

## Consumer behavior

### SNS

- If the slot exists, mount only the SNS block in the slot.
- If the slot does not exist, preserve the existing legacy fallback.
- Do not move the slot, current section, CTA, or weather node.

### Weather

- Mount only the weather node after SNS according to the weather component's own contract.
- Do not move the slot, current section, SNS, or CTA.

### CTA

- Render an inert `<template>` in the footer.
- Wait for the stable slot.
- Append only the CTA node to the slot tail.
- Observe only the slot's direct child list so a later SNS/weather mount cannot leave the CTA ahead of them.
- When correction is needed, move only the CTA itself with `slot.appendChild(cta)`.
- Never inspect or move SNS/weather nodes.
- Never inspect/reorder current-section children or root-level page order.
- Do not use placement timers.

## Acceptance invariant

With SNS + weather + CTA:
`current full content -> slot[SNS, weather, CTA] -> downstream content`

With SNS + CTA and no weather:
`current full content -> slot[SNS, CTA] -> downstream content`

With CTA disabled:
the CTA consumer makes no placement changes; other consumers retain their own order.

The CTA contract is intentionally agnostic to how SNS and weather establish their
relative order. It guarantees only that CTA remains after the other slot consumers
without moving those consumers.
