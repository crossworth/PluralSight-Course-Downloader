<?php

/**
 * PluralSight Courses Downloader
 * @Author: Pedro
 * @Date:   2016-09-14 09:24:05
 * @Last Modified by:   Pedro Henrique
 * @Last Modified time: 2016-09-16 11:41:23
 */

require_once 'vendor/autoload.php';
require_once 'functions.php';


define('SITE_USER', 'USER GOES HERE');
define('SITE_PASSWORD', 'PASSWORD GOES HERE');

define('BASE_URL', 'http://app.pluralsight.com');
define('INFO_URL', 'http://app.pluralsight.com/data/course/');
define('CONTENT_DATA_URL', 'http://app.pluralsight.com/data/course/content/');
define('CLIP_URL', 'http://app.pluralsight.com/training/player?');

define('DOWNLOAD_FOLDER', 'Courses');


$download = file_get_contents('download.txt');
chdir("../"); // go up one directory


createFolder(DOWNLOAD_FOLDER);
chdir(DOWNLOAD_FOLDER);


// Real logic below
$headers = array('User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_4) AppleWebKit/601.5.17 (KHTML, like Gecko) Version/9.1 Safari/601.5.17');

$data = array('RedirectUrl' => '', 'Username' => SITE_USER, 
				'Password' => SITE_PASSWORD, 'ShowCaptcha' => 'False', 
				'ReCaptchaSiteKey' => '6LeVIgoTAAAAAIhx_TOwDWIXecbvzcWyjQDbXsaV');


$session = new Requests_Session(BASE_URL);
$session->useragent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.82 Safari/537.36';
$session->data = $data;

// Faz o login
$response = $session->post('/id');

// convert the file to lines
$download = explode("\n", $download);


foreach($download as $course) {
	if (!empty($course) && isValidPluralSightURL($course)) {
		$courseId = extractCourseID($course);

		$courseInfo = getCourseInfo($courseId, $session);
		$title = $courseInfo->title;

		echo "Course: " . $title . " \n";

		$title = createFolder($title);
		chdir($title);

		$courseContent = getCourseContent($courseId, $session);

		$content = array();

		foreach ($courseContent as $module) {
			$clips = $module->clips;
			$moduleTitle = $module->title;

			$moduleTitle = createFolder($moduleTitle);
			chdir($moduleTitle);

			echo "Module: " . $moduleTitle . " \n";

			foreach($clips as $clip) {
				$clipTitle = $clip->title;
				$playerParameters = $clip->playerParameters;

				if (empty($clip->playerParameters)) {
					continue;
				}

				$url = generateClipURL($playerParameters);
				$AlreadyExists = downloadURL($url, $clipTitle, $session);

				// only sleep if the file was downloaded
				if (!$AlreadyExists) {
					$sleepTime = rand(30, 40);
					echo "Sleeping for " . $sleepTime . " seconds\n";
					sleep($sleepTime);
				}
				
			}

			chdir("../"); // goes out of the module folder
		}

		$sleepTime = rand(20, 30);
		echo "Sleeping for " . $sleepTime . " seconds\n\n\n";
		sleep($sleepTime);
		chdir('../'); // goes out of $title
	} else {
		echo $course . " is not a valid PluralSight URL\n";
	}
}





