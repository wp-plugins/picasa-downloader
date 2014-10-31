<?php
/*
Plugin Name: Picasa 2 WordPress ( Picasa Image Downloader )
Plugin URI: https://ibgroup.co.jp
Description: Downloading Google Picasa images into WordPress media library and delete Google Picasa images from WordPress admin panel.
Version: 1.0.0
Author: IBRIDGE
Author URI: http://ibgroup.co.jp
License: GPL
*/

define( 'GP2WP_Version'  , '1.0.0' );
define( 'GP2WP_Name'     , 'gp2wp' );
define( 'GP2WP_Dir_Name' , dirname( plugin_basename( __FILE__ ) ) );
define( 'GP2WP_Dir'      , plugin_dir_path( __FILE__ ) );
define( 'GP2WP_Url'      , plugin_dir_url( __FILE__ ) );


function gp2wp_setup() {
	// Load Translation
	$locale = apply_filters( 'plugin_locale', get_locale(), GP2WP_Name );
	load_textdomain( GP2WP_Name, WP_LANG_DIR . "/" . GP2WP_Dir_Name . "/" . GP2WP_Name . "-$locale.mo" );
	load_plugin_textdomain( GP2WP_Name, false, GP2WP_Dir_Name . '/languages/' );
}
add_action( 'plugins_loaded', 'gp2wp_setup' );


if ( ! class_exists( 'gp2wp' ) ) {
class gp2wp {
	
	private $get_all_options;
	private $user;
	private $pass;
	private $service;
	
	function __construct() {
		
		$this->option_group_name = 'gp2wp_option_group';
		$this->get_all_options   = get_option( $this->option_group_name );
		$this->photo_row_options =  array(
			'10'  => __( 'Per 10 Photos', GP2WP_Name ),
			'30'  => __( 'Per 30 Photos', GP2WP_Name ),
			'50'  => __( 'Per 50 Photos', GP2WP_Name ),
			'100' => __( 'Per 100 Photos', GP2WP_Name ),
		);
		
		add_action( 'admin_menu', array( $this, 'register_options_pages' ) );
		add_action( 'admin_init', array( $this, 'update_options' ) );
		
		if ( $this->get_all_options['google-username'] && $this->get_all_options['google-password'] ) {
			
			// Vars
			$this->user = $this->get_all_options['google-username'];
			$pass       = $this->get_all_options['google-password'];
			
			
			// Zend
			set_include_path( dirname(__FILE__) . '/' );
			// echo get_include_path();
			require_once( 'Zend/Loader.php' );
			
			
			// Load
			Zend_Loader::loadClass( 'Zend_Gdata_Photos' );
			Zend_Loader::loadClass( 'Zend_Gdata_ClientLogin' );
			Zend_Loader::loadClass( 'Zend_Gdata_AuthSub' );
			Zend_Loader::loadClass( 'Zend_Gdata_Photos_PhotoQuery' );
			
			
			// Picasa API
			$service  = Zend_Gdata_Photos::AUTH_SERVICE_NAME;
			$client   = Zend_Gdata_ClientLogin::getHttpClient( $this->user, $pass, $service );
			$this->gp = new Zend_Gdata_Photos( $client );
			
			
			add_action( 'wp_ajax_gp2wp_ajax_album_feed',     array( $this, 'gp2wp_ajax_album_feed' ) );
			add_action( 'wp_ajax_gp2wp_ajax_download_photo', array( $this, 'gp2wp_ajax_download_photo' ) );
			add_action( 'wp_ajax_gp2wp_ajax_delete_photo',   array( $this, 'gp2wp_ajax_delete_photo' ) );
			
		}
		
	}
	
	/**
	 * Admin menu, style, scripts and page content.
	 */
	function register_options_pages() {
		
		$role_user  = 'upload_files';
		$role_admin = 'manage_options';
		$menu = array();
		$menu[] = add_menu_page( __( 'Picasa', GP2WP_Name ), __( 'Picasa', GP2WP_Name ), $role_user, GP2WP_Dir_Name, array( $this, 'browser_page' ) );
		$menu[] = add_submenu_page( GP2WP_Dir_Name, __( 'Settings', GP2WP_Name ), __( 'Settings', GP2WP_Name ), $role_admin, GP2WP_Dir_Name . '_settings', array( $this, 'settings_page' ) );  
		
		foreach ( $menu as $key => $value ) {
			add_action( 'admin_print_styles-' . $value, array( $this, 'print_styles' ) );
			add_action( 'admin_print_scripts-' . $value, array( $this, 'print_scripts' ) );
		}
		 
	}
	
	function print_styles() { 
		
		wp_enqueue_style( 'gp2wp-style', plugins_url( '/css/style.css', __FILE__ ) );
		
	}
	
	function print_scripts() { 
		
		wp_enqueue_script( 'gp2wp-blockui', plugins_url( '/js/jquery.blockui/jquery.blockUI.min.js', __FILE__ ), array('jquery'), '2.60.0' );
		wp_enqueue_script( 'gp2wp-magnific-popup', plugins_url( '/js/jquery.magnific-popup/jquery.magnific-popup.min.js', __FILE__ ), array('jquery'), '0.9.9' );
		
	}
	
	function browser_page() {
		?>
		<div id="gp2wp" class="wrap">
		
			<h2><?php _e( 'Picasa Browser', GP2WP_Name ); ?>
			
			<div class="gp2wp-content">
			
				<?php echo $this->get_user_feed(); ?>
			
			</div>
		
		</div>
		<?php
	}
	
	function settings_page() {
		?>
		<div id="gp2wp" class="wrap">
		
			<h2><?php _e( 'Picasa Settings', GP2WP_Name ); ?>
			
			<div class="gp2wp-content">
				
				<form method="post" action="">
					<?php wp_nonce_field( GP2WP_Dir_Name . '_value', GP2WP_Dir_Name . '_field' ); ?>
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php _e( 'Google Username', GP2WP_Name ); ?></th>
							<td><input type="text" name="<?php echo $this->option_group_name; ?>[google-username]" value="<?php echo esc_attr( $this->get_all_options['google-username'] ); ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php _e( 'Google Password', GP2WP_Name ); ?></th>
							<td><input type="text" name="<?php echo $this->option_group_name; ?>[google-password]" value="<?php echo esc_attr( $this->get_all_options['google-password'] ); ?>" /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php _e( 'Photos Rows', GP2WP_Name ); ?></th>
							<td>
								<select name="<?php echo $this->option_group_name; ?>[photo-rows]">
									<?php
									$max_results = $this->get_all_options['photo-rows'];
									foreach( $this->photo_row_options as $key => $label ) {
										$val = false;
										if ( $key == $max_results ) { $val = $key; }
										?>
										<option value="<?php echo $key; ?>"<?php selected( $key , $val ); ?>><?php echo $label; ?></option>
										<?php
									}
									?>
								</select>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Save', GP2WP_Name ) ); ?>
				</form>
					
			</div>
		
		</div>
		<?php
	}
	
	/*
	 * Update options with nonce
	 */
	function update_options() {
			
		if ( ! empty( $_POST[ GP2WP_Dir_Name . '_field' ] ) ) {
			if ( check_admin_referer( GP2WP_Dir_Name . '_value', GP2WP_Dir_Name . '_field' ) ) {
			
				foreach ( $_POST[$this->option_group_name] as $key => $value ) {
					$this->get_all_options[$key] = esc_html( $value );
				}

				update_option( $this->option_group_name, $this->get_all_options );
			
				wp_safe_redirect( menu_page_url( GP2WP_Dir_Name . '_settings', false ) );
			
			}
		}
		
	}
	
	/**
	 * Get all user albums
	 * @return html
	 */
	function get_user_feed() {
		
		// Album List
		$output = '';
		
		try {
			
			$output .= '<h2>' . __( 'Album list', GP2WP_Name ) . '</h2>';
			
			$output .= '<table class="form-table gp2wp-user-feed-table">';
				
				$output .= '<thead><tr><th>' . __( 'Album Name', GP2WP_Name ) . '</th><th>' . __( 'Action', GP2WP_Name ) . '</th></tr></thead>';
				
				$output .= '<tbody>';
					$userFeed = $this->gp->getUserFeed( 'default' );
					foreach ( $userFeed as $userEntry ) {
						
						$album_id = $userEntry->gphotoId->text;
						
						$output .= '<tr>';
							$output .= '<td>' . $userEntry->title->text . '</td>';
							//$output .= '<td>'. $album_id . '</td>';
							$output .= '<td><a href="" class="gp2wp-ajax-pagination button" data-album-id="' . $album_id . '" data-page-num="1">' . __( 'View', GP2WP_Name ) . '</a></td>';
						$output .= '</tr>';
						
					}
				$output .= '</tbody>';
				
			$output .= '</table>';
			
			$output .= '<div id="gp2wp-ajax-album-feed"></div>';
			
			ob_start();
				
				?>
				<script type="text/javascript">
				jQuery(function(){
					jQuery('body').append('<div class="gp2wp_overlay"></div>');
					
					jQuery(document.body).on('click', '.gp2wp-ajax-pagination', (function(event) {
						event.preventDefault();
						
						var _this    = jQuery(this);
						var _albumId = _this.data('album-id');
						var _pageNum = _this.data('page-num');
						
						jQuery.ajax({
							url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
							method: 'POST',
							data: {
								'action'  : 'gp2wp_ajax_album_feed',
								'album_id': _albumId,
								'page_num': _pageNum
							},
							dataType: 'html',
							beforeSend: function() {
								jQuery('.gp2wp_overlay').fadeIn().block({message: null, overlayCSS: {background: '#fff url(<?php echo plugins_url( 'images/' , __FILE__ ) . 'ajax-loader.gif'; ?>) no-repeat center', backgroundSize: '16px', opacity: 0.6, cursor:'none'}});
							},
							complete: function() {
								jQuery('html, body').animate({scrollTop:jQuery('#gp2wp-ajax-album-feed').offset().top}, 'slow');
								jQuery('.gp2wp_overlay').unblock().fadeOut();
							},
							success: function(data) {
								jQuery('#gp2wp-ajax-album-feed').html(data);
							}
						});
					}));
					
					jQuery(document.body).on('click', '.gp2wp-ajax-download', (function(event) {
						event.preventDefault();
						
						var _this        = jQuery(this);
						var _elementsId  = _this.attr('id');
						var _albumId     = _this.data('album-id');
						var _pageNum     = _this.data('page-num');
						var _title       = _this.data('title');
						var _originalURL = _this.data('original-url');
						
						jQuery.ajax({
							url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
							method: 'POST',
							data: {
								'action'      : 'gp2wp_ajax_download_photo',
								'album_id'    : _albumId,
								'page_num'    : _pageNum,
								'title'       : _title,
								'original_url': _originalURL
							},
							dataType: 'html',
							beforeSend: function() {
								jQuery('.gp2wp_overlay').fadeIn().block({message: null, overlayCSS: {background: '#fff url(<?php echo plugins_url( 'images/' , __FILE__ ) . 'ajax-loader.gif'; ?>) no-repeat center', backgroundSize: '16px', opacity: 0.6, cursor:'none'}});
							},
							complete: function() {
								jQuery('#'+ _elementsId).removeClass('gp2wp-ajax-download').addClass('button-disabled');
								jQuery.magnificPopup.open({
									items: {
										src: '#gp2wp-mfp-inline-content',
										type: 'inline'
									},
									fixedContentPos: true,
									removalDelay: 300,
									mainClass: 'dress-mfp-zoom-in',
								});
								jQuery('.gp2wp_overlay').unblock().fadeOut();
							},
							success: function(data) {
								jQuery('#gp2wp-ajax-album-feed').html(data);
							}
						});
					}));
					
					jQuery(document.body).on('click', '.gp2wp-ajax-delete', (function(event) {
						event.preventDefault();
						
						var _this    = jQuery(this);
						var _albumId = _this.data('album-id');
						var _pageNum = _this.data('page-num');
						var _photoId = _this.data('photo-id');
						
						jQuery.ajax({
							url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
							method: 'POST',
							data: {
								'action'  : 'gp2wp_ajax_delete_photo',
								'album_id': _albumId,
								'page_num': _pageNum,
								'photo_id': _photoId
							},
							dataType: 'html',
							beforeSend: function() {
								jQuery('.gp2wp_overlay').fadeIn().block({message: null, overlayCSS: {background: '#fff url(<?php echo plugins_url( 'images/' , __FILE__ ) . 'ajax-loader.gif'; ?>) no-repeat center', backgroundSize: '16px', opacity: 0.6, cursor:'none'}});
							},
							complete: function() {
								jQuery('html, body').animate({scrollTop:(jQuery('#gp2wp-ajax-album-feed').offset().top - 50 )}, 'slow');
								jQuery.magnificPopup.open({
									items: {
										src: '#gp2wp-mfp-inline-content',
										type: 'inline'
									},
									fixedContentPos: true,
									removalDelay: 300,
									mainClass: 'dress-mfp-zoom-in',
								});
								jQuery('.gp2wp_overlay').unblock().fadeOut();
							},
							success: function(data) {
								jQuery('#gp2wp-ajax-album-feed').html(data);
							}
						});
					}));
					
					jQuery(document.body).on('change', '.gp2wp-photo-rows', (function(event) {
						var _this    = jQuery(this);
						var _rows    = jQuery(this).val();
						var _albumId = _this.data('album-id');
						// console.log( _rows );
						
						jQuery.ajax({
							url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
							method: 'POST',
							data: {
								'action'     : 'gp2wp_ajax_album_feed',
								'album_id'   : _albumId,
								'page_num'   : 1,
								'photo_rows' : _rows
							},
							dataType: 'html',
							beforeSend: function() {
								jQuery('.gp2wp_overlay').fadeIn().block({message: null, overlayCSS: {background: '#fff url(<?php echo plugins_url( 'images/' , __FILE__ ) . 'ajax-loader.gif'; ?>) no-repeat center', backgroundSize: '16px', opacity: 0.6, cursor:'none'}});
							},
							complete: function() {
								jQuery('html, body').animate({scrollTop:jQuery('#gp2wp-ajax-album-feed').offset().top}, 'slow');
								jQuery('.gp2wp_overlay').unblock().fadeOut();
							},
							success: function(data) {
								jQuery('#gp2wp-ajax-album-feed').html(data);
							}
						});
					}));
					
					<?php do_action( 'gp2wp_fronted_js' ); ?>
				});
				</script>
				<?php
				
				$output .= ob_get_contents();
				
			ob_end_clean();
			
		} catch ( Zend_Gdata_App_HttpException $e ) {
			
			$output .= __( 'Error: ', GP2WP_Name ) . $e->getMessage();
			if ( $e->getResponse() != null ) {
				
				$output .= '<p>' . __( 'Body: ', GP2WP_Name ) . $e->getResponse()->getBody() . '</p>';
				
			}
			
		} catch ( Zend_Gdata_App_Exception $e ) {
			
			$output .= __( 'Error: ', GP2WP_Name ) . $e->getMessage();
			
		}
		
		return $output;
	
	}
	
	/**
	 * Get all photos in album
	 * @return html
	 */
	function gp2wp_ajax_album_feed( $album_id = '', $page_num = '', $photo_rows = '' ) {
		
		if ( empty( $_POST['album_id'] ) && empty( $album_id ) ) {
			_e( 'Error: Absent Album ID.', GP2WP_Name );
			die();
		}
		if ( ! empty( $_POST['album_id'] ) ) {
			$album_id = esc_html( $_POST['album_id'] );
		}
		
		if ( empty( $_POST['page_num'] ) && empty( $page_num ) ) {
			_e( 'Error: Absent Page Number.', GP2WP_Name );
			die();
		}
		if ( ! empty( $_POST['page_num'] ) ) {
			$page_num = esc_html( $_POST['page_num'] );
		}
		$page_num = intval( $page_num );
		
		if ( ! empty( $_POST['photo_rows'] ) ) {
			$photo_rows = esc_html( $_POST['photo_rows'] );
			$this->get_all_options['photo-rows'] = intval( $photo_rows );
			update_option( $this->option_group_name, $this->get_all_options );
			//update_option( $this->option_group_name['photo-rows'], $photo_rows );
		}
		
		$max_results = intval( $this->get_all_options['photo-rows'] );
		
		$query = $this->gp->newAlbumQuery();    
		$query->setUser( 'default' );
		$query->setAlbumId( $album_id );
		$query->setMaxResults( $max_results );
		$query->setStartIndex( ( ( $page_num - 1 ) * $max_results ) + 1 );
		
		$feed = '';
		
		try {
			
			$feed = $this->gp->getAlbumFeed( $query );
			
			// Item Num
			$item_results = $feed->gphotoNumPhotos->text;
			
			// Page Num
			$max_page_num = ceil( $item_results / $max_results );
			
			if ( $feed ) {
				
				echo '<div class="gp2wp-album-name">';
					echo '<h2>' . $feed->getTitle() . '</h2>';
					echo '<span>' . $item_results. __( ' photo(s) find.', GP2WP_Name ) . '</span>';
					echo '<select name="gp2wp-photo-rows" class="gp2wp-photo-rows" data-album-id="' . $album_id . '" data-page-num="' . $page_num . '">';
							foreach( $this->photo_row_options as $key => $label ) {
								$val = false;
								if ( $key == $max_results ) { $val = $key; }
								?>
									<option value="<?php echo $key; ?>"<?php selected( $key , $val ); ?>><?php echo $label; ?></option>
								<?php
							}
					echo '</select>';
				echo '</div>';
				
				if ( $max_page_num > 1 ) {
					
					echo '<div class="gp2wp-pagination top">';
					
					if ( $page_num == 1 ) {
						
						// Next
						echo '<a href="" class="gp2wp-ajax-pagination button next" data-album-id="' . $album_id . '" data-page-num="' . ( $page_num + 1 ) . '">' . __( 'Next', GP2WP_Name ) . '</a>';
						
						echo $page_num . __( '/' ) . $max_page_num;
						
					} elseif ( $page_num > 1 ) {
						
						if ( $page_num == $max_page_num ) {
							
							// Prev
							echo '<a href="" class="gp2wp-ajax-pagination button prev" data-album-id="' . $album_id . '" data-page-num="' . ( $page_num - 1 ) . '">' . __( 'Prev', GP2WP_Name ) . '</a>';
							
							echo $page_num . __( '/' ) . $max_page_num;
							
						} else {
							
							// Prev
							echo '<a href="" class="gp2wp-ajax-pagination button prev" data-album-id="' . $album_id . '" data-page-num="' . ( $page_num - 1 ) . '">' . __( 'Prev', GP2WP_Name ) . '</a>';
							
							echo $page_num . __( '/' ) . $max_page_num;
							
							// Next 
							echo '<a href="" class="gp2wp-ajax-pagination button next" data-album-id="' . $album_id . '" data-page-num="' . ( $page_num + 1 ) . '">' . __( 'Next', GP2WP_Name ) . '</a>';
							
						}
						
					}
					
					echo '</div>';
				
				}
				
				echo '<table class="form-table gp2wp-album-feed-table">';
					foreach ( $feed as $entry ) {
						
						$title        = $entry->getTitle();
						$summary      = $entry->getSummary();
						$thumb        = $entry->getMediaGroup()->getThumbnail();
						$thumb_url    = $thumb[1]->url;
						$medium       = $entry->getMediaGroup()->getContent();
						$medium_url   = $medium[0]->getUrl();
						$original_url = str_replace( $title, '', $medium_url ) . 's0/' . $title;
						$tags         = $entry->getMediaGroup()->getKeywords();
						$size         = $entry->getGphotoSize();
						$height       = $entry->getGphotoHeight();
						$width        = $entry->getGphotoWidth();
						$photo_id     = $entry->getGphotoId();
						//$albumid      = $entry->getGphotoAlbumId();
						
						echo '<tr>';
							echo '<td><img src="' . $thumb_url . '"/></td>';
							echo '<td>' . __( 'File: ', GP2WP_Name ) . $title . '</td>';
							echo '<td><span class="gp2wp-ajax-download button left" id="' . $photo_id . '" data-album-id="' . $album_id . '" data-page-num="' . $page_num . '" data-title="' . $title . '" data-original-url="' . $original_url . '" >' . __( 'Download' ) . '</span></td>';
							echo '<td><span class="gp2wp-ajax-delete button right" data-album-id="' . $album_id . '" data-page-num="' . $page_num . '" data-photo-id="' . $photo_id . '">' . __( 'Delete', GP2WP_Name ) . '</span></td>';
						echo '</tr>';
						
					}
				echo '</table>';
				
				if ( $max_page_num > 1 ) {
					
					echo '<div class="gp2wp-pagination bottom">';
					
					if ( $page_num == 1 ) {
						
						// Next
						echo '<a href="" class="gp2wp-ajax-pagination button next" data-album-id="' . $album_id . '" data-page-num="' . ( $page_num + 1 ) . '">' . __( 'Next', GP2WP_Name ) . '</a>';
						
						echo $page_num . __( '/' ) . $max_page_num;
						
					} elseif ( $page_num > 1 ) {
						
						if ( $page_num == $max_page_num ) {
							
							// Prev
							echo '<a href="" class="gp2wp-ajax-pagination button prev" data-album-id="' . $album_id . '" data-page-num="' . ( $page_num - 1 ) . '">' . __( 'Prev', GP2WP_Name ) . '</a>';
							
							echo $page_num . __( '/' ) . $max_page_num;
							
						} else {
							
							// Prev
							echo '<a href="" class="gp2wp-ajax-pagination button prev" data-album-id="' . $album_id . '" data-page-num="' . ( $page_num - 1 ) . '">' . __( 'Prev', GP2WP_Name ) . '</a>';
							
							echo $page_num . __( '/' ) . $max_page_num;
							
							// Next 
							echo '<a href="" class="gp2wp-ajax-pagination button next" data-album-id="' . $album_id . '" data-page-num="' . ( $page_num + 1 ) . '">' . __( 'Next', GP2WP_Name ) . '</a>';
							
						}
						
					}
					
					echo '</div>';
				
				}
				
			}
			
		} catch ( Zend_Gdata_App_Exception $e ) {
			
			echo __( 'Error: ', GP2WP_Name ) . $e->getResponse();
			
		}
		
		die();
	
	}
	
	/**
	 * Download photo in album
	 */
	function gp2wp_ajax_download_photo() {
		
		if ( empty( $_POST['album_id'] ) ) {
			_e( 'Error: Absent Album ID.', GP2WP_Name );
			die();
		}
		$album_id = esc_html( $_POST['album_id'] );
		
		if ( empty( $_POST['page_num'] ) ) {
			_e( 'Error: Absent Page Number.', GP2WP_Name );
			die();
		}
		$page_num = intval( esc_html( $_POST['page_num'] ) );
		
		if ( empty( $_POST['title'] ) ) {
			_e( 'Error: Absent Image Title.', GP2WP_Name );
			die();
		}
		$title = esc_html( $_POST['title'] );
		
		if ( empty( $_POST['original_url'] ) ) {
			_e( 'Error: Absent Image URL.', GP2WP_Name );
			die();
		}
		$original_url = esc_html( $_POST['original_url'] );
		
		$image_html = media_sideload_image( $original_url, '', $title );
		
		echo '<div id="gp2wp-mfp-inline-content" class="mfp-hide">';
				
			echo __( 'Download Completed.', GP2WP_Name ); 
		
		echo '</div>';
		
		$this->gp2wp_ajax_album_feed( $album_id, $page_num );
		
		die();       
	
	}
	
	/**
	 * Delete photo in album
	 */
	function gp2wp_ajax_delete_photo() {
		
		if ( empty( $_POST['album_id'] ) ) {
			_e( 'Error: Absent Album ID.', GP2WP_Name );
			die();
		}
		$album_id = esc_html( $_POST['album_id'] );
		
		if ( empty( $_POST['page_num'] ) ) {
			_e( 'Error: Absent Page Number.', GP2WP_Name );
			die();
		}
		$page_num = intval( esc_html( $_POST['page_num'] ) );
		
		if ( empty( $_POST['photo_id'] ) ) {
			_e( 'Error: Absent Photo ID.', GP2WP_Name );
			die();
		}
		$photo_id = esc_html( $_POST['photo_id'] );
		
		echo '<div id="gp2wp-mfp-inline-content" class="mfp-hide">';
		
			try {
				
				$photo = $this->gp->getPhotoEntry( 'http://picasaweb.google.com/data/entry/api/user/' . $this->user . '/albumid/' . $album_id . '/photoid/' . $photo_id );
				$photo->delete();
				
				echo __( 'Delete Completed.', GP2WP_Name ); 
				
			} catch ( Zend_Gdata_App_Exception $e ) {
				
				echo __( 'Error: ', GP2WP_Name ) . $e->getResponse();
				
			}
		
		echo '</div>';
		
		$this->gp2wp_ajax_album_feed( $album_id, $page_num );
		
		die();       
	
	}

}
$gp2wp = new gp2wp();
}

?>