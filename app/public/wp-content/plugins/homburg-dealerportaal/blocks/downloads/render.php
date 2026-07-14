<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array $attributes Blokattributen (met standaardwaarden uit block.json).
 */
echo HDP_Blocks::render_downloads_pagina( $attributes );
