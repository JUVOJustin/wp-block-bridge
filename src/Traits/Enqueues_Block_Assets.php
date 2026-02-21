<?php
/**
 * Trait for enqueueing block frontend assets.
 *
 * Provides both internal (automatic) and public (manual) methods for
 * managing block assets across rendering contexts.
 *
 * WordPress automatically deduplicates assets by handle/ID, so calling these
 * methods multiple times for the same block is safe and will not cause duplicate output.
 *
 * @see https://developer.wordpress.org/reference/functions/wp_enqueue_script/
 * @see https://developer.wordpress.org/reference/functions/wp_enqueue_style/
 * @see https://developer.wordpress.org/reference/functions/wp_enqueue_script_module/
 *
 * @package juvo\WP_Block_Bridge\Traits
 */

namespace juvo\WP_Block_Bridge\Traits;

use WP_Block_Type;
use WP_Block_Type_Registry;

/**
 * Enqueues all frontend assets for a block.
 */
trait Enqueues_Block_Assets {

	// =========================================================================
	// Public API -- for manual asset management in page builder integrations
	// =========================================================================

	/**
	 * Enqueues all frontend assets (scripts, styles, modules) for a block by name.
	 *
	 * Use this when you need to force-enqueue a block's assets outside of
	 * `render_block()`, e.g., in Elementor's `get_script_depends()` or
	 * Bricks' `enqueue_scripts()`.
	 *
	 * @param string $block_name Full block name (e.g., 'my-plugin/my-block').
	 */
	public static function enqueue_block_assets( string $block_name ): void {
		$block_type = self::resolve_block_type( $block_name );
		if ( ! $block_type ) {
			return;
		}
		self::enqueue_block_frontend_assets( $block_type );
	}

	/**
	 * Returns all frontend script handles for a registered block.
	 *
	 * Merges handles from `script` (frontend + editor) and `viewScript` (frontend-only)
	 * fields in block.json. Useful for Elementor's `get_script_depends()` or
	 * Bricks' `$scripts` property.
	 *
	 * Does NOT include script module IDs (`viewScriptModule`), as those use a
	 * separate loading system incompatible with classic script dependency arrays.
	 *
	 * @param string $block_name Full block name (e.g., 'my-plugin/my-block').
	 * @return array<string> Script handles.
	 */
	public static function get_block_script_handles( string $block_name ): array {
		$block_type = self::resolve_block_type( $block_name );
		if ( ! $block_type ) {
			return array();
		}

		return array_merge(
			$block_type->script_handles,
			$block_type->view_script_handles
		);
	}

	/**
	 * Returns all frontend style handles for a registered block.
	 *
	 * Merges handles from `style` (frontend + editor) and `viewStyle` (frontend-only)
	 * fields in block.json. Useful for Elementor's `get_style_depends()`.
	 *
	 * @param string $block_name Full block name (e.g., 'my-plugin/my-block').
	 * @return array<string> Style handles.
	 */
	public static function get_block_style_handles( string $block_name ): array {
		$block_type = self::resolve_block_type( $block_name );
		if ( ! $block_type ) {
			return array();
		}

		return array_merge(
			$block_type->style_handles,
			$block_type->view_style_handles
		);
	}

	/**
	 * Returns all frontend script module IDs for a registered block.
	 *
	 * Returns IDs from `viewScriptModule` in block.json. These are enqueued via
	 * `wp_enqueue_script_module()` and are separate from classic script handles.
	 *
	 * @param string $block_name Full block name (e.g., 'my-plugin/my-block').
	 * @return array<string> Script module IDs.
	 */
	public static function get_block_script_module_ids( string $block_name ): array {
		$block_type = self::resolve_block_type( $block_name );
		if ( ! $block_type ) {
			return array();
		}

		return $block_type->view_script_module_ids;
	}

	// =========================================================================
	// Internal -- used by render_block() for automatic asset enqueuing
	// =========================================================================

	/**
	 * Resolves a WP_Block_Type from the registry by block name.
	 *
	 * @param string $block_name Full block name (e.g., 'my-plugin/my-block').
	 * @return WP_Block_Type|null The block type, or null if not registered.
	 */
	private static function resolve_block_type( string $block_name ): ?WP_Block_Type {
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( $block_name );
		return $block_type instanceof WP_Block_Type ? $block_type : null;
	}

	/**
	 * Enqueues all frontend assets for a block type instance.
	 *
	 * @param WP_Block_Type $block_type The block type to enqueue assets for.
	 */
	private static function enqueue_block_frontend_assets( WP_Block_Type $block_type ): void {
		self::enqueue_scripts( $block_type );
		self::enqueue_styles( $block_type );
	}

	/**
	 * Enqueues all frontend scripts and script modules for a block type.
	 *
	 * @param WP_Block_Type $block_type The block type.
	 */
	private static function enqueue_scripts( WP_Block_Type $block_type ): void {
		foreach ( $block_type->script_handles as $handle ) {
			wp_enqueue_script( $handle );
		}

		foreach ( $block_type->view_script_handles as $handle ) {
			wp_enqueue_script( $handle );
		}

		foreach ( $block_type->view_script_module_ids as $module_id ) {
			wp_enqueue_script_module( $module_id );
		}
	}

	/**
	 * Enqueues all frontend styles for a block type.
	 *
	 * @param WP_Block_Type $block_type The block type.
	 */
	private static function enqueue_styles( WP_Block_Type $block_type ): void {
		foreach ( $block_type->style_handles as $handle ) {
			wp_enqueue_style( $handle );
		}

		foreach ( $block_type->view_style_handles as $handle ) {
			wp_enqueue_style( $handle );
		}
	}
}
