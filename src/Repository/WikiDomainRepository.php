<?php

declare(strict_types=1);

namespace App\Repository;

use GuzzleHttp\Client as GuzzleClient;
use Psr\Cache\CacheItemPoolInterface;

/**
 * A WikiDomainRepository is responsible for fetching a list of all wikis from the Sitematrix API.
 */
class WikiDomainRepository
{
    /** @var GuzzleClient */
    private $guzzle;

    /** @var CacheItemPoolInterface */
    private $cache;

    private const CACHE_KEY = 'global-search-wikidomainlookup';

    /** @var string Duration of cache for lookup table, as accepted by DateInterval::createFromDateString() */
    private const CACHE_TIME = '10 hours';

    public function __construct(GuzzleClient $guzzle, CacheItemPoolInterface $cache)
    {
        $this->guzzle = $guzzle;
        $this->cache = $cache;
    }

    /**
     * Load the site matrix, either from cache or using the sitematrix API.
     * @return string[]
     */
    public function load(): array
    {
        $cacheItem = $this->cache->getItem(self::CACHE_KEY);
        $lookup = $cacheItem->get();
        if (null === $lookup) {
            $lookup = $this->loadUncached();
            $cacheItem->set($lookup)
                ->expiresAfter(\DateInterval::createFromDateString(self::CACHE_TIME));
            $this->cache->save($cacheItem);
        }
        return $lookup;
    }

    /**
     * Fetch the site matrix from the API.
     * @return string[]
     */
    public function loadUncached(): array
    {
        $res = $this->guzzle->request('GET', 'https://meta.wikimedia.org/w/api.php', [
            'query' => [
                'format' => 'json',
                'formatversion' => 2,
                'action' => 'sitematrix',
                'smlangprop' => 'site',
                'smsiteprop' => 'url|dbname',
            ],
        ]);
        $decoded = json_decode($res->getBody()->getContents(), true)['sitematrix'];
        $lookup = [];
        foreach ($decoded as $k => $v) {
            if ('count' === $k) {
                continue;
            }
            $sites = 'specials' === $k ? $v : $v['site'];
            foreach ($sites as $site) {
                $lookup[$site['dbname']] = parse_url($site['url'], PHP_URL_HOST);
            }
        }
        return $lookup;
    }
}
