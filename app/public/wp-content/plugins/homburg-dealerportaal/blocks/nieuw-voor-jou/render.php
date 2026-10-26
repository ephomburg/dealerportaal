<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array $attributes Blokattributen (geen).
 */
echo HDP_Portal_Render::render_nieuw_voor_jou(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_nieuw_voor_jou() escapet elk veld zelf.
