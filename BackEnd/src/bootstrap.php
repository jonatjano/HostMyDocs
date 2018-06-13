<?php

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;

$isDevMode = true;
$config = Setup::createAnnotationMetadataConfiguration(array(__DIR__ . "/models"), $isDevMode);

$conn = [
    // 'dbname' => 'HostMyDocs',
    // 'user' => 'user',
    // 'password' => 'password',
    // 'host' => 'db',
    'driver' => 'pdo_sqlite',
    'path' => __DIR__ . '/db.sqlite'
];

$entityManager = EntityManager::create($conn, $config);
return $entityManager;
