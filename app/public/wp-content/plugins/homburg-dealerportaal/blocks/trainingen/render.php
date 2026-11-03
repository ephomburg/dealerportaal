<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array $attributes Blokattributen.
 */
echo HDP_Trainingen_Render::render_pagina( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendert zelf veilig.
