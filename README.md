# BC CMS

BC CMS is a full stack PHP/Javascript/SCSS framework with CMS for websites and other browser based projects.

BC CMS is branched from Codeigniter 2.0 (2013)

Needs PHP >= 5.6 and MySQL compatible database.

Project goals:

* For developers - to give a clean HMVC framework to work with, by separating modules and panels in one way, 
  and controllers, views, modules, javascript and css in other way. There is no need for compilation step (like Grunt),
  as everything is compiled on the fly but cached for speed. Additionally the framework keeps finding different parts
  of the application easy and has a lot of built-in website oriented helpers. The resulting application has to be highly
  portable to different hosting environments.
* For designers and project managers - to allow designers, dependent of experience, work with content, css or templates
  even when website is built. This allows more flexibility and agileness in project management. Very modular structure allows
  making changes in very late stages of the project in very big hurry and still not mess up everything.
* For content managers - to give straightforward, glutter free and logical CMS interface (as much as project complexity allows).

Setup:

Blank database is in "_db" directory.

Configuration files are in "config" directory:
* Config file name has to be "hostname.php" (if your project is visible in http://www.mysite.com/, rename config file to 
  "www.mysite.com.php")
* Base url has to be "folder" in your url, including "/" in the end (if your url is http://localhost/bccms/ or
  http://www.mysite.com/bccms/, this has to be "/bccms/")
* Base path has to be your website location in server filesystem (eg "/var/www/html/www.mysite.com/")
* Modules is an array of modules, "cms" should be always included. (EG to add "mysite" module, just add "mysite" to the array) 

Check .htaccess file and remove redirection to www start when needed.

CMS login is at your website location + "admin/" (http://www.mysite.com/admin/)
