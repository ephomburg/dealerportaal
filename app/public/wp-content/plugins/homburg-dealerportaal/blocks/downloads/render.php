<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array $attributes Blokattributen (met standaardwaarden uit block.json).
 */
echo HDP_Blocks::render_downloads_pagina( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_downloads_pagina() escaped elk veld al zelf.
