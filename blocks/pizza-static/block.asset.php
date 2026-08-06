<?php
/**
 * Script dependencies for this block's editor script (block.js).
 *
 * WordPress reads this file automatically when block.json declares the
 * script as "file:./block.js" (register_block_script_handle() swaps the
 * .js extension for .asset.php). Without it the script registers with an
 * empty dependency list and load order against the wp-* editor bundles
 * is not guaranteed.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'dependencies' => array(
		'wp-blocks',
		'wp-element',
		'wp-components',
		'wp-block-editor',
		'wp-i18n',
		'wp-server-side-render',
	),
	'version'      => defined( 'PIZZATIER_VERSION' ) ? PIZZATIER_VERSION : '2.1.1',
);
