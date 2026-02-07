<?php

if(!class_exists('bcloud')){
	include_once dirname(__FILE__, 3) . '/backuply/lib/bcloud.php';
}

final class caws extends bcloud{
	public $product_name = 'AWS';
	
	//Don't need to do anything here as the bcloud class will handle the rest

}