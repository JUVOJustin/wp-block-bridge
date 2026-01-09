# WP Block Bridge

Use WordPress blocks as the source of truth for page builders like Bricks and Elementor.

## Features

- Render block templates in page builder elements
- Automatic asset enqueueing (script modules)
- Interactivity API directive processing
- Unified API for context and attributes across all render contexts
- 
## Requirements

- PHP 8.1+
- Blocks must be registered using `block.json` metadata
- Blocks must be registered globally (e.g., via `register_block_type()` or `wp_register_block_types_from_metadata_collection()`)

## Installation

```bash
composer require juvo/wp-block-bridge
```

## Usage

### In render.php (Gutenberg + Page Builders)

```php
use juvo\WP_Block_Bridge\Block_Bridge;

// Get context and attributes - works everywhere
$context    = Block_Bridge::context( $block ?? null );
$attributes = Block_Bridge::attributes( $block ?? null );

// Your block markup...
ob_start();
?>
<div class="my-block">
    <!-- block content -->
</div>
<?php

// Wrap and process directives automatically
echo Block_Bridge::render( (string) ob_get_clean(), $block ?? null );
```

### In Bricks Element

```php
use juvo\WP_Block_Bridge\Block_Bridge;

class My_Bricks_Element extends \Bricks\Element {
    public function render(): void {
        Block_Bridge::render_block(
            'my-plugin/my-block',
            __DIR__ . '/render.php',
            [ 'postId' => get_the_ID() ]
        );
    }
}
```

### In Elementor Widget

```php
use juvo\WP_Block_Bridge\Block_Bridge;

class My_Elementor_Widget extends \Elementor\Widget_Base {
    protected function render(): void {
        Block_Bridge::render_block(
            'my-plugin/my-block',
            __DIR__ . '/render.php',
            [ 'postId' => get_the_ID() ]
        );
    }
}
```

## API

| Method | Description |
|--------|-------------|
| `context($block)` | Get block context array |
| `attributes($block)` | Get block attributes array |
| `render($html, $block)` | Wrap HTML and process directives |
| `render_block($name, $path, $context, $attrs)` | Full render for page builders |
| `is_bridge_context()` | Check if rendering via page builder |
| `is_editor_context()` | Check if in block editor SSR |