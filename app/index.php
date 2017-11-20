#!/usr/bin/env php
<?php


include dirname( __DIR__ ) . '/vendor/autoload.php';


$app = new \Niirrty\Md2Pdf\Md2PdfApp();
$app->run();

