# Asset Enqueuing

How block scripts and styles are loaded across rendering contexts, and when manual enqueuing is required.

## Automatic Enqueuing

In most cases, block assets are enqueued automatically. No manual intervention is needed.

### Gutenberg (Frontend)

When a block is rendered on the frontend via `render_block()`, WordPress automatically enqueues all assets declared in `block.json`:

| block.json Field | Asset Type | Enqueued Automatically |
|---|---|---|
| `script` | Classic script | Yes |
| `viewScript` | Classic script (frontend-only) | Yes |
| `viewScriptModule` | ES module (frontend-only) | Yes |
| `style` | Stylesheet | Yes |
| `viewStyle` | Stylesheet (frontend-only) | Yes |

### Block_Bridge::render_block() (Page Builders)

When rendering a block via `Block_Bridge::render_block()` in Bricks or Elementor, all frontend assets are enqueued automatically before the render template is included. This covers the same asset types as Gutenberg.

```php
// Bricks Element -- assets are enqueued automatically
public function render(): void {
    Block_Bridge::render_block(
        'my-plugin/my-block',
        __DIR__ . '/render.php',
        [ 'postId' => get_the_ID() ]
    );
}
```

**For most blocks, this is sufficient.** No additional enqueue logic is needed.

## Manual Enqueuing

Manual enqueuing is only required when a page builder must load assets **before** `render()` runs.

### When Is Manual Enqueuing Needed?

**Elementor** resolves script and style dependencies via `get_script_depends()` and `get_style_depends()` on the widget class. These methods are called **before** `render()`, and Elementor uses them to:
- Include the correct scripts in the editor preview (live editing)
- Optimize asset loading by only including scripts for widgets present on the page

**Bricks Builder** uses the `$scripts` property or `enqueue_scripts()` method on the element class for similar purposes.

If your widget/element always calls `Block_Bridge::render_block()`, automatic enqueue is usually enough.
Add manual enqueue only when the builder requires assets earlier than render.

### Classic Handles vs Script Modules

The key difference is **what the builder dependency API can express**:

- `get_script_depends()` / `$scripts` only accept classic script **handles**
- `viewScriptModule` uses script module **IDs** (`wp_enqueue_script_module()`), not handles

Because of this:

- `script` / `viewScript` -> can be returned from `get_script_depends()` via `Block_Bridge::get_block_script_handles()`
- `style` / `viewStyle` -> can be returned from `get_style_depends()` via `Block_Bridge::get_block_style_handles()`
- `viewScriptModule` -> cannot be returned in `get_script_depends()`; use `Block_Bridge::get_block_script_depends()` (or `enqueue_block_assets()`) when you need module loading before render

### How To Determine If Pre-Render Enqueue Is Needed

Use this practical workflow instead of deciding up front:

1. Start with only `Block_Bridge::render_block()` in your builder widget/element.
2. Open the builder editor (Elementor/Bricks), add the widget, and hard-refresh once.
3. If preview works, keep it simple and stop.
4. If preview fails while frontend works, check browser console + Network tab:
   - missing classic handle (`script`/`viewScript`) -> declare handles via `get_block_script_handles()`
   - missing module behavior (`viewScriptModule`) -> call `get_block_script_depends()` or `enqueue_block_assets()` before render
5. If both are present, use `get_block_script_depends()` in dependency hooks (Elementor) or `enqueue_block_assets()` in enqueue hooks (Bricks).

### Quick Signals (Symptom -> Action)

| Symptom in builder preview | Likely cause | Action |
|---|---|---|
| Frontend works, preview JS never starts | Assets loaded too late for preview lifecycle | Preload in builder hook (`get_script_depends()` / `enqueue_scripts()`) |
| Console shows missing classic global/function | `script` / `viewScript` handle not declared early | Return `get_block_script_handles()` from dependency method |
| Console shows module-dependent behavior missing | `viewScriptModule` cannot be expressed as handle | Use `get_block_script_depends()` or `enqueue_block_assets()` |
| No issues in preview or frontend | Automatic enqueue is sufficient | Keep only `render_block()` |

### Common Scenario: Third-Party Libraries

The most common reason for manual enqueuing is **third-party libraries** that need to be loaded in the page builder's editor context. Examples:
- Swiper (slider/gallery)
- Leaflet (maps)
- Chart.js (charts)
- Any library that initializes via DOM observation or custom elements

These libraries are usually bundled into `script`/`viewScript` (classic) or `viewScriptModule` (module). If bundled as classic scripts, declare handles for builder dependency graphs. If bundled as modules, call `enqueue_block_assets()` when preload is required.

## API Reference

### `Block_Bridge::enqueue_block_assets( string $block_name ): void`

Enqueues all frontend assets (scripts, styles, and script modules) for a registered block. Resolves the block type from the registry internally.

```php
Block_Bridge::enqueue_block_assets( 'my-plugin/my-block' );
```

### `Block_Bridge::get_block_script_handles( string $block_name ): array`

Returns all frontend **classic script** handles for a block. Merges handles from both `script` and `viewScript` fields in `block.json`.

Does **not** include script module IDs (`viewScriptModule`), as those use a separate loading system (`wp_enqueue_script_module`) incompatible with classic script dependency arrays.

```php
$handles = Block_Bridge::get_block_script_handles( 'my-plugin/my-block' );
// e.g., [ 'my-plugin-my-block-script', 'my-plugin-my-block-view-script' ]
```

### `Block_Bridge::get_block_script_depends( string $block_name ): array`

Returns classic script handles for builder dependency APIs and enqueues script modules (`viewScriptModule`) as a side effect.

Use this in APIs like Elementor `get_script_depends()` where you must return handles but also need module preload.

```php
$handles = Block_Bridge::get_block_script_depends( 'my-plugin/my-block' );
// Returns classic handles and enqueues any module IDs.
```

### `Block_Bridge::get_block_style_handles( string $block_name ): array`

Returns all frontend style handles for a block. Merges handles from both `style` and `viewStyle` fields in `block.json`.

```php
$handles = Block_Bridge::get_block_style_handles( 'my-plugin/my-block' );
// e.g., [ 'my-plugin-my-block-style' ]
```

### `Block_Bridge::get_block_script_module_ids( string $block_name ): array`

Returns all frontend script module IDs for a block (`viewScriptModule` field). These are enqueued via `wp_enqueue_script_module()` and are separate from classic script handles.

```php
$module_ids = Block_Bridge::get_block_script_module_ids( 'my-plugin/my-block' );
// e.g., [ 'my-plugin-my-block-view-script-module' ]
```

## Examples

### Elementor Widget

```php
use juvo\WP_Block_Bridge\Block_Bridge;

class My_Widget extends \Elementor\Widget_Base {

    private const BLOCK_NAME = 'my-plugin/my-block';

    public function get_script_depends(): array {
        // Returns classic handles and preloads modules if present.
        return Block_Bridge::get_block_script_depends( self::BLOCK_NAME );
    }

    public function get_style_depends(): array {
        return Block_Bridge::get_block_style_handles( self::BLOCK_NAME );
    }

    protected function render(): void {
        // render_block() enqueues all block assets automatically.
        Block_Bridge::render_block(
            self::BLOCK_NAME,
            PLUGIN_PATH . 'build/Blocks/MyBlock/render.php',
            [ 'postId' => get_the_ID() ]
        );
    }
}
```

### Bricks Element

```php
use juvo\WP_Block_Bridge\Block_Bridge;

class My_Element extends \Bricks\Element {

    private const BLOCK_NAME = 'my-plugin/my-block';

    public function enqueue_scripts(): void {
        Block_Bridge::enqueue_block_assets( self::BLOCK_NAME );
    }

    public function render(): void {
        Block_Bridge::render_block(
            self::BLOCK_NAME,
            PLUGIN_PATH . 'build/Blocks/MyBlock/render.php',
            [ 'postId' => get_the_ID() ]
        );
    }
}
```

### Force-Enqueue Without Rendering

In rare cases, you may need to ensure a block's assets are loaded without rendering the block (e.g., a shared library used by multiple blocks):

```php
add_action( 'wp_enqueue_scripts', function () {
    if ( has_block( 'my-plugin/my-block' ) ) {
        Block_Bridge::enqueue_block_assets( 'my-plugin/my-block' );
    }
});
```

## Context Summary

| Context | Scripts Loaded | How |
|---|---|---|
| **Gutenberg frontend** | Automatic | WordPress enqueues on `render_block()` |
| **Gutenberg editor** | Automatic | WordPress enqueues `script`, `editorScript` on block registration |
| **Block_Bridge::render_block()** | Automatic | Block_Bridge enqueues before including render template |
| **Elementor editor preview** | Depends | Use `get_block_script_depends()` + `get_block_style_handles()` when preview needs pre-render assets |
| **Elementor frontend** | Automatic | `render_block()` enqueues; optional manual declaration for optimization |
| **Bricks frontend** | Automatic | `render_block()` enqueues during render |
| **Bricks editor** | Depends | Use `$scripts` for handles and `enqueue_scripts()` + `enqueue_block_assets()` for module preload |
| **REST API** | Not applicable | Headless consumers manage their own assets |
