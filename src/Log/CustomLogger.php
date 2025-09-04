<?php declare(strict_types=1);

namespace OptimizelyCampaign\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class CustomLogger implements LoggerInterface
{
    public const PLUGIN_NAME = 'OptimizelyCampaign';

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    //    public function emergency($message, array $context = array())
    //    {
    //        $this->logger->emergency($this->constructMessage($message), $context);
    //    }
    public function emergency($message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $this->constructMessage($message), $context);
    }

    //    public function alert($message, array $context = array())
    //    {
    //        $this->logger->alert($this->constructMessage($message), $context);
    //    }
    public function alert($message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $this->constructMessage($message), $context);
    }

    //    public function critical($message, array $context = array())
    //    {
    //        $this->logger->critical($this->constructMessage($message), $context);
    //    }
    public function critical($message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $this->constructMessage($message), $context);
    }

    //    public function error($message, array $context = array())
    //    {
    //        $this->logger->error($this->constructMessage($message), $context);
    //    }
    public function error($message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $this->constructMessage($message), $context);
    }

    //    public function warning($message, array $context = array())
    //    {
    //        $this->logger->warning($this->constructMessage($message), $context);
    //    }
    public function warning($message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $this->constructMessage($message), $context);
    }

    //    public function notice($message, array $context = array())
    //    {
    //        $this->logger->notice($this->constructMessage($message), $context);
    //    }
    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $this->constructMessage($message), $context);
    }

    //    public function info($message, array $context = array())
    //    {
    //        $this->logger->notice($this->constructMessage($message), $context);
    //    }
    public function info($message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $this->constructMessage($message), $context);
    }

    //    public function debug($message, array $context = array())
    //    {
    //        $this->logger->debug($this->constructMessage($message), $context);
    //    }
    public function debug($message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $this->constructMessage($message), $context);
    }

    public function log($level, $message, array $context = []): void
    {
        $this->logger->log($level, $this->constructMessage($message), $context);
    }

    private function constructMessage($message)
    {
        if (\is_string($message)) {
            $message = self::PLUGIN_NAME . ' - ' . $message;
        }

        return $message;
    }
}
