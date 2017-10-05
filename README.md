# extension-static-generator
Directus extension for the static website generator

## Installation

1. Clone this repo into `/customs/extensions/static_generator`  
`$ cd customs/extensions`  
`$ git clone git@github.com:directus/extension-static-generator.git static_generator`
2. Checkout the `dev-multi` branch  
`$ cd static_generator`  
`$ git checkout dev-multi`
3. Install the `npm` dependencies  
`$ npm install`

_Note: If you've installed this extension by downloading this repo as zip, and moving it into the folder yourself; please make sure to rename the folder to `static_generator`_

## Setting up for recurring site generation
A cron job can be set up to generate the website based on the selected frequency in the extensions admin panel settings.  To do so, set up a cron job on
your server to run every minute:

* * * * * wget -O - http://yoursite.com/api/extensions/static_generator/cron >/dev/null 2>&1

NOTE:  This is for Linux based systems and require the `wget` program, which is typically installed by default.  
