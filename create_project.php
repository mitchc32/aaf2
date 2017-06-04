<?php

// base variables

$data = [
    'name' => '',
    'directory' => '',
    'url' => '',
    'timezone' => 'America/Chicago',
    'hasDev' => false,
    'dev' => [
        'url' => ''
    ]
];

$dirs = [
    'assets/css',
    'assets/js',
    'assets/images',
    'config',
    'controllers',
    'views/website',
    'lib',
    'static'
];

// collect all the variable data from the user

echo "What is the display name of your project: ";
$data['name'] = trim(fgets(STDIN));

echo "What is the project directory (full path /full/path/to/dir): ";
$data['directory'] = trim(fgets(STDIN));
$data['directory'] = rtrim($data['directory'], '/') . '/';

echo "What is the production URL of your project: ";
$data['url'] = trim(fgets(STDIN));

echo "What is the default timezone (PHP Timezone Strings; default America/Chicago): ";
$tz = trim(fgets(STDIN));

if (!empty($tz)) {
    $data['timezone'] = $tz;
}

echo "Does this project have a dev environment (y/n): ";
$a = trim(fgets(STDIN));

if ($a == 'y') {
    $data['hasDev'] = true;
    
    echo "What is the development URL of your project: ";
    $data['dev']['url'] = trim(fgets(STDIN));
} else {
    $data['hasDev'] = false;
}

// check and create the base directory
if (!file_exists($data['directory']) && !mkdir($data['directory'], 0775, true)) {
    die("ERROR: Could not create directory $data[directory]\n");
} else if (!is_writable($data['directory'])) {
    die("ERROR: Could not directory, $data[directory], already exists but is not writable\n");
}

// create all base system directories
foreach ($dirs as $dir) {
    if (!mkdir($data['directory'] . $dir, 0775, true)) {
        die("ERROR: Could not create directory $data[directory]$dir\n");
    }
}

// create the env.json file
if (!file_put_contents($data['directory'] . 'config/env.json', fillTemplate($data, file_get_contents(__DIR__ . '/default/env.txt')))) {
    die("ERROR: Could not create file $data[directory]config/env.json \n");
}

// create the base routes file
if (!file_put_contents($data['directory'] . 'config/routes.json', file_get_contents(__DIR__ . '/default/routes.txt'))) {
    die("ERROR: Could not create file $data[directory]config/routes.json \n");
}

// create the htaccess file
if (!file_put_contents($data['directory'] . '.htaccess', file_get_contents(__DIR__ . '/default/htaccess.txt'))) {
    die("ERROR: Could not create file $data[directory].htaccess \n");
}

// create the index.php file
if (!file_put_contents($data['directory'] . 'index.php', file_get_contents(__DIR__ . '/default/index.txt'))) {
    die("ERROR: Could not create file $data[directory]index.php \n");
}

// create the base site controller
if (!file_put_contents($data['directory'] . 'controllers/Site.php', file_get_contents(__DIR__ . '/default/Site.txt'))) {
    die("ERROR: Could not create file $data[directory]controllers/Site.php \n");
}

// create the composer.json file
if (!file_put_contents($data['directory'] . 'composer.json', file_get_contents(__DIR__ . '/default/composer.txt'))) {
    die("ERROR: Could not create file $data[directory]composer.php \n");
}

// clean it all up
chmod($data['directory'], 0775);

echo "Project successfully created! Make sure to run composer update in $data[directory]\n\n";




function fillTemplate($vars, $templateStr) {
    $fill = prepVars($vars);
    
    return str_replace(array_keys($fill), array_values($fill), $templateStr);
}

function prepVars($vars, $prefix='') {
    $fill = [];
    
    foreach ($vars as $k=>$v) {
        if (is_array($v)) {
            $fill = array_merge($fill, prepVars($v, $k . '.'));
        } else {
            $fill['{{ ' . $prefix . $k . ' }}'] = $v;
        }
    }
    
    return $fill;
}