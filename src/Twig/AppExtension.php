<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    /** @var ContainerInterface The application's container interface. */
    protected $container;

    /** @var float Request time. */
    private $requestTime;

    /**
     * AppExtension constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Custom functions made available to Twig.
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('request_time', [$this, 'requestTime']),
        ];
    }

    /**
     * Get the duration of the current HTTP request in seconds.
     * @return float
     */
    public function requestTime(): float
    {
        if (isset($this->requestTime)) {
            return $this->requestTime;
        }

        $startTime = $this->container->get('request_stack')
            ->getCurrentRequest()
            ->server
            ->get('REQUEST_TIME_FLOAT');
        $this->requestTime = microtime(true) - $startTime;

        return $this->requestTime;
    }
}
