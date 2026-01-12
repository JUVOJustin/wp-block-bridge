<?php
/**
 * Trait for enqueueing block frontend assets.
 *
 * Handles enqueueing of all block assets required for frontend rendering,
 * including styles, scripts, and script modules.
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

/**
 * Enqueues all frontend assets for a block.
 */
trait Enqueues_Block_Assets {

	/**
	 * Enqueues all frontend assets for a block type.
	 *
	 * Handles enqueueing of scripts and styles needed for frontend rendering:
	 * - Scripts: script (editor+frontend), viewScript (frontend-only), viewScriptModule (ES modules)
	 * - Styles: style (editor+frontend), viewStyle (frontend-only)
	 *
	 * @param WP_Block_Type $block_type The block type to enqueue assets for.
	 */
	private static function enqueue_block_frontend_assets( WP_Block_Type $block_type ): void {
		self::enqueue_block_scripts( $block_type );
		self::enqueue_block_styles( $block_type );
	}

	/**
	 * Enqueues all frontend scripts for a block type.
	 *
	 * Handles:
	 * - script_handles: Scripts for both editor and frontend (block.json `script`)
	 * - view_script_handles: Frontend-only scripts (block.json `viewScript`)
	 * - view_script_module_ids: Frontend ES modules (block.json `viewScriptModule`)
	 *
	 * @param WP_Block_Type $block_type The block type.
	 */
	private static function enqueue_block_scripts( WP_Block_Type $block_type ): void {
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
	 * Handles:
	 * - style_handles: Styles for both editor and frontend (block.json `style`)
	 * - view_style_handles: Frontend-only styles (block.json `viewStyle`, WP 6.5+)
	 *
	 * @param WP_Block_Type $block_type The block type.
	 */
	private static function enqueue_block_styles( WP_Block_Type $block_type ): void {
		foreach ( $block_type->style_handles as $handle ) {
			wp_enqueue_style( $handle );
		}

		foreach ( $block_type->view_style_handles as $handle ) {
			wp_enqueue_style( $handle );
		}
	}
}
