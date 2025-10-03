<?php

require __DIR__ . "/vendor/autoload.php";

$client = new Google\Client();

use Dotenv\Dotenv;

// Load .env file
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();


$client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
$client->setRedirectUri("http://localhost/AHV2/google-auth/authorized.php");

$client->addScope("email");
$client->addScope("profile");
