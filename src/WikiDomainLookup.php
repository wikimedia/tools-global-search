<?php

declare(strict_types=1);

namespace App;

use GuzzleHttp\Client as GuzzleClient;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class WikiDomainLookup
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

    public function load(): array
    {
        $cacheItem = $this->cache->getItem(self::CACHE_KEY);
        $lookup = null; //$cacheItem->get();
        if ($lookup === null) {
            $lookup = $this->loadUncached();
            $cacheItem->set($lookup)
                ->expiresAfter(\DateInterval::createFromDateString(self::CACHE_TIME));
            $this->cache->save($cacheItem);
        }
        return $lookup;
    }

    public function loadUncached(): array
    {
        $res = $this->guzzle->request('GET', 'https://meta.wikimedia.org/w/api.php', [
            'query' => [
                'format' => 'json',
                'formatversion' => 2,
                'action' => 'sitematrix',
                'smlangprop' => 'site',
                'smsiteprop' => 'url|dbname',
            ]
        ]);
        $decoded = json_decode($res->getBody()->getContents(), true)['sitematrix'];
        $lookup = [];
        foreach ($decoded as $k => $v) {
            if ($k === 'count') {
                continue;
            }
            $sites = $k === 'specials' ? $v : $v['site'];
            foreach ($sites as $site) {
                $lookup[$site['dbname']] = parse_url($site['url'], PHP_URL_HOST);
            }
        }
        return $lookup;
    }
}
