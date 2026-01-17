<?php

//declare(strict_types=1);

use cryodrift\fw\Core;

if (!isset($ctx)) {
    $ctx = Core::newContext(new \cryodrift\fw\Config());
}


// Register CLI route for this module
\cryodrift\fw\Router::addConfigs($ctx, [
  'printrtojson/cli' => \cryodrift\printrtojson\Cli::class
], \cryodrift\fw\Router::TYP_CLI);
