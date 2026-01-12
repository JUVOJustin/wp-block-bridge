<?php
/**
 * Block Bridge - Use WordPress blocks as source of truth for page builders.
 *
 * Provides utilities to render blocks and their assets outside the block editor,
 * enabling consistent styling and scripting across Bricks Builder, Elementor, etc.
 *
 * @package juvo\WP_Block_Bridge
 */

declare(strict_types=1);

namespace juvo\WP_Block_Bridge;

use juvo\WP_Block_Bridge\Traits\Detects_Render_Mode;
use juvo\WP_Block_Bridge\Traits\Enqueues_Block_Assets;
use juvo\WP_Block_Bridge\Traits\Wraps_Block_Html;
use WP_Block;
use WP_Query;

/**
 * Main class for bridging WordPress blocks to page builders.
 *
 * Stores a single render context for the current bridge render cycle.
 * PHP renders blocks sequentially, so only one context is active at a time.
 */
class Block_Bridge {

	use Detects_Render_Mode;
	use Enqueues_Block_Assets;
	use Wraps_Block_Html;

	/**
	 * Current render context for bridge rendering.
	 *
	 * @var Render_Context|null
	 */
	private static ?Render_Context $current_context = null;

	/**
	 * Retrieves block context data for the current render.
	 *
	 * Works seamlessly across all rendering contexts:
	 * - Native Gutenberg: Pass `$block` from render.php
	 * - Bricks/Elementor: Context stored internally (no param needed)
	 *
	 * @param WP_Block|null $block Optional WP_Block instance from render.php.
	 * @return array<string, mixed> The context data array.
	 */
	public static function context( ?WP_Block $block = null ): array {
		if ( null !== self::$current_context ) {
			return self::$current_context->context;
		}

		if ( $block instanceof WP_Block ) {
			return $block->context;
		}

		return array();
	}

	/**
	 * Retrieves the full Render_Context object for advanced use cases.
	 *
	 * Most render templates should use `context()` instead.
	 * This method is useful when you need access to mode, attributes, or block_type.
	 *
	 * @param WP_Block|null        $block      Optional WP_Block instance.
	 * @param array<string, mixed> $attributes Optional attributes from render.php.
	 * @return Render_Context|null Current render context or null.
	 */
	public static function render_context( ?WP_Block $block = null, array $attributes = array() ): ?Render_Context {
		if ( null !== self::$current_context ) {
			return self::$current_context;
		}

		if ( $block instanceof WP_Block ) {
			return Render_Context::from_block( $block, $attributes );
		}

		return null;
	}

	/**
	 * Renders a block template for page builders (Bricks, Elementor, etc.).
	 *
	 * Enqueues the block's assets, creates a bridge context, includes the render
	 * template, and processes interactivity directives.
	 *
	 * @param string               $block_name  Full block name (e.g., 'autoscout-sync/vehicle-equipment').
	 * @param string               $render_path Absolute path to the block's render.php template.
	 * @param array<string, mixed> $context     Context data passed to the render template.
	 * @param array<string, mixed> $attributes  Block attributes (optional, merged with defaults).
	 */
	public static function render_block(
		string $block_name,
		string $render_path,
		array $context = array(),
		array $attributes = array()
	): void {
		$ctx = Render_Context::from_bridge( $block_name, $context, $attributes );

		if ( ! $ctx || ! $ctx->block_type ) {
			return;
		}

		self::enqueue_block_frontend_assets( $ctx->block_type );

		if ( ! file_exists( $render_path ) ) {
			return;
		}

		self::$current_context = $ctx;

		try {
			ob_start();
			include $render_path;

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render.php template is expected to produce safe HTML output.
			echo ob_get_clean();
		} finally {
			self::$current_context = null;
		}
	}

	/**
	 * Processes HTML output for block rendering with interactivity support.
	 *
	 * Handles wrapper attributes and directive processing automatically based on
	 * the current render context. Can be called from render.php templates.
	 *
	 * For interactive blocks:
	 * - Adds `data-wp-interactive` to root element
	 * - Wraps with block wrapper attributes in native block context
	 * - Processes directives in bridge/REST contexts
	 *
	 * @param string               $html        The rendered HTML content.
	 * @param WP_Block|null        $block       Optional WP_Block instance from render.php (for native context).
	 * @param array<string, mixed> $extra_attrs Optional extra attributes for the wrapper div.
	 * @return string Processed HTML with attributes and directives applied.
	 */
	public static function render(
		string $html,
		?WP_Block $block = null,
		array $extra_attrs = array()
	): string {
		// Determine context: use stored bridge context, or create from native block.
		$ctx = self::render_context( $block );

		if ( ! $ctx ) {
			return $html;
		}

		$html = self::wrap_html( $html, $ctx, $extra_attrs );

		if ( $ctx->requires_directive_processing() ) {
			return wp_interactivity_process_directives( $html );
		}

		return $html;
	}

	/**
	 * Checks if currently rendering via Block_Bridge (page builder context).
	 *
	 * Render templates can use this to determine if they're in native block
	 * context (where WordPress handles interactivity processing) or bridge
	 * context (where Block_Bridge handles it).
	 *
	 * @return bool True if in bridge context, false if in native block context.
	 */
	public static function is_bridge_context(): bool {
		return null !== self::$current_context && self::$current_context->is_bridge();
	}

	/**
	 * Retrieves block attributes for the current render.
	 *
	 * Works seamlessly across all rendering contexts:
	 * - Native Gutenberg: Pass `$block` from render.php
	 * - Bricks/Elementor: Attributes stored internally (no param needed)
	 *
	 * @param WP_Block|null $block Optional WP_Block instance from render.php.
	 * @return array<string, mixed> The validated attributes.
	 */
	public static function attributes( ?WP_Block $block = null ): array {
		if ( null !== self::$current_context ) {
			return self::$current_context->attributes;
		}

		if ( $block instanceof WP_Block ) {
			return $block->attributes;
		}

		return array();
	}

	/**
	 * Gets a preview example post for editor SSR rendering.
	 *
	 * Returns the latest post of a given type when in the block editor context,
	 * enabling meaningful previews during block development.
	 *
	 * @param string $post_type The post type to query.
	 * @return int|null Post ID if in edit context and post found, null otherwise.
	 */
	public static function get_preview_example_post( string $post_type ): ?int {
		if ( ! self::is_editor_context() ) {
			return null;
		}

		$query = new WP_Query(
			array(
				'post_type'      => $post_type,
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		);

		if ( empty( $query->posts ) ) {
			return null;
		}

		return (int) $query->posts[0];
	}
}
