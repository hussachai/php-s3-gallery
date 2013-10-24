<?php

error_reporting(E_ALL);
require_once('sdk/sdk.class.php');
require_once('common.inc.php');

// Create the S3 access object
$s3  = new AmazonS3();
$sdb = new AmazonSDB();

$fileName = $_GET['file_name'];
$s3->delete_object(GALLERY_BUCKET, $fileName); 
$s3->delete_object(GALLERY_THUMBS_BUCKET, $fileName); 
$sdb->delete_attributes(GALLERY_DOMAIN, $fileName);
header( 'Location: index.php' );

?>