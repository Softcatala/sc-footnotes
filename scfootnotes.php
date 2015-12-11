<?php
/*
Plugin Name: SC Footnotes
Plugin URI: https://github.com/Softcatala/wp-theme-mover
Description: Elegant and easy to use footnotes. Based on John Watson fd-footnotes plugin (http://flagrantdisregard.com)
Version: 0.0.1
Author: Pau Iranzo
Author URI: http://www.softcatala.org

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 3
of the License, or any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
*/

define('SCFOOTNOTE_TEXTDOMAIN', 'scfootnote');

if (function_exists('load_plugin_textdomain')) {
	load_plugin_textdomain(SCFOOTNOTE_TEXTDOMAIN, false, dirname(plugin_basename(__FILE__)).'/languages' );
}

add_action('admin_menu', 'scfootnote_config_page');
add_action('wp_enqueue_scripts', 'scfootnote_enqueue_scripts');

function scfootnote_enqueue_scripts() {
	if (is_admin()) return;

	wp_enqueue_script('jquery');
	wp_enqueue_script( 'scfootnote_script', plugins_url( 'scfootnotes.js', __FILE__ ), array('jquery'), '1.34');
	wp_enqueue_script('scfootnote_script');
}

function scfootnote_config_page() {
	global $wpdb;
	register_setting( 'scfootnote-group', 'scfootnote_collapse' );
	register_setting( 'scfootnote-group', 'scfootnote_single' );
	register_setting( 'scfootnote-group', 'scfootnote_style' );

	if ( function_exists('add_submenu_page') )
		add_submenu_page('options-general.php',
			__('Footnotes', SCFOOTNOTE_TEXTDOMAIN),
			__('Footnotes', SCFOOTNOTE_TEXTDOMAIN),
			'manage_options', __FILE__, 'scfootnote_conf');
}

function scfootnote_conf() {
	$options['scfootnote_single'] = esc_attr( get_option('scfootnote_single') );
	$options['scfootnote_collapse'] = esc_attr( get_option('scfootnote_collapse') );
	$options['scfootnote_style'] = esc_attr( get_option('scfootnote_style') );

	?>

	<div class="wrap">

	<h2>Footnotes Settings</h2>
	<form method="post" action="options.php">
	<?php settings_fields('scfootnote-group' ); ?>
	<?php do_settings_sections( 'scfootnote-group' ); ?>
	<div id="knowledge-graph" class="wpseotab active">
	<h3>General</h3>
	<p>
		<input id="scfootnote_single" name="scfootnote_single" type="checkbox" value="1"<?php if ($options['scfootnote_single']==1) echo ' checked'; ?> />
		<label for="scfootnote_single"><?php _e('Only show footnotes on single post/page', SCFOOTNOTE_TEXTDOMAIN); ?></label>
	</p>

	<p>
		<input id="scfootnote_collapse" name="scfootnote_collapse" type="checkbox" value="1"<?php if ($options['scfootnote_collapse']==1) echo ' checked'; ?> />
		<label for="scfootnote_collapse"><?php _e('Collapse footnotes until clicked', SCFOOTNOTE_TEXTDOMAIN); ?></label>
	</p>

	<h3>Display</h3>
	<p>
		<input id="scfootnote_style_default" name="scfootnote_style" type="radio" value="default"<?php if ($options['scfootnote_style']=='default') echo ' checked'; ?> />
		<label for="scfootnote_style_default"><?php _e('Use default style based on a numeric list', SCFOOTNOTE_TEXTDOMAIN); ?></label>
	</p>

	<p>
		<input id="scfootnote_style_custom" name="scfootnote_style" type="radio" value="custom"<?php if ($options['scfootnote_style']=='custom') echo ' checked'; ?> />
		<label for="scfootnote_style_custom"><?php _e('Use custom style adding a footer section with a numeric style', SCFOOTNOTE_TEXTDOMAIN); ?></label>
	</p>

	<?php submit_button(); ?>

	<?php
}

// Converts footnote markup into actual footnotes
function scfootnote_convert($content) {
	$options['scfootnote_single'] = esc_attr( get_option('scfootnote_single') );
	$options['scfootnote_collapse'] = esc_attr( get_option('scfootnote_collapse') );
	$options['scfootnote_style'] = esc_attr( get_option('scfootnote_style') );

	$collapse = 0;
	$single = 0;
	$linksingle = false;
	if (isset($options['scfootnote_collapse'])) $collapse = $options['scfootnote_collapse'];
	if (isset($options['scfootnote_single'])) $single = $options['scfootnote_single'];
	if (!is_page() && !is_single() && $single) $linksingle = true;

	$post_id = get_the_ID();

	$n = 1;
	$notes = array();
	if (preg_match_all('/\[(\d+\..*?)\]/s', $content, $matches)) {
		foreach($matches[0] as $fn) {
			$note = preg_replace('/\[\d+\.(.*?)\]/s', '\1', $fn);
			$notes[$n] = $note;

			$singleurl = '';
			if ($linksingle) $singleurl = get_permalink();

			$content = str_replace($fn, "<sup class='footnote'><a href='$singleurl#fn-$post_id-$n' id='fnref-$post_id-$n' onclick='return scfootnote_show($post_id)'>$n</a></sup>", $content);
			$n++;
		}

		// *****************************************************************************************************
		// Workaround for wpautop() bug. Otherwise it sometimes inserts an opening <p> but not the closing </p>.
		// There are a bunch of open wpautop tickets. See 4298 and 7988 in particular.
		$content .= "\n\n";
		// *****************************************************************************************************

		if (!$linksingle) {
			switch( $options['scfootnote_style'] ) {
				case 'custom':
					$content .= '</section>';
					$content .= '<footer class="contingut-footer">
								 <hr>
								 <div class="alerta">

					';
					for ($i = 1; $i < $n; $i++) {
						$content .= '<p id="fn-'.$post_id.'-'.$i.'"><strong><a href="#fnref-'.$post_id.'-'.$i.'">'.$i.'.</a></strong>'. $notes[$i] .'<p>';
					}
					$content .= '</div></footer><section>';
					break;
				default:
					if ($options['scfootnote_style'] == 'default') {
						$content .= "<div class='footnotes' id='footnotes-$post_id'>";
						$content .= "<div class='footnotedivider'></div>";

						if ($collapse) {
							$content .= "<a href='#' onclick='return scfootnote_togglevisible($post_id)' class='footnotetoggle'>";
							$content .= "<span class='footnoteshow'>" . sprintf(_n('Show %d footnote', 'Show %d footnotes', $n - 1, SCFOOTNOTE_TEXTDOMAIN), $n - 1) . "</span>";
							$content .= "</a>";

							$content .= "<ol style='display: none'>";
						} else {
							$content .= "<ol>";
						}
						for ($i = 1; $i < $n; $i++) {
							$content .= "<li id='fn-$post_id-$i'>$notes[$i] <span class='footnotereverse'><a href='#fnref-$post_id-$i'>&#8617;</a></span></li>";
						}
						$content .= "</ol>";
						$content .= "</div>";
					}
					break;
			}
		}
	}

	return($content);
}

add_action('the_content', 'scfootnote_convert', 1);
?>