<?php

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}


if(!defined('WP_CLI')){
	return;
}

if(!defined('BACKUPLY_PRO')){
	die('It\'s a Pro feature');
}

/**
* Backup and restore your WordPress site.
*/
class BACKUPLY_CLI extends \WP_CLI_Command {

	/**
	  * Creates Backup
	  * ## OPTIONS
	  * [--exclude=<exclude>]
	  * : Excludes directories or database.
	  * ---
	  * options:
	  *   - dir
	  *   - db
	  * ---
	  *
	  * [--location_id=<location_id>]
	  * : Location ID in case of remote backup
	  * ---
	  * default: 0
	  * ---
	  *
	  * ## EXAMPLES
	  * # To backup just database at location_id 4
	  * $ wp backuply backup --exclude=dir --location_id=4
	  *
	  * # To backup both database and directories on local folder
	  * $ wp backuply backup
	  *
	*/
	public function backup($args, $args_assoc){
		global $backuply;

		if(!isset($backuply)){
			WP_CLI::error('Backuply is not defined!');
			die();
		}

		if(!empty($backuply['status'])){
			WP_CLI::error('Backuply is already creating a backup!');
			die();
		}

		$bak_options = array('backup_dir' => true, 'backup_db' => true, 'backup_location' => 0);
		
		// Checking for --exclude if its set to db
		if(!empty($args_assoc['exclude']) && $args_assoc['exclude'] === 'db'){
			$bak_options['backup_db'] = false;
		}

		// Checking for --exclude if its set to dir
		if(!empty($args_assoc['exclude']) && $args_assoc['exclude'] === 'dir'){
			$bak_options['backup_dir'] = false;
		}

		// Checking for --location_id=<id> arg
		if(!empty($args_assoc['location_id'])){
			$bak_options['backup_location'] = sanitize_text_field($args_assoc['location_id']);
		}

		$remote_locs = get_option('backuply_remote_backup_locs');

		if(!empty($bak_options['backup_location']) && !empty($remote_locs) && !array_key_exists($bak_options['backup_location'], $remote_locs)){
			WP_CLI::error('The location entered dosen\'t match any stored locations');
			die();
		}

		backuply_create_log_file();

		update_option('backuply_backup_stopped', false);
		update_option('backuply_status', $bak_options);
		backuply_status_log('Initializing...', 'info', 13);

		if(wp_schedule_single_event(time(), 'backuply_backup_cron')){
			wp_schedule_single_event(time() + BACKUPLY_TIMEOUT_TIME, 'backuply_timeout_check', array('is_restore' => false));
			backuply_status_log('Creating a job to start Backup', 'info', 17);
			spawn_cron(); //To call the cron immediately
			WP_CLI::success('Backup has been started and an email notification will be send on when it\'s done');
			die();
		}

		WP_CLI::error('There was some issue creating a backup.');
	}

	/**
	  * Returns Backuply and PHP version
	*/
    public function version($args, $args_assoc){

        if(!defined('BACKUPLY_VERSION')){
            WP_CLI::error('No version found');
        }

        WP_CLI::line('Backuply Version: ' . BACKUPLY_VERSION . "\n"
            . 'PHP Version : ' . phpversion()
        );
    }

	/**
	  * Restores Backup
	  *
	  * ## OPTIONS
	  * [--backup_name=<backup_name>]
	  * : Backup name you want to restore
	  *
	  * ## EXAMPLES
	  * # Restore a backup
	  * $ wp backuply restore --backup_name=wp_127.0.0.1_2022-10-17_06-35-22
	*/
	public function restore($args, $args_assoc){
		global $backuply;

		if(!isset($backuply)){
			WP_CLI::error('Backuply has not been defined!');
			die();
		}

		if(file_exists(BACKUPLY_BACKUP_DIR . 'restoration/restoration.php')){
			WP_CLI::error('Restore is already happening!');
			die();
		}

		// Checking for backu_name=<backup_name> arg
		if(empty($args_assoc['backup_name'])){
			WP_CLI::error('No backup selected for restore');
			die();
		}

		$backup_infos = backuply_get_backups_info();
		
		if(empty($backup_infos)){
			WP_CLI::error('No backup found to restore.');
		}
	
		foreach($backup_infos as $info){
			if($info->name === $args_assoc['backup_name']){
				$restore_bak = $info;
				break;
			}
		}
		
		if(empty($restore_bak)){
			WP_CLI::error('Could not find the specified backup to restore.');
			die();
		}

		$data['loc_id'] = isset($restore_bak->backup_location) ? esc_attr($restore_bak->backup_location) : '';
		$data['restore_dir'] = esc_attr($restore_bak->backup_dir);
		$data['restore_db'] = esc_attr($restore_bak->backup_db);
		$data['backup_backup_dir'] = esc_attr(BACKUPLY_BACKUP_DIR);
		$data['fname'] = esc_attr($restore_bak->name .'.'. $restore_bak->ext);
		$data['softpath'] = get_home_path();
		$data['dbexist'] = ($restore_bak->backup_db != 0) ? 'softsql.sql' : '';
		$data['soft_version'] = 'yes';
		$data['backup_file_loc'] = '';
		$data['size'] = esc_attr($restore_bak->size);
		$data['backup_site_url'] = esc_attr($restore_bak->backup_site_url);
		$data['backup_site_path'] = esc_attr($restore_bak->backup_site_path);
		$data['sess_key'] = wp_generate_password(32, false);
		$data['security'] = wp_create_nonce('backuply_nonce');
		$data['action'] = 'backuply_restore_curl_query';

		backuply_init_restore($data);
		WP_CLI::success('Restore has been initiated succesfully.');
	}

    /**
	  * Gets Last log
	  *
	  * ## OPTIONS
	  * [--format=<format>]
	  * : Format in which you want response to be.
	  *
	  * ---
	  * default: table
	  * options:
	  *   - table
	  *   - json
	  *
	  * ## EXAMPLES
	  * # Get last log in json format
	  * $ wp backuply progress --format=json
	*/
    public function progress($args, $args_assoc){
		
		$last_log = false;
		
		$is_restore = false;
		if(!empty($args_assoc['restore'])){
			$is_restore = true;
		}
		
		$log_file = BACKUPLY_BACKUP_DIR . 'backuply_log.php';
		
		if(!file_exists($log_file) || filesize($log_file) <= 0){
			WP_CLI::error('log file dosen\'t exist');
			return;
		}
		
		$log = file($log_file);
		
		$response = array();
		list($response[0]['message'], $response[0]['type'], $response[0]['progress']) = explode('|', end($log));
		
		$response[0]['message'] = strip_tags($response[0]['message']);
		$response[0]['progress'] = trim($response[0]['progress'], "\n");

		$format = 'table';
		if(isset($args_assoc['format']) && $args_assoc['format'] == 'json'){
			$format = 'json';
		}

		WP_CLI\Utils\format_items($format, $response, array('message', 'type', 'progress'));
		die();
    }

	/**
	  * Syncs with remote cloud storage
	  * ## OPTIONS
	  * [--location_id=<location_id>]
	  * : Location ID to sync backups with
	  *
	  * ## EXAMPLES
	  * $ wp backuply sync --location_id=4
	*/
	public function sync($args, $args_assoc){
		
		// Cheeking for location_id=<id> arg
		if(empty($args_assoc['location_id'])){
			WP_CLI::error('You did\'t specify remote location to sync from.');
			die();
		}
		
		if(!function_exists('backuply_sync_remote_backup_infos')) {
			include_once BACKUPLY_DIR . '/functions.php';
		}
		
		$loc = sanitize_text_field($args_assoc['location_id']);
		
		if(empty($loc)){
			WP_CLI::error('You forgot to mention the value of --location_id.');
		}
		
		$synced = backuply_sync_remote_backup_infos($loc);
		
		if(empty($synced)){
			WP_CLI::error('Unable to sync backup to the given location, make sure you entered correct location ID.');
			die();
		}

		WP_CLI::success('The backup location has been synced.');
	}
	

	/**
	  * Lists Backups and locations
	  * 
	  * ## OPTIONS
	  * <type>
	  * : List all the backups or locations.
	  * ---
	  * options:
	  *	  - backups
	  *	  - locations
	  * ---
	  *
	  * [--format=<format>]
	  * : The format you want the result.
	  * ---
	  * default: table
	  * options:
	  *   - table
	  *   - json
	  *   - yaml
	  *   - count
	  * ---
	  *
	  * ## EXAMPLES
	  * 
	  * # List all backups
	  * $ wp backuply backups
	  *
	  * # Lists all backup locations
	  * $ wp backuply locations
	  *
	  * # Lists all backups in format json
	  * $ wp backuply backups --format=json
	*/
	public function list($args, $args_assoc){
		global $backuply;

		if(!isset($backuply)){
			WP_CLI::error('Backuply has not been defined.');
			die();
		}

		if(empty($args)) {
			WP_CLI::line("usage : wpbackuply list backups
  or : wp backuply list locations");
			return;
		}
		
		$format = 'table'; //Default format will be table
		if(!empty($args_assoc['format'])){
			
			$allowed_formats = array('table', 'json', 'yaml', 'csv', 'count');

			if(in_array($args_assoc['format'], $allowed_formats)){
				$format = sanitize_text_field($args_assoc['format']);
			}
		}

		$remote_locs = get_option('backuply_remote_backup_locs');

		if(empty($args) || empty($args[0])){
			WP_CLI::error('You forgot to mention what to list please check wp help backuply list.');
		}

		$backup_infos = backuply_get_backups_info();

		if($args[0] == 'backups'){

			foreach($backup_infos as $id => $info){
				
				if(isset($info->backup_location) && (empty($remote_locs) || !array_key_exists($info->backup_location, $remote_locs))){
					continue;
				}

				$will_restore = '';

				if(!empty($info->backup_dir) && !empty($info->backup_db)){
					$will_restore = 'Files & Folders, Database';
				}else{
					if(!empty($info->backup_dir)){
						$will_restore = 'Files & Folders';
					}else{
						$will_restore = 'Database';
					}
				}

				//$tb_data[$id]['name'] = $info->name;
				$tb_data[$id]['id'] = $id;
				$tb_data[$id]['name'] = $info->name;
				$tb_data[$id]['size'] = backuply_format_size($info->size);
				$tb_data[$id]['will_restore'] = $will_restore;
				
				if(isset($info->backup_location)){
					$tb_data[$id]['loc'] = $remote_locs[$info->backup_location]['name'] .'('. $remote_locs[$info->backup_location]['protocol'] .')';
				} else {
					$tb_data[$id]['loc'] = 'Local';
				}
			}

			WP_CLI\Utils\format_items($format, $tb_data, array('id', 'name', 'size', 'will_restore', 'loc'));
		}

		if($args[0] == 'locations'){
			$table = [];

			foreach($remote_locs as $id => $loc){
				$tb_data['id'] = $id;
				$tb_data['name'] = $loc['name'] . '('. $loc['protocol'] .')';
				$tb_data['folder'] = !empty($loc['backup_loc']) ? $loc['backup_loc'] : '/';
				array_push($table, $tb_data);
			}

			WP_CLI\Utils\format_items($format, $table, array('id', 'name', 'folder'));
		}
	}
	
	/**
	  * Kills or Stops backup or restore
	  *
	  * <type>
	  * : Kills backup or restore(if its downloading file)
	  * ---
	  * options:
	  *   - backup
	  *   - restore
	  *
	  * ## EXAMPLES
	  * # Kill Backup Process
	  * $ wp backuply kill backup
	  *
	  * # Kill Restore Process
	  * $ wp backuply kill restore
	*/
	public function kill($args, $args_assoc){

		if(empty($args[0])){
			WP_CLI::error('You forgot to mention what to kill');
		}

		switch($args[0]){
			case 'backup':
				$res = $this->kill_process('backup');
				break;
				
			case 'restore':
				$res = $this->kill_process('restore');
				break;
		}
		
		
		if(!empty($res)){
			WP_CLI::success('Process killed successfully');
			return;
		}
		
		WP_CLI::error('Something Went Wrong Unabled to kill the process');
		
	}

	// Stops or kills the process
	private function kill_process($type){

		if($type === 'backup'){
			$headers['User-Agent'] = 'Backuply_CLI';
			
			backuply_status_log('Stopping the Backup', 'info', -1);
			update_option('backuply_backup_stopped', true);
			
			return true;
		}

		if($type === 'restore'){
			if(file_exists(BACKUPLY_BACKUP_DIR . 'restoration/restoration.php') || file_exists(BACKUPLY_BACKUP_DIR . 'restoration')){
				@unlink(BACKUPLY_BACKUP_DIR . 'restoration/restoration.php');
				@rmdir(BACKUPLY_BACKUP_DIR . 'restoration');
				return true;
			}
		}
		
		return false;
	}

}

WP_CLI::add_command('backuply', 'BACKUPLY_CLI');