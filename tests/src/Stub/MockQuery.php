<?php
declare(strict_types=1);

namespace NextcloudSaaS\Tests\Stub;

/**
 * Fluent query stub matching WHMCS Capsule chaining used by Helper::getDomain.
 */
final class MockQuery
{
    /** @var list<array{0: string, 1: mixed}> */
    private array $wheres = [];

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(
        private readonly string $table,
        private readonly array $rows,
    ) {
    }

    public function where(string $column, mixed $value): self
    {
        $clone = clone $this;
        $clone->wheres[] = [$column, $value];
        return $clone;
    }

    /**
     * @param list<string> $columns
     * @return list<object>
     */
    public function get(array $columns = ['*']): array
    {
        $matched = $this->filterRows();
        $result = [];

        foreach ($matched as $row) {
            $obj = new \stdClass();
            foreach ($columns as $column) {
                if ($column === '*') {
                    foreach ($row as $key => $val) {
                        $obj->{$key} = $val;
                    }
                    break;
                }
                $obj->{$column} = $row[$column] ?? null;
            }
            $result[] = $obj;
        }

        return $result;
    }

    public function value(string $column): mixed
    {
        $matched = $this->filterRows();
        if ($matched === []) {
            return null;
        }

        return $matched[0][$column] ?? null;
    }

    public function update(array $data): int
    {
        return 0;
    }

    public function insert(array $data): bool
    {
        return true;
    }

    public function first(): ?object
    {
        $rows = $this->get();
        return $rows[0] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function filterRows(): array
    {
        return array_values(array_filter(
            $this->rows,
            function (array $row): bool {
                foreach ($this->wheres as [$column, $value]) {
                    if (!array_key_exists($column, $row)) {
                        return false;
                    }
                    if ($row[$column] != $value) {
                        return false;
                    }
                }
                return true;
            }
        ));
    }
}
