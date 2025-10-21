<?php

namespace App\GraphQL\AutoDiscovery;

use App\GraphQL\AutoDiscovery\Introspection\TypeUtils;
use Illuminate\Config\Repository;

class QueryBuilder
{
    protected array $schemaData = [];

    public function __construct(protected Repository $config) {}

    public function setSchemaData(array $schemaData): void
    {
        $this->schemaData = $schemaData;
    }

    public function buildQueryDocument(
        string $rootFieldName,
        array $variables = [],
        ?array $selection = null,
        ?string $alias = null,
        ?string $operationName = null
    ): array {
        $operationName = $operationName ?: 'Op_' . \Illuminate\Support\Str::studly($rootFieldName);
        $schema = $this->schemaData['__schema'] ?? null;
        $queryTypeName = $schema['queryType']['name'] ?? 'Query';
        $queryType = $this->findTypeByName($queryTypeName);

        $fieldDef = $this->findField($queryType, $rootFieldName);
        $usingFallback = false;

        if (!$fieldDef) {
            $hint = $this->getFallbackOperationHint($rootFieldName);
            if (!$hint) {
                throw new \RuntimeException("Unknown root field '$rootFieldName' and no fallback hints available.");
            }
            $usingFallback = true;

            $argsFromHints = [];
            foreach (($hint['args'] ?? []) as $argName => $argType) {
                $argsFromHints[] = [
                    'name' => $argName,
                    'type' => ['kind' => 'SCALAR', 'name' => $argType],
                ];
            }

            $fieldDef = [
                'name' => $rootFieldName,
                'args' => $argsFromHints,
                'type' => ['kind' => 'OBJECT', 'name' => null],
            ];

            if ($selection === null) {
                $selection = $hint['default_selection'] ?? null;
            }
        }

        // Variables/arguments (fixed: only define used or required variables)
        [$varDefs, $argsList] = $this->buildVariablesAndArgs($fieldDef, $variables);

        // Selection set
        $returnType = $fieldDef['type'] ?? ['kind' => 'SCALAR', 'name' => 'Unknown'];
        $selectionStr = $this->renderSelection($rootFieldName, $returnType, $selection, $usingFallback);

        $fieldAliased = $alias ? ($alias . ': ' . $rootFieldName) : $rootFieldName;

        $doc = "query {$operationName}{$varDefs} { {$fieldAliased}{$argsList} {$selectionStr} }";

        return [
            'query' => $doc,
            'variables' => $variables,
            'operationName' => $operationName,
        ];
    }

    protected function findTypeByName(string $name): ?array
    {
        $types = $this->schemaData['__schema']['types'] ?? [];
        foreach ($types as $t) {
            if (($t['name'] ?? '') === $name) {
                return $t;
            }
        }
        return null;
    }

    protected function findField(?array $type, string $fieldName): ?array
    {
        if (!$type) {
            return null;
        }
        foreach ($type['fields'] ?? [] as $f) {
            if (($f['name'] ?? '') === $fieldName) {
                return $f;
            }
        }
        return null;
    }

    protected function isNonNull(array $type): bool
    {
        return ($type['kind'] ?? null) === 'NON_NULL';
    }

    protected function buildVariablesAndArgs(array $fieldDef, array $variables): array
    {
        $args = $fieldDef['args'] ?? [];
        $defs = [];
        $argsList = [];

        foreach ($args as $arg) {
            $name = $arg['name'];
            $type = $arg['type'] ?? ['kind' => 'SCALAR', 'name' => 'String'];
            $required = $this->isNonNull($type);
            $hasVar = array_key_exists($name, $variables);

            if ($required && !$hasVar) {
                throw new \InvalidArgumentException("Missing required GraphQL argument: {$name}");
            }

            if ($hasVar || $required) {
                $typeStr = \App\GraphQL\AutoDiscovery\Introspection\TypeUtils::typeToString($type);
                $defs[] = '$' . $name . ': ' . $typeStr;
            }

            if ($hasVar) {
                $argsList[] = $name . ': $' . $name;
            }
        }

        $varDefs = count($defs) ? '(' . implode(', ', $defs) . ')' : '';
        $argsRendered = count($argsList) ? '(' . implode(', ', $argsList) . ')' : '';

        return [$varDefs, $argsRendered];
    }

    protected function renderSelection(string $rootFieldName, array $returnType, ?array $selection, bool $usingFallback): string
    {
        $unwrapped = \App\GraphQL\AutoDiscovery\Introspection\TypeUtils::unwrapType($returnType);
        if ($unwrapped['kind'] === 'SCALAR' || $unwrapped['kind'] === 'ENUM') {
            return '';
        }

        if ($selection === null) {
            $selection = $this->autoSelection($unwrapped);
        }

        $content = $this->renderSelectionArray($unwrapped, $selection);
        return '{ ' . $content . ' }';
    }

    protected function renderSelectionArray(array $type, array $selection): string
    {
        $unwrapped = \App\GraphQL\AutoDiscovery\Introspection\TypeUtils::unwrapType($type);
        $parts = [];
        foreach ($selection as $key => $value) {
            if (is_int($key)) {
                $parts[] = $value;
            } else {
                $nestedType = $this->findNestedFieldReturnType($unwrapped, $key);
                if ($nestedType) {
                    $parts[] = $key . ' { ' . $this->renderSelectionArray($nestedType, (array) $value) . ' }';
                } else {
                    $parts[] = $key;
                }
            }
        }
        return implode(' ', $parts);
    }

    protected function findNestedFieldReturnType(array $parentType, string $fieldName): ?array
    {
        $typeDef = $this->findTypeByName($parentType['name'] ?? '');
        if (!$typeDef) {
            return null;
        }
        foreach ($typeDef['fields'] ?? [] as $f) {
            if (($f['name'] ?? '') === $fieldName) {
                return $f['type'];
            }
        }
        return null;
    }

    protected function autoSelection(array $unwrappedReturnType): array
    {
        $maxFields = (int) $this->config->get('graphql_client.selection.max_fields', 50);
        $defaultDepth = (int) $this->config->get('graphql_client.selection.default_depth', 1);
        $typeDef = $this->findTypeByName($unwrappedReturnType['name'] ?? '');
        if (!$typeDef) {
            return [];
        }

        $result = [];
        $count = 0;

        foreach ($typeDef['fields'] ?? [] as $field) {
            if ($count >= $maxFields) break;

            $fieldType = $field['type'] ?? null;
            if (!$fieldType) continue;

            $un = \App\GraphQL\AutoDiscovery\Introspection\TypeUtils::unwrapType($fieldType);
            if (in_array($un['kind'], ['SCALAR', 'ENUM'], true)) {
                $result[] = $field['name'];
                $count++;
                continue;
            }

            if ($defaultDepth > 0) {
                $nestedDef = $this->findTypeByName($un['name'] ?? '');
                if ($nestedDef) {
                    $nestedScalars = [];
                    foreach ($nestedDef['fields'] ?? [] as $nf) {
                        $nun = \App\GraphQL\AutoDiscovery\Introspection\TypeUtils::unwrapType($nf['type'] ?? []);
                        if (in_array($nun['kind'], ['SCALAR', 'ENUM'], true)) {
                            $nestedScalars[] = $nf['name'];
                        }
                        if (count($nestedScalars) >= $maxFields) break;
                    }
                    if ($nestedScalars) {
                        $result[$field['name']] = $nestedScalars;
                        $count++;
                    }
                }
            }
        }

        return $result;
    }

    protected function getFallbackOperationHint(string $op): ?array
    {
        $fallbackEnabled = (bool) $this->config->get('graphql_client.fallback.enabled', false);
        if (!$fallbackEnabled) {
            return null;
        }
        $path = $this->config->get('graphql_client.fallback.operations_hints');
        if (!$path || !is_file($path)) {
            return null;
        }
        $hints = include $path;
        return $hints['operations'][$op] ?? null;
    }
}
