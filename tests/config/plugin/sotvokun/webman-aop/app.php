<?php

declare(strict_types=1);

$testsPath = dirname(__DIR__, 4);

return [
    'enable' => true,
    'class_path' => sys_get_temp_dir() . '/webman-aop-tests',
    'scan_dirs' => [$testsPath . '/Fixtures/Service'],
];
