<?php

/*
Plugin Name: WebPM Theme Switcher
Plugin URI: http://webplantmedia.com
Description: Allow your readers to switch themes.
Version: 1.0
Author: Chris Baldelomar
Author URI: http://webplantmedia.com

Adapted from Alex King's style switcher.
http://www.alexking.org/software/wordpress/

To use, add the "Theme Switcher" widget to your sidebar, 
or call wp_theme_switcher() directly, like so:

  <li>Themes:
	<?php wp_theme_switcher(); ?>
  </li>

This will create a list of themes for your readers to select.

If you would like a dropdown box rather than a list, add this:

  <li>Themes:
	<?php wp_theme_switcher('dropdown'); ?>
  </li>


*/ 

class ThemeSwitcherWidget extends WP_Widget {
	function ThemeSwitcherWidget()
	{
		return $this->WP_Widget('theme-switcher-widget', __('Theme Switcher Widget', 'theme-switcher'), array('description' => __('A widget with options for switching themes.', 'theme-switcher')));
	}

	function widget($args, $instance)
	{
		global $theme_switcher;
		$title = empty( $instance['title'] ) ? __('Theme Switcher', 'theme-switcher') : $instance['title'];
		echo $args['before_widget'];
		echo $args['before_title'] . $title . $args['after_title'];
		echo $theme_switcher->theme_switcher_markup($instance['displaytype'], $instance);
		echo $args['after_widget'];
	}

	function update($new_instance) 
	{
        $new_instance['title'] = trim( $new_instance['title'] );
        $new_instance['displaytype'] = trim( $new_instance['displaytype'] );
		return $new_instance;
	}

	function form($instance) 
	{
        $title = isset( $instance['title'] ) ? $instance['title'] : 'Themes';
        $type = isset( $instance['displaytype'] ) ? $instance['displaytype'] : '';
        //pr($instance);
		?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>">
				<span><?php _e('Title:', 'theme-switcher'); ?></span>
				<input type="text" name="<?php echo $this->get_field_name('title'); ?>" id="<?php echo $this->get_field_id('title'); ?>" value="<?php echo esc_attr($title); ?>" />
			</label>
		</p>
			
		<p><label for="<?php echo $this->get_field_id('displaytype'); ?>"><?php _e('Display themes as:', 'theme-switcher'); ?></label></p>
		<p>
			<span><input type="radio" name="<?php echo $this->get_field_name('displaytype'); ?>" value="list" <?php
				if ( 'list' == $type ) {
					echo ' checked="checked"';
				}
			?> /> <?php _e('List', 'theme-switcher'); ?></span>
			<span><input type="radio" name="<?php echo $this->get_field_name('displaytype'); ?>" value="dropdown" <?php 
				if ( 'dropdown' == $type ) {
					echo ' checked="checked"';
				}
			?>/> <?php _e('Dropdown', 'theme-switcher'); ?></span>
		</p>
		<?php
	}
}

class ThemeSwitcher {

    private $stylesheet;
    private $template;
    private $optionsframework_id;

	function __construct()
	{
        $this->theme_switcher_init();
		add_action('init', array(&$this, 'set_theme_cookie'));
		//add_action('init', array(&$this, 'theme_switcher_init'));
		add_action('widgets_init', array(&$this, 'event_widgets_init'));
		
        add_filter('option_optionsframework', array(&$this, 'get_optionsframework'));
        add_filter('stylesheet', array(&$this, 'get_stylesheet'));
        add_filter('option_stylesheet', array(&$this, 'get_stylesheet'));
		add_filter('template', array(&$this, 'get_template'));
	}

	function event_widgets_init()
	{
		register_widget('ThemeSwitcherWidget');
	}
	
	function get_optionsframework($array) {
        if ( !empty( $this->optionsframework_id ) )
            $array['id'] = $this->optionsframework_id;

        return $array;
	}

	function get_stylesheet($stylesheet = '') {
        if ( !empty( $this->stylesheet ) ) {
            return $this->stylesheet;
        }
        else {
            return $stylesheet;
        }
	}

	function get_template($template) {
        if ( !empty( $this->template ) )
            return $this->template;
        else
            return $template;
	}

	function get_theme() {
		if ( ! empty($_COOKIE["wptheme" . COOKIEHASH] ) ) {
			return $_COOKIE["wptheme" . COOKIEHASH];
		} else {
			return '';
		}
	}

	function theme_switcher_init() {
        $valid_theme = true;

		$theme = $this->get_theme();

		if (empty($theme)) {
			$valid_theme = false;
		}

		$theme = wp_get_theme($theme);

		// Don't let people peek at unpublished themes.
		if (isset($theme['Status']) && $theme['Status'] != 'publish')
			$valid_theme = false;
		
		if (empty($theme)) {
			$valid_theme = false;
		}
        
        if ( $valid_theme ) {
            $this->stylesheet = $theme['Stylesheet'];
            $this->template = $theme['Template'];
            $option_id = preg_replace("/\W/", "_", strtolower($theme['Stylesheet']) );
            $this->optionsframework_id = 'of_' . $option_id;
        }
    }

	function set_theme_cookie() {
		load_plugin_textdomain('theme-switcher');
		$expire = time() + 30000000;
		if ( ! empty($_GET["wptheme"] ) ) {
			setcookie(
				"wptheme" . COOKIEHASH,
				stripslashes($_GET["wptheme"]),
				$expire,
				COOKIEPATH
			);
			$redirect = remove_query_arg('wptheme');
			wp_redirect($redirect);
			exit;
		}

        $host = explode(".",$_SERVER['HTTP_HOST']);
        if (sizeof($host) == 3 ) {
            $subdomain = array_shift($host);
            if ($subdomain != 'demo') {
                $domain = array_shift($host);
                if ( !empty($subdomain) && !empty($domain) ) {
                    $url = home_url('/').'?wptheme='.$domain.'-'.$subdomain;
                    wp_redirect($url);
                    exit;
                }
            }
        }
	}
	
	function theme_switcher_markup($style = "text", $instance = array()) {
		if ( ! $theme_data = wp_cache_get('themes-data', 'theme-switcher') ) {
			$themes = (array) wp_get_themes();
			if ( function_exists('is_site_admin') ) {
				$allowed_themes = (array) get_site_option( 'allowedthemes' );
				foreach( $themes as $key => $theme ) {
				    if( isset( $allowed_themes[ wp_specialchars( $theme[ 'Stylesheet' ] ) ] ) == false ) {
						unset( $themes[ $key ] );
				    }
				}
			}

			$default_theme = wp_get_theme();

			$theme_data = array();
			foreach ((array) $themes as $theme_name => $data) {
				// Skip unpublished themes.
				if (empty($theme_name) || isset($themes[$theme_name]['Status']) && $themes[$theme_name]['Status'] != 'publish')
					continue;
				$theme_data[add_query_arg('wptheme', $theme_name, get_option('home'))] = $data['Name'];
			}
			
			asort($theme_data);

			wp_cache_set('themes-data', $theme_data, 'theme-switcher');
		}

		$ts = '<ul id="themeswitcher">'."\n";		

		if ( $style == 'dropdown' ) {
			$ts .= '<li>' . "\n\t" . '<select name="themeswitcher" onchange="location.href=this.options[this.selectedIndex].value;">'."\n";
		}

		foreach ($theme_data as $url => $theme_name) {
			if (
				! empty($_COOKIE["wptheme" . COOKIEHASH]) && $_COOKIE["wptheme" . COOKIEHASH] == $theme_name ||
				empty($_COOKIE["wptheme" . COOKIEHASH]) && ($theme_name == $default_theme)
			) {
				$pattern = 'dropdown' == $style ? '<option value="%1$s" selected="selected">%2$s</option>' : '<li>%2$s</li>';
			} else {
				$pattern = 'dropdown' == $style ? '<option value="%1$s">%2$s</option>' : '<li><a href="%1$s">%2$s</a></li>';
			}				
			$ts .= sprintf($pattern,
				esc_attr($url),
				esc_html($theme_name)
			);

		}

		if ( 'dropdown' == $style ) {
			$ts .= "</select>\n</li>\n";
		}
		$ts .= '</ul>';
		return $ts;
	}
}

$theme_switcher = new ThemeSwitcher();

function wp_theme_switcher($type = '')
{
	global $theme_switcher;
	echo $theme_switcher->theme_switcher_markup($type);
}
