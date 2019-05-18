<?php

declare(strict_types=1);

namespace App\Controller;

use GuzzleHttp\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
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
     */
    public function queryAction(Request $request)
    {
        $query = $request->query->get('query');
        $client = new Client([
            'verify' => $_ENV['ELASTIC_INSECURE'] ? false : true
        ]);
        $uri = $_ENV['ELASTIC_HOST'].'/*,*:*/_search?q='.urlencode($query);

        $res = $client->request('GET', $uri);
        $data = json_decode($res->getBody()->getContents(), true);

        return $this->render('default/result.html.twig', [
            'total' => $data['hits']['total'],
            'hits' => $this->formatHits($data, $query),
        ]);
    }

    private function formatHits(array $data, string $query)
    {
        $hits = $data['hits']['hits'];
        $newData = [];

        foreach ($hits as $hit) {
            $result = $hit['_source'];
            $newData[] = [
                'wiki' => $result['wiki'],
                'title' => $result['title'],
                'text' => $this->highlightQueryInText($result['text'], $query),
                'source_text' => $this->highlightQueryInText($result['source_text'], $query),
            ];
        }

        return $newData;
    }

    private function highlightQueryInText(string $text, string $query): string
    {
        // First make the original text HTML safe.
        $text = htmlspecialchars($text);

        // Highlight the query, and return the result.
        return str_replace($query, "<span class='text-danger'>$query</span>", $text);
    }
}
