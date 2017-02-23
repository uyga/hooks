<?php
define('HOOKS_BASEDIR', dirname(__FILE__) . '/');

spl_autoload_register(
    function($class) {
        static $map;
        if (!$map) {
            $map = require_once 'autoload.php';
        }
        if (isset($map[$class]) && file_exists($map[$class])) {
            require_once $map[$class];
        }
    }
);
