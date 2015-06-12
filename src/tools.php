<?php 
namespace lcdgpio;

class tools
{
    public static function getIpLocale()
    {
        $ip = exec('ifconfig | grep "inet [^ ]*:[^ ]*" -o | cut -d: -f2 | head -n1');
        return $ip;
    }
}