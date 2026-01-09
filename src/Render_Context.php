<?php
/**
 * Immutable render context for block rendering.
 *
 * Encapsulates all state needed during a block render cycle, supporting
 * nested renders via a context stack managed by Block_Bridge.
 *
 * @package juvo\WP_Block_Bridge
 */

declare(strict_types=1);

namespace juvo\WP_Block_Bridge;

use WP_Block;
use WP_Block_Type;
use WP_Block_Type_Registry;

/**
 * Immutable value object holding render state for a single block render.
 */
final class Render_Context {

	/**
	 * Constructs a new render context.
	 *
	 * @param string               $block_name Block name (e.g., 'autoscout-sync/vehicle-equipment').
	 * @param array<string, mixed> $context    Validated block context data.
	 * @param array<string, mixed> $attributes Validated block attributes.
	 * @param Render_Mode          $mode       Current render mode.
	 * @param WP_Block|null        $block      Native WP_Block instance (if in native context).
	 * @param WP_Block_Type|null   $block_type Resolved block type (cached).
	 */
	private function __construct(
		public readonly string $block_name,
		public readonly array $context,
		public readonly array $attributes,
		public readonly Render_Mode $mode,
		public readonly ?WP_Block $block = null,
		public readonly ?WP_Block_Type $block_type = null,
	) {}

	/**
	 * Creates context from a native WP_Block instance.
	 *
	 * Used when block is rendered via standard Gutenberg block rendering.
	 *
	 * @param WP_Block             $block      The WP_Block instance from render.php.
	 * @param array<string, mixed> $attributes Block attributes from render.php.
	 * @return self New render context instance.
	 */
	public static function from_block( WP_Block $block, array $attributes = array() ): self {
		$block_type = $block->block_type;
		$mode       = self::detect_mode_for_native();

		return new self(
			block_name: $block->name,
			context: $block->context,
			attributes: $attributes,
			mode: $mode,
			block: $block,
			block_type: $block_type,
		);
	}

	/**
	 * Creates context for page builder (bridge) rendering.
	 *
	 * Used when block is rendered via Bricks, Elementor, or other page builders.
	 *
	 * @param string               $block_name Full block name.
	 * @param array<string, mixed> $context    Context data to pass to the block.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return self|null New render context instance, or null if block not registered.
	 */
	public static function from_bridge( string $block_name, array $context = array(), array $attributes = array() ): ?self {
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( $block_name );

		if ( ! $block_type ) {
			return null;
		}

		return new self(
			block_name: $block_name,
			context: self::filter_context( $block_type, $context ),
			attributes: self::apply_attribute_defaults( $block_type, $attributes ),
			mode: Render_Mode::BRIDGE,
			block: null,
			block_type: $block_type,
		);
	}

	/**
	 * Detects render mode for native block context.
	 *
	 * Checks request context to determine if in editor or REST API context.
	 *
	 * @return Render_Mode Detected render mode.
	 */
	private static function detect_mode_for_native(): Render_Mode {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context check.
		if ( isset( $_REQUEST['context'] ) && 'edit' === $_REQUEST['context'] ) {
			return Render_Mode::EDITOR;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Only checking for substring.
		$uri = $_SERVER['REQUEST_URI'] ?? '';
		if ( false !== strpos( $uri, '/wp/v2/block-renderer/' ) ) {
			return Render_Mode::REST_API;
		}

		return Render_Mode::NATIVE;
	}

	/**
	 * Filters context to only include keys declared in uses_context.
	 *
	 * @param WP_Block_Type        $block_type Block type instance.
	 * @param array<string, mixed> $context    Raw context data.
	 * @return array<string, mixed> Filtered context.
	 */
	private static function filter_context( WP_Block_Type $block_type, array $context ): array {
		$uses_context = $block_type->uses_context ?? array();

		if ( empty( $uses_context ) ) {
			return array();
		}

		return array_intersect_key( $context, array_flip( $uses_context ) );
	}

	/**
	 * Applies default attribute values from block schema.
	 *
	 * @param WP_Block_Type        $block_type Block type instance.
	 * @param array<string, mixed> $attributes Raw attributes.
	 * @return array<string, mixed> Attributes with defaults applied.
	 */
	private static function apply_attribute_defaults( WP_Block_Type $block_type, array $attributes ): array {
		$schema    = $block_type->attributes ?? array();
		$validated = array();

		foreach ( $schema as $key => $definition ) {
			if ( array_key_exists( $key, $attributes ) ) {
				$validated[ $key ] = self::cast_value( $attributes[ $key ], $definition );
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
	 * @param mixed                $value      Raw value.
	 * @param array<string, mixed> $definition Attribute schema definition.
	 * @return mixed Type-cast value.
	 */
	private static function cast_value( mixed $value, array $definition ): mixed {
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
	 * Checks if block type supports the Interactivity API.
	 *
	 * @return bool True if interactivity is supported.
	 */
	public function supports_interactivity(): bool {
		if ( ! $this->block_type ) {
			return false;
		}

		$supports = $this->block_type->supports;

		if ( ! is_array( $supports ) ) {
			return false;
		}

		$interactivity = $supports['interactivity'] ?? false;

		return true === $interactivity || is_array( $interactivity );
	}

	/**
	 * Whether this context is in bridge mode (page builder).
	 *
	 * @return bool True if in bridge context.
	 */
	public function is_bridge(): bool {
		return $this->mode === Render_Mode::BRIDGE;
	}

	/**
	 * Whether this context has a native WP_Block instance.
	 *
	 * @return bool True if native block context.
	 */
	public function is_native(): bool {
		return $this->block instanceof WP_Block;
	}

	/**
	 * Whether directive processing is required for this context.
	 *
	 * @return bool True if directives must be processed server-side.
	 */
	public function requires_directive_processing(): bool {
		return $this->supports_interactivity() && $this->mode->requires_directive_processing();
	}
}
