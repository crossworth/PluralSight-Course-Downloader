<?php

require_once 'vendor/autoload.php';

if (php_sapi_name() != 'cli') {
    die("You must run this script from the terminal: php pluralsight.php\n");    
}

/**
* download_time
*   full - the clip duration, sleep until time is reached
*   half - half the clip duration
*   number - the number duration
* If the number is less than 30-40 seconds it's very likely the account will be banned
*
* download_file
*    name of the file with the courses link's
*
* download_folder
*    folder to put the download courses 
**/

$config = array('user' => '<YOUR_USER_NAME>', 
                'password' => '<YOUR_PASSWORD>',
                'download_folder' => 'Courses',
                'download_file' => 'download.txt', 
                'download_time' => 'full');
class PluralSight {
    const BASE_URL = 'http://app.pluralsight.com';
    const INFO_URL = 'http://app.pluralsight.com/data/course/';
    const CONTENT_DATA_URL = 'http://app.pluralsight.com/learner/content/courses/';
    const CLIP_URL = 'http://app.pluralsight.com';
    const MAX_GET_TRIES = 8;

    private $config;
    private $courses;
    private $session;

    private $defaultPath;

    public function __construct($config) {
        $this->config = $config;    
    }

    public function run() {
        $this->courses = file_get_contents($this->config['download_file']);
        $this->courses = explode("\n", $this->courses);

        $this->CreateFolder($this->config['download_folder']);
        chdir($this->config['download_folder']);
        $this->defaultPath = getcwd();

        $this->InitSession();

        foreach ($this->courses as $course) {
            $this->DownloadCourse($course);
        }
    }

    private function InitSession() {
        $headers = array('User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_4) AppleWebKit/601.5.17 (KHTML, like Gecko) Version/9.1 Safari/601.5.17');

        $data = array('RedirectUrl' => '', 'Username' => $this->config['user'], 
                      'Password' => $this->config['password'], 'ShowCaptcha' => 'False', 
                      'ReCaptchaSiteKey' => '6LeVIgoTAAAAAIhx_TOwDWIXecbvzcWyjQDbXsaV');


        $this->session = new Requests_Session(self::BASE_URL);
        $this->session->useragent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.82 Safari/537.36';
        $this->session->data = $data;

        $response = $this->session->post('/id');
    }

    private function DownloadCourse($course) {
        if (!$this->IsValidPluralSightURL($course)) {
            printf("Invalid PluralSight URL: %s\n", $course);
            return;
        }

        $courseID = $this->GetCourseIDFromURL($course);
        $courseInfo = $this->GetCourseInfo($courseID);

        if ($courseInfo->status_code != 200) {
            printf("Error, status code %d returned while requesting course info for %s\n", $courseInfo->status_code, $course);
            return;
        }

        $courseDescription = "";

        $courseInfo = json_decode($courseInfo->body);
        
        $courseDescription .= "Title: " . $courseInfo->title . "\n";
        $courseDescription .= "Level: " . $courseInfo->level . "\n";
        $courseDescription .= "Duration: " . $courseInfo->duration . "\n";
        $courseDescription .= "Release Date: " . $courseInfo->releaseDate . "\n";
        $courseDescription .= "Name: " . $courseInfo->name . "\n";

        $courseDescription .= "Authors:" . "\n";
        foreach ($courseInfo->authors as $author) {
            $courseDescription .= $author->firstName . "  " . $author->lastName . "\n";    
        }
        
        $courseDescription .= "\n";

        $courseDescription .= "Short Description: " . $courseInfo->shortDescription . "\n";
        $courseDescription .= "Description: " . $courseInfo->description . "\n";
        $courseDescription .= "\n";
        $courseDescription .= "\n";

        $durationSeconds = 0;
        $durationSeconds = preg_replace("/^([\d]{1,2})\:([\d]{2})$/", "00:$1:$2", $courseInfo->duration);
        sscanf($durationSeconds, "%d:%d:%d", $hours, $minutes, $seconds);
        $durationSeconds = $hours * 3600 + $minutes * 60 + $seconds;

        printf("Downloading course %s, expected download duration: ~%s\n", $courseInfo->title, gmdate("H:i:s", $durationSeconds + 360));

        $title = $this->createFolder($courseInfo->title);
        chdir($title);

        $courseContent = $this->GetCourseContent($courseID);
	
        $content = array();

        foreach ($courseContent->modules as $module) {

            $clips = $module->clips;
            $moduleTitle = $module->title;

            $moduleTitle = $this->createFolder($moduleTitle);
            chdir($moduleTitle);

            printf("\tModule %s\n", $moduleTitle);

            $courseDescription .= $module->title . "\n";

            foreach($clips as $clip) {
                $clipTitle = $clip->title;
                $playerParameters = $clip->playerUrl;

                if (empty($clip->playerUrl)) {
                    continue;
                }

                $courseDescription .= "    " . $clip->title . " " . $clip->duration . "\n";

                $duration = $clip->duration;

                $duration = preg_replace("/.*T([\d]{1,2})M([\d]{2})\..*/", "00:$1:$2", $duration);
                sscanf($duration, "%d:%d:%d", $hours, $minutes, $seconds);
                $duration = $hours * 3600 + $minutes * 60 + $seconds;

                $url = self::CLIP_URL . $clip->playerUrl;

                $timeStart = time();

                $this->DownloadURL($url, $clipTitle);

                $sleepTime = $this->GetSleepTime($duration, $timeStart);

                if ($sleepTime > 0) {
                    printf("\tSleeping for %d seconds\n", $sleepTime);
                    sleep($sleepTime);
                }
            }

            $courseDescription .= "\n";

            chdir("../"); // get out of the module folder
        }

        file_put_contents("Course.txt", $courseDescription);

        $sleepTimeModule = $this->GetSleepTime();
        printf("Sleeping for %d seconds\n\n", $sleepTimeModule);
        sleep($sleepTimeModule);
        chdir($this->defaultPath);
    }

    private function GetSleepTime($clipDuration = 0, $startTime = 0) {
        if ($this->config['download_time'] != 'full' && $this->config['download_time'] != 'half') {
            return (is_numeric($this->config['download_time'])) ? $this->config['download_time'] : 30;
        }

        if (empty($clipInfo) && empty($startTime)) {
            return 30;
        }
        
        if (empty($startTime)) {
            return $clipDuration;
        }

        $currentTime = time();

        $timePassed = $currentTime - $startTime;

        if ($timePassed > $clipDuration) {
            return 0;
        } else {
            $sleepTime = $clipDuration - $timePassed;
            return ($this->config['download_time'] == 'half') ? $sleepTime / 2 : $sleepTime;
        }
    }

    private function DownloadURL($url, $clipTitle) {
        $url_ = str_ireplace("http://app.pluralsight.com/player?", "", $url);
        $query = array();
        parse_str($url_, $query);

        $author = $query['author'];
        $name = $query['name'];
        $clipID = $query['clip'];
        $course = $query['course'];

        $displayID = $name . "-" . $clipID;

        $simpleURL = str_ireplace("http://app.pluralsight.com", "", $url);

        $result = $this->session->get($simpleURL);

        $body = $result->body;

        $wideScreenSupport = false;
        $wideScreenSupport = preg_match("/courseSupportsWidescreenVideoFormats: true,/", $body);

        $qualities = array(
            'high-widescreen' => array('width' => 1280, 'height' => 720),
            'high' => array('width' => 1024, 'height' => 768),
            'medium' => array('width' => 848, 'height' => 640),
            'low' => array('width' => 640, 'height' => 480),
        );

        $quality = ($wideScreenSupport) ? $qualities['high-widescreen'] : $qualities['high'];
        $quality = $quality['width'] . "x". $quality['height'];

        $formats = array("mp4", "webm");
        $currentFormat = 0;

        $clipPost = array(
            'a' => $author,
            'cap' => 'false',
            'cn' => $clipID,
            'course' => $course,
            'lc' => 'en',
            'm' => $name,
            'mt' => $formats[$currentFormat],
            'q' => $quality
        );

        $this->session->data = $clipPost;

        for ($i = 1; $i < self::MAX_GET_TRIES; $i++) {
            $result = $this->session->post('/training/Player/ViewClip');

            $downloadLink = $result->body;
	#	printf("%s",$downloadLink);
            if ($i + 1 == self::MAX_GET_TRIES && downloadLink == "Bad Request") {
                die("Error 403: Your account probably was blocked.\n");
            } elseif ($downloadLink == "Bad Request") {
                printf("\tError, could not get the download link, trying to get another resolution/format in 5 seconds.\n");

                if ($i > 3) {
                    $quality = array_values($qualities)[$i - count($qualities)]['width'] . "x". array_values($qualities)[$i - count($qualities)]['height'];

                    $clipPost['q'] = $quality;
                    $currentFormat = 1;
                    $clipPost['mt'] = $formats[$currentFormat];

                    $session->data = $clipPost;
                } else {
                    $quality = array_values($qualities)[$i]['width'] . "x". array_values($qualities)[$i]['height'];

                    $clipPost['q'] = $quality;
                    $currentFormat = 0;
                    $clipPost['mt'] = $formats[$currentFormat];

                    $session->data = $clipPost;
                }

                sleep(5);
            } else {
                break;
            }
        };
        

        $clipTitle = $this->ConvertNameForWindows($clipTitle);

        #if (!file_exists($clipTitle . "." . $formats[$currentFormat]) && filesize($clipTitle . "." . $formats[$currentFormat]) > 0) {
        if (!file_exists($clipTitle . "." . $formats[$currentFormat])) {
            echo "\tDownloading file: " . $clipTitle . " \n";
            $data = fopen($downloadLink, 'r');
            if ($data) {
                file_put_contents($clipTitle . "." . $formats[$currentFormat], $data);
            } else {
                echo "\tError downloading: " . $clipTitle . " \n";    
            }

            return false;
        } else {
            echo "\tSkipping: " . $clipTitle . " file already exists.\n";
            return true;
        }
    }

    private function GetCourseContent($courseID) {
        $result = $this->session->get(self::CONTENT_DATA_URL . $courseID);
	#var_dump($result->body);
        $result = json_decode($result->body);
        return $result;
    }

    private function GetCourseInfo($courseID) {
        $result = $this->session->get(self::INFO_URL . $courseID);
        return $result;
    }

    private function ConvertNameForWindows($name) {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $name = str_ireplace("<", "-", $name);
            $name = str_ireplace(">", "-", $name);
            $name = str_ireplace(":", "-", $name);
            $name = str_ireplace("\"", "'", $name);
            $name = str_ireplace("/", "-", $name);
            $name = str_ireplace("\\", "-", $name);
            $name = str_ireplace("|", "-", $name);
            $name = str_ireplace("?", " ", $name);
            $name = str_ireplace("*", " ", $name);
        }

        return $name;
    }

    private function CreateFolder($name) {
        $name = $this->ConvertNameForWindows($name);
    
        if (!is_dir($name)) {
            mkdir($name, 0777, true);
        }

        return $name;
    }

    private function IsValidPluralSightURL($url) {
        $regex = "/(http|https):\\/\\/app\\.pluralsight.com\\/library\\/courses\\/[a-zA-Z0-9\\-_]+/";
        $result = preg_match($regex, $url);
        return $result;
    }

    private function GetCourseIDFromURL($courseUrl) {
        $course = str_ireplace("https://app.pluralsight.com/library/courses/", "", $courseUrl);
        $course = str_ireplace("/table-of-contents", "", $course);
        return $course;
    }

}

$app = new PluralSight($config);
$app->run();
