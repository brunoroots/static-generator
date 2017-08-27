<?php 
namespace StaticGenerator;

use Directus\Util\ArrayUtils;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;

class Config {
        
    public static function getTemplateStoragePath()
    {
        return __DIR__ . '/../storage/templates';
    }
    
    public static function getOutputStoragePath()
    {
        return __DIR__ . '/../storage/output';
    }
} 