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

use WP_Block;
use WP_Block_Type;
use WP_Block_Type_Registry;
use WP_Query;

/**
 * Main class for bridging WordPress blocks to page builders.
 */
class Block_Bridge {

	/**
	 * Stores the current render context for access within render templates.
	 *
	 * @var array<string, mixed>
	 */
	private static array $current_context = array();

	/**
	 * Stores the current render attributes for access within render templates.
	 *
	 * @var array<string, mixed>
	 */
	private static array $current_attributes = array();

	/**
	 * Indicates if currently rendering via Block_Bridge (page builder context).
	 *
	 * @var bool
	 */
	private static bool $is_bridge_context = false;

	/**
	 * Renders a block template with its associated assets (scripts, styles).
	 *
	 * Enqueues the block's view script modules and includes the render template,
	 * allowing blocks to be used outside the block editor context.
	 * Validates context and attributes against the block type definition.
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
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( $block_name );

		if ( ! $block_type ) {
			return;
		}

		self::enqueue_block_assets( $block_type );

		self::$is_bridge_context  = true;
		self::$current_context    = self::validate_context( $block_type, $context );
		self::$current_attributes = self::validate_attributes( $block_type, $attributes );

		$content = '';

		if ( ! file_exists( $render_path ) ) {
			self::reset_state();
			return;
		}

		if ( ! self::supports_interactivity( $block_type ) ) {
			include $render_path;
			self::reset_state();
			return;
		}

		ob_start();
		include $render_path;
		$html = (string) ob_get_clean();

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Directives processor returns safe HTML.
		echo wp_interactivity_process_directives( $html );

		self::reset_state();
	}

	/**
	 * Resets internal state after rendering.
	 */
	private static function reset_state(): void {
		self::$is_bridge_context  = false;
		self::$current_context    = array();
		self::$current_attributes = array();
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
		return self::$is_bridge_context;
	}

	/**
	 * Checks if block type supports the Interactivity API.
	 *
	 * @param WP_Block_Type $block_type The block type instance.
	 * @return bool True if interactivity is supported.
	 */
	private static function supports_interactivity( WP_Block_Type $block_type ): bool {
		$supports = $block_type->supports;

		if ( ! is_array( $supports ) ) {
			return false;
		}

		$interactivity = $supports['interactivity'] ?? false;

		return true === $interactivity || is_array( $interactivity );
	}

	/**
	 * Enqueues all block assets (script modules, scripts, styles).
	 *
	 * @param WP_Block_Type $block_type The block type instance.
	 */
	private static function enqueue_block_assets( WP_Block_Type $block_type ): void {
		foreach ( $block_type->view_script_module_ids as $script_module_id ) {
			wp_enqueue_script_module( $script_module_id );
		}

		foreach ( $block_type->view_script_handles as $script_handle ) {
			wp_enqueue_script( $script_handle );
		}

		foreach ( $block_type->style_handles as $style_handle ) {
			wp_enqueue_style( $style_handle );
		}

		foreach ( $block_type->view_style_handles as $style_handle ) {
			wp_enqueue_style( $style_handle );
		}
	}

	/**
	 * Validates context against block type's uses_context definition.
	 *
	 * Only keeps context keys that the block declares it uses, preventing
	 * accidental data leakage and ensuring render template compatibility.
	 *
	 * @param WP_Block_Type        $block_type The block type instance.
	 * @param array<string, mixed> $context    Raw context data.
	 * @return array<string, mixed> Validated context with only allowed keys.
	 */
	private static function validate_context( WP_Block_Type $block_type, array $context ): array {
		$uses_context = $block_type->uses_context ?? array();

		if ( empty( $uses_context ) ) {
			return array();
		}

		return array_intersect_key( $context, array_flip( $uses_context ) );
	}

	/**
	 * Validates and normalizes attributes against block type's attribute schema.
	 *
	 * Applies default values from the schema and casts types appropriately.
	 * Unknown attributes are stripped for security and consistency.
	 *
	 * @param WP_Block_Type        $block_type The block type instance.
	 * @param array<string, mixed> $attributes Raw attribute data.
	 * @return array<string, mixed> Validated attributes with defaults applied.
	 */
	private static function validate_attributes( WP_Block_Type $block_type, array $attributes ): array {
		$schema    = $block_type->attributes ?? array();
		$validated = array();

		foreach ( $schema as $key => $definition ) {
			if ( array_key_exists( $key, $attributes ) ) {
				$validated[ $key ] = self::cast_attribute_value( $attributes[ $key ], $definition );
				continue;
			}

			if ( array_key_exists( 'default', $definition ) ) {
				$validated[ $key ] = $definition['default'];
			}
		}

		return $validated;
	}

	/**
	 * Casts an attribute value to its declared type.
	 *
	 * @param mixed                $value      The raw attribute value.
	 * @param array<string, mixed> $definition The attribute definition from schema.
	 * @return mixed The type-cast value.
	 */
	private static function cast_attribute_value( mixed $value, array $definition ): mixed {
		$type = $definition['type'] ?? 'string';

		return match ( $type ) {
			'string'  => (string) $value,
			'number'  => is_numeric( $value ) ? (float) $value : 0,
			'integer' => (int) $value,
			'boolean' => (bool) $value,
			'array'   => is_array( $value ) ? $value : array(),
			'object'  => is_array( $value ) || is_object( $value ) ? (array) $value : array(),
			default   => $value,
		};
	}

	/**
	 * Retrieves the render context, supporting both block and page builder contexts.
	 *
	 * When called from render.php, pass the $block variable if available.
	 * In block context, returns $block->context. Otherwise returns the
	 * context passed via render_block() for page builder usage.
	 *
	 * @param WP_Block|null $block Optional WP_Block instance from render.php.
	 * @return array<string, mixed> The context data.
	 */
	public static function get_context( ?WP_Block $block = null ): array {
		if ( $block instanceof WP_Block ) {
			return $block->context;
		}

		return self::$current_context;
	}

	/**
	 * Retrieves validated attributes for the current render.
	 *
	 * In native block context, use the $attributes variable directly from render.php.
	 * For page builder context, returns attributes validated against the block schema.
	 *
	 * @param array<string, mixed>|null $attributes Optional attributes from render.php.
	 * @return array<string, mixed> The validated attributes.
	 */
	public static function get_attributes( ?array $attributes = null ): array {
		if ( is_array( $attributes ) ) {
			return $attributes;
		}

		return self::$current_attributes;
	}

	/**
	 * Resolves the post ID from block context or attributes.
	 *
	 * Handles the common pattern of determining which post to render for,
	 * checking block context first, then attributes, then falling back to current post.
	 *
	 * @param bool                 $use_block_context Whether to prioritize block context over attributes.
	 * @param array<string, mixed> $block_context     Block context array (typically from $block->context).
	 * @param array<string, mixed> $attributes        Block attributes array.
	 * @return int The resolved post ID.
	 */
	public static function get_post_id( bool $use_block_context = true, array $block_context = array(), array $attributes = array() ): int {
		if ( $use_block_context ) {
			return (int) ( $block_context['postId'] ?? get_the_ID() );
		}

		return (int) ( $attributes['postId'] ?? get_the_ID() );
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

	/**
	 * Checks if currently in block editor SSR context.
	 *
	 * WordPress sets the 'context' request parameter during editor server-side rendering.
	 *
	 * @return bool True if in editor context, false otherwise.
	 */
	public static function is_editor_context(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context check
		return isset( $_REQUEST['context'] ) && 'edit' === $_REQUEST['context'];
	}
}
