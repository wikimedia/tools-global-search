<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    protected RequestStack $requestStack;

    /** @var float Request time. */
    private float $requestTime;

    /**
     * AppExtension constructor.
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * Custom functions made available to Twig.
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('request_time', [$this, 'requestTime']),
            new TwigFunction('csv', [$this, 'csv'], ['is_safe' => ['html']]),
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

        $startTime = $this->requestStack
            ->getCurrentRequest()
            ->server
            ->get('REQUEST_TIME_FLOAT');
        $this->requestTime = microtime(true) - $startTime;

        return $this->requestTime;
    }

    /**
     * Properly escape the given string using double-quotes so that it is safe to use as a cell in CSV exports.
     * @param string $content
     * @return string
     */
    public function csv(string $content): string
    {
        return '"'.str_replace('"', '""', $content).'"';
    }
}
