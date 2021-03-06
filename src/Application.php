<?php

/*
 * This file is part of the coolert/weather.
 *
 * (c) coolert <lvhui@gmx.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Coolert\PackageTemplate;

use Symfony\Component\Console\Application as BaseApplication;
use Coolert\PackageTemplate\Commands\Command;

class Application extends BaseApplication
{
    /**
     * Application constructor.
     * @param string $name
     * @param string $version
     */
    public function __construct($name,$version)
    {
        parent::__construct($name, $version);
        $this->add(new Command());
    }
}