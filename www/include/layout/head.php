<!DOCTYPE html>
<html lang="en">
    <head>
        <base href="<?php echo getBaseUrl(); ?>">
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo (defined("TITLE")) ? TITLE :'Tiki Manager Web Administration ';  ?> : <?php echo $page_title; ?></title>
        <link href="vendor/twitter/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="vendor/fortawesome/font-awesome/css/font-awesome.min.css" rel="stylesheet">
        <link href="themes/<?php echo THEME; ?>/css/<?php echo THEME; ?>.css" rel="stylesheet">
    </head>

    <body>