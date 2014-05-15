<?php
require_once 'vendor/autoload.php';

// Container definition
$c = new \Pimple\Container();
$c['basic_string'] = 'basic string';
$c['basic_function'] = function($c) {
    return $c['basic_string'];
};
$c['complete_function'] = function($c) {
    return array($c['basic_string'], $c['basic_function']);
};
$c['factory_function'] = $c->factory(function($c){
        return array('complex', $c['basic_string'], $c['basic_string']);
    });

// Graph fun times.
$graph = new \PimpleGraph();
$graph->processContainer($c);
echo $graph->toDot() . PHP_EOL;
