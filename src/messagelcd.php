<?php 
namespace lcdgpio;
require_once 'vendor/autoload.php';

class messagelcd
{
    const GPIO_LOW = 0;
    const GPIO_HIGH = 1;
    const GAUCHE = 0;
    const DROITE = 1;
    private $lcds = array();
    public static function cli()
    {
        if ('cli' == PHP_SAPI)
        {
            global $argc;
            global $argv;
            $phpled = new messagelcd();
            $phpled->run(isset($argc)?$argc:'',isset($argv)?$argv:'');
        }
    }
    public function run($argc, $argv)
    {
        if ('cli' == PHP_SAPI) 
        {
            $ip = tools::getIpLocale();
            $lcd = new lcd();
            $lcd->message($argv[1]);
        }
        else
        {
            //var_dump('sudo php '.__FILE__.' "'.(isset($_REQUEST['q'])?$_REQUEST['q']:'salut').'"');
            exec('sudo php '.__FILE__.' "'.(isset($_REQUEST['q'])?$_REQUEST['q']:'').'"', $a);
            echo '<pre>';var_dump($a);
        
        }
    }
    public static function shutdwon()
    {
        exec('pkill -f "'.__FILE__.'"');
    }
}
messagelcd::cli();
