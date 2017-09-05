<?php
/*
Plugin Name: WP Posts Date Alert
Plugin URI: http://webcake.no003.info/
Description: 投稿の公開日と現在の日付を比較してメッセージを表示するプラグインです。
Author: Tsuyoshi.
Version: 2.3.0
Author URI: http://webcake.no003.info/
License: GPL
Copyright: Tsuyoshi.
*/
class PostsDateAlert
{
	/** プラグイン名称 */
	const PLUGIN_NAME = 'WP Posts Date Alert';

	/** ページ名称 */
	const PAGE_NAME = 'WP Posts Date Alert';

	/** 接頭辞 */
	const PREFIX = 'wppda';

	/**
	 *	コンストラクタ
	 */
	public function __construct() {
		// 管理者ページでのみ実行
		if( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'plugin_menu' ) );

			// 投稿画面に独自メタボックスを定義
			add_action('admin_menu', array( $this, 'add_custom_box' ) );

			// 独自メタボックスの入力値を保存
			add_action('save_post', array( $this, 'save_postmeta' ));

			// 翻訳ファイルの読み込み
			add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		}

		// メッセージを表示
		add_filter( 'the_content', array( $this, 'hook_the_content_filter' ), 9999 );

		// プラグイン付属のCSSを使うにチェックが入っていた場合、CSSを読み込む
		if( get_option( self::n( 'use_css' ) ) == 1 ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'hook_wp_enqueue_scripts_action' ) );
		}
	}

	/**
	 *	CSSを読み込む
	 */
	public function hook_wp_enqueue_scripts_action() {
		wp_enqueue_style( self::n( 'style' ), plugins_url( 'style.css', __FILE__ )  );
	}

	/**
	 * 翻訳ファイルの読み込み
	 */
	public function load_textdomain() {
		load_plugin_textdomain( self::PREFIX, false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
	}

	/**
	 * メッセージを表示
	 */
	public function hook_the_content_filter( $content ) {
		// 警告文を表示するかどうか
		if ( $this->is_disp() === true ) {
			// 投稿の本文にメッセージを付与して返す
			$content =  $this->get_content( $content );
		}

		return $content;
	}

	/**
	 * 警告文を表示するかどうか
	 */
	public function is_disp()
	{
		// 独自メタボックスのカスタムフィールドの値を取得 (古い記事に警告文を表示する / しない)
		$meta_values = get_post_meta( $GLOBALS["post"]->ID, self::n( 'is_display_alert' ), true );

		// 古い記事に警告文を表示する場合
		if( '0' !== $meta_values )
		{
			// 日付のチェック
			if ( true === self::check_date() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 *	日付のチェック
	 */
	public static function check_date() {
		$type = get_option( self::n( 'use_type' ), -1 );
		$comparison = get_option( self::n( 'comparison' ), 0 );

		if( 1 == $comparison ) {
			// 最終更新日時で比較
			$time = get_the_modified_time( 'U' );
		}
		else {
			// 公開日時で比較
			$time = get_the_time( 'U' );
		}

		// 差を求める
		// MINUTE_IN_SECONDS  = 60 (seconds)
		// HOUR_IN_SECONDS    = 60 * MINUTE_IN_SECONDS
		// DAY_IN_SECONDS     = 24 * HOUR_IN_SECONDS
		// WEEK_IN_SECONDS    = 7 * DAY_IN_SECONDS
		// YEAR_IN_SECONDS    = 365 * DAY_IN_SECONDS
		$day = round( (int) abs( $time - current_time( 'timestamp' ) ) / DAY_IN_SECONDS );

		if( 0 == $type ) {
			// 年の場合
			if( $day >= get_option( self::n( 'date' ) ) * 365 ) {
				return true;
			}
		} elseif( 1 == $type ) {
			// 日の場合
			if( $day >= get_option( self::n( 'date' ) ) ) {
				return true;
			}
		}

		return  false;
	}

	/**
	 *	警告文を返す
	 */
	public function get_content( $content = null ) {
		$position = get_option( self::n( 'alert_position' ), -1 );
		$wrapper = get_option( self::n( 'use_wrapper' ), -1 );
		$alert = get_option( self::n( 'alert' ), '' );

		// divで括るにチェックが入っていた場合
		if( 1 == $wrapper ) {
			$alert = '<div id="' . self::n( 'alert' ) . '">' . $alert . '</div>';
		}

		if( 0 == $position ) {
			// 投稿の本文の前にメッセージを連結
			$content = $alert . $content;
		} elseif ( 1 == $position ) {
			// 投稿の本文の後にメッセージを連結
			$content = $content . $alert;
		}

		// 投稿の本文が null で、自動出力せずテンプレートタグを使用する場合
		if( true === is_null($content) && 2 == $position )
		{
			// 警告文のみ返す
			$content = $alert;
		}

		return $content;
	}

	/**
	 *	接頭辞を付けて名称を返す
	 */
	public static function n( $name ) {
		return self::PREFIX . '_' . $name;
	}

	/**
	 *	プラグインメニュー
	 */
	public function plugin_menu() {
		// メニューの設定にサブメニューとして追加
		add_options_page(
			// サブメニューページのタイトル
			self::PLUGIN_NAME,
			// プルダウンに表示されるメニュー名
			self::PAGE_NAME,
			// サブメニューの権限名
			'manage_options',
			// サブメニューのスラッグ
			basename( __FILE__ ),
			// サブメニューページのコールバック関数
			array( $this, 'plugin_page' )
		);
	}

	/**
	 *	プラグインページ
	 */
	public function plugin_page() {
		// 生成した一時トークンの取得
		$nonce_field = isset( $_POST[ self::n( 'option_nonce' ) ] ) ? $_POST[ self::n( 'option_nonce' ) ] : null;

		// 生成した一時トークンをチェックする
		if ( wp_verify_nonce( $nonce_field, wp_create_nonce( __FILE__ ) ) ) {
			// データベースに値を設定する
			update_option( self::n( 'date' ), $_POST[ self::n( 'date' ) ] );
			update_option( self::n( 'alert' ), wp_unslash( $_POST[ self::n( 'alert' ) ] ) );
			update_option( self::n( 'use_type' ), $_POST[ self::n( 'use_type' ) ] );
			update_option( self::n( 'comparison' ), $_POST[ self::n( 'comparison' ) ] );
			update_option( self::n( 'alert_position' ), $_POST[ self::n( 'alert_position' ) ] );
			update_option( self::n( 'use_css' ), $this->get_checkbox( self::n('use_css') ) );
			update_option( self::n( 'use_wrapper' ), $this->get_checkbox( self::n('use_wrapper') ) );

			// 画面に更新されたことを伝えるメッセージを表示
			echo '<div class="updated"><p><strong>' . __( 'Settings saved', self::PREFIX ) . '</strong></p></div>';
		}

		// フォームの表示
		$this->the_form();
	}

	/**
	 * チェックボックスの値を取得する
	 */
	private function get_checkbox($name)
	{
		$value = isset( $_POST[ $name ] ) ? $_POST[ $name ] : null;
		return $value;
	}

	/**
	 *	投稿画面に独自メタボックスを定義
	 */
	public function add_custom_box() {
		if( function_exists( 'add_meta_box' )) {
			add_meta_box( self::n( 'section_old_post' ), __( 'Old Post', self::PREFIX ), array( $this, 'inner_custom_box' ), 'post', 'side' );
			add_meta_box( self::n( 'section_old_post' ), __( 'Old Post', self::PREFIX ), array( $this, 'inner_custom_box' ), 'page', 'side' );
		}
	}

	/**
	 *	独自メタボックスの入力フィールドを定義
	 */
	public function inner_custom_box( $post ) {

		// 認証に nonce を生成する
		echo '<input type="hidden" name="' . self::n( 'meta_nonce' ) . '" id="' . self::n( 'meta_nonce' ) . '" value="' .
		wp_create_nonce( plugin_basename( __FILE__ ) ) . '" />';

		// 独自メタボックスのカスタムフィールドの値を取得 (古い記事に警告文を表示する / しない)
		$meta_values = get_post_meta( $post->ID, self::n( 'is_display_alert' ), true );

		// デフォルトおよび、古い記事に警告文を表示するにチェックが入っている場合はチェック状態にする
		$checked = '';
		if( '0' !== $meta_values )
		{
			$checked = 'checked="checked" ';
		}

		// チェックボックス定義
		echo '<input type="checkbox" id="'. self::n( 'is_display_alert' ) . '" name="'. self::n( 'is_display_alert' ) . '" value="1" '. $checked .' />';
		echo '<label for="'. self::n( 'is_display_alert' ) . '">' . __( 'I display a warning sentence for an old post', self::PREFIX ) . '</label> ';
	}

	/**
	 *	独自メタボックスの入力値を保存
	 */
	public function save_postmeta( $post_id ) {
		// 生成した一時トークンの取得
		$nonce_field = isset( $_POST[ self::n( 'meta_nonce' ) ] ) ? $_POST[ self::n( 'meta_nonce' ) ] : null;

		// 生成した一時トークンをチェックする
		if ( !wp_verify_nonce( $nonce_field, plugin_basename( __FILE__ ) ) ) {
			return $post_id;
		}

		// 自動保存の場合は処理しない
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// 投稿の編集が可能か
		if ( !current_user_can( 'edit_post', $post_id ) ) {
			return $post_id;
		}

		// 入力値を取得
		$is_display_alert = $_POST[ self::n( 'is_display_alert' ) ];

		// チェックを入れずに投稿を保存した場合は、0を保存する
		if( '1' !== $is_display_alert )
		{
			// 警告文を表示しない
			$is_display_alert = '0';
		}

		// カスタムフィールドに保存
		update_post_meta( $post_id, self::n( 'is_display_alert' ), $is_display_alert );

		return $is_display_alert;
	}

	/**
	 *	フォームの表示
	 */
	private function the_form() {
	?>
		<div class="wrap">
			<h2><?php echo esc_html( self::PLUGIN_NAME ); ?></h2>
			<form method="post">
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for="<?php echo self::n( 'date' ); ?>"><?php _e( 'Period', self::PREFIX ); ?></label></th>
						<td><input name="<?php echo self::n( 'date' ); ?>" type="number" step="1" min="1" id="<?php echo self::n( 'date' ); ?>" value="<?php echo get_option( self::n( 'date' ) ); ?>" class="small-text" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Year or Day', self::PREFIX ); ?></th>
						<td>
						<fieldset><legend class="screen-reader-text"><span><?php _e( 'Year or Day', self::PREFIX ); ?></span></legend>
						<p>
							<label><input name="<?php echo self::n( 'use_type' ); ?>" type="radio" value="0" <?php checked( 0, get_option( self::n( 'use_type' ), 0 ) ); ?>	/> <?php _e( 'Year', self::PREFIX ); ?></label><br />
							<label><input name="<?php echo self::n( 'use_type' ); ?>" type="radio" value="1" <?php checked( 1, get_option( self::n( 'use_type' ) ) ); ?> /> <?php _e( 'Day', self::PREFIX ); ?></label>
						</p>
						</fieldset>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Comparison', self::PREFIX ); ?></th>
						<td>
						<fieldset><legend class="screen-reader-text"><span><?php _e( 'Comparison', self::PREFIX ); ?></span></legend>
						<p>
							<label><input name="<?php echo self::n( 'comparison' ); ?>" type="radio" value="0" <?php checked( 0, get_option( self::n( 'comparison' ), 0 ) ); ?>	/> <?php _e( 'Published on', self::PREFIX ); ?></label><br />
							<label><input name="<?php echo self::n( 'comparison' ); ?>" type="radio" value="1" <?php checked( 1, get_option( self::n( 'comparison' ) ) ); ?> /> <?php _e( 'Last updated', self::PREFIX ); ?></label>
						</p>
						</fieldset>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Message', self::PREFIX ); ?></th>
						<td><fieldset><legend class="screen-reader-text"><?php _e( 'Message', self::PREFIX ); ?></span></legend>
						<p><label for="<?php echo self::n( 'alert' ); ?>"><?php _e( 'Please input the message which you want to display possible HTML', self::PREFIX ); ?></label></p>
						<p>
						<textarea name="<?php echo self::n( 'alert' ); ?>" rows="10" cols="50" id="<?php echo self::n( 'alert' ); ?>" class="large-text code"><?php echo esc_textarea( get_option( self::n( 'alert' ) ) ); ?></textarea>
						</p>
						</fieldset></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Output', self::PREFIX ); ?></th>
						<td>
						<fieldset><legend class="screen-reader-text"><span><?php _e( 'Output', self::PREFIX ); ?></span></legend>
						<p>
							<label><input name="<?php echo self::n( 'alert_position' ); ?>" type="radio" value="0" <?php checked( 0, get_option( self::n( 'alert_position' ), 0 ) ); ?>	/> <?php _e( 'Before a body', self::PREFIX ); ?></label><br />
							<label><input name="<?php echo self::n( 'alert_position' ); ?>" type="radio" value="1" <?php checked( 1, get_option( self::n( 'alert_position' ) ) ); ?> /> <?php _e( 'After a body', self::PREFIX ); ?></label><br />
							<label><input name="<?php echo self::n( 'alert_position' ); ?>" type="radio" value="2" <?php checked( 2, get_option( self::n( 'alert_position' ) ) ); ?> /> <?php _e( "I use template tag <code>&lt;?php if ( function_exists( 'wppda_alert' ) ) wppda_alert(); ?&gt;</code> without outputting it automatically", self::PREFIX ); ?></label>
						</p>
						</fieldset>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Option', self::PREFIX ); ?></th>
						<td>
							<fieldset><legend class="screen-reader-text"><span><?php _e( 'Option', self::PREFIX ); ?></span></legend>
							<label for="<?php echo self::n( 'use_css' ); ?>">
							<input name="<?php echo self::n( 'use_css' ); ?>" type="checkbox" id="<?php echo self::n( 'use_css' ); ?>" value="1" <?php checked( '1', get_option(  self::n( 'use_css' ) ) ); ?> />
							<?php _e( 'I use the CSS attached to the plug in', self::PREFIX ); ?></label><br />
							<label for="<?php echo self::n( 'use_wrapper' ); ?>">
							<input name="<?php echo self::n( 'use_wrapper' ); ?>" type="checkbox" id="<?php echo self::n( 'use_wrapper' ); ?>" value="1" <?php checked( '1', get_option(  self::n( 'use_wrapper' ) ) ); ?> />
							<?php _e( 'Enclose with a', self::PREFIX ); ?> <code>&lt;div id="<?php echo self::n( 'alert' ); ?>"&gt;&lt;/div&gt;</code></label>
							</fieldset>
						</td>
					</tr>
				</table>

				<?php // フォームにhiddenフィールドとして追加するためのnonceを出力します ?>
				<?php wp_nonce_field( wp_create_nonce( __FILE__ ),  self::n( 'option_nonce' ) ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
	<?php
	}
}

// インスタンス生成
$PostsDateAlert = new PostsDateAlert();

/**
 *	テンプレートタグ
 *	メッセージを出力
 */
function wppda_alert() {
	if ( true == PostsDateAlert::is_disp() ) {
		echo PostsDateAlert::get_content();
	}
}
