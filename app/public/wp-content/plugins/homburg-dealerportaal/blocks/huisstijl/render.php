<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array $attributes Blokattributen (met standaardwaarden uit block.json).
 */
echo HDP_Downloads_Render::render_huisstijl_pagina( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_huisstijl_pagina() escaped elk veld al zelf.
