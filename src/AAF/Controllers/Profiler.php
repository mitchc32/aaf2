<?php

namespace AAF\Controllers;

use AAF\App;
use AAF\Routing\Routes;
use AAF\Http\Response;

class Profiler extends BaseController {

    public function _default() {
        /* add in the css */
        Response::addStyle($this->renderString([], file_get_contents(__DIR__.'/../Views/profiler.css.twig')));

        /* in the js */
        Response::addScript($this->renderString([], file_get_contents(__DIR__.'/../Views/profiler.js.twig')));

        /* set the variables for the profiler template */
        $vars = [
            'httpCode' => http_response_code(),
            'route' => Routes::$route,
            'roles' => (session_status() == PHP_SESSION_ACTIVE) ? App::get('_aaf_user', $_SESSION) : [],
            'memory' => memory_get_peak_usage(true),
            'time' => [
                'start' => (App::valid('REQUEST_TIME_FLOAT', $_SERVER)) ? $_SERVER['REQUEST_TIME_FLOAT'] : 0,
                'end' => microtime(true),
                'profile' => App::$profile,
                'total' => 0
            ]
        ];

        /* calculate the total execution time */
        $vars['time']['total'] = round($vars['time']['end'] - $vars['time']['start'], 3);

        /* put it all together in the template */
        return $this->renderString($vars, file_get_contents(__DIR__.'/../Views/profiler.html.twig'));
    }

}