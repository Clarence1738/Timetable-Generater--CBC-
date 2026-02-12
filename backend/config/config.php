<?php
use Medoo\Medoo;

return[
  'database' => [
  'type' => 'mysql',
  'host' => 'localhost',
  'database' => 'timetable',
  'username' => 'root',
  'password' => '',
  'charset' => 'utf8mb4',
  'option' => [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_PERSISTENT => true,
  ]
  ],
];


