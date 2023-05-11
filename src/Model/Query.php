<?php

declare(strict_types=1);

namespace App\Model;

/**
 * A Query produces the parameters needed to be passed to the CloudElastic service.
 */
class Query
{
    public const PRE_TAG = '%**%';
    public const POST_TAG = '*%%*';
    public const MAX_RESULTS = 5000;

    /** @var string The query string. */
    protected string $query;

    /** @var int[] Array of namespace IDs. */
    protected array $namespaces;

    /** @var bool Whether to get params for a regular expression search. */
    protected bool $regex;

    /** @var bool Whether the params should be for a case-insensitive search. */
    protected bool $ignoreCase;

    /** @var string Regular expression for page title. */
    protected string $titlePattern;

    /**
     * Query constructor.
     * @param string $query
     * @param int[] $namespaces
     * @param bool $regex
     * @param bool $ignoreCase
     * @param string|null $titlePattern
     */
    public function __construct(
        string $query,
        array $namespaces,
        bool $regex = false,
        bool $ignoreCase = false,
        ?string $titlePattern = null
    ) {
        // Silently use regex to do exact match if query is wrapped in double-quotes.
        if ('"' === substr($query, 0, 1) && '"' === substr($query, -1, 1)) {
            $regex = true;
            $query = preg_quote(substr($query, 1, -1));
        }

        $this->query = $query;
        $this->namespaces = $namespaces;
        $this->regex = $regex;
        $this->ignoreCase = $ignoreCase;
        $this->titlePattern = $titlePattern;
    }

    /**
     * Get parameters needed to make the CloudElastic query.
     * @return array|mixed[]
     */
    public function getParams(): array
    {
        $params = $this->regex ? $this->getRegexQuery() : $this->getPlainQuery();

        if (!empty($this->namespaces)) {
            $params['query']['bool']['filter'][] = [ 'terms' => [
                'namespace' => $this->namespaces,
            ] ];
        }

        if ($this->titlePattern) {
            $params['query']['bool']['filter'][] = [ 'regexp' => [
                'title.keyword' => $this->titlePattern,
            ] ];
        }

        return $params;
    }

    /**
     * Params to be passed to CloudElastic for a plain (normal) query.
     * @return mixed[]
     */
    private function getPlainQuery(): array
    {
        return [
            'timeout' => '150s',
            'size' => self::MAX_RESULTS,
            '_source' => ['wiki', 'namespace_text', 'title'],
            'query' => [
                'bool' => [
                    'filter' => [
                        [ 'match' => [
                            'source_text.plain' => $this->query,
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
                        'source_text.plain' => $this->query,
                    ],
                ],
            ],
        ];
    }

    /**
     * Params to be passed to CloudElastic for a regular expression query.
     * @return mixed[]
     */
    private function getRegexQuery(): array
    {
        return [
            'timeout' => '150s',
            'size' => 5000,
            '_source' => ['wiki', 'namespace_text', 'title'],
            'query' => [
                'bool' => [
                    'filter' => [
                        [ 'source_regex' => [
                            'regex' => $this->query,
                            'field' => 'source_text',
                            'ngram_field' => 'source_text.trigram',
                            'max_determinized_states' => 20000,
                            'max_expand' => 10,
                            'case_sensitive' => !$this->ignoreCase,
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
                            'regex' => [$this->query],
                            'locale' => 'en',
                            'regex_flavor' => 'lucene',
                            'skip_query' => true,
                            'regex_case_insensitive' => $this->ignoreCase,
                            'max_determinized_states' => 20000,
                        ],
                    ],
                ],
            ],
        ];
    }
}
