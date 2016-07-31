<?php

namespace AAF\Composer;

use AAF\App;

class Installer {
    
    public static function __callStatic($method, $params) {
        echo App::dump([
            getcwd(),
            $params
        ])."\n";
    }
    
    protected static function _commandInstall() {
        echo "AAF\Installer::commandInstall\n";
    }
    
    protected static function _commandUpdate() {
        echo "AAF\Installer::commandUpdate\n";
    }
    
}