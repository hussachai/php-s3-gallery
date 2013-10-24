<?php

define('GALLERY_BUCKET', 'sirimon-gallery');
define('GALLERY_THUMBS_BUCKET', 'sirimon-gallery-thumbs');
define('THUMB_SIZE', 100);
define('GALLERY_DOMAIN', 'gallery');

session_start();
if($_SESSION['auth']!=true){
	$token = $_GET['token'];
	if($token=='sirimon18'){
		$_SESSION['auth'] = true;
	}else{
		echo "Access denied";
		exit(0);
	}
}

function getBucketObjects($s3, $bucket, $prefix='') {
	$objects = array();
	$next = '';
	do {
		$res = $s3->list_objects($bucket, array('marker'=>urldecode($next), 'prefix'=>$prefix));
		if(!$res->isOK()) return null;
		$contents = $res->body->Contents;
		foreach($contents as $content){
			$objects[] = $content;
		}
		$isTruncated = $res->body->IsTruncated == 'true';
		if($isTruncated){
			$next = $objects[count($objects)-1]->Key;
		}
	}while($isTruncated);
	
	return $objects;
}
function findDistributionForBucket($cf, $bucket){
	$res = $cf->list_distributions();
	if(!$res->isOK()){
		return null;
	}
	$needle = $bucket . ".";
	$distributions = $res->body->DistributionSummary;
	foreach($distributions as $distribution){
		if(substr($distribution->Origin, 0, strlen($needle))==$needle){
			return $distribution;
		}
	}
	return null;
}

function uploadObject($s3, $bucket, $fileName, $data, $acl=S3_ACL_PRIVATE, $contentType="image/jpeg"){
		$try = 1;
		$sleep = 1;
		//$fileResource = fopen($filePath, 'r');
		do{
			$res = $s3->create_object($bucket, $fileName, array(
				//'fileUpload'=>$fileResource,
				'body'=>$data,
				'acl'=>$acl,
				'contentType'=>$contentType
			));
			if($res->isOK()) return true;
			sleep($sleep);
			$sleep *= 2;
		}while(++$try<6);
			
		return false;
}

function guessType($file){
		$info = pathinfo($file, PATHINFO_EXTENSION);
		switch(strtolower($info)){
			case "jpg":
			case "jpeg":
				return "image/jpg";
			case "png":
				return "image/png";
			case "gif":
				return "image/gif";
			case "htm":
			case "html":
				return "text/html";
			case "txt":
				return "text/plain";
			default:
				return "text/plain";
		}
}

function thumbnailImage($imageBitsIn, $contentType){
		$imageIn = ImageCreateFromString($imageBitsIn);
		$inX = ImageSx($imageIn);
		$inY = ImageSy($imageIn);
		if($inX > $inY){
			$outX = THUMB_SIZE;
			$outY = (int)(THUMB_SIZE * ((float)$inY/$inX));
		}else{
			$outX = (int)(THUMB_SIZE * ((float)$inX/$inY));
			$outY = THUMB_SIZE;
		}
		$imageOut = ImageCreateTrueColor($outX, $outY);
		ImageFill($imageOut, 0, 0, ImageColorAllocate($imageOut, 255, 255, 255));
		ImageCopyResized($imageOut, $imageIn, 0, 0, 0, 0, $outX, $outY, $inX, $inY);
		$fileOut = tempnam("/tmp", "aws"). ".aws";
		
		switch($contentType){
			case 'image/jpg':
				$ret = ImageJPEG($imageOut, $fileOut, 100);
				break;
			case 'image/png':
				$ret = ImagePNG($imageOut, $fileOut, 0);
				break;
			case 'image/gif':
				$ret = ImageGIF($imageOut, $fileOut);
				break;
			default:
				unlink($fileOut);
				return false;
		}
		
		if(!$ret){
			unlink($fileOut);
			return false;
		}
		
		$imageBitsOut = file_get_contents($fileOut);
		unlink($fileOut);
		return $imageBitsOut;
}

?>