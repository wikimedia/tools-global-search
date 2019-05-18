<?php

declare(strict_types=1);

namespace App\Controller;

use GuzzleHttp\Client;
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

    /**
     * Index page with the form to enter a search query.
     * @Route("/")
     * @return Response
     */
    public function indexAction(): Response
    {
        return $this->render('default/index.html.twig');
    }

    /**
     * @Route("/query", methods={"GET"}, name="QueryAction")
     * @param Request $request
     * @param CacheItemPoolInterface $cache
     * @return Response
     */
    public function queryAction(Request $request, CacheItemPoolInterface $cache)
    {
        $query = $request->query->get('query');
        $this->cache = $cache;

        if ($this->cache->hasItem($query)) {
            return $this->render('default/result.html.twig', $this->cache->getItem($query)->get());
        }

        $this->client = new Client([
            'verify' => $_ENV['ELASTIC_INSECURE'] ? false : true
        ]);
        $uri = $_ENV['ELASTIC_HOST'].'/*,*:*/_search?preference=_prefer_nodes:cloudelastic1001-cloudelastic-chi-eqiad,cloudelastic1002-cloudelastic-chi-eqiad,cloudelastic1003-cloudelastic-chi-eqiad';
//        $uri = $_ENV['ELASTIC_HOST'].'/psi:testwiki/_search?preference=_prefer_nodes:cloudelastic1001-cloudelastic-chi-eqiad,cloudelastic1002-cloudelastic-chi-eqiad,cloudelastic1003-cloudelastic-chi-eqiad';

        $filters = [
//            {'term': {'namespace': str(args.ns)}},
            'source_regex' => [
                'regex' => $query,
                'field' => 'source_text',
                'ngram_field' => 'source_text.trigram',
                'max_determinized_states' => 20000,
                'max_expand' => 10,
                'case_sensitive' => true,
                'locale' => 'en',
            ],
        ];

        $request = new \GuzzleHttp\Psr7\Request('GET', $uri, [
            'Content-Type' => 'application/json',
        ], \GuzzleHttp\json_encode([
            'timeout' => '150s',
            'size' => 100,
            '_source' => ['wiki', 'namespace_text', 'title'],
            'query' => [
                'bool' => [
                    'filter' => $filters,
                ]
            ],
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
                        ]
//                        'highlight_query' => [
//                            'bool' =>
//                        ],
                    ],
                ],
            ],
            'stats' => ['global-search'],
        ]));
        $res = $this->client->send($request);
//        $res = $this->client->request('GET', $uri, [
//            'form_params' => [
//                'size' => 10,
//                '_source' => ['wiki', 'namespace_text', 'title'],
//                'query' => [
//                    'bool' => [
//                        'filters' => $filters,
//                    ]
//                ],
//                'stats' => ['global-search'],
//            ],
//            'headers' => [
//                'Content-Type' => 'application/json',
//            ],
////            'http_errors' => false,
//        ]);
        $data = json_decode($res->getBody()->getContents(), true);
        $data = [
            'total' => $data['hits']['total'],
            'hits' => $this->formatHits($data, $query),
        ];

        $cacheItem = $this->cache->getItem($query)
            ->set($data)
            ->expiresAfter(new \DateInterval('P10M'));
        $this->cache->save($cacheItem);

        return $this->render('default/result.html.twig', $data);
    }

    private function formatHits(array $data, string $query)
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

    private function getUrlForTitle(string $wiki, string $title): string
    {
        return 'https://'.$this->getWikiDomainFromDbName($wiki).'/wiki/'.$title;
    }

    private function getWikiDomainFromDbName(string $wiki): string
    {
        $cacheKey = 'wiki.'.$wiki;
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $res = $this->client->request('GET', "https://xtools.wmflabs.org/api/project/normalize/$wiki");
        $domain = json_decode($res->getBody()->getContents(), true)['domain'];

        $cacheItem = $this->cache->getItem($cacheKey)
            ->set($domain)
            ->expiresAfter(new \DateInterval('P7D'));
        $this->cache->save($cacheItem);

        return $domain;
    }

    private function highlightQuery(string $text): string
    {
        // First make the original text HTML safe.
        $text = htmlspecialchars($text);

        return strtr($text, [
            self::PRE_TAG => "<span class='highlight'>",
            self::POST_TAG => "</span>",
        ]);
//        return preg_replace('/'.$query.'/i', "<span class='text-danger'>$query</span>", $text);
    }

//    private function truncateText(string $text, string $query): string
//    {
//
//    }
}
