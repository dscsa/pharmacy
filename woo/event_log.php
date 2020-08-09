<?php

class EventLog{
    function tableName(){
        global $wpdb;
        return $wpdb->prefix . 'goodpill_event_log';
    }
    function init(){
        $table_name = $this->tableName();

        $sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		type varchar(30) NULL,
		data json NOT NULL,
		time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		PRIMARY KEY  (id)
	);";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    function log($type, $data){
        global $wpdb;

        $data = json_encode($data);
        $wpdb->insert($this->tableName(), [
            'type' => $type,
            'data' => $data,
            'time' => current_time('mysql')
        ]);
    }
}
