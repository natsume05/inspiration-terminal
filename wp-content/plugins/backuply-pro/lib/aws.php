<?php

if(!class_exists('bcloud')){
	include_once dirname(__FILE__, 3) . '/backuply/lib/bcloud.php';
}

class aws extends bcloud {
	public $product_name = 'AWS'; // Updating the product data, for logs
	
	//Don't need to do anything here as the bcloud class will handle the rest
}