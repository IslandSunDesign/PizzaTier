<?php
namespace PizzaTier\Builder;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Renders a LayerDTO into a flat absolutely-positioned layer div.
 *
 * All layers (crust, sauce, cheese, toppings, drizzle, cut) use the same
 * flat format so they can be stacked inside .np-pizza-stage via z-index.
 *
 * render_closed() — standard flat layer (used for all types)
 * render_open()   — kept for backward-compat; now delegates to render_closed()
 */
class LayerRenderer {

	public function render_closed( LayerDTO $dto ): string {
		if ( ! $dto->image_url ) {
			// No image — render an empty placeholder div so JS can find it
			return '';
		}

		$z     = (int) $dto->z_index;
		$type  = sanitize_key( $dto->type );
		$slug  = sanitize_key( $dto->slug );
		$src   = esc_url( $dto->image_url );
		$alt   = esc_attr( $dto->alt ?: $dto->type . ': ' . $dto->slug );

		$extra = implode( ' ', array_map( 'sanitize_html_class', $dto->extra_classes ) );
		$cls   = trim( "np-layer-div pizzatier-layer pizzatier-layer-{$type} pizzatier-layer-{$slug} {$extra}" );

		$html = '<div class="' . esc_attr( $cls ) . '"'
			. ' data-layer-type="' . esc_attr( $type ) . '"'
			. ' data-layer-slug="' . esc_attr( $slug ) . '"'
			. ' style="z-index:' . $z . ';">'
			. '<img src="' . $src . '"'
			. ' alt="' . $alt . '"'
			. ' class="pizzatier-layer-img pizzatier-layer-img--' . esc_attr( $type ) . '"'
			. ' style="opacity:1;"'
			. ' loading="lazy" decoding="async">'
			. '</div>';

		return apply_filters( 'pizzatier_layer_html', $html, $dto->type, $dto->slug );
	}

	/**
	 * Backward-compat alias — previously used for "open" nested layers.
	 * Now all layers are flat; delegates to render_closed().
	 */
	public function render_open( LayerDTO $dto ): string {
		return $this->render_closed( $dto );
	}
}
