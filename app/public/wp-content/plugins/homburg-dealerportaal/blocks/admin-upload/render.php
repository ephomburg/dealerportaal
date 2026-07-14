<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array $attributes Blokattributen (met standaardwaarden uit block.json).
 */
echo HDP_Admin_Upload::render( $attributes );
