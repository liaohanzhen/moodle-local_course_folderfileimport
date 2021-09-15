<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Settings used by the lesson ocal_course_folderfileimport
 *
 * @package    local_course_folderfileimport
 * @copyright  Martin Liao
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 **/

defined('MOODLE_INTERNAL') || die;

// Ensure the configurations for this site are set
if ( $hassiteconfig ){

	// Create the new settings page
	// - in a local plugin this is not defined as standard, so normal $settings->methods will throw an error as
	// $settings will be NULL
	$settings = new admin_settingpage( 'local_course_folderfileimport', '课程文件夹导入' );

	// Create 
	$ADMIN->add( 'localplugins', $settings );

	// Add a setting field to the settings for this page
	$settings->add( new admin_setting_configtext(
		'local_course_folderfileimport/repos_pluginname', // the reference use to configuration
		'Repository Plugin Name', // the friendly title for the config
		'This is the repository used to access the Import course folder', // helper text for this config field
		'ossbucket', // This is the default value		
		PARAM_TEXT // This is the type of Parameter this config is
	
    ) );
    
    $settings->add( new admin_setting_configtext(
		'local_course_folderfileimport/repos_instance', // the reference use to configuration
		'Repository Instance Name', // the friendly title for the config
		'This is the repository instance name used to access the Import course folder', // helper text for this config field
		'AliyunOSSTemp', // This is the default value		
		PARAM_TEXT // This is the type of Parameter this config is
	
    ) );
    
    $settings->add( new admin_setting_configtext(
		'local_course_folderfileimport/importname_trim', // the reference use to configuration
		'Import filename trim from', // the friendly title for the config
		'This is the num used to trim import name', // helper text for this config field
		'3', // This is the default value		
		PARAM_INT // This is the type of Parameter this config is
	
    ) );
    
    $settings = new admin_settingpage( 'local_course_folderfileupload', '文件夹文件上传OSS' );

	// Create 
    $ADMIN->add( 'localplugins', $settings );
    
    $settings->add( new admin_setting_configtext(
		'local_course_folderfileupload/upload_prefix', // the reference use to configuration
		'保存到OSS路径前缀', // the friendly title for the config
		'保存到OSS路径前缀', // helper text for this config field
		'moodle_upload/', // This is the default value		
		PARAM_TEXT // This is the type of Parameter this config is
	
    ) );
    
    $settings->add( new admin_setting_configtext(
		'local_course_folderfileupload/upload_repository', // the reference use to configuration
		'文件上传使用的AliyunOSS', // the friendly title for the config
		'文件上传使用的AliyunOSS', // helper text for this config field
		'14', // This is the default value		
		PARAM_INT // This is the type of Parameter this config is
	
	) );

}
