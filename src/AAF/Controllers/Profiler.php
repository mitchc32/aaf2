<?php

namespace AAF\Controllers;

use AAF\App as App;
use AAF\Routing\Routes;
use AAF\Http\Response as Response;

class Profiler extends Plugin {

    public function _default() {
        /* add in the css */
        Response::addStyle($this->render([], 'profiler.css.twig'));

        /* in the js */
        Response::addScript($this->render([], 'profiler.js.twig'));

        /* set the variables for the profiler template */
        $vars = [
            'httpCode' => http_response_code(),
            'route' => Routes::$route,
            'roles' => App::get('_aaf_user', $_SESSION),
            'memory' => memory_get_peak_usage(true),
            'time' => [
                'start' => App::get('REQUEST_TIME_FLOAT', $_SERVER),
                'end' => microtime(true),
                'profile' => App::$profile,
                'total' => 0
            ]
        ];

        /* calculate the total execution time */
        $vars['time']['total'] = round($vars['time']['end'] - $vars['time']['start'], 3);

        /* put it all together in the template */
        return $this->render($vars, 'profiler.html.twig');
    }

}