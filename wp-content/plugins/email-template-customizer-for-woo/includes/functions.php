<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! function_exists( 'villtheme_include_folder' ) ) {
	function villtheme_include_folder( $path, $prefix = '', $ext = array( 'php' ) ) {

		/*Include all files in payment folder*/
		if ( ! is_array( $ext ) ) {
			$ext = explode( ',', $ext );
			$ext = array_map( 'trim', $ext );
		}
		$sfiles = scandir( $path );
		foreach ( $sfiles as $sfile ) {
			if ( $sfile != '.' && $sfile != '..' ) {
				if ( is_file( $path . "/" . $sfile ) ) {
					$ext_file  = pathinfo( $path . "/" . $sfile );
					$file_name = $ext_file['filename'];
					if ( $ext_file['extension'] ) {
						if ( in_array( $ext_file['extension'], $ext ) ) {
							$class = preg_replace( '/\W/i', '_', $prefix . ucfirst( $file_name ) );

							if ( ! class_exists( $class ) ) {
								require_once $path . $sfile;
								if ( class_exists( $class ) ) {
									new $class;
								}
							}
						}
					}
				}
			}
		}
	}
}
function viwec_get_emails_list( $type = '' ) {
	return get_posts( array(
		'numberposts' => - 1,
		'post_type'   => 'viwec_template',
		'meta_key'    => 'viwec_settings_type',// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'  => $type,// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	) );
}

function viwec_render_email_template( $id ) {
	$email_template = VIWEC\INC\Email_Render::init([ 'template_id' => $id ]);
	$data           = get_post_meta( $id, 'viwec_email_structure', true );
	$data           = json_decode(VIWEC\INC\Init::html_entity_decode( $data ), true );
	$email_template->render( $data );
}

function viwec_parse_styles( $data ) {
	if ( empty( $data ) ) {
		return '';
	}

	$style = '';
	if ( is_array( $data ) ) {
		foreach ( $data as $key => $value ) {
			if ( $key === 'border-style' && isset( $data['border-width'] ) && $data['border-width'] == '0px' ) {
				continue;
			}
			$style .= "{$key}:{$value};";
		}

		$border_width = isset( $data['border-width'] ) && $data['border-width'] !== '0px' ? true : false;
		$border_style = isset( $data['border-style'] ) ? true : false;

		$style .= $border_width && ! $border_style ? 'border-style:solid;' : '';
	} else {
		$style = $data;
	}

	return $style;
}

function viwec_allowed_html() {
	$allow_html = wp_kses_allowed_html( 'post' );
	foreach ( $allow_html as $key => $value ) {
		if ( in_array( $key, [ 'div', 'span', 'a', 'input', 'form' ] ) ) {
			$allow_html[ $key ]['data-*'] = 1;
		}
	}
	$allow_html['div']['style'] = [ 'display' => 1 ];

	return array_merge( $allow_html, [
			'input'  => [
				'type'         => 1,
				'id'           => 1,
				'name'         => 1,
				'class'        => 1,
				'placeholder'  => 1,
				'autocomplete' => 1,
				'style'        => 1,
				'value'        => 1,
				'data-*'       => 1,
			],
			'option' => [ 'value' => 1 ],
			'style'  => [
				'type'  => 1,
				'id'    => 1,
				'name'  => 1,
				'class' => 1,
			],
			'meta'   => [ 'http-equiv' => 1, 'content' => 1, 'name' => 1 ]
		]
	);
}

function viwec_safe_kses_styles( $styles ) {
	$styles[] = 'display';

	return $styles;
}

function viwec_get_pro_version() {
	?>
    <a target="_blank" href="https://1.envato.market/BZZv1" class="viwec-get-pro-version vi-ui small button">
		<?php esc_html_e( 'Unlock this feature', 'viwec-email-template-customizer' ); ?>
    </a>
	<?php
}
if ( ! function_exists( 'villatheme_remove_object_filter' ) ) {
    /**
     * Remove an object filter.
     *
     * @param string $tag Hook name.
     * @param string $class Class name. Use 'Closure' for anonymous functions.
     * @param string|void $method Method name. Leave empty for anonymous functions.
     * @param string|int|void $priority Priority
     *
     * @return void
     */
    function villatheme_remove_object_filter( $tag, $class, $method = null, $priority = null ) {
        global $wp_filter;
        $filters = $wp_filter[ $tag ] ?? '';
        if ( empty ( $filters ) ) {
            return;
        }
        foreach ( $filters as $p => $filter ) {

            if ( ! is_null( $priority ) && ( (int) $priority !== (int) $p ) ) {
                continue;
            }
            $remove = false;
            foreach ( $filter as $identifier => $function ) {
                $function = $function['function'];
                if (
                        is_array( $function )
                        && (
                                is_a( $function[0], $class )
                                || ( is_array( $function ) && $function[0] === $class )
                        )
                ) {
                    $remove = ( $method && ( $method === $function[1] ) );
                } elseif ( $function instanceof Closure && $class === 'Closure' ) {
                    $remove = true;
                }
                if ( $remove ) {
                    $temp = $wp_filter[ $tag ][ $p ];
                    unset( $temp[ $identifier ] );
                    $wp_filter[ $tag ][ $p ] = $temp;
                }
            }
        }
    }
}