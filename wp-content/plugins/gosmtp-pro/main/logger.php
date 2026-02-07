<?php
namespace GOSMTP;

/**
* Class Logger.
*
* @since 1.0.0
*/
class Logger{
	
	public $table;

	public function __construct(){
		global $wpdb;
		
		$this->table = $wpdb->prefix . GOSMTP_DB_PREFIX .'email_logs';
	}

	public function create_table(){
		global $wpdb;
		
		$charsetCollate = $wpdb->get_charset_collate();

		if($wpdb->get_var("SHOW TABLES LIKE '$this->table'") == $this->table){
			return;
		}

		$sql = "CREATE TABLE $this->table (
		`id` INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
		`site_id` INT UNSIGNED NULL,
		`message_id` VARCHAR(255) NULL,
		`to` VARCHAR(255),
		`from` VARCHAR(255),
		`subject` VARCHAR(255),
		`body` LONGTEXT NULL,
		`headers` LONGTEXT NULL,
		`attachments` LONGTEXT NULL,
		`status` VARCHAR(20) DEFAULT 'pending',
		`response` TEXT NULL,
		`extra` TEXT NULL,
		`retries` INT UNSIGNED NULL DEFAULT 0,
		`resent_count` INT UNSIGNED NULL DEFAULT 0,
		`provider`  TEXT NULL,
		`source` VARCHAR(255) NULL,
		`created_at` TIMESTAMP NULL,
		`updated_at` TIMESTAMP NULL,
		`parent_id` INT UNSIGNED NULL DEFAULT 0
		) $charsetCollate;";
		
		// Make Sitepad compatible 
		$upgrade = ABSPATH . 'site-admin/includes/upgrade.php';
		$upgrade = file_exists($upgrade) ? $upgrade : ABSPATH . 'wp-admin/includes/upgrade.php';
		require_once( $upgrade );

		dbDelta($sql);
	}

	public function add_logs($data){
		global $wpdb;

		if(empty($data)){
			return false;
		}

		$result = $wpdb->insert($this->table, $data);
		
		if($result){
			return $wpdb->insert_id;
		}
		
		return false;
	}

	public function update_logs($data, $id){
		global $wpdb;
		
		if(empty($data) || empty($id)){
			return false;
		}
		
		$result = $wpdb->update( $this->table, $data, array( 'id' => $id ) );
		
		if($result){
			return true;
		}
		
		return false;
	}


	public function delete_log($id){
		global $wpdb;

		$data['id'] = $id;
		$result = $wpdb->delete($this->table, $data);
		
		if(!empty($result)){
			return true;
		}
		
		return false;
	}

	public function get_logs($for = 'records', $id = '', $args = array()){
		global $wpdb;
		
		$defaults = array(
			'interval' => array(),
			'limit' => 10,
			'offset' => 0,
			'pagination' => true,
			'multiselect' => array(),
		);
		
		$args = wp_parse_args( $args, $defaults );

		$query = '';
		$start = '';
		$end = date('y-m-d').' 23:59:59';
		
		if(!empty($args['filter'])){
			$query .= 'status="'.$args['filter'].'"';
		}
		
		if(!empty($args['interval']) && !empty($args['interval']['start'])){
			
			$start = $args['interval']['start'].' 00:00:00';
			
			if(!empty($args['interval']['end'])){
				$end = $args['interval']['end'].' 23:59:59';
			}
			
			$query .= (!empty($query) ? ' and (' : '').'`created_at` between "'.$start.'" and "'.$end.'" '.(!empty($query) ? ') ' : ' ');
		}

		if(!empty($args['search']) && empty($args['multiselect'])){
			$query .=  (!empty($query) ? ' and ' : '').' (
				`from` like "%'. $args['search'] .'%" or 
				`subject` like "%'. $args['search'] .'%" or 
				`to` like "%'. $args['search'] .'%" or 
				`body` like "%'. $args['search'] .'%" )';
		}else if(!empty($args['search'])){
			$search_qry ='';
			
			foreach($args['multiselect'] as $key => $value){
				$search_qry .= '`'.$value.'` like "%'.$args['search'].'%"';
				
				if(count($args['multiselect']) - 1 > $key){
					$search_qry .= ' or ';
				}	
			}
			
			$query .= (!empty($query) ? ' and ' : '').' ('.$search_qry.')';
		}

		if(!empty($query)){
			$query = ' where '.$query;
		}

		if(!empty($id)){
			$query = ' where id=' . $id;
		} else{
			if($for !== 'count'){
				$query .= ' order by id desc';
				if(!empty($args['pagination'])){
					$query .= ' LIMIT '.$args['limit'].' OFFSET '.$args['offset'];
				}
			}
		}
		
		// echo "SELECT * FROM ".$this->table. $query;

		try{
			if($for == 'count'){
				$result = $wpdb->get_results("SELECT count(*) as records FROM ".$this->table. $query)[0];
			}else{
				$result = $wpdb->get_results("SELECT * FROM ".$this->table. $query);
				if( count($result) == 0 ){
					return false;
				}
			}
			
			return $result;
		}catch(\Exception $e){}
		
		return false;
	}

	public function clear_records( $period = 0 ){
		
		if( empty($period) || $period == 0 ) return;
		
		global $wpdb;
		
		$date = ( new \DateTime( '-'.$period.' seconds' ))->format('Y-m-d H:i:s');
		$query = 'DELETE FROM `'.$this->table.'` WHERE created_at < %s';
		if( $period == -1 ){
			$query = 'TRUNCATE TABLE '.$this->table;
			$date = '';
		}
		
		$wpdb->query(
			$wpdb->prepare( $query , $date)
		);
	}
	
	public static function retention_logs(){
		global $gosmtp;
		
		if(empty($gosmtp->options['logs']['retention_period'])){
			return;
		}

		$logger = new Logger();
		
		// Clear logs
		$logger->clear_records($gosmtp->options['logs']['retention_period']);
	}
}