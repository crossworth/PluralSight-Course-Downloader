# PluralSight Course Downloader
---
Simple PHP script to download PluralSight courses.

This script simulate a real user watching the courses to avoid getting your account banned.
**Pluralsight will ban you** if you make too many requests or with no interval between the requests.

To avoid this problem the script will by default download each video file and wait a few seconds.
You can set the wait time on the **$config** array, the default value is **full** which will wait the full length of the video, you can set the value to: full (the full video length), half (half the video length) or a number.

The default message from Pluralsight when banning you is that you have made a lot of requests with a interval less than 40 seconds.
I have tried to set the interval to 60 seconds and I was still got banned after a few days.

*You have to edit the `pluralsight.php` file with your login information.*

### How to use
Before you can use this script you have to install the dependencies.
From terminal run `composer install`.

You must run this script from the terminal `php pluralsight.php`, make sure you have changed the user and password.
Put the courses links on the file `download.txt`, one course per line.


**Use it at your own risk**