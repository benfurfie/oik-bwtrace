<?php
/*
Plugin Name: oik bwtrace 
Plugin URI: http://www.oik-plugins.com/oik-plugins/oik-bwtrace
Description: Debug trace for WordPress, including action and filter tracing
Version: 1.24
Author: bobbingwide
Author URI: http://www.oik-plugins.com/author/bobbingwide
License: GPL2

    Copyright 2011-2015 Bobbing Wide (email : herb@bobbingwide.com )

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License version 2,
    as published by the Free Software Foundation.

    You may NOT assume that you can use any other version of the GPL.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    The license for this software can likely be found here:
    http://www.gnu.org/licenses/gpl-2.0.html

*/
global $bw_trace_options, $bw_trace_on, $bw_trace_level;

// Since this plugin is defined to load first... so that it can perform the trace reset
// then we need to load oik_boot ourselves... We need bw_array_get().
if (!function_exists( 'oik_require' )) {
  // check that oik is available.
  $oik_boot = dirname(dirname(__FILE__)) . "/oik/oik_boot.inc";
  if ( $oik_boot ) { 
    require_once( $oik_boot );
  }
}

/* Only carry on if "oik_require2()" exists - which indicates oik is version 1.17 or higher */
if ( function_exists( "oik_require2" )) {
  oik_init();
  oik_require( "bwtrace_boot.inc" ); 
  oik_require2( "includes/bwtrace.inc", "oik-bwtrace" );
} 

/**
 * Return the version of oik-bwtrace
 *
 * @return string - same as oik_version()
 */ 
function oik_bwtrace_version() {
  return oik_version();
}

/**
 * Arrange for this plugin to be loaded first
 *
 * 
 */
function bw_this_plugin_first() {
	// ensure path to this file is via main wp plugin path
	$wp_path_to_this_file = preg_replace('/(.*)plugins\/(.*)$/', WP_PLUGIN_DIR."/$2", __FILE__);
	$this_plugin = plugin_basename(trim($wp_path_to_this_file));
	$active_plugins = get_option('active_plugins');
	$this_plugin_key = array_search($this_plugin, $active_plugins);
	if ($this_plugin_key) { // if it's 0 it's the first plugin already, no need to continue
		array_splice($active_plugins, $this_plugin_key, 1);
		array_unshift($active_plugins, $this_plugin);
		update_option('active_plugins', $active_plugins);
	}
}


/**
 * Return TRUE if option is '1', FALSE otherwise 
 */
function bw_torf( $array, $option ) {
  $opt = bw_array_get( $array, $option );
  $ret = $opt > '0';
  return $ret;
}

/**
 * Startup processing for oik-bwtrace
 * 
 * Activate trace if the profile says so 
 * AND if the chosen IP address is being used
 * 
 */
function bw_trace_plugin_startup() {
  global $bw_trace_options, $bw_action_options;
  add_action("activated_plugin", "bw_this_plugin_first");
  $bw_trace_options = get_option( 'bw_trace_options' );
  $bw_trace_level = bw_torf( $bw_trace_options, 'trace' ); 
  $bw_trace_ip = null;
  if ( $bw_trace_level ) { 
    $bw_trace_ip = bw_array_get( $bw_trace_options, "ip", null );
    if ( $bw_trace_ip ) {
      $server = bw_array_get( $_SERVER, "REMOTE_ADDR", null );
      $bw_trace_level = ( $server == $bw_trace_ip );
    }
  }    
  
  if ( $bw_trace_level ) {
    bw_trace_on();
    global $bw_include_trace_count, $bw_include_trace_date, $bw_trace_anonymous, $bw_trace_memory, $bw_trace_post_id, $bw_trace_num_queries, $bw_trace_savequeries;
    $bw_include_trace_count = bw_torf( $bw_trace_options, 'count' );
    $bw_include_trace_date = bw_torf( $bw_trace_options, 'date' );
    $bw_trace_anonymous = !bw_torf( $bw_trace_options, 'qualified' );
    $bw_trace_memory = bw_torf( $bw_trace_options, "memory" );
    $bw_trace_post_id = bw_torf( $bw_trace_options, "post_id" );
    $bw_trace_num_queries = bw_torf( $bw_trace_options, "num_queries" );
    //$bw_trace_savequeries = bw_torf( $bw_trace_options, "savequeries" );
    $bw_trace_savequeries = $bw_trace_num_queries;
    
    
    oik_require2( "includes/oik-bwtrace.inc", "oik-bwtrace" );
    bw_trace_included_files();
    if ( $bw_trace_savequeries ) {
       bw_trace_set_savequeries();
    }
    bw_trace_saved_queries();
    
    // We should only do this if we want to trace actions
    add_action( "init", "bw_trace_actions" );
    // If we want to trace counting then we can start a little earlier
    //
    add_action( "plugins_loaded", "bw_trace_count_plugins_loaded" );
  } else {
    if ( !$bw_trace_ip ) {
      bw_trace_off();
    }    
  } 

  // We can reset the trace file regardless of the value of tracing
  // except when we're only tracing a specific IP
  
  // $bw_trace_level | $bw_trace_ip | reset ?
  // --------------- | ------------ | -----------
  // 0               | set          | NO
  // 0               | null         | YES
  // 1               | either       | depends on option
  if ( $bw_trace_ip && !$bw_trace_level ) { 
    $bw_trace_reset = false ;
  } else {
    $bw_trace_reset = bw_torf( $bw_trace_options, 'reset' );
  }
  if ( !empty( $_REQUEST['_bw_trace_reset'] ) ) {
    $bw_trace_reset = TRUE;
  } 
 
  // Shouldn't this be moved so that it's only performed if trace actions is enabled?  **?** 
  
  $bw_action_options = get_option( 'bw_action_options' );
  $bw_action_reset = bw_torf( $bw_action_options, 'reset' );
  if ( !empty( $_REQUEST['_bw_action_reset'] ) ) {
    $bw_action_reset = TRUE;
  } 
  
  if ( $bw_trace_reset ) {
    oik_require2( "includes/bwtrace.inc", "oik-bwtrace" );
    bw_trace_reset();
    $bw_action_reset = true;
  } 
  
  if ( $bw_action_reset ) {
    oik_require2( "includes/oik-bwtrace.inc", "oik-bwtrace" );
    bw_actions_reset();
  }

  if ( $bw_trace_level > '0' ) {
    bw_lazy_trace( ABSPATH . $bw_trace_options['file'], __FUNCTION__, __LINE__, __FILE__, 'tracelog' );
    bw_lazy_trace( $_SERVER, __FUNCTION__, __LINE__, __FILE__, "_SERVER" ); 
    bw_lazy_trace( bw_getlocale(), __FUNCTION__, __LINE__, __FILE__, "locale" );
    bw_lazy_trace( $_REQUEST, __FUNCTION__, __LINE__, __FILE__, "_REQUEST" );
    //bw_lazy_trace( $_POST, __FUNCTION__, __LINE__, __FILE__, "_POST" );
    //bw_lazy_trace( $_GET, __FUNCTION__, __LINE__, __FILE__, "_GET" );
    bw_lazy_trace( $bw_action_options, __FUNCTION__, __LINE__, __FILE__, "bw_action_options" );
  } 

  add_action('admin_init', 'bw_trace_options_init' );
  add_action('admin_init', 'bw_action_options_init' );
  add_action('admin_menu', 'bw_trace_options_add_page');
  add_action('admin_menu', 'bw_action_options_add_page');
}


/** 
 * Start the trace action logic if required 
 *
 * Load the bw_action options and check to see if "actions" is set. 
 * If so then set action tracing on, else set it off.
 * @TODO - why load oik-bwtrace.inc when it's off? Maybe we should only call bw_trace_actions_off() when it's available.
 */ 
function bw_trace_actions() {
  $bw_action_options = get_option( 'bw_action_options' );
  $trace_actions = bw_array_get( $bw_action_options, "actions", false );
  bw_trace2( $bw_action_options, "bw_action_options" );
  if ( $trace_actions ) {
    oik_require2( "includes/oik-bwtrace.inc", "oik-bwtrace" );
    bw_trace_actions_on();
    bw_lazy_trace_actions();
  } else {
    if ( is_callable( "bw_trace_actions_off" ) ) {
      bw_trace_actions_off();
    }
  }  
}

/**
 * Implement "plugins_loaded" for oik-bwtrace
 * 
 * Start the trace count logic if required
 * 
 * @TODO - it would be a lot nicer if we could start counting actions from the first time
 * one is invoked. To achieve this we probably need to create an MU plugin
 * and make it respond to 'muplugins_loaded'.
 * The MU plugin should be responsible for loading the relevant parts of oik and oik-bwtrace 
 * 
 */
function bw_trace_count_plugins_loaded() {
  $bw_action_options = get_option( 'bw_action_options' );
  $trace_count = bw_array_get( $bw_action_options, "count", false );
  if ( $trace_count ) {
    oik_require( "includes/oik-actions.php", "oik-bwtrace" );
    bw_trace_count_on();
    bw_lazy_trace_count();
  } else {
    if ( is_callable( "bw_trace_count_off" ) ) {
      bw_trace_count_off();
    }  
  }
}


if (function_exists( "add_action" )) {
  bw_trace_plugin_startup();
}

if ( function_exists( "is_admin" ) ) {
  if ( is_admin() ) {   
    oik_require2( "admin/oik-bwtrace.inc", "oik-bwtrace" );
  }
}

/**
 * Trace the 'wp' action
 *
 * Note: Trace of wp_rewrite commented out.
 * 
 * @param object $WP_Environment_Instance
 *
 */
function bw_trace_wp( $WP_Environment_Instance ) {
  bw_trace2();
  $home = is_home();
  $front = is_front_page();
  $show_on_front = get_option( "show_on_front" );
  $page_on_front = get_option( "page_on_front" );
  $page_for_posts = get_option( "page_for_posts" );
  bw_trace2( "show,page,posts", "$show_on_front,$page_on_front,$page_for_posts", false ); 
  bw_trace2( $home, "home", false );
  bw_trace2( $front, "front", false ); 
  // global $wp_rewrite;
  // bw_trace2( $wp_rewrite );
}

add_action( "oik_admin_menu", "oik_bwtrace_admin_menu" );
add_action( "wp", "bw_trace_wp" );
// Moved shutdown actions to includes/oik-bwtrace.inc 
bw_trace_add_shutdown_actions();

/**
 *
 * At shutdown create a trace log of the following:
 *
 * - included files
 * - saved queries
 * - general status report
 * 
 * Note: The general status report should also be reportable back to the browser
 * even when trace is not being run but when the trace plugin is activated.
 * So it shouldn't be where it currently is. 
 */
function bw_trace_add_shutdown_actions() {
  if ( function_exists( "bw_trace_included_files" ) ) {
    add_action( "shutdown", "bw_trace_included_files" );
  }
  if ( function_exists( "bw_trace_saved_queries" ) ) { 
    add_action( "shutdown", "bw_trace_saved_queries" );
  }
  if ( function_exists( "bw_trace_status_report" ) ) {
    add_action( "shutdown", "bw_trace_status_report" );
  }
}
/**
 * Relocate the plugin to become its own plugin and set the plugin server
 *
 * @TODO - check if this is still necessary
 * 
 */
function oik_bwtrace_admin_menu() {
  oik_register_plugin_server( __FILE__ );
  bw_add_relocation( "oik/oik-bwtrace.php", "oik-bwtrace/oik-bwtrace.php" );
  bw_add_relocation( "oik/includes/bwtrace.inc", "oik-bwtrace/includes/bwtrace.inc" );
  bw_add_relocation( "oik/includes/oik-bwtrace.inc", "oik-bwtrace/includes/oik-bwtrace.inc" );
  bw_add_relocation( "oik/admin/oik-bwtrace.inc", "oik-bwtrace/admin/oik-bwtrace.inc" );
  bw_add_relocation( "oik/admin/oik-bwaction.inc", "oik-bwtrace/admin/oik-bwaction.inc" );
}

