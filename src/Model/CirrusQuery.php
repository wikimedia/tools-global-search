<?php

declare(strict_types=1);

namespace App\Model;

/**
 * A CirrusQuery produces CloudElastic parameters from a pre-built
 * Elasticsearch query sourced from the CirrusSearch dump API. Namespace
 * filtering is handled at query-generation time by CirrusDumpQueryRepository.
 */
class CirrusQuery
{
    /** @var object The Elasticsearch query clause from CirrusSearch. */
    private object $esBody;

    /**
     * @param object $cirrusQuery The Elasticsearch query clause returned by CirrusDumpQueryRepository.
     */
    public function __construct(object $esBody)
    {
        $this->esBody = clone $esBody;
        if (isset($this->esBody->highlight)) {
            $this->esBody->highlight = clone $esBody->highlight;
            $this->esBody->highlight->pre_tags = [Query::PRE_TAG];
            $this->esBody->highlight->post_tags = [Query::POST_TAG];
        }
    }

    /**
     * Get parameters needed to make the CloudElastic query.
     * @return mixed[]
     */
    public function getParams(): array
    {
        return [
            'timeout' => '150s',
            'size' => Query::MAX_RESULTS,
            '_source' => ['wiki', 'namespace_text', 'title'],
            'query' => $this->esBody->query,
            'stats' => ['global-search'],
            'highlight' => $this->esBody->highlight ?? [
                'pre_tags' => [Query::PRE_TAG],
                'post_tags' => [Query::POST_TAG],
                'fields' => [
                    'source_text.plain' => [
                        'type' => 'experimental',
                    ],
                ],
            ],
        ];
    }
}
