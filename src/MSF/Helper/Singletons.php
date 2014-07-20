<?php
namespace MSF\Helper;

class Singletons {
    protected static $singletons = array();

    public static function getInstance() {
        $className = get_called_class();
        if (!isset(Singletons::$singletons[ $className ])) {
            Singletons::$singletons[ $className ] = new $className();
        }
        return Singletons::$singletons[ $className ];
    }
}


