/**
 * Integration patch for the current latest-disaster-info layout owner
 * (production source observed via bousai-disaster-info-seo-p0-js-after).
 *
 * This is intentionally a focused patch fragment, not a replacement plugin.
 * Fresh-read the live plugin source before applying.
 */

function ensurePostCurrentActionsSlot(root){
    var existing = root.querySelector(':scope > [data-bousai-slot="post-current-actions"]');

    if(existing){
        return existing;
    }

    var slot = document.createElement('div');
    slot.className = 'bousai-post-current-actions';
    slot.setAttribute('data-bousai-slot', 'post-current-actions');

    /*
     * Do not choose a visual position here. The existing reorder() below owns
     * placement. An empty slot has no content and no visual effect.
     */
    root.appendChild(slot);
    return slot;
}

/*
 * In reorder(), after:
 *   var current = findSection(root, '現在発表されている警報・災害情報');
 *
 * add:
 */
var postCurrentActions = current ? ensurePostCurrentActionsSlot(root) : null;

/*
 * Replace the current placement block:
 *
 * if(current){
 *     ...
 *     anchor = current;
 * }
 *
 * with:
 */
if(current){
    if(anchor){
        anchor = moveAfter(current, anchor);
    }else{
        root.insertBefore(current, root.firstChild);
        anchor = current;
    }

    /*
     * Stable contract: current and its post-current actions slot are a pair.
     * All existing primary/basic/news ordering continues after this slot.
     */
    if(postCurrentActions){
        anchor = moveAfter(postCurrentActions, anchor);
    }
}

/*
 * No other reorder() behavior changes.
 * Existing primary/basic/news/notice ordering continues to use 'anchor',
 * which now points to the stable slot when current exists.
 */
