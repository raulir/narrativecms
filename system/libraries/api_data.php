<?php
$GLOBALS['ajax_lib_version'][basename(__FILE__, '.php')] = 1;

/**
 * 
 * put data to db
 * 
 * @param string $id <=10 chars string
 * @param string $type <=10 chars, order/finished/registration/limiter
 * @param array data to be jsonised
 * 
 */
function put_data($id, $type, $data){
	
    global $db;
    
    $data_str = json_encode($data, JSON_PRETTY_PRINT);
    $time = time();
	
    $query = $db->prepare("INSERT INTO `cms_api` SET data_id = ?, type = ?, data = ?, created = ?, updated = ? ".
    		"ON DUPLICATE KEY UPDATE data = ?, updated = ? ");
    $query->bind_param('sssssss', $id, $type, $data_str, $time, $time, $data_str, $time);
    $query->execute();

}

/**
 * 
 * get data from db
 * 
 * @param string $id
 * @param string $type optional
 * 
 */
function get_data($id, $type = ''){
    
    global $db;
	
    if (!empty($type)){
        
        $query = $db->prepare("SELECT * FROM `cms_api` WHERE data_id = ? AND type = ? ");
        $query->bind_param('ss', $id, $type);
        $query->execute();
       
    } else {
        
        $query = $db->prepare("SELECT * FROM `cms_api` WHERE data_id = ? ");
        $query->bind_param('s', $id);
        $query->execute();
    	
    }
    
    $result = $query->get_result();
    
    $return = [];
    while ($row = $result->fetch_assoc()) {
    	$return[] = $row;
    }
    
    if (empty($return[0])){
        $return = false;
    } else {
        $return = json_decode($return[0]['data'], true);
    }

    return $return;
    
}

function delete_data($id, $type = ''){

    global $db;
	
    if (empty($type)){
    	
    	$query = $db->prepare("DELETE FROM `cms_api` WHERE data_id = ? ");
    	$query->bind_param('s', $id);
    	$query->execute();
    	 
    } else {

    	$query = $db->prepare("DELETE FROM `cms_api` WHERE data_id = ? AND type = ? ");
    	$query->bind_param('ss', $id, $type);
    	$query->execute();

    }

}

/*
function get_data_period_update($type, $start_time, $end_time, $limit = 10000){
    
    global $wpdb;
    
    $sql = "SELECT * FROM `cms_api` WHERE type = %s AND updated >= %s AND updated < %s LIMIT ".(int)$limit." ";
    $query = $wpdb->prepare($sql, $type, $start_time, $end_time);

    $result = $wpdb->get_results( $query, ARRAY_A );

    $return = [];
    
    foreach($result as $row){
        $return[$row['data_id']] = json_decode($row['data'], true);
    }
    
    return $return;
    
}

function get_data_period_times($type){
    
    global $wpdb;
    
    $sql = "SELECT updated FROM `cms_api` WHERE type = %s ";
    $query = $wpdb->prepare($sql, $type);

    $result = $wpdb->get_results( $query, ARRAY_A );
    
    $return = [];
    
    foreach($result as $row){
        $date = date('Y-m-d', $row['updated']);
        $return[$date] = strtotime($date);
    }
    
    return $return;
    
}

function get_data_prefix($prefix, $type = ''){

    global $wpdb;
	
    if (!empty($type)){
        
        $sql = "SELECT * FROM `cms_api` WHERE data_id LIKE %s AND type = %s ";
        $query = $wpdb->prepare($sql, $wpdb->esc_like($prefix) . '%', $type);
        
    } else {
        
        $sql = "SELECT * FROM `cms_api` WHERE data_id LIKE %s ";
        $query = $wpdb->prepare($sql, $wpdb->esc_like($prefix) . '%');
    
    }
    
    $return = $wpdb->get_results( $query, ARRAY_A );
    
    return $return;

}
*/

function pprint($array){
	print('<pre>');
	print_r($array);
	print('</pre>');
}
