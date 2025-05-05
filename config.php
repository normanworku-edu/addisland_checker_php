<?php
// Basic configuration
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']));
define('ADDLISLAND_URL_PREFIX', 'https://www.addisland.gov.et/en-us/certificate/');