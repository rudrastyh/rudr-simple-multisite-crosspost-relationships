<?php
/*
 * Plugin name: Simple Multisite Crossposting – Relationships Custom Fields
 * Author: Misha Rudrastyh
 * Author URI: https://rudrastyh.com
 * Description: Allows to crosspost post IDs and term IDs in custom fields
 * Plugin URI: https://rudrastyh.com/support/crossposting-relationships-fields
 * Version: 1.2
 * Network: true
 */

class Rudr_SMC_Relationships {

	function __construct() {

		add_filter( 'rudr_pre_crosspost_meta', array( $this, 'process_meta' ), 10, 3 );
		add_filter( 'rudr_pre_crosspost_termmeta', array( $this, 'process_meta' ), 10, 3 );

	}

	function process_meta( $meta_value, $meta_key, $object_id ) {

		if( ! class_exists( 'Rudr_Simple_Multisite_Crosspost' ) ) {
			return $meta_value;
		}

		// not an attachment custom field
		$post_relationship_meta_keys = apply_filters( 'rudr_crosspost_post_relationship_meta_keys', array() );
		if(
			in_array( $meta_key, $post_relationship_meta_keys )
			// Pods support
			|| 0 === strpos( $meta_key, '_pods_' ) && in_array( str_replace( '_pods_', '', $meta_key ), $post_relationship_meta_keys )
		) {
			return $this->process_post_relationships( $meta_value );
		}

		$term_relationship_meta_keys = apply_filters( 'rudr_crosspost_term_relationship_meta_keys', array() );
		if(
			in_array( $meta_key, $term_relationship_meta_keys )
			// Pods support
			|| 0 === strpos( $meta_key, '_pods_' ) && in_array( str_replace( '_pods_', '', $meta_key ), $term_relationship_meta_keys )
		) {
			return $this->process_term_relationships( $meta_value );
		}

		return $meta_value;

	}

	private function process_post_relationships( $meta_value ) {

		$meta_value = maybe_unserialize( $meta_value );
		$is_comma_separated = false;
		// let's make it array anyway for easier processing
		if( is_array( $meta_value ) ) {
			$ids = $meta_value;
		} elseif( false !== strpos( $meta_value, ',' ) ) {
			$is_comma_separated = true;
			$ids = array_map( 'trim', explode( ',', $meta_value ) );
		} else {
			$ids = array( $meta_value );
		}
		$new_blog_id = get_current_blog_id();
		restore_current_blog();

		$crossposted_ids = array();
		$crossposted_skus = array(); // we will process it after switching to a new blog
		foreach( $ids as $id ) {
			$post_type = get_post_type( $id );
			if( 'product' === $post_type && 'sku' === Rudr_Simple_Multisite_Woo_Crosspost::connection_type() ) {
				$crossposted_skus[] = get_post_meta( $id, '_sku', true );
			} else {
				if( $new_id = Rudr_Simple_Multisite_Crosspost::is_crossposted( $id, $new_blog_id ) ) {
					$crossposted_ids[] = $new_id;
				}
			}
		}

		switch_to_blog( $new_blog_id );

		// do we have some crossposted SKUs here? let's check if there are some in a new blog
		if( $crossposted_skus ) {
			foreach( $crossposted_skus as $crossposted_sku ) {
				if( $new_id = Rudr_Simple_Multisite_Woo_Crosspost::maybe_is_crossposted_product__sku( array( 'sku' => $crossposted_sku ) ) ) {
					$crossposted_ids[] = $new_id;
				}
			}
		}

		if( is_array( $meta_value ) ) {
			return maybe_serialize( $crossposted_ids );
		} elseif( $crossposted_ids ) {
			return $is_comma_separated ? join( ',', $crossposted_ids ) : reset( $crossposted_ids );
		} else {
			return 0;
		}

	}

	private function process_term_relationships( $meta_value ) {

		// can be either int or a serialized array
		$meta_value = maybe_unserialize( $meta_value );
		$is_comma_separated = false;
		// let's make it array anyway for easier processing
		if( is_array( $meta_value ) ) {
			$ids = $meta_value;
		} elseif( false !== strpos( $meta_value, ',' ) ) {
			$is_comma_separated = true;
			$ids = array_map( 'trim', explode( ',', $meta_value ) );
		} else {
			$ids = array( $meta_value );
		}
		$new_blog_id = get_current_blog_id();
		restore_current_blog();

		$terms_data = array();
		foreach( $ids as $id ) {
			$term = get_term( $id );
			if( ! $term ) {
				continue;
			}
			$terms_data[] = array( 'id' => $id, 'slug' => $term->slug, 'taxonomy' => $term->taxonomy );
		}

		switch_to_blog( $new_blog_id );

		$crossposted_term_ids = array();
		foreach( $terms_data as $term_data ) {
			$crossposted_term = get_term_by( 'slug', $term_data[ 'slug' ], $term_data[ 'taxonomy' ] );
			if( $crossposted_term ) {
				$crossposted_term_ids[] = $crossposted_term->term_id;
			}
		}

		if( is_array( $meta_value ) ) {
			return maybe_serialize( $crossposted_term_ids );
		} elseif( $crossposted_term_ids ) {
			return $is_comma_separated ? join( ',', $crossposted_term_ids ) : reset( $crossposted_term_ids );
		} else {
			return 0;
		}

	}


}

new Rudr_SMC_Relationships;
