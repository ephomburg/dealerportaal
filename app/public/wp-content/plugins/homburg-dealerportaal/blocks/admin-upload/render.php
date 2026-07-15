<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array $attributes Blokattributen (met standaardwaarden uit block.json).
 */
echo HDP_Admin_Upload::render( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render() escaped elk veld al zelf.
