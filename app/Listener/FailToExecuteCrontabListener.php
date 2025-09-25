<?php


declare(strict_types=1);

namespace App\Listener;

use Hyperf\Crontab\Event\FailToExecute;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

#[Listener]
class FailToExecuteCrontabListener implements ListenerInterface
{
    protected LoggerInterface $logger;

    public function __construct(ContainerInterface $container)
    {
        $this->logger = $container->get(LoggerFactory::class)->get('text');
    }

    public function listen(): array
    {
        return [
            FailToExecute::class,
        ];
    }

    /**
     * @param FailToExecute $event
     */
    public function process(object $event): void
    {
        var_dump($event->crontab->getName());
        var_dump($event->throwable->getMessage());
        $this->logger->info(sprintf('[%s] %s', $event->time, $event->throwable->getMessage()));
    }
}
