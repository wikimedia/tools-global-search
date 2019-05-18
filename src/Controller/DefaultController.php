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
        $uri = $_ENV['ELASTIC_HOST'].'/*,*:*/_search?q='.urlencode($query);

        $res = $this->client->request('GET', $uri);
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
            $newData[] = [
                'wiki' => rtrim($this->getWikiDomainFromDbName($result['wiki']), '.org'),
                'title' => $result['title'],
                'url' => $this->getUrlForTitle($result['wiki'], $result['title']),
                'text' => $this->highlightQueryInText($result['text'], $query),
                'source_text' => $this->highlightQueryInText($result['source_text'], $query),
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

    private function highlightQueryInText(string $text, string $query): string
    {
        // First make the original text HTML safe.
        $text = htmlspecialchars($text);

        // Highlight the query, and return the result.
        return preg_replace('/'.$query.'/i', "<span class='text-danger'>$query</span>", $text);
    }
}
