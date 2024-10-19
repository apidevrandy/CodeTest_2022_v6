<?php

namespace DTApi\Helpers;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FirePHPHandler;

class LogHelper
{
    private $logger;
    
    private function customPushHandler($logType)
    {
        $this->logger->pushHandler(new StreamHandler(storage_path('logs/'.$logType.'/laravel-' . date('Y-m-d') . '.log'), Logger::DEBUG));
        $this->logger->pushHandler(new FirePHPHandler());
    }

    public function customAddLogInfo($logMessage, $logData)
    {
        $this->logger->addInfo($logMessage, $logData);
    }

    public function customAdminLogger($logType)
    {
        $this->logger = new Logger('admin_logger');

        $this->customPushHandler($logType);
    }

    public function customPushLogger($logType)
    {
        $this->logger = new Logger('push_logger');

        $this->customPushHandler($logType);
    }
}