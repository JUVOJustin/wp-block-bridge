<?php
/**
 * Trait for detecting WordPress block render mode.
 *
 * Provides static methods to detect the current rendering context
 * based on request parameters and URI patterns.
 *
 * @package juvo\WP_Block_Bridge\Traits
 */

declare(strict_types=1);

namespace juvo\WP_Block_Bridge\Traits;

/**
 * Detects the current block rendering context.
 */
trait Detects_Render_Mode {

	/**
	 * Checks if currently in block editor SSR context.
	 *
	 * WordPress sets the 'context' request parameter to 'edit' during
	 * server-side rendering in the block editor.
	 *
	 * @return bool True if in editor context.
	 */
	public static function is_editor_context(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context check.
		return isset( $_REQUEST['context'] ) && 'edit' === $_REQUEST['context'];
	}

	/**
	 * Checks if currently in block renderer REST API context.
	 *
	 * WordPress uses /wp/v2/block-renderer/ endpoint for editor block previews.
	 * In this context, interactivity directives must be processed server-side.
	 *
	 * @return bool True if in block renderer context.
	 */
	public static function is_block_renderer_context(): bool {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Only checking for substring.
		$uri = $_SERVER['REQUEST_URI'] ?? '';

		return false !== strpos( $uri, '/wp/v2/block-renderer/' );
	}
}
