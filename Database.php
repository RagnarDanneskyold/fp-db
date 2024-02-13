<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;
    private const SKIP_VALUE = '{{skipValue}}';

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }
    public function buildQuery(string $query, array $args = []): string
    {
        if (empty($args)) {
            return $query;
        }

        $specifiers = $this->getSpecifiersFromQuery($query);
        $currentArgumentIndex = 0;

        foreach ($specifiers as $index => $specifier) {
            if (str_contains($specifier, '{')) {
                if ($args[$index] === self::SKIP_VALUE) {
                    $query = preg_replace(
                        '/' . str_replace('?', '\?', $specifier) . '/',
                        '',
                        $query,
                        1
                    );
                    $currentArgumentIndex++;
                    continue;
                }
                $parsedConditionalBlock = $this->parseConditionalBlock($specifier, $args, $currentArgumentIndex);
                $query = preg_replace(
                    '/' . str_replace('?', '\?', $specifier) . '/',
                    str_replace(['{', '}'], '', $parsedConditionalBlock),
                    $query,
                    1
                );
                continue;
            }

            $queryPart = $this->buildQueryPartBySpecifierAndArgument($specifier, $args[$currentArgumentIndex]);
            $query = preg_replace('/' . '\\' . $specifier . '/',  $queryPart, $query, 1);
            $currentArgumentIndex++;
        }

        return $query;
    }
    private function getSpecifiersFromQuery(string $query): array
    {
        $result = [];
        preg_match_all('/\?[d,f,a,#]{0,1}|\{[^}]+\}/', $query, $specifiersMatches, PREG_SET_ORDER);
        foreach ($specifiersMatches as $match) {
            $result[] = $match[0];
        }

        return $result;
    }
    private function parseConditionalBlock(string $block, array $args, int &$startArgumentsIndex): string
    {
        $result = $block;
        $blockSpecifiers = $this->getSpecifiersFromQuery(str_replace(['{', '}'], '', $block));
        foreach ($blockSpecifiers as $specifier) {
            $blockPart = $this->buildQueryPartBySpecifierAndArgument($specifier, $args[$startArgumentsIndex]);
            $result = preg_replace('/' . '\\' . $specifier . '/',  $blockPart, $result, 1);
            $startArgumentsIndex++;
        }

        return $result;
    }

    private function buildQueryPartBySpecifierAndArgument(string $specifier, int|float|array|string|bool|null $arg): string
    {
        if (
            in_array($specifier, ['?','?d','?f']) &&
            $arg === null
        ) {
            return ' NULL ';
        }
        echo "<pre>";

        $queryPart = '';
        switch ($specifier) {
            case '?':
                $queryPart = $this->castEmptySpecificatorArgumentToQuery($arg);
                break;
            case '?d':
                $queryPart = strval($arg);
                break;
            case '?f':
                $queryPart = strval($arg);
                break;
            case '?a':
                $queryPart = $this->castArrayArgumentToQuery($arg);
                break;
            case '?#':
                $queryPart = $this->castIdentifierArgumentToQuery($arg);
                break;
        }
        echo "</pre>";
        return $queryPart;
    }

    private function castArrayArgumentToQuery(array $arg): string
    {
        if(array_is_list($arg)) {
            return implode(', ', $arg);
        }
        $result = '';
        foreach ($arg as $key => $value) {
            if ($key !== array_key_first($arg)) {
                $result .= ', ';
            }
            $result .=  '`' . $key . '`' . ' = ' . $this->castEmptySpecificatorArgumentToQuery($value);
        }

        return $result;
    }

    public function skip(): string
    {
        return self::SKIP_VALUE;
//        throw new Exception('skip');
    }

    private function castEmptySpecificatorArgumentToQuery(float|int|bool|string|null $arg): string
    {
        if (is_null($arg)) {
            return 'NULL';
        }
        if(is_bool($arg)) {
            return ' ' . strval((int)$arg) . ' ';
        }
        if (is_string($arg)) {
            return "'" . $arg . "'";
        }

        return strval($arg);
    }

    private function castIdentifierArgumentToQuery(array|string $arg): string
    {
        if (is_array($arg)) {
            $result = [];
            foreach ($arg as $identifier) {
                $result[] = '`' . $identifier . '`';
            }
            return implode(', ' , $result);
        }

        return '`' . $arg . '`';
    }

}