<?php

error_reporting(E_ALL);
require_once('sdk/sdk.class.php');
require_once('common.inc.php');

// Create the S3 access object
$s3  = new AmazonS3();

$sdb = new AmazonSDB();
$sdb->create_domain(GALLERY_DOMAIN);

$fileName = pathinfo($_FILES["img_file"]["name"], PATHINFO_BASENAME);
echo $_FILES["img_file"]["size"];
if ((($_FILES["img_file"]["type"] == "image/gif")
	|| ($_FILES["img_file"]["type"] == "image/jpeg") || ($_FILES["img_file"]["type"] == "image/pjpeg")
	|| ($_FILES["img_file"]["type"] == "image/png")) && ($_FILES["img_file"]["size"] < 2000000)){
	if ($_FILES["img_file"]["error"] > 0){
		echo "Error: " . $_FILES["img_file"]["error"];
   }else{
		$data = file_get_contents($_FILES["img_file"]["tmp_name"]);
		$contentType = guessType($_FILES["img_file"]["name"]);
		
		if (uploadObject($s3, GALLERY_BUCKET, $fileName, $data, AmazonS3::ACL_PUBLIC, $contentType)){
			$dataThumb   = thumbnailImage($data, $contentType);
			if(uploadObject($s3, GALLERY_THUMBS_BUCKET, $fileName, $dataThumb, AmazonS3::ACL_PUBLIC, $contentType)){
				$attrs = array('title' => $_POST["img_title"],
       		'description' => $_POST["img_desc"]);
				$sdb->put_attributes(GALLERY_DOMAIN, $fileName, $attrs, true);
				header( 'Location: index.php' );
			}
		}
		echo "Could not upload file '${fileName}' to bucket '{GALLERY_BUCKET}'\n";
	}
}else{
	echo "Invalid file '${fileName}'";
}

?>