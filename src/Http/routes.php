<?php

$cp_path = trim(env('CMS_CP_PATH', 'cp'), '/');


$router->any($cp_path, 'Cp@homepage')->where('any', '.*');
$router->any($cp_path.'/{any}', 'Cp@all')->where('any', '.*');
