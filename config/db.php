<?php
    $host = 'localhost';
    $dbname = 'YOUR_DB_NAME';
    $username = 'YOUR_DB_USERNAME';
    $password = 'YOUR_DB_PASSWORD';

    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

    try{
        $pdo = new PDO($dsn, $username, $password);

        $pdo->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );

    } catch (PDOException $e) {
        exit('データベース接続に失敗しました。');
    }