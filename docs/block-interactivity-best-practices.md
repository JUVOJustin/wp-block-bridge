# Block Interactivity Best Practices

How to add frontend interactivity to blocks that render across Gutenberg, Bricks, Elementor, and the REST API.

## The Problem

WordPress blocks can be rendered in multiple contexts:

| Context | Renderer | DOM Lifecycle |
|---|---|---|
| **Frontend** | `render_block()` | Static HTML, loaded once |
| **Gutenberg Editor** | `ServerSideRender` (React) | HTML replaced on every attribute change via REST API |
| **Bricks Builder** | `Block_Bridge::render_block()` | Builder-managed DOM with live preview |
| **Elementor** | `Block_Bridge::render_block()` | Builder-managed DOM with AJAX preview |
| **REST API** | `render_block()` | Headless / decoupled consumers |

Each context has different expectations for when and how JavaScript initializes interactive behavior (sliders, accordions, modals, etc.).

## Why the Interactivity API Is Not the Right Fit

The WordPress [Interactivity API](https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/) (`@wordpress/interactivity`) uses Preact under the hood to manage DOM state via directives like `data-wp-interactive`, `data-wp-on--click`, and `data-wp-context`.

This works well on the **frontend** where the HTML is rendered once and Preact hydrates it. But it breaks in every other context:

### Gutenberg Editor (`ServerSideRender`)

The `ServerSideRender` component uses **React** and the REST API. Every time block attributes change, React replaces the entire block HTML. This destroys Preact's event listeners and internal state.

As confirmed by the Interactivity API maintainer ([gutenberg#74523](https://github.com/WordPress/gutenberg/discussions/74523)):

> "The problem is that although you might manually enqueue the script modules, every time that block is re-rendered by React, the event listeners attached by Preact will break."
>
> "We would need to have a version of the `ServerSideRender` component that, instead of using React and the REST API to update the HTML, would use the Interactivity API itself and its router. But it is not trivial to implement."

### Page Builders (Bricks, Elementor)

Page builders render blocks outside of the Gutenberg context entirely. The Interactivity API's server-side directive processing (`wp_interactivity_process_directives()`) may not run, and its script modules (`viewScriptModule`) are not automatically enqueued.

### Script Module Limitations in the Editor

The Interactivity API requires `viewScriptModule` (ES modules loaded via `<script type="module">`). WordPress's Script Modules API has a critical limitation: **modules are not forwarded into the block editor's iframe**. The editor has built-in machinery to inject classic scripts (`wp_enqueue_script`) into its iframe, but this machinery does not cover `wp_enqueue_script_module()`.

This means even if you manually enqueue a script module via `enqueue_block_editor_assets`, it loads in the parent admin document while the `ServerSideRender` HTML lives inside the editor iframe -- different documents.

## Recommended Approach: Vanilla JS with `MutationObserver`

Use vanilla JavaScript with a `MutationObserver` pattern for blocks that need interactivity in all rendering contexts. This approach:

- Works on the frontend, in the editor, and in page builders
- Survives DOM replacement (React re-renders in `ServerSideRender`)
- Has no dependency on the Interactivity API or Preact
- Uses the `script` field in `block.json` which loads on both frontend and editor

### block.json Configuration

```json
{
  "script": "file:./view.js",
  "supports": {
    "html": false
  }
}
```

**Why `script` instead of `viewScript` or `viewScriptModule`:**

| Field | Loads on Frontend | Loads in Editor | Format |
|---|---|---|---|
| `viewScriptModule` | Yes | No | ES Module |
| `viewScript` | Yes | No | Classic |
| `script` | Yes | Yes | Classic |
| `scriptModule` | Yes | Yes | ES Module (not yet supported by `@wordpress/scripts` build system) |

`script` is the only field that reliably loads in both contexts with current tooling.

> **Note on modules:** `scriptModule` is the correct future-proof field (frontend + editor, ES module format), but as of early 2026, `@wordpress/scripts` does not include `scriptModule` in its entry point detection (`moduleFields` only contains `viewScriptModule` and `viewModule`). Once build tooling catches up, switching from `script` to `scriptModule` will be a single-line change in `block.json`.

### view.js Pattern

```js
import { register } from 'swiper/element/bundle'; // or any library

register();

/**
 * Initializes a single block instance.
 * Must be idempotent -- safe to call multiple times on the same element.
 */
function initBlock(wrapper) {
  if (wrapper.dataset.blockReady) {
    return;
  }

  // ... initialization logic (event listeners, library setup, etc.)

  wrapper.dataset.blockReady = 'true';
}

/**
 * Finds and initializes all uninitialized block instances.
 */
function initAllBlocks() {
  document
    .querySelectorAll('.my-block:not([data-block-ready])')
    .forEach(initBlock);
}

/**
 * Bootstraps initialization and watches for future DOM insertions.
 */
function setup() {
  initAllBlocks();
  const observer = new MutationObserver(initAllBlocks);
  observer.observe(document.body, { childList: true, subtree: true });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', setup);
} else {
  setup();
}
```

Key principles:

1. **Idempotent initialization** -- Use a `data-block-ready` guard so re-running on the same element is a no-op
2. **MutationObserver** -- Watches for DOM changes to catch `ServerSideRender` injections, page builder previews, and lazy-loaded content
3. **No framework dependency** -- Pure DOM APIs work in every context
4. **Self-contained** -- All state lives in the DOM or in closures scoped to the block wrapper

### render.php Pattern

Use plain `data-*` attributes instead of Interactivity API directives:

```php
<?php
// Works in Gutenberg, Bricks, and Elementor via Block_Bridge.
$context = Block_Bridge::context( $block ?? null );

ob_start();
?>
<div
  class="my-block"
  data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>"
>
  <!-- Block markup. No data-wp-* directives. -->
</div>
<?php
echo Block_Bridge::render( (string) ob_get_clean(), $block ?? null );
```

- Pass configuration via `data-*` attributes or derive it from DOM structure
- No `data-wp-interactive`, `data-wp-context`, `data-wp-on--*`, or `data-wp-class--*`
- `Block_Bridge::render()` handles wrapper processing for page builders

## When the Interactivity API IS Appropriate

The Interactivity API remains the right choice when:

- The block is **frontend-only** (no editor preview needed, no page builder support)
- The block uses **client-side navigation** (`@wordpress/interactivity-router`)
- The block needs to **share state** with other Interactivity API blocks on the page
- You are building a **Full Site Editing** theme where all rendering is Gutenberg-native

## Summary

| Requirement | Interactivity API | Vanilla JS + MutationObserver |
|---|---|---|
| Frontend rendering | Works | Works |
| Gutenberg editor preview (`ServerSideRender`) | Breaks on re-render | Works (MutationObserver) |
| Bricks Builder | Not supported | Works (Block_Bridge) |
| Elementor | Not supported | Works (Block_Bridge) |
| REST API consumers | Directives ignored | Plain HTML, consumer adds JS |
| ES Module format | Required (`viewScriptModule`) | Optional (use `script` or future `scriptModule`) |
| Shared state across blocks | Built-in stores | Manual (events, globals, or custom) |

For blocks that need to work across all rendering contexts, **use vanilla JS with `MutationObserver` and the `script` field in `block.json`**.

## References

- [Gutenberg Discussion #74523](https://github.com/WordPress/gutenberg/discussions/74523) -- Interactivity API + ServerSideRender incompatibility
- [Script Modules in WordPress 6.5](https://make.wordpress.org/core/2024/03/04/script-modules-in-6-5/) -- Script Modules API documentation
- [Block metadata: viewScriptModule](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#view-script-module) -- Block.json field reference
