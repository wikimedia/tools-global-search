<?php

declare(strict_types=1);

namespace App\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    /** @var Client */
    private $client;

    /** @var CacheItemPoolInterface */
    private $cache;

    private const PRE_TAG = '%**%';
    private const POST_TAG = '*%%*';
    private const MAX_RESULTS = 5000;

    /**
     * The only route.
     * @Route("/")
     * @param Request $request
     * @param CacheItemPoolInterface $cache
     * @return Response
     */
    public function indexAction(Request $request, CacheItemPoolInterface $cache): Response
    {
        $query = $request->query->get('q');
        $regex = (bool)$request->query->get('regex');
        $ret = [
            'q' => $query,
            'regex' => $regex,
            'max_results' => self::MAX_RESULTS,
        ];

        if ($query) {
            $ret = array_merge($ret, $this->getResults($query, $regex, $cache));
            return $this->render('default/result.html.twig', $ret);
        }

        return $this->render('default/index.html.twig', $ret);
    }

    /**
     * Get results based on given Request.
     * @param string $query
     * @param bool $regex
     * @param CacheItemPoolInterface $cache
     * @return array
     */
    public function getResults(string $query, bool $regex, CacheItemPoolInterface $cache): array
    {
        $this->cache = $cache;
        $cacheItem = $query.'.'.$regex;

        if ($this->cache->hasItem($cacheItem)) {
            return $this->cache->getItem($cacheItem)->get();
        }

        $params = $regex
            ? $this->getParamsForRegexQuery($query)
            : $this->getParamsForPlainQuery($query);

        $res = $this->makeRequest($params);
        $data = [
            'query' => $query,
            'regex' => $regex,
            'total' => $res['hits']['total'],
            'hits' => $this->formatHits($res, $query),
        ];

        $cacheItem = $this->cache->getItem($cacheItem)
            ->set($data)
            ->expiresAfter(new \DateInterval('P10M'));
        $this->cache->save($cacheItem);
        return $data;
    }

    private function makeRequest($params)
    {
        $this->client = new Client([
            'verify' => $_ENV['ELASTIC_INSECURE'] ? false : true
        ]);

        // FIXME: Eventually will be able to remove _prefer_nodes
        $uri = $_ENV['ELASTIC_HOST'].'/*,*:*/_search?preference=_prefer_nodes:cloudelastic1001-cloudelastic-chi-eqiad,'.
            'cloudelastic1002-cloudelastic-chi-eqiad,cloudelastic1003-cloudelastic-chi-eqiad';

        $request = new \GuzzleHttp\Psr7\Request('GET', $uri, [
            'Content-Type' => 'application/json',
        ], \GuzzleHttp\json_encode($params));

        // FIXME: increase cURL timeout
        try {
            $res = $this->client->send($request);
        } catch (ClientException $e) {
            dump($e->getResponse()->getBody()->getContents());
            throw $e;
        }

        return json_decode($res->getBody()->getContents(), true);
    }

    /**
     * Build the data structure for each hit, giving the view what it needs.
     * @param array $data
     * @return array
     */
    private function formatHits(array $data): array
    {
        $hits = $data['hits']['hits'];
        $newData = [];

        foreach ($hits as $hit) {
            $result = $hit['_source'];
            $title = ($result['namespace_text'] ? $result['namespace_text'].':' : '').$result['title'];
            $newData[] = [
                'wiki' => rtrim($this->getWikiDomainFromDbName($result['wiki']), '.org'),
                'title' => $title,
                'url' => $this->getUrlForTitle($result['wiki'], $title),
                'source_text' => $this->highlightQuery($hit['highlight']['source_text.plain'][0]),
//                'source_text' => $this->highlightQuery($result['source_text'], $query),
            ];
        }

        return $newData;
    }

    /**
     * Get the URL to the page with the given title on the given wiki.
     * @param string $wiki
     * @param string $title
     * @return string
     */
    private function getUrlForTitle(string $wiki, string $title): string
    {
        return 'https://'.$this->getWikiDomainFromDbName($wiki).'/wiki/'.$title;
    }

    /**
     * Query XTools API to get the domain of the wiki with the given database name. Results are cached for a week.
     * @param string $wiki
     * @return string
     */
    private function getWikiDomainFromDbName(string $wiki): string
    {
        $cacheKey = 'wiki.'.$wiki;
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        // $this->client should be set at this point.
        $res = $this->client->request('GET', "https://xtools.wmflabs.org/api/project/normalize/$wiki");
        $domain = json_decode($res->getBody()->getContents(), true)['domain'];

        $cacheItem = $this->cache->getItem($cacheKey)
            ->set($domain)
            ->expiresAfter(new \DateInterval('P7D'));
        $this->cache->save($cacheItem);

        return $domain;
    }

    /**
     * Make the highlight text safe and wrap the search term in a span so that we can style it.
     * @param string $text
     * @return string
     */
    private function highlightQuery(string $text): string
    {
        $text = htmlspecialchars($text);
        return strtr($text, [
            self::PRE_TAG => "<span class='highlight'>",
            self::POST_TAG => "</span>",
        ]);
    }

    /**
     * Params to be passed to Cloud Elastic for a plain (normal) query.
     * @param string $query
     * @return array
     */
    private function getParamsForPlainQuery(string $query): array
    {
        return [
            'timeout' => '150s',
            'size' => self::MAX_RESULTS,
            '_source' => ['wiki', 'namespace_text', 'title'],
            'query' => [
                'bool' => [
                    'filter' => [
                        'match' => [
                            'source_text.plain' => $query,
                        ],
                    ],
                ]
            ],
            'stats' => ['global-search'],
            'highlight' => [
                'pre_tags' => [self::PRE_TAG],
                'post_tags' => [self::POST_TAG],
                'fields' => [
                    'source_text.plain' => [
                        'type' => 'experimental',
                    ]
                ],
                'highlight_query' => [
                    'match' => [
                        'source_text.plain' => $query,
                    ]
                ],
            ],
        ];
    }

    /**
     * Params to be passed to Cloud Elastic for a regular expression query.
     * @param string $query
     * @return array
     */
    private function getParamsForRegexQuery(string $query): array
    {
        return [
            'timeout' => '150s',
            'size' => 100,
            '_source' => ['wiki', 'namespace_text', 'title'],
            'query' => [
                'bool' => [
                    'filter' => [
                        'source_regex' => [
                            'regex' => $query,
                            'field' => 'source_text',
                            'ngram_field' => 'source_text.trigram',
                            'max_determinized_states' => 20000,
                            'max_expand' => 10,
                            'case_sensitive' => true,
                            'locale' => 'en',
                        ],
                    ],
                ]
            ],
            'stats' => ['global-search'],
            'highlight' => [
                'pre_tags' => [self::PRE_TAG],
                'post_tags' => [self::POST_TAG],
                'fields' => [
                    'source_text.plain' => [
                        'type' => 'experimental',
                        'number_of_fragments' => 1,
                        'fragmenter' => 'scan',
                        'fragment_size' => 150,
                        'options' => [
                            'regex' => [$query],
                            'locale' => 'en',
                            'regex_flavor' => 'lucene',
                            'skip_query' => true,
                            'max_determinized_states' => 20000,
                        ],
                    ],
                ],
            ],
        ];
    }
}
