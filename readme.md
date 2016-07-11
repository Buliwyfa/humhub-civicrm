Create a file named civi-config.php with the following content:

<?php
// Put in public_html/protected/humhub/modules/user/civi-config.php

define('CIVI_SERVER', 'https://www.commonspace.scot');
define('CIVI_URI', CIVI_SERVER . '/sites/all/modules/civicrm/extern/rest.php');
# See http://civicrm.stackexchange.com/questions/9945/how-do-i-set-up-an-api-key-for-a-user

define('CIVI_SITE_KEY', '');
define('CIVI_USER_KEY', '');

define('CIVI_HH_GUID_FIELD', 'custom_8');
define('CIVI_HH_USERNAME_FIELD', 'custom_9');
define('CIVI_HH_GENDER_FIELD', 'custom_7');