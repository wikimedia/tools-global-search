<?php

declare(strict_types=1);

namespace App\Controller;

use App\WikiDomainLookup;
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

    /** @var bool Whether the results were pulled from cache. */
    private $fromCache = false;

    private const PRE_TAG = '%**%';
    private const POST_TAG = '*%%*';
    private const MAX_RESULTS = 5000;

    /** @var string Duration of cache for main results set, as accepted by DateInterval::createFromDateString() */
    private const CACHE_TIME = '10 minutes';

    /** @var string[]|null Map from wiki dbname to domain name */
    private $domainLookup;

    /**
     * Splash page, shown when user is logged out.
     * @Route("/splash")
     */
    public function splashAction(): Response
    {
        return $this->render('jumbotron.html.twig');
    }

    /**
     * The main route.
     * @Route("/", name="home")
     * @param Request $request
     * @param CacheItemPoolInterface $cache
     * @return Response
     */
    public function indexAction(Request $request, CacheItemPoolInterface $cache): Response
    {
        if (!$this->get('session')->get('logged_in_user')) {
            return $this->render('jumbotron.html.twig');
        }
        $query = $request->query->get('q');
        $regex = (bool)$request->query->get('regex');
        [$namespaces, $namespaceIds] = $this->parseNamespaces($request);
        $purgeCache = (bool)$request->query->get('purge');
        $ret = [
            'q' => $query,
            'regex' => $regex,
            'max_results' => self::MAX_RESULTS,
            'namespaces' => $namespaces,
        ];

        if ($query) {
            $ret = array_merge($ret, $this->getResults($query, $regex, $namespaceIds, $cache, $purgeCache));
            $ret['from_cache'] = $this->fromCache;
            return $this->render('default/result.html.twig', $ret);
        }

        return $this->render('default/index.html.twig', $ret);
    }

    /**
     * Parse the namespaces parameter of the query string.
     * @param Request $request
     * @return mixed[] [normalized comma-separated list as a string, array of ids as ints]
     */
    private function parseNamespaces(Request $request): array
    {
        $param = $request->query->get('namespaces', '');

        if ('' === $param) {
            $ids = [];
        } else {
            $ids = array_map(
                'intval',
                explode(',', $param)
            );
        }

        return [
            implode(',', $ids),
            $ids,
        ];
    }

    /**
     * Get results based on given Request.
     * @param string $query
     * @param bool $regex
     * @param int[] $namespaceIds
     * @param CacheItemPoolInterface $cache
     * @param bool $purgeCache
     * @return mixed[]
     */
    public function getResults(
        string $query,
        bool $regex,
        array $namespaceIds,
        CacheItemPoolInterface $cache,
        bool $purgeCache = false
    ): array {
        $this->cache = $cache;
        $cacheItem = md5($query.$regex.implode('|', $namespaceIds));

        if (!$purgeCache && $this->cache->hasItem($cacheItem)) {
            $this->fromCache = true;
            return $this->cache->getItem($cacheItem)->get();
        }

        $params = $regex
            ? $this->getParamsForRegexQuery($query)
            : $this->getParamsForPlainQuery($query);

        if (!empty($namespaceIds)) {
            $params['query']['bool']['filter'][] = [ 'terms' => [
                'namespace' => $namespaceIds,
            ] ];
        }

        $res = $this->makeRequest($params);
        $data = [
            'query' => $query,
            'regex' => $regex,
            'total' => $res['hits']['total'],
            'hits' => $this->formatHits($res),
        ];

        $cacheItem = $this->cache->getItem($cacheItem)
            ->set($data)
            ->expiresAfter(\DateInterval::createFromDateString(self::CACHE_TIME));
        $this->cache->save($cacheItem);
        return $data;
    }

    /**
     * Query the CloudElastic service with the given params.
     * @param mixed[] $params
     * @return mixed[]
     */
    private function makeRequest(array $params): array
    {
        $this->client = new Client([
            'verify' => $_ENV['ELASTIC_INSECURE'] ? false : true,
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
            // Dump the full response in development environments since Guzzle truncates the error messages.
            if ('dev' === $_ENV['APP_ENV']) {
                dump($e->getResponse()->getBody()->getContents());
            }
            throw $e;
        }

        return json_decode($res->getBody()->getContents(), true);
    }

    /**
     * Build the data structure for each hit, giving the view what it needs.
     * @param mixed[] $data
     * @return mixed[]
     */
    private function formatHits(array $data): array
    {
        $hits = $data['hits']['hits'];
        $newData = [];

        foreach ($hits as $hit) {
            $result = $hit['_source'];
            $title = ($result['namespace_text'] ? $result['namespace_text'].':' : '').$result['title'];
            $domain = $this->getWikiDomainFromDbName($result['wiki']);
            $newData[] = [
                'wiki' => rtrim($domain, '.org'),
                'title' => $title,
                'url' => $this->getUrlForTitle($domain, $title),
                'source_text' => $this->highlightQuery(
                    $hit['highlight']['source_text.plain'][0] ?? ''
                ),
            ];
        }

        return $newData;
    }

    /**
     * Get the URL to the page with the given title on the given wiki.
     * @param string $domain
     * @param string $title
     * @return string
     */
    private function getUrlForTitle(string $domain, string $title): string
    {
        return 'https://'.$domain.'/wiki/'.$title;
    }

    /**
     * Query XTools API to get the domain of the wiki with the given database name. Results are cached for a week.
     * @param string $wiki
     * @return string
     */
    private function getWikiDomainFromDbName(string $wiki): string
    {
        if (null === $this->domainLookup) {
            $this->domainLookup = (new WikiDomainLookup($this->client, $this->cache))->load();
        }
        return $this->domainLookup[$wiki] ?? 'WIKINOTFOUND';
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
     * @return mixed[]
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
                        [ 'match' => [
                            'source_text.plain' => $query,
                        ] ],
                    ],
                ],
            ],
            'stats' => ['global-search'],
            'highlight' => [
                'pre_tags' => [self::PRE_TAG],
                'post_tags' => [self::POST_TAG],
                'fields' => [
                    'source_text.plain' => [
                        'type' => 'experimental',
                    ],
                ],
                'highlight_query' => [
                    'match' => [
                        'source_text.plain' => $query,
                    ],
                ],
            ],
        ];
    }

    /**
     * Params to be passed to Cloud Elastic for a regular expression query.
     * @param string $query
     * @return mixed[]
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
                        [ 'source_regex' => [
                            'regex' => $query,
                            'field' => 'source_text',
                            'ngram_field' => 'source_text.trigram',
                            'max_determinized_states' => 20000,
                            'max_expand' => 10,
                            'case_sensitive' => true,
                            'locale' => 'en',
                        ] ],
                    ],
                ],
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
