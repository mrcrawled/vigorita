<?php

namespace MABEL_SI\Core\Common\Managers{

	use MABEL_SI\Core\Common\Linq\Enumerable;

	class Script_Style_Manager {

		private static $scripts = array();
		private static $inline_styles = array();
		private static $styles = array();
		private static $script_variables = array();
		public static $frontend_js_var = 'mabel_script_vars';

		public static function add_style($id,$file) {
			self::$styles [] = array(
				'id' => $id,
				'file' => $file
			);
		}

		public static function add_script($id,$file,$dependencies = array()) {
			self::$scripts[] = array(
				'id' => $id,
				'file' => $file,
				'dependencies' => is_array($dependencies) ? $dependencies : explode(',',$dependencies)
			);
		}

		public static function add_inline_style($handle, $css_rule, $styles)
		{
			self::$inline_styles[$handle][] = array(
				'css_rule' => $css_rule,
				'styling' => $styles
			);
		}

		public static function publish_inline_styles() {
			foreach(self::$inline_styles as $handle => $styles) {
				$styles_array = Enumerable::from($styles)->select(function($s) {
					if(is_string($s['styling']))
						$css_str = $s['styling'];
					else
					$css_str = Enumerable::from($s['styling'])->join(function($v,$k){
						return $k.':'.$v.';';
					},'');
					return $s['css_rule'] . '{' . wp_strip_all_tags($css_str) . '}';
				})->toArray();
				wp_add_inline_style( $handle, join('',$styles_array) );
			}

		}

		public static function add_script_variable($key, $val)
		{
			self::$script_variables[$key] = $val;
		}

		public static function register_scripts() {
			foreach(self::$scripts as $script) {
				wp_register_script(
					$script['id'],
					Config_Manager::$url . $script['file'],
					$script['dependencies'],
					Config_Manager::$version
				);
			}
		}
		public static function register_styles(){
			foreach(self::$styles as $style) {
				wp_register_style(
					$style['id'],
					Config_Manager::$url . $style['file'],
					array(),
					Config_Manager::$version
				);
			}
		}

		public static function publish_script($id) {

			if(!wp_script_is($id,'enqueued'))
				wp_enqueue_script($id);
		}
		public static function publish_style($id) {
			if(!wp_style_is($id,'enqueued'))
				wp_enqueue_style($id);
		}
		public static function publish_scripts(){
			foreach(self::$scripts as $script) {
				if(!wp_script_is($script['id'],'enqueued'))
					wp_enqueue_script($script['id']);
			}
		}
		public static function publish_styles() {
			foreach(self::$styles as $style) {
				if(!wp_style_is($style['id'],'enqueued'))
					wp_enqueue_style($style['id']);
			}
		}

		public static function add_script_vars() {
			if(sizeof(self::$script_variables) > 0) {
				wp_localize_script(Config_Manager::$slug, self::$frontend_js_var, self::$script_variables);
			}
		}

	}
}