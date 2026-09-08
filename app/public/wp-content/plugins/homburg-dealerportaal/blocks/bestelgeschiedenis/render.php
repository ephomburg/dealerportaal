<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array $attributes Blokattributen (met standaardwaarden uit block.json).
 */
echo HDP_Bestelgeschiedenis_Render::render_bestelgeschiedenis_pagina( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_bestelgeschiedenis_pagina() escaped elk veld al zelf.
