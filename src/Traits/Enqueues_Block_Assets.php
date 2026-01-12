<?php
/**
 * Trait for enqueueing block frontend assets.
 *
 * Handles registration and enqueueing of all block assets meant for frontend
 * rendering, including styles, scripts, and script modules.
 *
 * @package juvo\WP_Block_Bridge\Traits
 */

declare(strict_types=1);

namespace juvo\WP_Block_Bridge\Traits;

use WP_Block_Type;

/**
 * Enqueues all frontend assets for a block.
 */
trait Enqueues_Block_Assets {

	/**
	 * Enqueues all frontend assets for a block type.
	 *
	 * Handles the following block.json asset fields:
	 * - viewScriptModule: ES modules for interactivity
	 * - viewScript: Traditional view scripts
	 * - style: Block styles (editor + frontend)
	 * - viewStyle: Frontend-only styles (WP 6.5+)
	 *
	 * @param WP_Block_Type $block_type The block type to enqueue assets for.
	 */
	private static function enqueue_block_frontend_assets( WP_Block_Type $block_type ): void {
		self::enqueue_view_script_modules( $block_type );
		self::enqueue_view_scripts( $block_type );
		self::enqueue_block_styles( $block_type );
		self::enqueue_view_styles( $block_type );
	}

	/**
	 * Enqueues view script modules (ES modules for interactivity).
	 *
	 * @param WP_Block_Type $block_type The block type.
	 */
	private static function enqueue_view_script_modules( WP_Block_Type $block_type ): void {
		if ( empty( $block_type->view_script_module_ids ) ) {
			return;
		}

		foreach ( $block_type->view_script_module_ids as $script_module_id ) {
			wp_enqueue_script_module( $script_module_id );
		}
	}

	/**
	 * Enqueues view scripts (traditional JavaScript).
	 *
	 * @param WP_Block_Type $block_type The block type.
	 */
	private static function enqueue_view_scripts( WP_Block_Type $block_type ): void {
		if ( empty( $block_type->view_script_handles ) ) {
			return;
		}

		foreach ( $block_type->view_script_handles as $script_handle ) {
			wp_enqueue_script( $script_handle );
		}
	}

	/**
	 * Enqueues block styles (used in both editor and frontend).
	 *
	 * @param WP_Block_Type $block_type The block type.
	 */
	private static function enqueue_block_styles( WP_Block_Type $block_type ): void {
		if ( empty( $block_type->style_handles ) ) {
			return;
		}

		foreach ( $block_type->style_handles as $style_handle ) {
			wp_enqueue_style( $style_handle );
		}
	}

	/**
	 * Enqueues frontend-only view styles (WP 6.5+).
	 *
	 * @param WP_Block_Type $block_type The block type.
	 */
	private static function enqueue_view_styles( WP_Block_Type $block_type ): void {
		if ( ! property_exists( $block_type, 'view_style_handles' ) ) {
			return;
		}

		if ( empty( $block_type->view_style_handles ) ) {
			return;
		}

		foreach ( $block_type->view_style_handles as $style_handle ) {
			wp_enqueue_style( $style_handle );
		}
	}
}
