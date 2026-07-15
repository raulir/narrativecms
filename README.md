# Narrative CMS

Narrative CMS is a full stack PHP/Javascript/SCSS framework with CMS for websites and other browser based projects.

* Allows rapid and lean, no-nonsense web development
* For working with everchanging designs and constant client feedback
* Keeps codebase clean and robust, which leaves time to focus on end product, not on technical problems
* Reusable and extendable panels (page partials), grouped to the modules
* Follows HMVC pattern - keeps storage access, business logic, templating, frontend logic and styling separated for each panel
* Headless CMS option through built in json interface
* One page app option where whole site/app works by updating page partials
* Robust and tested tools to work with dynamic page partials and API calls
* Makes possible for designers and users to upload content and start building pages even in the early stages of project
* Fast. Even without optional built-in caching
* Built in image optimisation and optimal loading, automatic modern image formats (e.g. webp) serving
* Built in SCSS (SASS3) compiler
* Built in externals optimiser, only scripts and styles really needed on page are loaded
* User and access key system
* Full multi-language support

### How it works for users

* Admins can add one or more panels to page positions
* A panel is named `module/panel` (for example `user/login` or `myproject/intro`)
* Each panel is a small unit of its own: field definition, optional PHP, HTML template, SCSS, and JS
* Modules group related panels; a lot of standard functionality has modules already - and they can be extended by user's design or even functionalities

### More information

* Most deeper documentation lives under **`modules/<module>/docs/`**. 
* Start with **`modules/cms/docs/`** for the core CMS (runtime, panels, images, video, access, conventions, and more). 

### Installation and setup

https://github.com/raulir/narrativecms/wiki/2.-Installation
