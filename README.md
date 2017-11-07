# BC CMS

BC CMS is a full stack PHP/Javascript/SCSS framework with CMS for websites and other browser based projects.

* Allows rapid and lean, no-nonsense web development
* For working with everchanging designs and constant client feedback
* Keeps codebase clean and robust, which leaves time to focus on end product, not on technical problems
* Reusable and extendable panels (page partials), grouped to the modules
* Follows HMVC pattern - keeps storage access, business logic, templating, frontend logic and styling separated for each panel
* Robust and tested tools to work with dynamic page partials and API calls
* Makes possible for designers and users to upload content and start building pages even in the early stages of project
* Fast. Even without optional built-in caching
* Built in image optimisation and optimal loading
* Built in SCSS (SASS3) compiler
* Built in externals optimiser, only scripts and styles really needed on page are loaded

Non-technical information and case studies: http://www.bytecrackers.com/

BC CMS is branched from Codeigniter 2.0 (2013)

Needs PHP >= 5.6 and MySQL compatible database.

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
