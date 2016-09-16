<?php

/**
 * @Author: Pedro
 * @Date:   2016-09-14 10:30:45
 * @Last Modified by:   Pedro Henrique
 * @Last Modified time: 2016-09-16 10:58:22
 * 
 * This script uses https://github.com/rmccue/Requests
 * use composer to get it
 */


function WindowsFixFileName($name) {
	if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
		$name = str_ireplace("<", "(less than)", $name);
		$name = str_ireplace(">", "(greater than)", $name);
		$name = str_ireplace(":", "-", $name);
		$name = str_ireplace("\"", "'", $name);
		$name = str_ireplace("/", "-", $name);
		$name = str_ireplace("\\", "-", $name);
		$name = str_ireplace("|", "-", $name);
		$name = str_ireplace("?", "(question mark)", $name);
		$name = str_ireplace("*", "(asterisk)", $name);
	}

	return $name;
}


function createFolder($path) {
	$path = WindowsFixFileName($path);

	
	if (!is_dir($path)) {
	    mkdir($path, 0777, true);
	}
	return $path;
}

function isValidPluralSightURL($course) {
	$re = "/(http|https):\\/\\/app\\.pluralsight.com\\/library\\/courses\\/[a-zA-Z0-9\\-_]+/";
	$result = preg_match($re, $course);
	return $result;
}


function extractCourseID($course) {
	$course = str_ireplace("https://app.pluralsight.com/library/courses/", "", $course);
	$course = str_ireplace("/table-of-contents", "", $course);
	return $course;
}


function getCourseInfo($courseID, $session) {
	$result = $session->get(INFO_URL . $courseID);
	$result =  json_decode($result->body);
	return $result;
}

function getCourseContent($courseID, $session) {
	$result = $session->get(CONTENT_DATA_URL . $courseID);
	$result =  json_decode($result->body);
	return $result;
}

function generateClipURL($playerParameters) {
	return CLIP_URL . $playerParameters;
}

function downloadURL($url, $title, $session) {
	$url_ = str_ireplace("http://app.pluralsight.com/training/player?", "", $url);

	$query = array();
	parse_str($url_, $query);

	$author = $query['author'];
	$name = $query['name'];
	$clipID = $query['clip'];
	$course = $query['course'];

	$displayId = $name . "-" . $clipID;

	$simpleURL = str_ireplace("http://app.pluralsight.com", "", $url);

	$result = $session->get($simpleURL);

	$body = $result->body;

	$wideScreenSupport = false;
	$wideScreenSupport = preg_match("/courseSupportsWidescreenVideoFormats: true,/", $body);


	// $regex = '/moduleCollection\\s*:\\s*new\\s+ModuleCollection\\((\\[.+?\\])\\s*,\\s*\\$rootScope\\)/'; 
	// preg_match_all($regex, $body, $matches);


	// $data = json_decode($matches[1][0]);


	$qualities = array(
		'high-widescreen' => array('width' => 1280, 'height' => 720),
		'high' => array('width' => 1024, 'height' => 768),
		'medium' => array('width' => 848, 'height' => 640),
		'low' => array('width' => 640, 'height' => 480),
	);

	$wideScreenSupport = false;

	$quality = ($wideScreenSupport) ? $qualities['high-widescreen'] : $qualities['high'];
	$quality = $quality['width'] . "x". $quality['height'];

	
	$ext = "mp4"; // or webm

	// We are hard coding the quality as the extesion, we should look this in the future
	$clipPost = array(
		'a' => $author,
		'cap' => 'false',
		'cn' => $clipID,
		'course' => $course,
		'lc' => 'en',
		'm' => $name,
		'mt' => $ext,
		'q' => $quality
	);

	$session->data = $clipPost;
	

	// if we get a bad request we try get another good one
	// max 8 try
	$MAX_TRY = 8;
	for ($i = 1; $i < $MAX_TRY; $i++) {
		
		$result = $session->post('/training/Player/ViewClip');

		$downloadLink = $result->body;

		if ($downloadLink == "Bad Request") {
			echo "Error, could not get the download link, trying to get another resolution/format in 5 seconds.\n";

			if ($i > 3) {
				$quality = array_values($qualities)[$i - count($qualities)]['width'] . "x". array_values($qualities)[$i - count($qualities)]['height'];

				$clipPost['q'] = $quality;
				$ext = 'webm';

				$session->data = $clipPost;
			} else {
				$quality = array_values($qualities)[$i]['width'] . "x". array_values($qualities)[$i]['height'];

				$clipPost['q'] = $quality;
				$ext = 'mp4';

				$session->data = $clipPost;
			}

			sleep(5);
		} else {
			break;
		}
	};
	

	$title = WindowsFixFileName($title);

	if (!file_exists($title . "." . $ext)) {
		echo "Downloading file: " . $title . " \n";
		file_put_contents($title . "." . $ext, fopen($downloadLink, 'r'));
		return false;
	} else {
		echo "Skipping: " . $title . " file already exists.\n";
		return true;
	}
}











