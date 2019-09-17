<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\Query;
use App\Repository\CloudElasticRepository;
use App\Repository\WikiDomainRepository;
use GuzzleHttp\Client;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * A DefaultController serves the main routes for the application and processing the submitted form.
 */
class DefaultController extends AbstractController
{
    /** @var Client */
    private $client;

    /** @var CacheItemPoolInterface */
    private $cache;

    /** @var bool Whether the results were pulled from cache. */
    private $fromCache = false;

    /** @var string Duration of cache for main results set, as accepted by DateInterval::createFromDateString() */
    private const CACHE_TIME = '10 minutes';

    /** @var string[]|null Map from wiki dbname to domain name */
    private $domainLookup;

    /**
     * DefaultController constructor.
     * @param CacheItemPoolInterface $cache
     */
    public function __construct(CacheItemPoolInterface $cache)
    {
        $this->client = new Client([
            'verify' => $_ENV['ELASTIC_INSECURE'] ? false : true,
        ]);
        $this->cache = $cache;
    }

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
     * @return Response
     */
    public function indexAction(Request $request): Response
    {
        if (!$this->get('session')->get('logged_in_user')) {
            return $this->render('jumbotron.html.twig');
        }
        $query = $request->query->get('q');
        $regex = (bool)$request->query->get('regex');
        $ignoreCase = (bool)$request->query->get('ignorecase');
        [$namespaces, $namespaceIds] = $this->parseNamespaces($request);
        $purgeCache = (bool)$request->query->get('purge');

        $ret = [
            'q' => $query,
            'regex' => $regex,
            'max_results' => Query::MAX_RESULTS,
            'namespaces' => $namespaces,
            'ignore_case' => $ignoreCase,
        ];

        if ($query) {
            $ret = array_merge($ret, $this->getResults($query, $regex, $ignoreCase, $namespaceIds, $purgeCache));
            $ret['from_cache'] = $this->fromCache;
            return $this->render('default/result.html.twig', $ret);
        }

        return $this->render('default/index.html.twig', $ret);
    }

    /**
     * Get results based on given Request.
     * @param string $query
     * @param bool $regex
     * @param bool $ignoreCase
     * @param int[] $namespaceIds
     * @param bool $purgeCache
     * @return mixed[]
     */
    public function getResults(
        string $query,
        bool $regex,
        bool $ignoreCase,
        array $namespaceIds,
        bool $purgeCache = false
    ): array {
        $cacheItem = md5($query.$regex.$ignoreCase.implode('|', $namespaceIds));

        if (!$purgeCache && $this->cache->hasItem($cacheItem)) {
            $this->fromCache = true;
            return $this->cache->getItem($cacheItem)->get();
        }

        $query = new Query($query, $namespaceIds, $regex, $ignoreCase);
        $params = $query->getParams();
        $res = (new CloudElasticRepository($this->client, $params))->makeRequest();
        $data = [
            'query' => $query,
            'regex' => $regex,
            'ignore_case' => $ignoreCase,
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
     * Query Siteinfo API to get the domain of the wiki with the given database name.
     * @param string $wiki
     * @return string
     */
    private function getWikiDomainFromDbName(string $wiki): string
    {
        if (null === $this->domainLookup) {
            $this->domainLookup = (new WikiDomainRepository($this->client, $this->cache))->load();
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
            Query::PRE_TAG => "<span class='highlight'>",
            Query::POST_TAG => "</span>",
        ]);
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
}
