<?php
	global $wpdb;	
	$form_url		=	'admin.php?page=simpletix';
	$iframe_url		=	admin_url().'admin.php?page=simpletix';		
	$store_address		=	'';
	$domain			=	'';	
	
	if( isset( $_REQUEST['domain'] ) )
	{
		$domain			=	$_REQUEST['domain'];	
	}
	else if( isset( $_REQUEST['SimpleTixDomain'] ) )
	{
		$domain			=	$_REQUEST['SimpleTixDomain'];	
	}
	
	
	if( $domain != '' )
	{		
		$store_address	=	str_replace('http://','',$domain);		
		$store_address	=	str_replace('https://','',$store_address);		
		
		update_option('simpletix_domain_name', $store_address);		
		$url	=	'https://developerapi.simpletix.com/api/v1/tickets/GetApplicationIdByDomain/?domain='.$store_address;		
		$is_valid_store	=	file_get_contents( $url );				
		
		if( $is_valid_store == 'null' )
		{								
			update_option('simpletix_appid', '');			
			update_option('simpletix_sslurl', '');
			update_option('simpletix_store_name', '');				
?>
			<div class="error"><p> SimpleTix store address is not valid.</p></div>
<?php					
		}
		else
		{			
			$store_app_id	=	str_replace('"','',$is_valid_store);			
			update_option('simpletix_appid', $store_app_id);			
			$get_store_detail	=	'https://developerapi.simpletix.com/api/v1/kiosk/GetSettings/?domain='.$store_address;	
			$store_detail		=	json_decode( file_get_contents( $get_store_detail ) );					
			update_option('simpletix_sslurl', $store_detail->SslUrl);
			update_option('simpletix_store_name', $store_detail->StoreName);
			
			//	Delete All Events			
			$wpdb->query( $wpdb->prepare( "DELETE FROM simpletix_event WHERE 1=%d", 1 ) );
			
			//	Get Link Events
			$get_link_events	=	'https://developerapi.simpletix.com/api/v1/kiosk/GetGAEvents/?domain='.$store_address;	
			$link_events		=	json_decode( file_get_contents( $get_link_events ) );					
			
			if( !empty( $link_events) )
			{			
				foreach( $link_events as $event )
				{
					$wpdb->query( $wpdb->prepare( "INSERT INTO `simpletix_event` (`event_id`,`event_title`,`event_type`) VALUES(%d, %s,%d)",$event->EventId,$event->Title,$event->EventType ) );
				}				
			}
			
			//	Get Flex Events			
			$get_flex_events	=	'https://developerapi.simpletix.com/api/v1/kiosk/GetOTEvents/?domain='.$store_address;	
			$flex_events		=	json_decode( file_get_contents( $get_flex_events ) );	
			
			if( !empty( $flex_events) )
			{			
				foreach( $flex_events as $event )
				{									
					$wpdb->query( $wpdb->prepare( "INSERT INTO `simpletix_event` (`event_id`,`event_title`,`event_type`) VALUES( %d,%s,%d )", $event->EventId,$event->Title,$event->EventType ) );
				}
			}
			
			
		}						
	}		
?>
<link href="<?php echo WP_PLUGIN_URL; ?>/simpletix-ticketing-event-registration/css/style.css" rel="stylesheet"/>
<link href="<?php echo WP_PLUGIN_URL; ?>/simpletix-ticketing-event-registration/css/bootstrap.min.css" rel="stylesheet"/>
<script type="text/javascript" src="<?php echo WP_PLUGIN_URL; ?>/simpletix-ticketing-event-registration/js/jquery.js"></script>
<script type="text/javascript">
	jQuery(document).ready(function(){					
		jQuery('#simpletix_form').submit(function(){									
			var store_address	=	jQuery.trim( jQuery('#domain').val() );
			
			if( store_address == '' )
			{			
				alert( 'Please enter the simpletix store address.' );
				jQuery('#domain').focus();				
				return false;				
			}
		});	

		jQuery('#connect_account').click(function(){					
			var store_address	=	jQuery.trim( jQuery('#domain').val() );
			
			if( store_address == '' )
			{			
				alert( 'Please enter the simpletix store address.' );
				jQuery('#domain').focus();				
				return false;				
			}
			
			jQuery('#simpletix_form').submit();
		});		
	});	
</script>
<br/>

<div class="jumbotron">

<?php
	$app_id			=	get_option('simpletix_appid');
	$ssl_url		=	get_option('simpletix_sslurl');
	$store_name		=	get_option('simpletix_store_name');
	
	if( !empty( $app_id ) && !empty( $ssl_url ) && !empty( $store_name ) )
	{			
?>
		<p style="font-size:14px;font-weight:bold;">SimpleTix of Wordpress is a complete solution for selling tickets directly from Wordpress.</p>
		<p class="green-text">Your SimpleTix Plugin has been succesfully setup!</p>
		<form id="simpletix_form" name="simpletix_form" class="form-horizontal" method="POST" action="<?php echo $form_url; ?>">
			<label class="box_label">SimpleTix Domain:</label>
			<input id="domain" name="domain" type="text" class="domain-box" value="http://<?php echo get_option('simpletix_domain_name'); ?>">
		</form>    	
		<a href="javascript:void(0);"  id="connect_account" name="connect_account"  class="save-btn">Save</a><br>
		<a href="admin.php?page=simpletix-settings" class="sky-text">How to use this plugin?</a><br>		
<?php		
	}
	else
	{
?>	
		
<?php
		if( isset ( $_GET['store'] ) )
		{	
?>
			<iframe style="width:100%;height:800px;" src="https://signup.simpletix.com/SimpleTixCorp/StoreModeQuestion.aspx?LandingPage=WP&Banner=<?php echo $iframe_url; ?>"></iframe>
<?php	
		}
		else
		{	 
?>
			<h1><img src="https://www.simpletix.com/wp-content/uploads/2014/03/logo-normal.png" alt="SimpleTix"/></h1>		
			<p>SimpleTix for WordPress is a complete solution for selling tickets directly from Wordpress.</p>		
			<form id="simpletix_form" name="simpletix_form" class="form-horizontal" method="POST" action="<?php echo $form_url; ?>">		
				<fieldset>			
					<div class="form-group" style="margin-left:0px">				
						<label class="control-label" for="inputLarge">Enter your SimpleTix store address.</label>
						<input class="form-control input-lg" id="domain" name="domain" style="width:50% !important;" type="text" value="<?php echo get_option('simpletix_domain_name'); ?>">					
						<label class="control-label" >Eg. MaxMajor.SimpleTix.Com</label>					
					</div>
					
					<button id="connect_account" name="connect_account" type="button" class="btn btn-warning">Connect this account</button>				
					
				</fieldset>
			</form>	
			<p>
				<div class="alert alert-dismissable alert-info">
					<button type="button" class="close" data-dismiss="alert">×</button>
					<strong>Don't have a SimpleTix account?</strong> <a href="<?php echo $form_url; ?>&store=iframe" class="alert-link">Create</a> your store now.
				</div>
			</p>
<?php
		}	
	}
?>		
</div>