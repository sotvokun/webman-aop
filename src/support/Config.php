<?php
namespace Sotvokun\Webman\Aop\support;

class Config
{
    public static function getClassPath()
    {
        return config('plugin.sotvokun.webman-aop.app.class_path', runtime_path('aop'));
    }

    public static function getScanDirs()
    {
        return config('plugin.sotvokun.webman-aop.app.scan_dirs', []);
    }
}
