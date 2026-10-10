<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*

| -------------------------------------------------------------------------

| Hooks

| -------------------------------------------------------------------------

| This file lets you define "hooks" to extend CI without hacking the core

| files.  Please see the user guide for info:

|

|	http://codeigniter.com/user_guide/general/hooks.html

|

*/

$hook['post_controller_constructor'] = array(
    
    'class'    => 'Formlogger',
    
    'function' => 'logFormData',
    
    'filename' => 'formlogger.php',
    
    'filepath' => 'hooks'

);

$hook['post_controller_constructor'][] = array(
    'class'    => 'UploadLogger',
    'function' => 'logUploads',
    'filename' => 'UploadLogger.php',
    'filepath' => 'hooks'
);

$hook['post_controller_constructor'][] = array(
    'class'    => 'ExtCustomerDbHook',
    'function' => 'init',
    'filename' => 'ExtCustomerDbHook.php',
    'filepath' => 'hooks'
);


// Session Guard — enforces single active session per user account
// (gated by $config['single_session_login'] in config.php)
$hook['post_controller_constructor'][] = array(
    'class'    => 'SessionGuard',
    'function' => 'check',
    'filename' => 'SessionGuard.php',
    'filepath' => 'hooks'
);

// Error Monitor — captures PHP errors, exceptions, and fatal errors to database
// Uses pre_controller so CI DB is available for logging
$hook['pre_controller'][] = array(
    'class'    => 'ErrorMonitorHook',
    'function' => 'capture',
    'filename' => 'ErrorMonitorHook.php',
    'filepath' => 'hooks'
);

/* End of file hooks.php */

/* Location: ./application/config/hooks.php */