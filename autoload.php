<?php

//function walktaskLoader($class)
//{
//    print_r('walktaskLoader已加载');
//    $path = str_replace('\\', DIRECTORY_SEPARATOR, $class);
//    $file = __DIR__ . '/Tasks/' . $path . '.php';
//    if (file_exists($file)) {
//        require_once $file;
//    }
//}
//spl_autoload_register('walktaskLoader');
spl_autoload_register(function ($class){
    print_r('spl_autoload_register_1');exit;
});

//print_r('autoload已加载');
require_once __DIR__ . '/WalktaskFactory.php';
require_once __DIR__ . '/Interfaces/TaskInterface.php';
require_once __DIR__ . '/Tasks/ContinuityJoinActivityTask.php';
require_once __DIR__ . '/Tasks/GowalkTask.php';
require_once __DIR__ . '/Tasks/TeamActivityWithFriendsTask.php';
require_once __DIR__ . '/Tasks/TeamPrivateActivityTask.php';
require_once __DIR__ . '/Tasks/TeamPublicActivityTask.php';
require_once __DIR__ . '/Tasks/Walk20Task.php';
require_once __DIR__ . '/Tasks/Walk50Task.php';
require_once __DIR__ . '/Tasks/Walk300Task.php';
