<?php

class StatusLog{
    /**
     * @var StatusLog
     */
    private static $instance;
    private $log;
    private $errors = [];

    static function getInstance(){
        if(!isset(self::$instance)){
            self::$instance = new self();
        }

        return self::$instance;
    }

    static function log($message, $data = null){
            self::getInstance()->log[] = [
                'message' => $message,
                'data' => $data
            ];
    }

    static function error($message, $data = null){
        self::getInstance()->errors[] = $message;
        self::getInstance()->log($message, $data);
    }

    static function hasErrors(){
        return count(self::getInstance()->errors) > 0;
    }

    static function getErrors(){
        return self::getInstance()->errors;
    }

    static function getLastError(){
       return self::getInstance()->errors[count(self::getInstance()->errors) - 1];
    }



    static function display(){
        echo "<pre>";
        print_r(self::getInstance()->log);
        echo "</pre>";
    }
}