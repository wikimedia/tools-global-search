<?php

declare(strict_types=1);

namespace App\Repository;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A CloudElasticRepository is responsible for communicating with the CloudElastic service.
 * @see https://wikitech.wikimedia.org/wiki/CloudElastic
 */
class CloudElasticRepository
{
    /** @var Client The GuzzleHttp client. */
    protected $client;

    /** @var array|mixed[] The array of params to pass to CloudElastic. */
    protected $params;

    /**
     * CloudElasticRepository constructor.
     * @param Client $client
     * @param mixed[] $params
     */
    public function __construct(Client $client, array $params)
    {
        $this->client = $client;
        $this->params = $params;
    }

    /**
     * Query the CloudElastic service with the given params.
     * @return mixed[]
     */
    public function makeRequest(): array
    {
        $uri = $_ENV['ELASTIC_HOST'].'/*,*:*/_search';

        $request = new \GuzzleHttp\Psr7\Request('GET', $uri, [
            'Content-Type' => 'application/json',
        ], \GuzzleHttp\json_encode($this->params));

        // FIXME: increase cURL timeout
        try {
            $res = $this->client->send($request);
        } catch (BadResponseException $e) {
            // Dump the full response in development environments since Guzzle truncates the error messages.
            if ('dev' === $_ENV['APP_ENV']) {
                dump($e->getResponse()->getBody()->getContents());
            }

            // Convert to Symfony-friendly exception.
            throw new HttpException(
                $e->getResponse()->getStatusCode(),
                $e->getResponse()->getReasonPhrase()
            );
        }

        return json_decode($res->getBody()->getContents(), true);
    }
}
