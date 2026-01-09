<?php
/**
 * Trait for wrapping block HTML output.
 *
 * Handles the creation of wrapper divs with appropriate attributes
 * for both native block and bridge contexts.
 *
 * @package juvo\WP_Block_Bridge\Traits
 */

declare(strict_types=1);

namespace juvo\WP_Block_Bridge\Traits;

use juvo\WP_Block_Bridge\Render_Context;

/**
 * Wraps block HTML with appropriate wrapper attributes.
 */
trait Wraps_Block_Html {

	/**
	 * Wraps HTML content with a div containing block wrapper attributes.
	 *
	 * In native block context, uses get_block_wrapper_attributes() for full support.
	 * In bridge context, builds attributes manually with essential data attributes.
	 *
	 * @param string               $html        The HTML content to wrap.
	 * @param Render_Context       $ctx         Current render context.
	 * @param array<string, mixed> $extra_attrs Additional attributes for the wrapper.
	 * @return string Wrapped HTML.
	 */
	private static function wrap_html( string $html, Render_Context $ctx, array $extra_attrs = array() ): string {
		if ( $ctx->supports_interactivity() ) {
			$extra_attrs['data-wp-interactive'] = $ctx->block_name;
		}

		$wrapper_attrs = $ctx->is_native()
			? get_block_wrapper_attributes( $extra_attrs )
			: self::build_attributes( $extra_attrs );

		return sprintf( '<div %s>%s</div>', $wrapper_attrs, $html );
	}

	/**
	 * Builds an HTML attribute string from an associative array.
	 *
	 * @param array<string, mixed> $attrs Attribute key-value pairs.
	 * @return string Formatted HTML attributes string.
	 */
	private static function build_attributes( array $attrs ): string {
		$parts = array();

		foreach ( $attrs as $name => $value ) {
			if ( is_bool( $value ) ) {
				if ( $value ) {
					$parts[] = esc_attr( $name );
				}
				continue;
			}

			$parts[] = sprintf( '%s="%s"', esc_attr( $name ), esc_attr( (string) $value ) );
		}

		return implode( ' ', $parts );
	}
}
