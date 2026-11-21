<?php
/*
Plugin Name: Pz-Talk
Plugin URI: https://popozure.info
Description: 顔アイコンと吹き出しを表示させて会話を表現できます。
Version: 1.1.3
Author: poporon
Author URI: https://popozure.info
*/
class Pz_Talk {
	public	$slug;
	public	$text_domain;		// as slug
	
	public	$plugin_basename;
	public	$plugin_dir_path;
	public	$plugin_dir_url;
	
	public $options;
	
	protected	$defaults = array(
			'code1'				=>	'talk',
			'code2'				=>	'message',
			'message-me-right'	=>	'1',
			'talk-me-right'		=>	null,
			'flg-edit-insert'	=>	'1',
			'flg-adminbar'		=>	'1',
			'flg-debug'			=>	null,
			'plugin-name'		=>	'Pz-Talk',
			'plugin-version'	=>	'1.1.2'
		);

	function __construct() {
		$this->slug = basename(dirname(__FILE__));
		$this->text_domain		= $this->slug;
		
		$this->plugin_basename	= plugin_basename(__FILE__);
		$this->plugin_dir_path	= plugin_dir_path(__FILE__);
		$this->plugin_dir_url	= plugin_dir_url (__FILE__);
		
		$options = get_option('pz_talk_options', $this->defaults );
		$this->options = $this->normalize_options($options);
		if ($options !== $this->options) {
			update_option('pz_talk_options', $this->options);
		}
		
		// 日本語化
		load_plugin_textdomain($this->text_domain, false, $this->slug.'/languages');
		
		// ショートコードの設定
		if ($this->options['code1']) {
			add_shortcode($this->options['code1'], array($this, 'shortcode1'));				// ショートコードの設定
		}
		if ($this->options['code2']) {
			add_shortcode($this->options['code2'], array($this, 'shortcode2'));				// ショートコードの設定
		}
		add_action('wp_enqueue_scripts', array($this, 'enqueue'));							// スクリプト・CSSの設定
		
		if ($this->options['flg-debug']) {
			add_action('wp_footer', array($this, 'footer'));								// デバッグ情報
		}
		
		add_action('wp_before_admin_bar_render', array($this, 'admin_bar_menu'), 12);
		// 管理画面のとき
		if (is_admin()) {
			add_action('wp_ajax_pz_talk_image_id', array($this, 'image_id'));
			add_action('admin_post_pz_talk_reset_settings', array($this, 'reset_settings'));
			add_action('admin_menu', array($this, 'add_menu') );											// 設定メニュー
			add_action('admin_print_scripts', array($this, 'admin_scripts') );								// スクリプト
			add_action('admin_print_footer_scripts', array($this, 'admin_footer') );						// テキストエディタ用クイックタグ
			add_filter('mce_buttons', array($this, 'add_mce_button') );										// ビジュアルエディタ用ボタン
			add_filter('mce_external_plugins', array($this, 'add_mce_plugin') );							// ビジュアルエディタ用ボタン
			add_filter('plugin_action_links_'.$this->plugin_basename, array($this, 'action_links') );		// プラグイン画面
		}
	}

	public function reset_settings() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to reset these settings.', $this->text_domain), '', array('response' => 403));
		}
		check_admin_referer('pz-talk-reset-settings');
		$this->options = $this->normalize_options($this->defaults);
		update_option('pz_talk_options', $this->options);
		wp_safe_redirect(admin_url('options-general.php?page=pz-talk-settings&pz-talk-reset=1'));
		exit;
	}

	public function admin_bar_menu() {
		if (!current_user_can('manage_options') || empty($this->options['flg-adminbar'])) {
			return;
		}
		global $wp_admin_bar;
		if (!$wp_admin_bar->get_node('pz')) {
			$wp_admin_bar->add_menu(array('id' => 'pz', 'title' => 'Pz', 'href' => '#'));
		}
		$wp_admin_bar->add_menu(array(
			'id' => 'pz-tlk-settings',
			'parent' => 'pz',
			'title' => '<span class="ab-icon dashicons dashicons-admin-generic" aria-hidden="true" style="font-size: 16px; margin-right: 4px; color: inherit;"></span>'.esc_html__('Talk/Settings', $this->text_domain),
			'href' => admin_url('options-general.php?page=pz-talk-settings'),
			'meta' => array('target' => '_parent'),
		));
	}

	protected function normalize_options($options) {
		if (is_string($options)) {
			$options = maybe_unserialize($options);
		}
		if (!is_array($options)) {
			$options = array();
		}
		foreach ($this->defaults as $key => $value) {
			if (!array_key_exists($key, $options)) {
				$options[$key] = $value;
			}
		}
		if (empty($options['url-0'])) {
			$options['url-0'] = $this->plugin_dir_url.'img/unknown.png';
		}
		return $options;
	}

	// ショートコード処理
	function shortcode1($opt , $content = null) {
		// デバッグ情報
		if ($this->options['flg-debug']) {
			echo PHP_EOL;
			echo '<!-- [Pz-Talk] Debug information ---'.PHP_EOL;
			echo print_r($opt, true);
			echo '--- [Pz-Talk] /-->'.PHP_EOL;
		}
		
		// 登場人物設定から情報を取得
		$opt		=	$this->pz_talk_getperson($opt);
		
		// 右寄せの指定
		$position	=	null;
		if	(isset($opt['position']) && (strtolower(substr($opt['position'], 0, 1)) == 'r') ) {
			$position	=	1;
		} elseif (isset($opt['pos']) && (strtolower(substr($opt['pos'], 0, 1)) == 'r') ) {
			$position	=	1;
		} elseif (isset($opt['subtype']) && (strtolower(substr($opt['subtype'], 0, 1)) == 'r') ) {
			$position	=	1;
		} elseif ($this->options['talk-me-right'] && $opt['me']) {
			$position	=	1;
		}
		
		// HTMLタグ
		if	($position) {
			$content	= '<div class="pz-talk-me"><figure class="pz-talk-face"><img src="'.$opt['url'].'" alt=""><figcaption class="pz-talk-name"><B>'.$opt['name'].'</B></figcaption></figure><div class="pz-talk-content-me"><div class="pz-talk-message-me">'.do_shortcode($content).'</div></div></div>';
		} else {
			$content	= '<div class="pz-talk"><figure class="pz-talk-face"><img src="'.$opt['url'].'" alt=""><figcaption class="pz-talk-name"><B>'.$opt['name'].'</B></figcaption></figure><div class="pz-talk-content"><div class="pz-talk-message">'.do_shortcode($content).'</div></div></div>';
		}
		return $content;
	}

	// ショートコード処理
	function shortcode2($opt , $content = null) {
		// デバッグ情報
		if ($this->options['flg-debug']) {
			echo PHP_EOL;
			echo '<!-- [Pz-Talk] Debug information ---'.PHP_EOL;
			echo print_r($opt, true);
			echo '--- [Pz-Talk] /-->'.PHP_EOL;
		}
		
		// 登場人物設定から情報を取得
		$opt		=	$this->pz_talk_getperson($opt);
		
		if	($opt['me']) {
			$content	= '<div class="pz-line-me"><div class="pz-line-content-me"><div class="pz-line-message-me">'.do_shortcode($content).'</div></div></div>';
		} else {
			$content	= '<div class="pz-line"><figure class="pz-line-face"><img src="'.$opt['url'].'" alt=""></figure><div class="pz-line-name">'.$opt['name'].'</div><div class="pz-line-content"><div class="pz-line-message">'.do_shortcode($content).'</div></div></div>';
		}
		return $content;
	}

	// 登場人物設定から情報を取得
	function pz_talk_getperson($opt) {
		// パラメータが空だったとき
		if (!is_array($opt)) {
			$opt = array( 'name' => '' );
		}
		
		// 名前とアイコンのパラメータを取得
		$name		=	isset($opt['name']) ? $opt['name'] : '';
		$icon		=	isset($opt['icon']) ? $opt['icon'] : '';
		if ($this->options['flg-debug']) {
			echo '<!-- [Pz-Talk] name=\''.$name.'\' icon=\''.$icon.'\' /-->'.PHP_EOL;
		}
		
		unset($url);
		$i			=	1;
		while (isset($this->options['name-'.$i])) {
			if		($name	==	$this->options['name-'.$i]) {
				if ($this->options['flg-debug']) {
					echo '<!-- [Pz-Talk] name hit i='.$i.' /-->'.PHP_EOL;
				}
				if	($icon	==	'') {
					if ($this->options['flg-debug']) {
						echo '<!-- [Pz-Talk] no icon set URL /-->'.PHP_EOL;
					}
					$url	=	$this->options['url-'.$i];
				}
				$me		=	isset($this->options['me-'.$i]) ? $this->options['me-'.$i] : null;
			}
			if		(($icon) && ($icon == $this->options['name-'.$i])) {
				if ($this->options['flg-debug']) {
					echo '<!-- [Pz-Talk] icon hit i='.$i.' set URL /-->'.PHP_EOL;
				}
				$url		=	$this->options['url-'.$i];
			}
			$i++;
		}
		if (!isset($url)) {
			if ($this->options['flg-debug']) {
				echo '<!-- [Pz-Talk] new face i='.$i.' /-->'.PHP_EOL;
			}
			$url		=	'';
			$me			=	null;
			if ($name) {
				$this->options['name-'.$i]	=	$name;
				$this->options['url-'.$i]	=	$url;
				$this->options['me-'.$i]	=	$me;
				$result = update_option('pz_talk_options', $this->options);
			}
		}
		if (!$url) {
			if ($this->options['flg-debug']) {
				echo '<!-- [Pz-Talk] no URL /-->'.PHP_EOL;
			}
			if (!isset($this->options['url-0'])) {
				$this->options['url-0']	=	$this->plugin_dir_url.'img/unknown.png';
				$result = update_option('pz_talk_options', $this->options);
			}
			$url	=	$this->options['url-0'];
		}
		
		$opt['name']	=	$name;
		$opt['url']		=	$url;
		$opt['me']		=	$me;
		
		return $opt;
	}

	// スクリプト・CSSの設定
	function enqueue() {
		wp_enqueue_style('pz-talk', plugin_dir_url (__FILE__).'css/style.css');
	}

	// デバッグ情報
	function footer() {
		echo PHP_EOL;
		echo '<!-- [Pz-Talk] Debug information ---'.PHP_EOL;
		echo print_r($this->options, true);
		echo '--- [Pz-Talk] /-->'.PHP_EOL;
	}

	public function image_id() {
		check_ajax_referer('pz-talk-image-id', 'nonce');
		if (!current_user_can('manage_options') || !current_user_can('upload_files')) {
			wp_send_json_error(null, 403);
		}
		$url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
		$id = $url ? attachment_url_to_postid($url) : 0;
		wp_send_json_success(array('id' => $id && wp_attachment_is_image($id) ? $id : 0));
	}
	// 管理画面のスクリプト
	function admin_scripts() {
		wp_enqueue_media();		// メディアアップローダの javascript API
		wp_enqueue_script('mediapicker', plugins_url('js/mediapicker.js', __FILE__ ) , array( 'jquery' ), filemtime(plugin_dir_path(__FILE__).'js/mediapicker.js'), true);		// メディアピッカー
		wp_localize_script('mediapicker', 'pzTalkMedia', array('nonce' => wp_create_nonce('pz-talk-image-id')));
		wp_enqueue_script('pz-talk-admin', plugins_url('js/admin.js', __FILE__ ) , array( 'jquery' ), filemtime(plugin_dir_path(__FILE__).'js/admin.js'), true);
		wp_enqueue_style('pz-talk-admin', plugin_dir_url (__FILE__).'css/admin.css', array(), filemtime(plugin_dir_path(__FILE__).'css/admin.css'));
	}

	// プラグイン一覧のクイックメニュー
	public function action_links($links) {
		$links[] = '<a href="options-general.php?page=pz-talk-settings">'.__('Settings', $this->text_domain).'</a>';
		return $links;
	}

	// 管理画面時のスタイルシート、スクリプト設定
	public function admin_footer() {
		if (wp_script_is('quicktags') ) {
			echo '<script>QTags.addButton(\'pz-talk\',\''.__('Talk', $this->text_domain ).'\',\'['.$this->options['code1'].' name=""]\',\'[/'.$this->options['code1'].']\',\'\',\''.__('Make Talk', $this->text_domain ).'\');</script>'.PHP_EOL;
		}
		require_once ('lib/pz-talk-modal.php');

	}

	// 管理画面時のスタイルシート、スクリプト設定
	public function add_mce_button($buttons) {
		if ($this->options['flg-edit-insert']) {
			$buttons[]		=	'pz_talk';
		}
		return	$buttons;
	}

	public function add_mce_plugin($plugins) {
		$plugins['pz_talk']		=	$this->plugin_dir_url .'js/mce.js';
		return	$plugins;
	}

	// 管理画面のサブメニュー追加
	public function add_menu() {
		add_options_page	(__('Talk Settings', $this->text_domain),__('[Pz] Talk', $this->text_domain), 'manage_options', 'pz-talk-settings', array($this, 'page_settings') );
	}

	// Pz カード 設定画面
	public function page_settings() {
		require_once ('lib/pz-talk-settings.php');
	}

}
$pz_Talk = new Pz_Talk;
