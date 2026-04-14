<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\CirrusQuery;
use App\Model\CirrusQueryValidator;
use GuzzleHttp\Client;

/**
 * Fetches the Elasticsearch query CirrusSearch would generate for a given search string,
 * using the cirrusDumpQuery debug API on test.wikipedia.org.
 */
class CirrusDumpQueryRepository
{
    /** @var Client */
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Fetch and return the parsed query AST from the cirrusDumpQueryAST API.
     *
     * @param string $searchQuery
     * @param int[] $namespaces
     * @return array The AST object (fields: query, rawQuery, featuresUsed, root, …).
     * @throws \RuntimeException
     */
    public function fetchAST(string $searchQuery, array $namespaces = []): array
    {
        $res = $this->client->request('GET', 'https://test.wikipedia.org/w/api.php', [
            'query' => [
                'action' => 'query',
                'list' => 'search',
                'srsearch' => $searchQuery,
                'srnamespace' => empty($namespaces) ? '*' : implode('|', $namespaces),
                'cirrusDumpQueryAST' => 'true',
            ],
        ]);

        $data = json_decode($res->getBody()->getContents(), true);
        return $this->extractAST($data);
    }

    /**
     * Fetch the query AST from the cirrusDumpQueryAST API, validate it for cross-wiki
     * compatibility, and then return the Elasticsearch query body.
     *
     * @param string $searchQuery
     * @param int[] $namespaces Namespace IDs to search; empty means all namespaces.
     * @return CirrusQuery
     * @throws \App\Exception\UnsupportedQueryException If the query uses host-wiki-only keywords.
     * @throws \RuntimeException If the dump cannot be retrieved or parsed.
     */
    public function getQuery(string $searchQuery, array $namespaces = []): CirrusQuery
    {
        $ast = $this->fetchAST($searchQuery, $namespaces);
        (new CirrusQueryValidator())->validate($ast);

        $res = $this->client->request('GET', 'https://test.wikipedia.org/w/api.php', [
            'query' => [
                'action' => 'query',
                'list' => 'search',
                'srsearch' => $searchQuery,
                'srnamespace' => empty($namespaces) ? '*' : implode('|', $namespaces),
                'cirrusDumpQuery' => 'true',
            ],
        ]);

        // Decode without true so that empty JSON objects {} stay as stdClass rather
        // than becoming [], which would round-trip back as [] instead of {}.
        $data = json_decode($res->getBody()->getContents());
        return new CirrusQuery( $this->extractBody( $data ) );
    }

    /**
     * Parse the cirrusDumpQueryAST API response and extract the AST object.
     *
     * @param array $data Decoded API response.
     * @return array The AST object (fields: query, rawQuery, featuresUsed, root, …).
     * @throws \RuntimeException
     */
    private function extractAST(array $data): array
    {
        $ast = $data['ast'] ?? null;
        if (null === $ast || !isset($ast['root'])) {
            throw new \RuntimeException(
                'CirrusSearch AST root not found in API response.'
            );
        }

        return $ast;
    }

    /**
     * Parse the cirrusDumpQuery API response and extract the main query body.
     *
     * @param object $data Decoded API response.
     * @return object The Elasticsearch query clause.
     * @throws \RuntimeException
     */
    private function extractBody(object $data): object
    {
        $dumpEntry = $data->{'__main__'} ?? null;

        if (null === $dumpEntry) {
            throw new \RuntimeException(
                'CirrusSearch query dump not found in API response.'
            );
        }

        $esBody = $dumpEntry->query ?? null;
        if (null === $esBody || !isset($esBody->query)) {
            throw new \RuntimeException(
                'Elasticsearch query clause not found in CirrusSearch dump.'
            );
        }

        return $esBody;
    }
}
