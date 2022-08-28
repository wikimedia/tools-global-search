<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\Query;
use App\Repository\CloudElasticRepository;
use App\Repository\WikiDomainRepository;
use GuzzleHttp\Client;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        $namespaceIds = $this->parseNamespaces($request);
        $titlePattern = $request->query->get('title');
        $purgeCache = (bool)$request->query->get('purge');

        $ret = [
            'q' => $query,
            'regex' => $regex,
            'max_results' => Query::MAX_RESULTS,
            'namespaces' => $namespaceIds,
            'title' => $titlePattern,
            'ignore_case' => $ignoreCase,
        ];

        if ($query) {
            $ret = array_merge($ret, $this->getResults(
                $query,
                $regex,
                $ignoreCase,
                $namespaceIds,
                $titlePattern,
                $purgeCache
            ));
            $ret['from_cache'] = $this->fromCache;
            return $this->formatResponse($request, $query, $ret);
        }

        return $this->render('default/index.html.twig', $ret);
    }

    /**
     * Get the rendered template for the requested format.
     * @param Request $request
     * @param string $query Query string, used for filenames.
     * @param mixed[] $data Data that should be passed to the view.
     * @return Response
     */
    private function formatResponse(Request $request, string $query, array $data): Response
    {
        $format = $request->query->get('format', 'html');
        if ('' == $format) {
            // The default above doesn't work when the 'format' parameter is blank.
            $format = 'html';
        }

        $formatMap = [
            'html' => 'text/html',
            'wikitext' => 'text/plain',
            'csv' => 'text/csv',
            'tsv' => 'text/tab-separated-values',
            'json' => 'application/json',
            'markdown' => 'text/plain',  // Firefox doesn't like text/markdown
        ];

        // Use HTML if unknown format requested.
        $format = isset($formatMap[$format]) ? $format : 'html';

        // Nothing more needed if requesting JSON.
        if ('json' === $format) {
            return new JsonResponse($data);
        }

        $response = new Response();

        $response->headers->set('Content-Type', $formatMap[$format]);
        if (in_array($format, ['csv', 'tsv'])) {
            $filename = $this->getFilenameForRequest($query);
            $response->headers->set(
                'Content-Disposition',
                "attachment; filename=\"{$filename}.$format\""
            );
        }

        return $this->render("default/result.$format.twig", $data, $response);
    }

    /**
     * Returns pretty filename from the given query, with problematic characters filtered out.
     * @param string $query
     * @return string
     */
    private function getFilenameForRequest(string $query): string
    {
        $filename = trim($query, '/');
        return trim(preg_replace('/[-\/\\:;*?|<>%#"]+/', '-', $filename));
    }

    /**
     * Get results based on given Request.
     * @param string $query
     * @param bool $regex
     * @param bool $ignoreCase
     * @param int[] $namespaceIds
     * @param string|null $titlePattern
     * @param bool $purgeCache
     * @return mixed[]
     */
    public function getResults(
        string $query,
        bool $regex,
        bool $ignoreCase,
        array $namespaceIds,
        ?string $titlePattern = null,
        bool $purgeCache = false
    ): array {
        $cacheItem = md5($query.$regex.$ignoreCase.$titlePattern.implode('|', $namespaceIds));

        if (!$purgeCache && $this->cache->hasItem($cacheItem)) {
            $this->fromCache = true;
            return $this->cache->getItem($cacheItem)->get();
        }

        $query = new Query($query, $namespaceIds, $regex, $ignoreCase, $titlePattern);
        $params = $query->getParams();
        $res = (new CloudElasticRepository($this->client, $params))->makeRequest();
        $data = [
            'regex' => $regex,
            'ignore_case' => $ignoreCase,
            'title' => $titlePattern,
            'total' => $res['hits']['total']['value'],
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
     * @return int[] Namespace IDs.
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

        return $ids;
    }
}
