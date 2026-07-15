<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array $attributes Blokattributen (met standaardwaarden uit block.json).
 */
echo HDP_Blocks::render_dealerportaal( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_dealerportaal() escaped elk veld al zelf.
