<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array $attributes Blokattributen (met standaardwaarden uit block.json).
 */
echo HDP_Blocks::render_content_pagina( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_content_pagina() escaped elk veld al zelf.
