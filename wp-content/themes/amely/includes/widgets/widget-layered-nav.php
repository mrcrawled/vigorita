<?php
/**
 * Contact Information Widget
 * Add contact information to the footer
 *
 */

if ( ! class_exists( 'Amely_Layered_Nav_Widget' ) ) {

	add_action( 'widgets_init', 'load_amely_layered_nav_widget' );

	function load_amely_layered_nav_widget() {
		register_widget( 'Amely_Layered_Nav_Widget' );
	}

	/**
	 * Layered Nav Widget by ThemeMove
	 */
	class Amely_Layered_Nav_Widget extends WPH_Widget {

		/**
		 * Register widget with WordPress.
		 */
		function __construct() {

			$attribute_array      = array();
			$attribute_taxonomies = wc_get_attribute_taxonomies();

			if ( $attribute_taxonomies ) {
				foreach ( $attribute_taxonomies as $tax ) {
					$attribute_array[ $tax->attribute_name ] = $tax->attribute_name;
				}
			}

			// Configure widget array
			$args = array(
				'slug'        => 'tm_layered_nav',
				// Widget Backend label
				'label'       => '&#x1f3c1; &nbsp;' . esc_html__( 'AMELY WooCommerce Layered Nav',
						'amely' ),
				// Widget Backend Description
				'description' => esc_html__( 'Shows a custom attribute in a widget which lets you narrow down the list of products when viewing product categories.',
					'amely' ),
			);

			// Configure the widget fields
			$args['fields'] = array(

				array(
					'name'   => esc_html__( 'Title', 'amely' ),
					'id'     => 'title',
					'type'   => 'text',
					'class'  => 'widefat',
					'std'    => esc_html__( 'Filter by', 'amely' ),
					'filter' => 'strip_tags|esc_attr',
				),

				array(
					'name'    => esc_html__( 'Attribute', 'amely' ),
					'id'      => 'attribute',
					'type'    => 'select',
					'std'     => '',
					'options' => $attribute_array,
				),

				array(
					'id'      => 'query_type',
					'type'    => 'select',
					'std'     => 'and',
					'name'    => esc_html__( 'Query type', 'amely' ),
					'options' => array(
						esc_html__( 'AND', 'amely' ) => 'and',
						esc_html__( 'OR', 'amely' )  => 'or',
					),
				),

				array(
					'id'      => 'display_type',
					'type'    => 'select',
					'std'     => 'list',
					'name'    => esc_html__( 'Display type', 'amely' ),
					'options' => array(
						esc_html__( 'List', 'amely' )     => 'list',
						esc_html__( 'Inline', 'amely' )   => 'inline',
						esc_html__( 'Dropdown', 'amely' ) => 'dropdown',
					),
				),

				array(
					'id'      => 'labels',
					'type'    => 'select',
					'std'     => 'on',
					'name'    => esc_html__( 'Show labels', 'amely' ),
					'options' => array(
						esc_html__( 'ON', 'amely' )  => 'on',
						esc_html__( 'OFF', 'amely' ) => 'off',
					),
				),

				array(
					'id'      => 'items_count',
					'type'    => 'select',
					'std'     => 'on',
					'name'    => esc_html__( 'Show items count', 'amely' ),
					'options' => array(
						esc_html__( 'ON', 'amely' )  => 'on',
						esc_html__( 'OFF', 'amely' ) => 'off',
					),
				),
			);

			$this->create_widget( $args );
		}

		function widget( $args, $instance ) {

			if ( ! is_post_type_archive( 'product' ) && ! is_tax( get_object_taxonomies( 'product' ) ) ) {
				return;
			}

			$_chosen_attributes = WC_Query::get_layered_nav_chosen_attributes();
			$taxonomy           = isset( $instance['attribute'] ) ? wc_attribute_taxonomy_name( $instance['attribute'] ) : '';
			$query_type         = isset( $instance['query_type'] ) ? $instance['query_type'] : 'and';
			$display_type       = isset( $instance['display_type'] ) ? $instance['display_type'] : 'list';

			if ( ! taxonomy_exists( $taxonomy ) ) {
				return;
			}

			$get_terms_args = array( 'hide_empty' => '1' );

			$orderby = wc_attribute_orderby( $taxonomy );

			switch ( $orderby ) {
				case 'name' :
					$get_terms_args['orderby']    = 'name';
					$get_terms_args['menu_order'] = false;
					break;
				case 'id' :
					$get_terms_args['orderby']    = 'id';
					$get_terms_args['order']      = 'ASC';
					$get_terms_args['menu_order'] = false;
					break;
				case 'menu_order' :
					$get_terms_args['menu_order'] = 'ASC';
					break;
			}

			$terms = get_terms( $taxonomy, $get_terms_args );

			if ( 0 === sizeof( $terms ) ) {
				return;
			}

			switch ( $orderby ) {
				case 'name_num' :
					usort( $terms, '_wc_get_product_terms_name_num_usort_callback' );
					break;
				case 'parent' :
					usort( $terms, '_wc_get_product_terms_parent_usort_callback' );
					break;
			}

			ob_start();

			$title = isset( $instance['title'] ) ? $instance['title'] : '';

			echo '' . $args['before_widget'];

			echo '' . $title ? $args['before_title'] . $title . $args['after_title'] : '';

			if ( 'dropdown' === $display_type ) {
				$found = $this->layered_nav_dropdown( $terms, $taxonomy, $query_type );
			} else {
				$found = $this->layered_nav_list( $terms, $taxonomy, $query_type, $instance );
			}

			echo '' . $args['after_widget'];

			// Force found when option is selected - do not force found on taxonomy attributes
			if ( ! is_tax() && is_array( $_chosen_attributes ) && array_key_exists( $taxonomy, $_chosen_attributes ) ) {
				$found = true;
			}

			if ( ! $found ) {
				ob_end_clean();
			} else {
				echo ob_get_clean();
			}
		}

		/**
		 * Return the currently viewed taxonomy name.
		 *
		 * @return string
		 */
		protected function get_current_taxonomy() {
			return is_tax() ? get_queried_object()->taxonomy : '';
		}

		/**
		 * Return the currently viewed term ID.
		 *
		 * @return int
		 */
		protected function get_current_term_id() {
			return absint( is_tax() ? get_queried_object()->term_id : 0 );
		}

		/**
		 * Return the currently viewed term slug.
		 *
		 * @return int
		 */
		protected function get_current_term_slug() {
			return absint( is_tax() ? get_queried_object()->slug : 0 );
		}

		/**
		 * Show dropdown layered nav.
		 *
		 * @param  array $terms
		 * @param  string $taxonomy
		 * @param  string $query_type
		 *
		 * @return bool Will nav display?
		 */
		protected function layered_nav_dropdown( $terms, $taxonomy, $query_type ) {
			$found = false;

			if ( $taxonomy !== $this->get_current_taxonomy() ) {
				$term_counts          = $this->get_filtered_term_product_counts( wp_list_pluck( $terms, 'term_id' ),
					$taxonomy,
					$query_type );
				$_chosen_attributes   = WC_Query::get_layered_nav_chosen_attributes();
				$taxonomy_filter_name = str_replace( 'pa_', '', $taxonomy );
				$taxonomy_label       = wc_attribute_label( $taxonomy );
				$any_label            = apply_filters( 'woocommerce_layered_nav_any_label',
					sprintf( __( 'Any %s', 'amely' ), $taxonomy_label ),
					$taxonomy_label,
					$taxonomy );

				echo '<a href="#" class="filter-pseudo-link link-taxonomy-' . $taxonomy_filter_name . '">' . esc_html__( 'Apply filter',
						'amely' ) . '</a>';

				echo '<select class="dropdown_layered_nav_' . $taxonomy_filter_name . '" data-filter-url="' . preg_replace( '%\/page\/[0-9]+%',
						'',
						str_replace( array(
							'&amp;',
							'%2C',
						),
							array(
								'&',
								',',
							),
							esc_js( add_query_arg( 'filtering',
								'1',
								remove_query_arg( array(
									'page',
									'_pjax',
									'filter_' . $taxonomy_filter_name,
								) ) ) ) ) ) . "&filter_" . esc_js( $taxonomy_filter_name ) . "=AMELY_FILTER_VALUE" . '">';

				echo '<option value="">' . esc_html( $any_label ) . '</option>';

				foreach ( $terms as $term ) {

					// If on a term page, skip that term in widget list
					if ( $term->term_id === $this->get_current_term_id() ) {
						continue;
					}

					// Get count based on current view
					$current_values = isset( $_chosen_attributes[ $taxonomy ]['terms'] ) ? $_chosen_attributes[ $taxonomy ]['terms'] : array();
					$option_is_set  = in_array( $term->slug, $current_values );
					$count          = isset( $term_counts[ $term->term_id ] ) ? $term_counts[ $term->term_id ] : 0;

					// Only show options with count > 0
					if ( 0 < $count ) {
						$found = true;
					} elseif ( 0 === $count && ! $option_is_set ) {
						continue;
					}

					echo '<option value="' . esc_attr( $term->slug ) . '" ' . selected( $option_is_set,
							true,
							false ) . '>' . esc_html( $term->name ) . '</option>';

				}

				echo '</select>';
			}

			return $found;
		}

		/**
		 * Get current page URL for layered nav items.
		 *
		 * @param string $taxonomy
		 *
		 * @return string
		 */
		protected function get_page_base_url( $taxonomy ) {

			if ( defined( 'SHOP_IS_ON_FRONT' ) ) {
				$link = home_url();
			} elseif ( is_post_type_archive( 'product' ) || is_page( wc_get_page_id( 'shop' ) ) ) {
				$link = get_post_type_archive_link( 'product' );
			} elseif ( is_product_category() ) {
				$link = get_term_link( get_query_var( 'product_cat' ), 'product_cat' );
			} elseif ( is_product_tag() ) {
				$link = get_term_link( get_query_var( 'product_tag' ), 'product_tag' );
			} else {
				$queried_object = get_queried_object();
				$link           = get_term_link( $queried_object->slug, $queried_object->taxonomy );
			}

			// Min/Max
			if ( isset( $_GET['min_price'] ) ) {
				$link = add_query_arg( 'min_price', wc_clean( $_GET['min_price'] ), $link );
			}

			if ( isset( $_GET['max_price'] ) ) {
				$link = add_query_arg( 'max_price', wc_clean( $_GET['max_price'] ), $link );
			}

			// Orderby
			if ( isset( $_GET['orderby'] ) ) {
				$link = add_query_arg( 'orderby', wc_clean( $_GET['orderby'] ), $link );
			}

			/**
			 * Search Arg.
			 * To support quote characters, first they are decoded from &quot; entities, then URL encoded.
			 */
			if ( get_search_query() ) {
				$link = add_query_arg( 's', rawurlencode( wp_specialchars_decode( get_search_query() ) ), $link );
			}

			// Post Type Arg
			if ( isset( $_GET['post_type'] ) ) {
				$link = add_query_arg( 'post_type', wc_clean( $_GET['post_type'] ), $link );
			}

			// Min Rating Arg
			if ( isset( $_GET['rating_filter'] ) ) {
				$link = add_query_arg( 'rating_filter', wc_clean( $_GET['rating_filter'] ), $link );
			}

			// All current filters
			if ( $_chosen_attributes = WC_Query::get_layered_nav_chosen_attributes() ) {
				foreach ( $_chosen_attributes as $name => $data ) {
					if ( $name === $taxonomy ) {
						continue;
					}
					$filter_name = sanitize_title( str_replace( 'pa_', '', $name ) );
					if ( ! empty( $data['terms'] ) ) {
						$link = add_query_arg( 'filter_' . $filter_name, implode( ',', $data['terms'] ), $link );
					}
					if ( 'or' == $data['query_type'] ) {
						$link = add_query_arg( 'query_type_' . $filter_name, 'or', $link );
					}
				}
			}

			return $link;
		}

		/**
		 * Count products within certain terms, taking the main WP query into consideration.
		 *
		 * @param  array $term_ids
		 * @param  string $taxonomy
		 * @param  string $query_type
		 *
		 * @return array
		 */
		protected function get_filtered_term_product_counts( $term_ids, $taxonomy, $query_type ) {
			global $wpdb;

			$tax_query  = WC_Query::get_main_tax_query();
			$meta_query = WC_Query::get_main_meta_query();

			if ( 'or' === $query_type ) {
				foreach ( $tax_query as $key => $query ) {
					if ( is_array( $query ) && $taxonomy === $query['taxonomy'] ) {
						unset( $tax_query[ $key ] );
					}
				}
			}

			$meta_query     = new WP_Meta_Query( $meta_query );
			$tax_query      = new WP_Tax_Query( $tax_query );
			$meta_query_sql = $meta_query->get_sql( 'post', $wpdb->posts, 'ID' );
			$tax_query_sql  = $tax_query->get_sql( $wpdb->posts, 'ID' );

			// Generate query
			$query           = array();
			$query['select'] = "SELECT COUNT( DISTINCT {$wpdb->posts}.ID ) as term_count, terms.term_id as term_count_id";
			$query['from']   = "FROM {$wpdb->posts}";
			$query['join']   = "
			INNER JOIN {$wpdb->term_relationships} AS term_relationships ON {$wpdb->posts}.ID = term_relationships.object_id
			INNER JOIN {$wpdb->term_taxonomy} AS term_taxonomy USING( term_taxonomy_id )
			INNER JOIN {$wpdb->terms} AS terms USING( term_id )
			" . $tax_query_sql['join'] . $meta_query_sql['join'];

			$query['where'] = "
			WHERE {$wpdb->posts}.post_type IN ( 'product' )
			AND {$wpdb->posts}.post_status = 'publish'
			" . $tax_query_sql['where'] . $meta_query_sql['where'] . "
			AND terms.term_id IN (" . implode( ',', array_map( 'absint', $term_ids ) ) . ")
		";

			if ( $search = WC_Query::get_main_search_query_sql() ) {
				$query['where'] .= ' AND ' . $search;
			}

			$query['group_by'] = "GROUP BY terms.term_id";
			$query             = apply_filters( 'woocommerce_get_filtered_term_product_counts_query', $query );
			$query             = implode( ' ', $query );
			$results           = $wpdb->get_results( $query );

			return wp_list_pluck( $results, 'term_count', 'term_count_id' );
		}

		/**
		 * Show list based layered nav.
		 *
		 * @param  array $terms
		 * @param  string $taxonomy
		 * @param  string $query_type
		 *
		 * @return bool   Will nav display?
		 */
		protected function layered_nav_list( $terms, $taxonomy, $query_type, $instance ) {

			$labels       = isset( $instance['labels'] ) ? $instance['labels'] : 'on';
			$items_count  = isset( $instance['items_count'] ) ? $instance['items_count'] : 'on';
			$display_type = isset( $instance['display_type'] ) ? $instance['display_type'] : 'list';

			$class = 'show-labels-' . $labels;
			$class .= ' show-display-' . $display_type;
			$class .= ' show-items-count-' . $items_count;

			// List display
			echo '<ul class="' . esc_attr( $class ) . '">';

			$term_counts        = $this->get_filtered_term_product_counts( wp_list_pluck( $terms, 'term_id' ),
				$taxonomy,
				$query_type );
			$_chosen_attributes = WC_Query::get_layered_nav_chosen_attributes();
			$found              = false;

			foreach ( $terms as $term ) {

				$current_values = isset( $_chosen_attributes[ $taxonomy ]['terms'] ) ? $_chosen_attributes[ $taxonomy ]['terms'] : array();
				$option_is_set  = in_array( $term->slug, $current_values );

				$count = isset( $term_counts[ $term->term_id ] ) ? $term_counts[ $term->term_id ] : 0;

				// Skip the term for the current archive
				if ( $this->get_current_term_id() === $term->term_id ) {
					continue;
				}

				// Only show options with count > 0
				if ( 0 < $count ) {
					$found = true;
				} elseif ( 0 === $count && ! $option_is_set ) {
					continue;
				}

				$filter_name    = 'filter_' . sanitize_title( str_replace( 'pa_', '', $taxonomy ) );
				$current_filter = isset( $_GET[ $filter_name ] ) ? explode( ',',
					wc_clean( $_GET[ $filter_name ] ) ) : array();
				$current_filter = array_map( 'sanitize_title', $current_filter );

				if ( ! in_array( $term->slug, $current_filter ) ) {
					$current_filter[] = $term->slug;
				}

				$link = $this->get_page_base_url( $taxonomy );

				// Add current filters to URL.
				foreach ( $current_filter as $key => $value ) {
					// Exclude query arg for current term archive term
					if ( $value === $this->get_current_term_slug() ) {
						unset( $current_filter[ $key ] );
					}

					// Exclude self so filter can be unset on click.
					if ( $option_is_set && $value === $term->slug ) {
						unset( $current_filter[ $key ] );
					}
				}

				if ( ! empty( $current_filter ) ) {
					$link = add_query_arg( $filter_name, implode( ',', $current_filter ), $link );

					// Add Query type Arg to URL
					if ( 'or' === $query_type && ! ( 1 === sizeof( $current_filter ) && $option_is_set ) ) {
						$link = add_query_arg( 'query_type_' . sanitize_title( str_replace( 'pa_', '', $taxonomy ) ),
							'or',
							$link );
					}
				}

				$item_class = $option_is_set ? ' chosen' : '';

				// Add Swatches block
				$isw_settings = get_option( 'isw_settings' );
				$swatch_span  = $swatch_style = '';

				if ( class_exists( 'SitePress' ) ) {

					global $sitepress;

					if ( method_exists( $sitepress, 'get_default_language' ) ) {

						$default_language = $sitepress->get_default_language();
						$current_language = $sitepress->get_current_language();

						if ( $default_language != $current_language ) {
							$isw_settings = get_option( 'isw_settings_' . $current_language );
						}
					}
				}

				if ( isset( $isw_settings['isw_attr'] ) && is_array( $isw_settings['isw_attr'] ) && in_array( $taxonomy,
						$isw_settings['isw_attr'] )
				) {

					$isw_attr = $isw_settings['isw_attr'];

					if ( isset( $isw_settings['isw_style'] ) && is_array( $isw_settings['isw_style'] ) ) {
						$isw_style = $isw_settings['isw_style'];

						for ( $i = 0; $i < count( $isw_style ); $i ++ ) {

							if ( $taxonomy == $isw_attr[ $i ] ) {

								$tooltip = $isw_settings['isw_tooltip'][ $i ][ $term->slug ];

								switch ( $isw_style[ $i ] ) {

									case 'isw_color':
										$item_class .= ' swatch-color';

										if ( isset( $isw_settings['isw_custom'] ) && is_array( $isw_settings['isw_custom'] ) ) {

											$isw_custom = isset( $isw_settings['isw_custom'][ $i ] ) ? $isw_settings['isw_custom'][ $i ] : '';

											if ( is_array( $isw_custom ) ) {

												foreach ( $isw_custom as $key => $value ) {

													if ( $term->slug == $key ) {
														$swatch_style = 'background-color:' . $value . ';';
													}
												}
											}

											if ( ! empty( $swatch_style ) ) {
												$swatch_span = '<span class="filter-swatch hint--top hint--bounce" aria-label="' . esc_attr( $tooltip ? $tooltip : $term->name ) . '" style="' . $swatch_style . '"></span>';
											}
										}

										break;

									case 'isw_image':
										$item_class .= ' swatch-image';

										if ( isset( $isw_settings['isw_custom'] ) && is_array( $isw_settings['isw_custom'] ) ) {

											$isw_custom = isset( $isw_settings['isw_custom'][ $i ] ) ? $isw_settings['isw_custom'][ $i ] : '';

											if ( is_array( $isw_custom ) ) {

												foreach ( $isw_custom as $key => $value ) {

													if ( $term->slug == $key ) {

														$swatch_span = '<span class="filter-swatch hint--top hint--bounce" aria-label="' . esc_attr( $tooltip ? $tooltip : $term->name ) . '"><img src="' . esc_url( $value ) . '" alt="' . esc_attr( $term->slug ) . '"/></span>';
													}
												}
											}
										}

										break;

									case 'isw_html':
										$item_class .= ' swatch-html';

										if ( isset( $isw_settings['isw_custom'] ) && is_array( $isw_settings['isw_custom'] ) ) {

											$isw_custom = isset( $isw_settings['isw_custom'][ $i ] ) ? $isw_settings['isw_custom'][ $i ] : '';

											if ( is_array( $isw_custom ) ) {

												foreach ( $isw_custom as $key => $value ) {

													if ( $term->slug == $key ) {

														$swatch_span = '<span class="filter-swatch hint--top hint--bounce" aria-label="' . esc_attr( $tooltip ? $tooltip : $term->name ) . '">' . $value . '</span>';
													}
												}
											}
										}

										break;

									case 'isw_text':
									default:
										$item_class .= ' swatch-text';

										if ( isset( $isw_settings['isw_custom'] ) && is_array( $isw_settings['isw_custom'] ) ) {

											$isw_custom = isset( $isw_settings['isw_custom'][ $i ] ) ? $isw_settings['isw_custom'][ $i ] : '';

											if ( is_array( $isw_custom ) ) {

												foreach ( $isw_custom as $key => $value ) {

													if ( $term->slug == $key ) {

														$swatch_span = '<span class="filter-swatch hint--top hint--bounce" aria-label="' . esc_attr( $tooltip ? $tooltip : $term->name ) . '">' . $value . '</span>';
													}
												}
											}
										}

										break;
								}
							}
						}
					}
				} else {
					$item_class = ' no-swatch';
				}

				if ( $count > 0 || $option_is_set ) {
					$link      = esc_url( apply_filters( 'woocommerce_layered_nav_link', $link, $term, $taxonomy ) );
					$term_html = '<a href="' . $link . '">' . $swatch_span . '<span class="term-name">' . esc_html( $term->name ) . '</span>' . '</a>';
				} else {
					$link      = false;
					$term_html = '<span>' . $swatch_span . '<span class="term-name">' . esc_html( $term->name ) . '</span>';
				}

				if ( $items_count == 'on' ) {
					$term_html .= ' ' . apply_filters( 'woocommerce_layered_nav_count',
							'<span class="count">' . absint( $count ) . '</span>',
							$count,
							$term );
				};

				echo '<li class="wc-layered-nav-term' . esc_attr( $item_class ) . '">';
				echo apply_filters( 'woocommerce_layered_nav_term_html',
					$term_html,
					$term,
					$link,
					$count );
				echo '</li>';

			}

			echo '</ul>';

			return $found;
		}
	}
}
