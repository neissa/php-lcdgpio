<?php
require 'vendor/autoload.php';

use lcdgpio\messagelcd;

$phpled = new messagelcd();

$phpled->run(isset($argc)?$argc:'',isset($argv)?$argv:'');