<?php
namespace PizzaTier\Builder;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Value object representing a single pizza layer for rendering.
 * get_field() can return null when no image is set — all string
 * properties default to '' and are cast in the constructor.
 */
class LayerDTO {
	public int    $z_index            = 1;
	public string $type               = '';
	public string $slug               = '';
	public string $image_url          = '';
	public string $alt                = '';
	public bool   $leave_wrapper_open = false;
	public array  $extra_classes      = [];

	public function __construct( array $props = [] ) {
		foreach ( $props as $k => $v ) {
			if ( ! property_exists( $this, $k ) ) { continue; }
			switch ( $k ) {
				case 'z_index':
					$this->z_index = (int) $v;
					break;
				case 'leave_wrapper_open':
					$this->leave_wrapper_open = (bool) $v;
					break;
				case 'extra_classes':
					$this->extra_classes = (array) $v;
					break;
				default:
					// All remaining typed string properties: cast null → ''
					$this->{$k} = (string) ( $v ?? '' );
			}
		}
	}
}
