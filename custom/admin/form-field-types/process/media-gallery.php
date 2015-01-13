<?	
	$gallery = array();
	
	$storage = new BigTreeStorage;
	if (is_array($field["input"])) {
		foreach ($field["input"] as $photo_count => $data) {
			
			if ($data["existing_video"] != $data["video"]) {
				$id = $data["video"];
				$videoType = $data["type"];
				stream_context_set_default(array('http' => array('method' => 'HEAD')));
				$found = true;
				if ($videoType == "youtube") {
					// Figure out if we have a 404. YouTube only.
					$headers = get_headers("http://i.ytimg.com/vi/$id/maxresdefault.jpg");
					foreach ($headers as $header) {
						if (substr($header,0,4) == "HTTP") {
							if (strpos($header,"404") !== false) {
								$found = false;
							}
						}
					}
				}
				if ($found) {
					if ($videoType == "youtube") {
						$image = "http://i.ytimg.com/vi/$id/maxresdefault.jpg";
					} else if ($videoType == "vimeo") {
						$imageHash = BigTree::cURL("http://vimeo.com/api/v2/video/$id.json");
						$json = json_decode($imageHash,true);
						$image = $json[0]["thumbnail_large"];
					}
				} else {
					$r = BigTree::cURL("http://gdata.youtube.com/feeds/api/videos/$id?v=2&prettyprint=true&alt=json");
					$json = json_decode($r,true);
					$thumbwidth = 0;
					$image = false;
					foreach ($json["entry"]['media$group']['media$thumbnail'] as $thumb) {
						if ($thumb["width"] > $thumbwidth) {
							$thumbwidth = $thumb["width"];
							$image = $thumb["url"];
						}
					}
				}
				// Make a copy of the YouTube image for us to pass off to the cropper.
				$local_copy = SITE_ROOT."files/".uniqid("temp-").$pinfo["extension"];
				file_put_contents($local_copy,BigTree::cURL($image));
				list($width, $height, $type, $attr) = getimagesize($local_copy);
				echo $width." ".$options["min_width"]." ".$height." ".$options["min_height"]."\n";
				
				if ($width < $options["min_width"] || $height < $options["min_height"]) {
					upscaleImage($local_copy,$local_copy,$options["min_width"],$options["min_height"]);
				}
		
				$value = $storage->store($local_copy,$id.".jpg",$field["options"]["directory"],false);
				$pinfo = BigTree::pathInfo($value);
				if (is_array($field["options"]["crops"])) {
					foreach ($field["options"]["crops"] as $crop) {
						// Make a square if the user forgot to enter one of the crop dimensions.
						if (!$crop["height"]) {
							$crop["height"] = $crop["width"];
						} elseif (!$crop["width"]) {
							$crop["width"] = $crop["height"];
						}
						$bigtree["crops"][] = array(
							"image" => $local_copy,
							"directory" => $field["options"]["directory"],
							"retina" => $field["options"]["retina"],
							"name" => $pinfo["basename"],
							"width" => $crop["width"],
							"height" => $crop["height"],
							"prefix" => $crop["prefix"],
							"thumbs" => $crop["thumbs"]
						);
					}
				}
				
				$gallery[] = array(
					"video" => $id,
					"type" => $videoType,
					"image" => $value,
					"caption" => $data["caption"]
				);
			} else {
				unset($data["existing_id"]);
				$gallery[] = $data;
			}
		}
	}

	$field["output"] = $gallery;
?>