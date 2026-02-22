<?php
/**
 * Render mode enumeration for block rendering contexts.
 *
 * Distinguishes between different WordPress rendering contexts to determine
 * how blocks should be processed (asset enqueueing, directive processing, etc.).
 *
 * @package juvo\WP_Block_Bridge
 */

declare(strict_types=1);

namespace juvo\WP_Block_Bridge;

/**
 * Defines the rendering context for a block.
 */
enum Render_Mode: string {

	/**
	 * Native Gutenberg block rendering via WP_Block.
	 * WordPress handles interactivity processing automatically.
	 */
	case NATIVE = 'native';

	/**
	 * Page builder context (Bricks, Elementor, etc.).
	 * Block_Bridge handles asset enqueueing and directive processing.
	 */
	case BRIDGE = 'bridge';

	/**
	 * REST API block renderer endpoint (/wp/v2/block-renderer/).
	 * Used for editor SSR previews, requires directive processing.
	 */
	case REST_API = 'rest_api';

	/**
	 * Block editor context (edit mode).
	 * Used when rendering previews in the block editor.
	 */
	case EDITOR = 'editor';

	/**
	 * Whether this mode requires server-side directive processing.
	 *
	 * In native context, WordPress handles directives automatically.
	 * All other contexts require explicit processing via wp_interactivity_process_directives().
	 *
	 * @return bool True if directives must be processed server-side.
	 */
	public function requires_directive_processing(): bool {
		return match ( $this ) {
			self::NATIVE => false,
			self::BRIDGE, self::REST_API, self::EDITOR => true,
		};
	}

	/**
	 * Whether this mode requires manual asset enqueueing.
	 *
	 * Native context enqueues assets via WordPress block rendering.
	 * Bridge context must enqueue assets explicitly.
	 *
	 * @return bool True if assets must be enqueued manually.
	 */
	public function requires_asset_enqueueing(): bool {
		return self::BRIDGE === $this;
	}
}
