<?php
/**
 * Plugin Name: By Lazy Load
 * Description: By Lazy Load for images, videos, iframes. With lightweight script instantly improve your sites load time. Simple use.
 * Version: 1.0.0
 * Author: Bayu Prahasto
 * Author URI: http://obaytek.com
 * License: GPL2
 */

if ( !defined('ABSPATH') ) exit;

define( 'BY_LZ_PLACEHOLDER_IMG', 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
define( 'BY_LZ_SKIP_CLASSES', '');

if(!class_exists('OBayTekWPLazyLoad')) :
class OBayTekWPLazyLoad {

    /**
     * Instance of the object.
     * 
     * @since  1.0.0
     * @static
     * @access public
     * @var null|object
     */
    public static $instance = null;

    /**
     * Access the single instance of this class.
     *
     * @since  1.0.0
     * @return OBayTekWPLazyLoad
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     * 
     * @since  1.0.0
     * @return OBayTekWPLazyLoad
     */
    private function __construct(){
        add_action( 'wp', array( $this, 'init' ), 99 ); // run this as late as possible
    }

	/**
	 * Initialize the setup
	 */
	public function init() {

		/* We do not touch the feeds / admin */
		if ( is_feed() || is_admin()) {
			return;
		}

		/**
		 * Filter to let plugins decide whether the plugin should run for this request or not
		 *
		 */
		if ( $this->by_lz_compat() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			$this->_setup_filtering();
		}
	}

    private function by_lz_compat() {
        if ( function_exists( 'mopr_get_option' ) && WP_CONTENT_DIR . mopr_get_option( 'mobile_theme_root', 1 ) == get_theme_root() ) {
            return false;
        }
        if ( function_exists( 'bnc_wptouch_is_mobile' ) || defined( 'WPTOUCH_VERSION' ) ) {
            return false;
        }
        if ( 1 == intval( get_query_var( 'print' ) ) || 1 == intval( get_query_var( 'printpage' ) ) ) {
            return false;
        }
        if ( isset( $_SERVER['HTTP_USER_AGENT'] ) && false !== strpos( $_SERVER['HTTP_USER_AGENT'], 'Opera Mini' ) ) {
            return false;
        }
        return true;
    }

	/**
	 * Enqueue styles
	 */
	public function enqueue_styles() {

	}

	/**
	 * Enqueue scripts
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'BY_LAZY_LOAD', plugin_dir_url( __FILE__ ).'/assets/by-lazy-load.js', null, 1, true );

		$by_lz_option = array();
        $by_lz_option['threshold'] = 500;
		wp_localize_script( 'BY_LAZY_LOAD', 'by_lz_option', $by_lz_option );
	}
    
	private function _setup_filtering() {

        add_filter( 'by_lz_filter', array( $this, 'filter_images' ) );
        add_filter( 'by_lz_filter', array( $this, 'filter_iframes' ) );
        add_filter( 'the_content', array( $this, 'filter' ), 10 );
        add_filter( 'widget_text', array( $this, 'filter' ), 200 );
        add_filter( 'post_thumbnail_html', array( $this, 'filter' ), 200 );
		add_filter( 'get_avatar', array( $this, 'filter' ), 200 );
    }
    
	/**
	 * Filter HTML content. Replace supported content with placeholders.
	 *
	 * @param string $content The HTML string to filter
	 * @return string The filtered HTML string
	 */
	public function filter( $content ) {
		/**
		 * Filter the content
		 *
		 * @param string $content The HTML string to filter
		 */
		$content = apply_filters( 'by_lz_filter', $content );

		return $content;
	}
    
   	/**
	 * Replace images with placeholders in the content
	 *
	 * @param string $content The HTML to do the filtering on
	 * @return string The HTML with the images replaced
	 */
	public function filter_images( $content ) {
		if(!$content) return $content;

		$match_content = $this->_get_content_haystack( $content );

		$matches = array();
		preg_match_all( '/<img[\s\r\n]+.*?>/is', $match_content, $matches );
		
		$search = array();
		$replace = array();

		foreach ( $matches[0] as $imgHTML ) {
			
			// don't do the replacement if the image is a data-uri
			if ( ! preg_match( "/src=['\"]data:image/is", $imgHTML ) ) {
				
				$placeholder_url_used = BY_LZ_PLACEHOLDER_IMG;
				// use low res preview image as placeholder if applicable
				if( preg_match( '/class=["\'].*?wp-image-([0-9]*)/is', $imgHTML, $id_matches ) ) {
					$img_id = intval($id_matches[1]);
					$tiny_img_data  = wp_get_attachment_image_src( $img_id, 'tiny-lazy' );
					$tiny_url = $tiny_img_data[0];
					//$placeholder_url_used = $tiny_url;
				}

				// replace the src and add the data-src attribute
				$replaceHTML = preg_replace( '/<img(.*?)src=/is', '<img$1src="' . esc_attr( $placeholder_url_used ) . '" data-lazy-type="image" data-lazy-src=', $imgHTML );
				
				// also replace the srcset (responsive images)
				$replaceHTML = str_replace( 'srcset', 'data-lazy-srcset', $replaceHTML );
				// replace sizes to avoid w3c errors for missing srcset
				$replaceHTML = str_replace( 'sizes', 'data-lazy-sizes', $replaceHTML );
				
				// add the lazy class to the img element
				if ( preg_match( '/class=["\']/i', $replaceHTML ) ) {
					$replaceHTML = preg_replace( '/class=(["\'])(.*?)["\']/is', 'class=$1lazy lazy-hidden $2$1', $replaceHTML );
				} else {
					$replaceHTML = preg_replace( '/<img/is', '<img class="lazy lazy-hidden"', $replaceHTML );
				}
				
				$replaceHTML .= '<noscript>' . $imgHTML . '</noscript>';
				
				array_push( $search, $imgHTML );
				array_push( $replace, $replaceHTML );
			}
		}

		$content = str_replace( $search, $replace, $content );

		return $content;

    }
    
	/**
	 * Replace iframes with placeholders in the content
	 *
	 * @param string $content The HTML to do the filtering on
	 * @return string The HTML with the iframes replaced
	 */
	public function filter_iframes( $content ) {

		$match_content = $this->_get_content_haystack( $content );

		$matches = array();
		preg_match_all( '|<iframe\s+.*?</iframe>|si', $match_content, $matches );
		
		$search = array();
		$replace = array();
		
		foreach ( $matches[0] as $iframeHTML ) {

			// Don't mess with the Gravity Forms ajax iframe
			if ( strpos( $iframeHTML, 'gform_ajax_frame' ) ) {
				continue;
			}

			$replaceHTML = '<img src="' . esc_attr( BY_LZ_PLACEHOLDER_IMG ) . '"  class="lazy lazy-hidden" data-lazy-type="iframe" data-lazy-src="' . esc_attr( $iframeHTML ) . '" alt="">';
			
			$replaceHTML .= '<noscript>' . $iframeHTML . '</noscript>';
			
			array_push( $search, $iframeHTML );
			array_push( $replace, $replaceHTML );
		}
		
		$content = str_replace( $search, $replace, $content );

		return $content;

    }
    
	/**
	 * Remove elements we don’t want to filter from the HTML string
	 *
	 * We’re reducing the haystack by removing the hay we know we don’t want to look for needles in
	 *
	 * @param string $content The HTML string
	 * @return string The HTML string without the unwanted elements
	 */
	protected function _get_content_haystack( $content ) {
		$content = $this->remove_noscript( $content );
		$content = $this->remove_skip_classes_elements( $content );

		return $content;
    }
    
	/**
	 * Remove <noscript> elements from HTML string
	 *
	 * @author sigginet
	 * @param string $content The HTML string
	 * @return string The HTML string without <noscript> elements
	 */
	public function remove_noscript( $content ) {
		return preg_replace( '/<noscript.*?(\/noscript>)/i', '', $content );
    }
    
	/**
	 * Remove HTML elements with certain classnames (or IDs) from HTML string
	 *
	 * @param string $content The HTML string
	 * @return string The HTML string without the unwanted elements
	 */
	public function remove_skip_classes_elements( $content ) {

		$skip_classes = $this->_get_skip_classes( 'html' );

		/*
		http://stackoverflow.com/questions/1732348/regex-match-open-tags-except-xhtml-self-contained-tags/1732454#1732454
		We can’t do this, but we still do it.
		*/
		$skip_classes_quoted = array_map( 'preg_quote', $skip_classes );
		$skip_classes_ORed = implode( '|', $skip_classes_quoted );

		$regex = '/<\s*\w*\s*class\s*=\s*[\'"](|.*\s)' . $skip_classes_ORed . '(|\s.*)[\'"].*>/isU';

		return preg_replace( $regex, '', $content );
    }
    
	/**
	 * Get the skip classes
	 *
	 * @param string $content_type The content type (image/iframe etc)
	 * @return array An array of strings with the class names
	 */
	protected function _get_skip_classes( $content_type ) {

		$skip_classes = array();

		$skip_classes_str = BY_LZ_SKIP_CLASSES;
		
		if ( strlen( trim( $skip_classes_str ) ) ) {
			$skip_classes = array_map( 'trim', explode( ',', $skip_classes_str ) );
		}

		if ( ! in_array( 'lazy', $skip_classes ) ) {
			$skip_classes[] = 'lazy';
		}

		/**
		 * Filter the class names to skip
		 *
		 * @param array $skip_classes The current classes to skip
		 * @param string $content_type The current content type
		 */
		$skip_classes = apply_filters( 'by_lz_skip_classes', $skip_classes, $content_type );
		
		return $skip_classes;
	}

}
endif;

// Init
OBayTekWPLazyLoad::get_instance();