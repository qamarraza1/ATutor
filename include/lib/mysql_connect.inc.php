<?php
/************************************************************************/
/* ATutor																*/
/************************************************************************/
/* Copyright (c) 2002-2013              								*/
/* Inclusive Design Institute                                           */
/* http://atutor.ca                                                     */
/* This program is free software. You can redistribute it and/or        */
/* modify it under the terms of the GNU General Public License          */
/* as published by the Free Software Foundation.                        */
/************************************************************************/
// $Id$
if (AT_INCLUDE_PATH !== 'NULL') {

function at_db_connect($db_host, $db_port, $db_login, $db_password, $db_name){
     if(defined('MYSQLI_ENABLED')){
        $db = mysqli_connect($db_host, $db_login,$db_password, $db_name, $db_port);
     } else{
        $db = @mysql_connect($db_host . ':' . $db_port, $db_login, $db_password);
     }   
	if (!$db) {
		// AT_ERROR_NO_DB_CONNECT 
		require_once(AT_INCLUDE_PATH . 'classes/ErrorHandler/ErrorHandler.class.php');
		$err = new ErrorHandler();
		trigger_error('VITAL#Unable to connect to db.', E_USER_ERROR);
		exit;
	}
    return $db;
}

function at_db_select($db_name, $db){
 if(defined('MYSQLI_ENABLED')){
     if (!@mysqli_select_db($db, $db_name)) {
		require_once(AT_INCLUDE_PATH . 'classes/ErrorHandler/ErrorHandler.class.php');
		$err = new ErrorHandler();
		trigger_error('VITAL#DB connection established, but database "'.DB_NAME.'" cannot be selected.',
						E_USER_ERROR);
		exit;
	}

 }else{
    if (!@mysql_select_db($db_name, $db)) {
		require_once(AT_INCLUDE_PATH . 'classes/ErrorHandler/ErrorHandler.class.php');
		$err = new ErrorHandler();
		trigger_error('VITAL#DB connection established, but database "'.DB_NAME.'" cannot be selected.',
						E_USER_ERROR);
		exit;
	}
 }
 
}
/*
		//get set_utf8 config
	$sql = 'SELECT * FROM '.TABLE_PREFIX."config WHERE name='set_utf8'";
	$result = mysql_query($sql, $db);
	if ($result){
		$row = mysql_fetch_assoc($result);
	}
	if ($row['value']==1){
		mysql_query("SET NAMES 'utf8'", $db); 
	}	
*/
}
//functions for properly escaping input strings
function my_add_null_slashes( $string ) {
    global $db;
    if(defined('MYSQLI_ENABLED')){
        return mysqli_real_escape_string($db, stripslashes($string));
    }else{
        return mysql_real_escape_string(stripslashes($string));
    }

}

function my_null_slashes($string) {
    return $string;
}

if ( get_magic_quotes_gpc() == 1 ) {
    $addslashes   = 'my_add_null_slashes';
    $stripslashes = 'stripslashes';
} else {
    if(defined('MYSQLI_ENABLED')){
        // mysqli_real_escape_string requires 2 params, breaking wherever
        // current $addslashes with 1 param exists. So hack with trim and 
        // manually run mysqli_real_escape_string requires during sanitization below
        $addslashes   = 'trim';
    }else{
        $addslashes   = 'mysql_real_escape_string';
    }
    $stripslashes = 'my_null_slashes';
}

/**
 * This function is used to make a DB query the same along the whole codebase
 * @access  public
 * @param   $query = Query string in the vsprintf format. Basically the first parameter of vsprintf function
 * @param   $params = Array of parameters which will be converted and inserted into the query
 * @param   $oneRow = Function returns the first element of the return array if set to TRUE. Basically returns the first row if it exists
 * @param   $sanitize = if True then addslashes will be applied to every parameter passed into the query to prevent SQL injections
 * @param   $callback_func = call back another db function, default mysql_affected_rows
 * @param   $array_type = Type of array, MYSQL_ASSOC (default), MYSQL_NUM, MYSQL_BOTH, etc.
 * @return  ALWAYS returns result of the query execution as an array of rows. If no results were found than array would be empty
 * @author  Alexey Novak, Cindy Li, Greg Gay
 */
function queryDB($query, $params=array(), $oneRow = false, $sanitize = true, $callback_func = "mysql_affected_rows", $array_type = MYSQL_ASSOC) {
    if(defined('MYSQLI_ENABLED') && $callback_func == "mysql_affected_rows"){
        $callback_func = "mysqli_affected_rows";
    }
    $sql = create_sql($query, $params, $sanitize);
    return execute_sql($sql, $oneRow, $callback_func, $array_type);

}

function sqlout($sql){
    //output the sql with variable values inserted
    global $sqlout;
    $sqlout = $sql;
}

function create_sql($query, $params=array(), $sanitize = true){
    global $addslashes, $db;

    // Prevent sql injections through string parameters passed into the query
    if ($sanitize) {
        foreach($params as $i=>$value) {
         if(defined('MYSQLI_ENABLED')){  
             $value = addslashes($addslashes($value));     
             $params[$i] = mysqli_real_escape_string($db, $value);
            }else {
             $params[$i] = $addslashes($value);           
            }
        }
    }
    $sql = vsprintf($query, $params);
    return $sql;
}
function execute_sql($sql, $oneRow, $callback_func, $array_type){
    global $db, $msg;
    
    $oneRowErrorMessage = 'Query "%s" which should returned only 1 row has returned more rows.';
    $displayErrorMessage = array('DB_QUERY', date('m/d/Y h:i:s a', time()));
    
    try {
        sqlout($sql);
        $oneRowErrorMessage = sprintf($oneRowErrorMessage, $sql);
        // NOTE ! Uncomment the error_log() line below to start logging database queries to your php_error.log. 
        // BUT  ! Use for debugging purposes ONLY. Creates very large logs if left running.
        
    error_log(print_r($sql, true), 0);    

        // Query DB and if something goes wrong then log the problem
        if(defined('MYSQLI_ENABLED')){
               $result = mysqli_query($db, $sql) or (error_log(print_r(mysqli_error($db), true), 0) and $msg->addError($displayErrorMessage));                
        }else{
               $result = mysql_query($sql, $db) or (error_log(print_r(mysql_error(), true), 0) and $msg->addError($displayErrorMessage));
        }
        
        // If the query was of the type which does not suppose to return rows e.g. UPDATE/SELECT/INSERT
        // is_bool is for mysql compatibility
        // === null is for mysqli compatibility
        if (is_bool($result) || $result === null) {
            if ($oneRow) {
                error_log(print_r($oneRowErrorMessage, true), 0);
                $msg->addError($displayErrorMessage);
            }
            if(isset($callback_func)){
                return $callback_func($db);                
            }else{
                return array();
            }
        }
        
        // If we need only one row then just grab it otherwise get all the results
        if ($oneRow) {
              if(defined('MYSQLI_ENABLED')){
                $row = mysqli_fetch_array($result, $array_type);              
              }else {
                $row = mysql_fetch_array($result, $array_type);              
              }

            // Check that there are no more than 1 row expected.
            
              if(defined('MYSQLI_ENABLED')){
                   if (mysqli_fetch_array($result, $array_type)) {
                    error_log(print_r($oneRowErrorMessage, true), 0);
                    $msg->addError($displayErrorMessage);
                    //return array();
                    return at_affected_rows($db);
                    }           
              }else{
                  if (mysql_fetch_array($result, $array_type)) {
                    error_log(print_r($oneRowErrorMessage, true), 0);
                    $msg->addError($displayErrorMessage);
                    //return array();
                    return at_affected_rows($db);
                    }            
              }

            unset($result);
            return ($row) ? $row : array();
        }
        
        $resultArray = array();
        if(defined('MYSQLI_ENABLED')){
            while ($row = mysqli_fetch_array($result, $array_type)) {
                $resultArray[] = $row;
            }    
        }else{
            while ($row = mysql_fetch_array($result, $array_type)) {
                $resultArray[] = $row;
            }
        }
        unset($result);
        return $resultArray;
    } catch (Exception $e) {
        error_log(print_r($e, true), 0);
        $msg->addError($displayErrorMessage);
    }
}
function queryDBresult($sql, $params = array(), $sanitize = true){
        global $db, $msg;
        $sql = create_sql($sql, $params, $sanitize);

        if(defined('MYSQLI_ENABLED')){
               $result = mysqli_query($db, $sql) or (error_log(print_r(mysqli_error(), true), 0) and $msg->addError($displayErrorMessage));        
        }else{
               $result = mysql_query($sql, $db) or (error_log(print_r(mysql_error(), true), 0) and $msg->addError($displayErrorMessage));
        }
       
    return $result;
}
function at_affected_rows($db){
    if(defined('MYSQLI_ENABLED')){
        return mysqli_affected_rows($db);    
    }else{
        return mysql_affected_rows($db);
    }
}
function at_db_version($db){
 	$sql = "SELECT VERSION() AS version";	
 	if(defined('MYSQLI_ENABLED')){
        $result = mysqli_query($db, $sql);
        $row = mysqli_fetch_assoc($result); 	
 	}else{
        $result = mysql_query($sql, $db);
        $row = mysql_fetch_assoc($result);
	}
	return $row;
}	
function at_db_create($sql, $db){
 	if(defined('MYSQLI_ENABLED')){
        $result = mysqli_query($db, $sql);	
 	}else{
        $result = mysql_query($sql, $db);
    }
    return $result;
}

function at_insert_id(){
    global $db;
    if(defined('MYSQLI_ENABLED')){
        return mysqli_insert_id($db);
    }else{
        return mysql_insert_id($db);
    }
}
function at_db_errno(){
    global $db;
    if(defined('MYSQLI_ENABLED')){
        return mysqli_errno($db);    
    }else{
        return mysql_errno($db);
    }
}
function at_db_error(){
    global $db;
    if(defined('MYSQLI_ENABLED')){
        return mysqli_error($db);    
    }else{
        return mysql_error($db);
    }
}

/////////
/// USED in classes/CSVExport.class.php & CSVImport.class.php
function at_field_type($result, $i){
    if(defined('MYSQLI_ENABLED')){
        return mysqli_fetch_field_direct($result, $i);    
    }else{
        return mysql_field_type($result, $i);    
    }

}
function at_num_fields($result){
    if(defined('MYSQLI_ENABLED')){
        return mysqli_field_count($result);    
    }else{
        return mysql_num_fields($result);    
    }

}
function at_free_result($result){
    if(defined('MYSQLI_ENABLED')){
        return mysqli_free_result($result);    
    }else{
        return mysql_free_result($result);    
    }

}
function at_field_flags($result, $i){
    if(defined('MYSQLI_ENABLED')){
        return mysqli_fetch_field_direct($result, $i);    
    }else{
        return mysql_field_flags($result, $i);   
    }

}
function at_field_name($result, $i){
    if(defined('MYSQLI_ENABLED')){
        return mysqli_fetch_field_direct($result, $i);   
    }else{
        return mysql_field_name($result, $i);   
    }
}

////
?>