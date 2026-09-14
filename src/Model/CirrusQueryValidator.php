<?php

declare(strict_types=1);

namespace App\Model;

use App\Exception\UnsupportedQueryException;

/**
 * Validates a CirrusSearch query AST for cross-wiki (global search) compatibility.
 *
 * Keywords that depend on host-wiki-specific state (database lookups, per-wiki ML models,
 * local namespace configuration, etc.) cannot produce meaningful results when sent to
 * arbitrary wikis and are therefore rejected.
 */
class CirrusQueryValidator
{
    /**
     * Keywords that are never portable across wikis.
     * @var string[]
     */
    private const HOST_WIKI_ONLY = [
        'morelike',
        'morelikethis',
        'deepcat',
        'deepcategory',
    ];

    /**
     * Validate the AST and throw if the query is not portable across wikis.
     *
     * @param array $ast The AST object as returned by the cirrusDumpQueryAST API.
     * @throws UnsupportedQueryException
     */
    public function validate(array $ast): void
    {
        $violations = [];

        if (isset($ast['root'])) {
            $this->walkNode($ast['root'], $violations);
        }

        if ($violations !== []) {
            throw new UnsupportedQueryException($violations);
        }
    }

    /**
     * Recursively walk an AST node and collect violations.
     *
     * @param array $node
     * @param string[] $violations
     */
    private function walkNode(array $node, array &$violations): void
    {
        // bool node: recurse into each clause
        foreach ($node['bool']['clauses'] ?? [] as $clause) {
            foreach (['MUST', 'SHOULD', 'MUST_NOT', 'FILTER'] as $clauseType) {
                if (isset($clause[$clauseType])) {
                    $this->walkNode($clause[$clauseType], $violations);
                }
            }
        }

        // keyword node: validate the keyword
        if (isset($node['keyword'])) {
            $this->validateKeyword($node['keyword'], $violations);
        }
    }

    /**
     * Check a single keyword node against the portability rules.
     *
     * @param array $keyword
     * @param string[] $violations
     */
    private function validateKeyword(array $keyword, array &$violations): void
    {
        $key = $keyword['key'] ?? '';

        // Always host-wiki-only
        if (in_array($key, self::HOST_WIKI_ONLY, true)) {
            $violations[] = "\"$key\" is not supported for cross-wiki search";
            return;
        }

        // incategory:id:<n> requires a PageStore DB lookup on the host wiki
        if ($key === 'incategory') {
            $value = $keyword['value'] ?? '';
            // pipe-separated values; check each part
            foreach (explode('|', $value) as $part) {
                if (str_starts_with(ltrim($part), 'id:')) {
                    $violations[] = "\"incategory\" with an \"id:\" value is not supported for cross-wiki search";
                    break;
                }
            }
            return;
        }
    }
}
