<?php
/*
Plugin Name: SimpleTix
Plugin URI: http://wordpress.org/plugins/simpletix-event-registration-and-ticketing/ 
Description: SimpleTix event registration and ticketing plug-in.
Author: Team SimpleTix
Author URI: http://www.SimpleTix.com/
Version: 1.0
*/

class SimpleTix{
	function __construct()
	{	
		add_action('admin_init', array( &$this, 'redirect_to_simpletix' ) );		
		add_action( 'admin_menu', array( &$this,'register_simpletix_menu' ) );		
		add_action( 'admin_enqueue_scripts', array( $this, 'simpletix_admin_scripts' ) );				
		add_action('wp_head', array(&$this,'simpletix_js_code'));				
		
		//	Add Media Button
		add_action( 'media_buttons_context', array(&$this,'simpletix_media_button' ) );
		
		// SimpleTix Shortcode
		add_shortcode('simpletix', array(&$this,'simpletix_shortcode'));	
		
		//	SimpleTix Media Popup Content Action
		add_action( 'admin_footer-post-new.php', array(&$this,'simpletix_media_popup_content' ) );
		add_action( 'admin_footer-page-new.php',array(&$this,'simpletix_media_popup_content' ) );
		add_action( 'admin_footer-post.php',    array(&$this,'simpletix_media_popup_content' ) );
		add_action( 'admin_footer-page.php',    array(&$this,'simpletix_media_popup_content' ) );
		add_action( 'admin_footer-widgets.php', array(&$this,'simpletix_media_popup_content' ) );
		add_action( 'admin_footer-index.php',   array(&$this,'simpletix_media_popup_content' ) );		
		
		//	SimpleTix Media Popup Script Code Action
		add_action( 'admin_footer-post-new.php', array(&$this,'simpletix_popupscript' ) );
		add_action( 'admin_footer-page-new.php', array(&$this,'simpletix_popupscript' ) );
		add_action( 'admin_footer-post.php', 	 array(&$this,'simpletix_popupscript' ) );
		add_action( 'admin_footer-page.php', 	 array(&$this,'simpletix_popupscript' ) );
		add_action( 'admin_footer-widgets.php',  array(&$this,'simpletix_popupscript' ) );
		add_action( 'admin_footer-index.php',    array(&$this,'simpletix_popupscript' ) );		
		add_action( 'wp_ajax_my_action', array(&$this,'my_action_callback' ) );		
		add_action( 'wp_ajax_custom_uploadify', array(&$this,'custom_uploadify_callback' ) );		
		add_action( 'wp_ajax_simpletix_buttons', array(&$this,'simpletix_buttons_callback' ) );						
	}
	
	function register_simpletix_menu()
	{			
		add_menu_page( 'SimpleTix', 'SimpleTix', 'manage_options', 'simpletix', array( &$this,'simpletix_setup' ), plugins_url( 'images/ticket-icon.png',__FILE__ ) ); 		
		add_submenu_page( 'simpletix', 'How to use', 'How to use', 'manage_options', 'simpletix-settings',  array( &$this, 'simpletix_guide' ) ); 						
	}
	
	function my_action_callback( )
	{		
		global $wpdb; 
		
		if( $_POST['button_id'] )
		{		
			check_ajax_referer( 'delete_image', 'security' );	
			$button_id		=	$_POST['button_id'];
			$button_detail	=	$wpdb->get_results( $wpdb->prepare( "SELECT * FROM simpletix_button WHERE button_id=%d",$button_id ) );					
			$file_name		=	end(explode('SimpleTix',$button_detail[0]->button_url));		
			$upload_dir 	=	wp_upload_dir();					
			$targetPath		=	$upload_dir['basedir'].'/simpletix-ticketing-event-registration/'.$file_name;						
			$query			=	$wpdb->prepare( "DELETE FROM simpletix_button WHERE button_id=%d",$button_id );
			
			if( $wpdb->query( $query ) )
			{										
				unlink( $targetPath );	
				echo json_encode( array( 'success' => '1' ) );
			}
			else
			{			
				echo json_encode( array( 'success' => '0' ) );
			}
			
			exit;			
		}
		else
		{			
			check_ajax_referer( 'specific_event_time', 'security' );
			$whatever = intval( $_POST['event_id'] );
			$data	=	file_get_contents('https://developerapi.simpletix.com/api/v1/kiosk/GetGAEventTimes/?eventid='.$_POST['event_id']);

			if( count( $data ) > 0  )
			{
				$response	=   json_decode( $data );
				$event_slot 	=	array();

				foreach( $response as $eventTime)
				{
					$event_start_array	=	explode('T',$eventTime->StartTime);			
					$event_start_date	=	date('m/d/Y',strtotime($event_start_array[0]));		
					$event_start_time	=	$event_start_array[1];			
					
					$time_array		=	explode( ':', $event_start_time);		
					
					$start_time	=	intval( $time_array[0] );
					
					if( intval($time_array[1]) > 0)
					{
						$start_time.=	':'.$time_array[1];		
					}
					
					if( intval($time_array[2]) > 0)
					{
						$start_time.=	':'.$time_array[2];				
					}
					
					if( $time_array[0] > 11 )
					{
						$start_time.=	'pm';		
					}
					else
					{
						$start_time.=	'am';		
					}
					
					$event_slot[]	=	$event_start_date.' '.$start_time.','.$eventTime->EventSectionTimeId;
				}
				
				echo json_encode( $event_slot );		
				die();				
			}
			else
			{		
				echo json_encode( array( ) );		
				die();				
			}		
		}	
	}
	
	function custom_uploadify_callback( )
	{
		global $wpdb; 
		$button_detail	=	$wpdb->get_results( $wpdb->prepare( "SELECT * FROM simpletix_button 1=%d", 1 ) );				
		$upload_dir 	=	wp_upload_dir();
		$targetPath		=	$upload_dir['basedir'].'/simpletix-ticketing-event-registration';

		if ( !file_exists( $targetPath ) )
		{
			mkdir( $targetPath );
		}
			
		$verifyToken	=	md5('unique_salt' . $_POST['timestamp']);

		if (!empty($_FILES) && $_POST['token'] == $verifyToken)
		{
			$tempFile 		=	$_FILES['Filedata']['tmp_name'];
			$targetFile		=	rtrim($targetPath,'/') . '/' .time().$_FILES['Filedata']['name'];
			$file_url		=	$upload_dir['baseurl'].'/simpletix-ticketing-event-registration/'.time().$_FILES['Filedata']['name'];
			
			// Validate the file type	
			$fileTypes = array('jpg','jpeg','gif','png'); // File extensions
			$fileParts = pathinfo($_FILES['Filedata']['name']);
			
			if (in_array($fileParts['extension'],$fileTypes))
			{		
				if(move_uploaded_file($tempFile,$targetFile))
				{
					$wpdb->query( $wpdb->prepare( "INSERT INTO simpletix_button ( button_url,button_type ) VALUES ( %s ,'3') ", $file_url ) );
					$button_id	=	$wpdb->insert_id;			
					$response	=	array(
										'error'=>0,
										'imageurl'=>$file_url,
										'button_id' =>$button_id
									);			
				}		
				else
				{
					$response	=	array(
										'error'=>'Unable to upload file.Please try again or later',
										'imageurl'=>''
									);			
				}		
			}
			else
			{		
				$response	=	array(
										'error'=>'Invalid file extension',
										'imageurl'=>''
									);	
			}	
			
			echo json_encode( $response );
			exit;		
		}		
	}
	
	function simpletix_buttons_callback( )
	{
		global $wpdb; 
		$button_type	=	$_REQUEST['button_type'];
		$button_id		=	1;
		$button_detail	=	$wpdb->get_results( $wpdb->prepare( "SELECT * FROM simpletix_button WHERE button_type= %d",$button_type ) );
		$data	=	' <table style="width:100%;">';
		$i = 1;

		$button_name	=	'';

		if( $button_type == 1 )
		{
			$button_name	=	'buy_ticket';
		}
		else
		{
			$button_name	=	'register_now';
		}

		foreach( $button_detail as $button )
		{
			$rem	=	$i % 3;
			
			if( $rem == 1 )
			{
				$data.=	'<tr>';
			}
			
			$data.=	'<td style="width:215px;"><table><tr><td><input style="position:reletive; vertical-align:middle;" class="'.$button_name.'" button_url="'.$button->button_url.'" value="'.$button->button_id.'" name="'.$button_name.'" id="'.$button_name.$button->button_id.'"  type="radio" /></td><td><img ref_id="'.$button_name.$button->button_id.'" class="'.$button_name.'_image" style="width:200px;cursor:pointer;" src="'.$button->button_url.'" /></td></tr></table></td>';		

			if( $rem == 0 )
			{
				$data.=	'</tr>';
			}
			
			$i++;	
		}

		$data.= '</table>';

		echo $data;
		exit;

	}
	
	function simpletix_media_button( $icons )
	{		
		$id = 'mm_sg_container';
		$title = '';		
		$icons .= "<a id='open_simpletix_popup' class='thickbox button' title='" . $title . "' href='#TB_inline?width=640&height=600&inlineId=" . $id . "'><img src='".plugins_url( 'images/ticket-icon.png',__FILE__ )."' />Add SimpleTix Button</a>";			
		return $icons;				
	}
	
	function simpletix_admin_scripts( )
	{			
		wp_deregister_script( 'jquery' ); // deregisters the default WordPress jQuery  
		wp_register_script('jquery', ("https://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"), false);
		wp_enqueue_script('jquery');
		
		wp_register_script( 'simpletix_colorbox', plugins_url( 'simpletix-ticketing-event-registration/js/colorbox.js' ) );
		wp_enqueue_script( 'simpletix_colorbox' );
		
		wp_register_script( 'simpletix_hashchange', plugins_url( 'simpletix-ticketing-event-registration/js/jquery.hashchange.min.js' ) );
		wp_enqueue_script( 'simpletix_hashchange' );
		
		wp_register_script( 'simpletix_easytab', plugins_url( 'simpletix-ticketing-event-registration/js/jquery.easytabs.min.js' ) );
		wp_enqueue_script( 'simpletix_easytab' );		
		
		wp_register_script( 'simpletix_uploadify', plugins_url( 'simpletix-ticketing-event-registration/uploadify/jquery.uploadify.min.js' ) );
		wp_enqueue_script( 'simpletix_uploadify' );		
		
		wp_register_style( 'simpletix_colorbox_css', plugins_url( 'simpletix-ticketing-event-registration/css/colorbox.css' ) );
		wp_enqueue_style( 'simpletix_colorbox_css' );				
		
 		wp_register_style( 'simpletix_tabs', plugins_url( 'simpletix-ticketing-event-registration/css/tabs.css' ) );
		wp_enqueue_style( 'simpletix_tabs' );				
		
		wp_register_style( 'simpletix_uploadify', plugins_url( 'simpletix-ticketing-event-registration/uploadify/uploadify.css' ) );
		wp_enqueue_style( 'simpletix_uploadify' );				
		
		wp_register_style( 'simpletix_style', plugins_url( 'simpletix-ticketing-event-registration/css/style.css' ) );
		wp_enqueue_style( 'simpletix_style' );						
		
		wp_register_style( 'simpletix_horizontal_tabs', plugins_url( 'simpletix-ticketing-event-registration/css/horizontal-tabs.css' ) );
		wp_enqueue_style( 'simpletix_horizontal_tabs' );						
	}	
	
	function simpletix_media_popup_content( )
	{			
		global $wpdb;		
		$ajax_nonce 	=	wp_create_nonce( "specific_event_time" );
		$delete_nonce	=	wp_create_nonce( "delete_image" );		
?>				
		<div id="mm_sg_container" style="display:none;">			
			<!-- Link Type Tabs Css -->												
			<style>        
				.progress { position:relative; width:400px; border: 1px solid #ddd; padding: 1px; border-radius: 3px; height: 2px;}
				.bar { background-color: #B4F5B4; width:0%; height:2px; border-radius: 3px; }
				.percent { position:absolute; display:inline-block; top:3px; left:48%; }
				#status{margin-top: 30px;}
			</style>
			
			<script type="text/javascript">												
				jQuery(document).ready(function(){												
					function adjust_height()
					{					
						var tb_height	=	jQuery('#TB_window').height();
						var tb_width	=	jQuery('#TB_window').width();
						var tb_contentheight	=	parseInt( tb_height )-70;
						var tb_contentwidth		=	parseInt( tb_width )-30;						
						
						jQuery('#TB_ajaxContent').removeAttr('style');
						jQuery('#TB_ajaxContent').css({"width":tb_contentwidth+"px","height":tb_contentheight+"px"});																														
					}
					
					jQuery( window ).resize(function() {						
						adjust_height();
					});
					
					jQuery('#fancybox_buy_ticket').colorbox({ width:"72%", height:"95%"});					
					jQuery('#fancybox_register_now').colorbox({ width:"72%", height:"95%"});
					
					jQuery(document.body).on('click', '.buy_ticket' ,function(){															
						var button_id	=	jQuery(this).val();
						var button_url	=	jQuery(this).attr('button_url');
						
						jQuery('#link_button_id').val( button_id );																					
						jQuery('#buy_ticket_button').attr( 'src', button_url );																											
						jQuery('#buy_ticket_button').show();
						jQuery('.fancybox-close').click();	
						adjust_height();												
					});
					
					jQuery(document.body).on('click', '.register_now' ,function(){					
						var button_id	=	jQuery(this).val();
						var button_url	=	jQuery(this).attr('button_url');
						
						jQuery('#link_button_id').val( button_id );																					
						jQuery('#register_now_button').attr( 'src', button_url );																											
						jQuery('#register_now_button').show();
						jQuery('.fancybox-close').click();
						adjust_height();
					});
					
					jQuery(document.body).on('click', '.buy_ticket_image' ,function(){																																	
						var ref_id	=	jQuery(this).attr('ref_id');											
						var button_id	=	jQuery('#'+ref_id).val();
						var button_url	=	jQuery('#'+ref_id).attr('button_url');
						
						jQuery('#'+ref_id).attr('checked','checked');
						jQuery('#link_button_id').val( button_id );																								
						jQuery('#buy_ticket_button').attr( 'src', button_url );																											
						jQuery('#buy_ticket_button').show();
						jQuery('#cboxClose').click();
						
						adjust_height();												
					});
					
					jQuery(document.body).on('click', '.register_now_image' ,function(){										
						var ref_id		=	jQuery(this).attr('ref_id');																	
						var button_id	=	jQuery('#'+ref_id).val();
						var button_url	=	jQuery('#'+ref_id).attr('button_url');
						
						jQuery('#'+ref_id).attr('checked','checked');
						
						jQuery('#link_button_id').val( button_id );																		 																				
						jQuery('#register_now_button').attr( 'src', button_url );																											
						jQuery('#register_now_button').show();
						jQuery('.fancybox-close').click();
						jQuery('#cboxClose').click();
						adjust_height();
					});
					
					jQuery('#delete_image').on('click',function(){										
						var button_custom_id	=	jQuery('#link_button_id').val();						
						var data = {
							action: 'my_action',
							security: '<?php echo $delete_nonce; ?>',
							button_id: button_custom_id
						};
						
						jQuery.post(ajaxurl, data, function(response) {										
							
							var data	=	jQuery.parseJSON( response );
								
							if( data.success == 1 )							
							{							
								jQuery('#delete_custom_image').removeAttr('src');								
								jQuery('#delete_custom_image').hide( );								
								jQuery('#delete_image').hide( );								
								jQuery('#link_button_id').val( '' );																									
								jQuery('.uploadify-button-text').text( 'Select Image To Upload' );																			
							}
							else
							{							
								alert( 'Unable to delete image. Please try again or later.' ) ;	
							}							
						});	
						
						return false;												
					});
					
					jQuery('#file_upload').uploadify({						
						'formData'     : {
							'timestamp' : '<?php echo $timestamp;?>',
							'token'     : '<?php echo md5('unique_salt' . $timestamp);?>'
						},	
						'buttonText' : 'Select Image To Upload',	
						//'buttonImage' : '<?php echo plugins_url( 'images/upload_button4.png', __FILE__ );  ?>',
						'fileTypeExts' : '*.gif; *.jpg; *.png; *.jpeg',	
						'width':155,						
						'height':50,													
						'swf'      : '<?php echo plugins_url( 'uploadify/uploadify.swf', __FILE__ );  ?>',						
						'uploader' : 'admin-ajax.php?action=custom_uploadify', /*'<?php echo plugins_url( 'uploadify/uploadify.php', __FILE__ );  ?>',*/						
						'onUploadSuccess' : function(file, data, response) {																					
							jQuery('.uploadify-queue').hide();
							var new_data	=	jQuery.parseJSON( data );	
							
							if( new_data.error == 0)
							{							
								jQuery('#delete_custom_image').attr('src',new_data.imageurl)
								jQuery('#delete_custom_image').show();
								jQuery('#delete_image').show();
								jQuery('#link_button_id').val( new_data.button_id );															
								jQuery('.uploadify-button-text').text( 'Select a different image' );																			
								adjust_height();
							}
						},						
						'onUploadError' : function(file, errorCode, errorMsg, errorString) {
							alert('The file ' + file.name + ' could not be uploaded: ' + errorString);
						}
					});
				
					// W7hen a link is clicked
					jQuery("a.tab").click(function () {
						// switch all tabs off
						jQuery(".tabs .active").removeClass("active");
						
						// switch this tab on
						jQuery(this).addClass("active");
						
						// slide all content up
						jQuery(".content").hide();
						
						// slide this content up						
						var content_show	=	jQuery(this).attr("title");
						jQuery("#"+content_show).show();					  
					});
					
					jQuery('#tab-container').easytabs();	
					
					jQuery('#tab-container').bind('easytabs:before', function() {
						jQuery('#tabs-event .tab-listing li').removeAttr('class');							
						jQuery('#event2').prop( "checked", false);						
						jQuery('#event1').prop( "checked", true );		
						jQuery("#event_type_block").hide();														
						jQuery("#simpletix_event_block").hide();
						jQuery("#link_type_block").hide();						
						jQuery("#link_image_block").hide();
						jQuery("#link_text_block").hide();						
						jQuery('#event_id').val('');						
						jQuery('#specific_time').val('');
						jQuery('#link_button_id').val('');						
						jQuery('#link_type2').prop( "checked", false);
						jQuery('#link_type1').prop( "checked", true );								
					});
					
					jQuery('.panel-container .tab-listing li').click(function(){
						jQuery('.tab-listing li').removeAttr('class');
						var event_id	=	jQuery(this).attr('id');						
						jQuery('#event_id').val( event_id );															
						jQuery(this).attr('class','active');									
						jQuery("#event_type_block").show();	
						jQuery("#link_type_block").show();
						show_link_type();
						get_event_data();						
					});
					
					function show_link_type( )
					{
						if( jQuery('#link_type1').is(':checked')) 
						{								
							jQuery('#link_image_block').show();
							jQuery('#link_text_block').hide();																												
						}
						else 
						{	
							jQuery('#link_text_block').show();
							jQuery('#link_image_block').hide();
						}
					}
					
					jQuery('input[name="event_type"]').click(function(){												
						get_event_data();
					});
					
					jQuery('input[name="link_type"]').click(function(){							
						show_link_type();																	
					});
					
					jQuery(document.body).on('click', '#specific_event_dropdown li' ,function(){					
						jQuery('#specific_event_dropdown li').removeAttr('class');
						jQuery(this).attr('class','active');							
						var time_id		=	jQuery(this).attr('id');									
						jQuery('#specific_time').val( time_id );						
					});		
					
					function get_event_data( )
					{							
						if( jQuery('#event2').is(':checked')) 
						{							
							adjust_height();							
							jQuery('#simpletix_loading_image').show();
							jQuery('#simpletix_event_block').hide();
							
							var event_id	=	jQuery('.panel-container .tab-listing').find('li.active').attr('id');
							var data = {
								action: 'my_action',
								security: '<?php echo $ajax_nonce; ?>',
								event_id: event_id
							};
							
							jQuery.post(ajaxurl, data, function(response) {																						
								jQuery('#simpletix_loading_image').hide();								
								var res			=	jQuery.parseJSON( response );										
								var new_data	=	'';	
								
								jQuery('#simpletix_event_block').show();
								jQuery('#specific_event_dropdown').empty();		
								
								if( res.length > 0 )
								{									
									for( var i=0;i<res.length;i++ )
									{									
										var event_time_array	=	res[i].split(',');
										
										jQuery("<li id='"+event_time_array[1]+"'>" + event_time_array[0] + " </li>").appendTo("#specific_event_dropdown");
									}
									
									adjust_height();
								}
								else
								{			
									jQuery("<li> No Time Found</li>").appendTo("#specific_event_dropdown");
									adjust_height();
								}
							});							
						}						
						else
						{												
							jQuery('#specific_time').val('');							
							jQuery('#simpletix_event_block').hide();
						}												
					}												
				});												
			</script>
			
			<form id="simpletix-sg-form">
				<input type="hidden" name="event_id" id="event_id" />				
				<input type="hidden" name="specific_time" id="specific_time" />
				<input type="hidden" name="link_button_id" id="link_button_id"/>				
				
				<h3>Select an Event</h3>				
				<div id="tab-container" class='tab-container'>
					<ul class='etabs'>
						<li class='tab'><a href="#tabs-event">Link to an Event</a></li>
						<li class='tab'><a href="#tabs-flexevent">Link to a Flex Pass</a></li>
					</ul>
					
					<div class='panel-container'>
						<div id="tabs-event">
							<ul class="tab-listing">							
								<?php
									$link_events	=	$wpdb->get_results( $wpdb->prepare( "SELECT * FROM simpletix_event WHERE event_type=%d", 2 ) );
									
									if( !empty( $link_events ) )
									{
										foreach( $link_events as $event)
										{
								?>
											<li id="<?php echo $event->event_id; ?>"><?php echo $event->event_title; ?></li>
								<?php	
										}
									}
									else
									{
								?>
										<li>No Event Found</li>
								<?php
									}
								?>
															
							</ul>						
						</div>
						
						<div id="tabs-flexevent">
							<ul class="tab-listing">							
								<?php
									$flex_events	=	$wpdb->get_results( $wpdb->prepare( " SELECT * FROM simpletix_event WHERE event_type=%d",4 ) );
									
									if( !empty( $flex_events ) )
									{
										foreach( $flex_events as $event)
										{
								?>
											<li id="<?php echo $event->event_id; ?>"><?php echo $event->event_title; ?></li>
								<?php	
										}
									}
									else
									{
								?>
										<li>No Event Found</li>
								<?php
									}
								?>
							</ul>
						</div>
					</div>
					<br/>				
					
					<div id="event_type_block" style="display:none;"><input type="radio" name="event_type" id="event1" value="e" checked="checked"/><b>Event Page</b>&nbsp;(Link to the event details and display list of times)&nbsp;&nbsp;<input type="radio"  name="event_type" id="event2" value="s" /><b>Specific Event Time Page</B>&nbsp;(Will display tickets for single time)</div>
					<img id="simpletix_loading_image" style="display:none;margin-left:200px;" src="<?php echo WP_PLUGIN_URL; ?>/simpletix-ticketing-event-registration/images/loading1.gif" />
					
					<div id="simpletix_event_block" style="display:none">						
						<h3>Select an Event Time</h3>												
						<div class='panel-container'>												
							<ul class="tab-listing" id="specific_event_dropdown">	
							
							</ul>							
						</div>	
					</div>
					
					<br/><br/>		
					
					<div id="link_type_block" style="display:none">
						<span style="font-size:20px;">Link Type:</span>
						<input type="radio" name="link_type" id="link_type1" value="image" checked="checked"/><span style="font-size:18px;">Image</span>&nbsp;&nbsp;<input type="radio" name="link_type" id="link_type2" value="text" /><span style="font-size:18px;">Text</span>
					</div>					
					
					<br/>		
					
					<div id="link_image_block" style="display:none;">					
						<div id="tabbed_box_1" class="tabbed_box">						
							<div class="tabbed_area">							
								<ul class="tabs">										
									<li><a href="javascript:void(0);" title="content_1" class="tab active">Buy Tickets Buttons</a></li>
									<li><a href="javascript:void(0);" title="content_2" class="tab">Register Now Buttons</a></li>											
									<li><a href="javascript:void(0);" title="content_3" class="tab">Upload your own image</a></li>												
								</ul>
								
								<div id="content_1" class="content">								
									<a id="fancybox_buy_ticket" href="<?php echo admin_url();  ?>admin-ajax.php?action=simpletix_buttons&button_type=1" title="Buy Tickets">
										<div style="float:left;margin-right:10px;"><img src="<?php echo plugins_url( 'images/ticket-gallery.png', __FILE__ );  ?>" /></div>
										<div style="margin-top:22px;font-size:15px;color:grey;">Select from our gallery of ticket buttons</div>																						
									</a>									
									<br/>
									
									<img src="" id="buy_ticket_button" style="display:none;width:200px;" />																				
								</div>
								
								<div id="content_2" class="content">
									<a id="fancybox_register_now" href="<?php echo admin_url();  ?>admin-ajax.php?action=simpletix_buttons&button_type=2" title="Register Now Button">
										<div style="float:left;margin-right:10px;"><img src="<?php echo plugins_url( 'images/ticket-gallery.png', __FILE__ );  ?>" /></div>
										<div style="margin-top:22px;font-size:15px;color:grey;">Select from our gallery of registration buttons</div>
									</a>									
									<br/>
									<img src="" id="register_now_button" style="display:none;width:200px;" />		
								</div>
								
								<div id="content_3" class="content">																																		
									<div id="file_upload_block" style="margin-bottom:20px;float:left;">									
										<input id="file_upload" name="file_upload" type="file" multiple="true"> 																			
									</div>	
									
									<div id="image_preview_block" style="float:left">										
										<img width="133" src="" id="delete_custom_image" name="delete_custom_image" style="display:none"/>																		
										<br/>											
										<input type="button" id="delete_image" name="delete_image" value="Delete Image" style="display:none"/>								
									</div>	
								</div>    								
							</div>
						</div>					
					</div>					
					
					<div id="link_text_block" style="display:none;">
						<span style="font-size:18px;">Link Text:</span>
						<input type="text" name="link_text" id="link_text" value="Get Tickets Now!" />						
					</div>
					
					<br/>		
					<div style="clear:both"></div>
					
					<div style="float:right">
						<a id="simpletix_submit_form" name="simpletix_submit_form" href="javascript:void(0);" class="button media-button button-primary button-large media-button-insert">Insert SimpleTix Button</a>												
					</div>					
				</div>
			</form>
		</div>				
<?php		
	}
	
	function simpletix_popupscript()
	{	
?>	
		<script type="text/javascript">				
			jQuery( document ).ready( function() {	
				jQuery('#simpletix_submit_form').click(function(){														
					jQuery( '#simpletix-sg-form' ).submit();					
				});
				
				function simpletix_build_shortocde( )
				{								
					var event_id		=	jQuery('#event_id').val();	
					var specific_time	=	jQuery('#specific_time').val();	
					
					if( specific_time != '' )
					{					
						event_id	=	event_id+','+specific_time;						
					}
					
					var button_id	=	jQuery('#link_button_id').val();						
					var link_id		=	'';
					
						
					if( button_id != '')
					{
						link_id	=	'image,'+button_id;
					}
					else
					{
						var link_text	=	jQuery('#link_text').val();
						link_id	=	'text,'+link_text;
					}
					
					return "[simpletix event_id='"+event_id+"' link_id='"+link_id+"']";
				}
				
				jQuery( '#simpletix-sg-form' ).submit( function( e ) {  
					var event_id	=	jQuery('#event_id').val();					
					
					if( event_id == '')
					{
						alert('Please select event');
						return false;
					}
					
					if( jQuery('#event2').is(':checked') ) 
					{	
						var specific_time	=	jQuery('#specific_time').val();	

						if( specific_time == '' )
						{
							alert( 'Please select event time' );
							return false;	
						}												
					}
					
					if( jQuery('#link_type1').is(':checked') ) 
					{															
						var button_id	=	jQuery('#link_button_id').val();						
						
						if( button_id == '')
						{
							alert( 'Please select link image' );
							return false;							
						}												
					}
					
					if( jQuery('#link_type2').is(':checked') ) 
					{					
						var link_text	=	jQuery.trim( jQuery('#link_text').val() );
						
						if( link_text == '')
						{						
							alert( 'Please enter link text' );
							jQuery('#link_text').focus();							
							return false;							
						}
					}	
					e.preventDefault();					
					var shortcode = simpletix_build_shortocde();
					window.parent.send_to_editor( shortcode );
					
					//Close window
					parent.tb_remove();
				});
				
			});
		</script>	
<?php	
	}
	
	function simpletix_setup()
	{ 	
		require_once('pages/configure_store.php');
	}

	function simpletix_js_code( )
	{				
		$output	=	'<script type="text/javascript">	
						
						
						function OpenTicketWindow(url)
						{
							window.open(url, null,"height=600,width=1000,status=1,toolbar=no,menubar=no,location=1,scrollbars=1");
							return false;
						}
					</script>';					
		echo $output;	
	}
	
	function simpletix_shortcode( $atts )
	{		
		global $wpdb;
		$ssl_url		=	get_option( 'simpletix_sslurl' );				
		$event_array	=	explode( ',',$atts['event_id']);
		
		$event_detail	=	$wpdb->get_results( 'SELECT * FROM simpletix_event WHERE event_id="'.$event_array[0].'"' );
			
		$event_type		=	$event_detail[0]->event_type;
		
		$type			=	( $event_type == 4) ? 'OpenTicket' : 'Event';
		
		if( count( $event_array ) > 1 )
		{
			$url	=	$ssl_url.'ticket-software/'.$type.'/'.$event_array[0].'/Time/'.$event_array[1];
		}
		else
		{
			$url	=	$ssl_url.'ticket-software/'.$type.'/'.$event_array[0].'/View';
		}
		
		$link_array		=	explode( ',',$atts['link_id']);
		$link			=	'';
		
		if( $link_array[0] == 'image' )
		{		
			$button_detail	=	$wpdb->get_results( $wpdb->prepare( "SELECT * FROM simpletix_button WHERE button_id=%d", $link_array[1] ) );			
			$link	=	'<img width="200" border="0" alt="Get Tickets" src="'.$button_detail[0]->button_url.'" style="opacity: 1;"/>';
		}
		else
		{				
			$link	=	$link_array[1];			
		}			
		
		$response 	=	'<a style="cursor:pointer;" onclick="OpenTicketWindow(\''.$url.'\')" title="Get Tickets">'.$link.'</a>';
		
		return $response;		
	}
	
	function simpletix_guide()
	{
		require_once('pages/how_to_use.php');
	}
	
	function redirect_to_simpletix( )
	{
		 if ( get_option( 'simpletix_redirect', false) )
		 {
			delete_option('simpletix_redirect');
			$redirect_url = admin_url( '?page=simpletix', 'http' );
			wp_redirect( $redirect_url );
			exit;
		}
	}
	
	function simpletix_activate( )
	{						
		global $wpdb;						
		$table_name1	=	"simpletix_button";	
		$table_name2	=	"simpletix_event";			
		
		add_option('simpletix_redirect', true);
		add_option('simpletix_domain_name', '');
		add_option('simpletix_appid', '');
		add_option('simpletix_sslurl', '');
		add_option('simpletix_store_name', '');	
		
		if( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s" , $table_name1 ) ) != $table_name1 )
		{						
			$sql	=	'CREATE TABLE IF NOT EXISTS '.$table_name1.' (			
						  `button_id` int(11) NOT NULL AUTO_INCREMENT,
						  `button_url` varchar(255) NOT NULL,						  
						  `button_type` int(11) NOT NULL,						  
						  PRIMARY KEY (`button_id`)
						);';
						
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
			
			for( $i = 1; $i<=30; $i++ )
			{							
				$wpdb->query(  $wpdb->prepare( "INSERT INTO `simpletix_button` (`button_id`, `button_url`, `button_type`) VALUES(%d, %s ,%d)", $i , plugins_url( 'images/buy_ticket/btn'.$i.'.png' , __FILE__ ), 1 ) );
			}

			for( $j = 1; $j<=67; $j++ )
			{							
				$wpdb->query( $wpdb->prepare( "INSERT INTO `simpletix_button` (`button_url`, `button_type`) VALUES( %s , %d )", plugins_url( 'images/register_button/btn'.$j.'.png' , __FILE__ ) ,2 ) );
			}	
		}
		
		if( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE  %s ",$table_name2 ) ) != $table_name2 )
		{						
			$sql	=	'CREATE TABLE IF NOT EXISTS '.$table_name2.' (			
						  `id` int(11) NOT NULL AUTO_INCREMENT,						  
						  `event_id` int(11) NOT NULL,						  
						  `event_title` varchar(255) NOT NULL,						  
						  `event_type` int(11) NOT NULL,						  
						  PRIMARY KEY (`id`)
						);';
						
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}			
	}
	
	function simpletix_deactivate( )
	{		
		delete_option('simpletix_redirect');
		delete_option('simpletix_domain_name');
		delete_option('simpletix_appid');		
		delete_option('simpletix_sslurl');
		delete_option('simpletix_store_name');				
	}		
}

//	Make object of Class
$ob_simpletix	=	new SimpleTix();	

if( isset( $ob_simpletix ) )
{
	register_activation_hook( __FILE__,array(&$ob_simpletix,'simpletix_activate'));		
	register_deactivation_hook( __FILE__, array(&$ob_simpletix,'simpletix_deactivate') );	
}

?>