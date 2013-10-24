<?php
error_reporting(E_ALL);
require_once('sdk/sdk.class.php');
require_once('common.inc.php');

// Create the S3 and CloudFront access objects
$s3 = new AmazonS3();
$cf = new AmazonCloudFront();
$sdb = new AmazonSDB();
// Find distributions for the two buckets
$dist       = findDistributionForBucket($cf, GALLERY_BUCKET);
$thumbsDist = findDistributionForBucket($cf, GALLERY_THUMBS_BUCKET);

// Get list of all objects in main bucket
$objects = getBucketObjects($s3, GALLERY_BUCKET);

// Get list of all objects in thumbnail bucket
$objectThumbs = getBucketObjects($s3, GALLERY_THUMBS_BUCKET);

/*
 * Create associative array of available thumbnails,
 * mapping object key to thumbnail URL (either S3
 * or CloudFront).
 */

$thumbs = array();
foreach ($objectThumbs as $objectThumb){
  $key = (string) $objectThumb->Key;

  if ($thumbsDist != null){
    $thumbs[$key] = 'http://' . $thumbsDist->DomainName . "/" . $key;
  }else{
    $thumbs[$key] = $s3->get_object_url(GALLERY_THUMBS_BUCKET, $key);
  }
}

$fileList = array();
foreach ($objects as $object){
  $key = (string) $object->Key;

  if ($dist != null){
    $url = 'http://' . $dist->DomainName . "/" . $key;
  }else{
    $url = $s3->get_object_url(GALLERY_BUCKET, $key);
  }
  $thumbURL = isset($thumbs[$key]) ? $thumbs[$key] : '';
  $fileList[] = array('thumb' => $thumbURL, 'url' => $url, 'name' => $key, 'size' => number_format((int)$object->Size));
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
  <link rel="stylesheet" type="text/css" href="resources/css/jquery.ad-gallery.css">
  <link rel="stylesheet" type="text/css" href="resources/css/buttons.css">
  <link rel="stylesheet" type="text/css" href="resources/css/main.css">
  <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>
  <script type="text/javascript" src="resources/js/jquery.ad-gallery.js"></script>
  <script type="text/javascript">
  function deleteImg(fileName){
		if(confirm('Are you sure to remove this image and its info from S3 and SDB repectively?')){  		
  			window.location='delete.php?file_name='+fileName;
  		}
  }
  $(function() {
    var galleries = $('.ad-gallery').adGallery({
    	loader_image: 'resources/images/loader.gif'
    });
  });
  </script>
  <title>AWS Powered Gallery Demo by Sirimon</title>
</head>
<body>
  <div id="container">
    <h1>AWS Powered Image Gallery</h1>
    <p>S3, CloudFront, SimpleDb, EC2 (EBS+Elastic IPs+Security Groups+Load Balancers)</p>
    <div id="gallery" class="ad-gallery">
      <div class="ad-image-wrapper">
      </div>
      <div class="ad-controls">
      </div>
      <div class="ad-nav">
        <div class="ad-thumbs">
          <ul class="ad-thumb-list">
          	<?php foreach($fileList as $file): ?>
          	<?php
          		$res = $sdb->get_attributes(GALLERY_DOMAIN, $file['name']);
          		$img_data = array();
          		foreach($res->body->GetAttributesResult->Attribute as $attr){
						if($attr->Name=='title'){
							$img_data['title'] = $attr->Value;
						}else if($attr->Name=='description'){
							$img_data['desc'] = $attr->Value;
						}
          		}
          	?>
				<li>
              <a href="<?php echo $file['url'] ?>">
                <img src="<?php echo $file['thumb'] ?>" title="<?php echo $img_data['title']?>" alt="<?php echo $img_data['desc']?>">
              </a>
              <div style="float:right;">
                <img src="resources/images/remove.png" width="12" height="12" style="border:none;" title="Delete" 
                	onclick="deleteImg('<?php echo $file['name']?>');"/>
              </div>
            </li>
				<?php endforeach ?>
          </ul>
        </div>
      </div>
    </div>
    <form id="form" action="upload.php" method="post" enctype="multipart/form-data">
    	<div style="margin: 20px;">
    		<label class="field">
    			Title
    		</label>
    		<div class="value">
    			<input type="text" name="img_title" />
    		</div>
    		<label class="field">
    			Description
    		</label>
    		<div class="value">
    			<input type="text" name="img_desc" style="width:450px;"/>
    		</div>
    		<label class="field">
    			File
    		</label>
    		<div class="value">
    			<input type="file" name="img_file" />
    		</div>
    		<div class="buttons" style="margin: 15px 0px 20px 0px;">
    			<button type="button" class="positive" onclick="document.getElementById('form').submit();">
    				<img src="resources/images/apply2.png"/>Upload
    			</button>
    		</div>
    	</div>
    </form>
  </div>
  <div style="height:50px;"></div>
</body>
</html>