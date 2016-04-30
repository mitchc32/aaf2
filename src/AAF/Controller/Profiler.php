<?php

namespace AAF\Controller;

use AAF\App as App;
use AAF\Http\Response as Response;

class Profiler extends Plugin {

    public function _default() {
        /* add in the css */
        Response::addStyle($this->render([], 'profiler.css.twig'));

        /* set the variables for the profiler template */
        $vars = [];

        return $this->render($vars, 'profiler.html.twig');
    }

}