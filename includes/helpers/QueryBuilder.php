<?php

class QueryBuilder
{
    private string $table = '';

    private array $columns = ['*'];

    private array $joins = [];

    private array $where = [];

    private array $order = [];

    private array $group = [];

    private array $having = [];

    private ?int $limit = null;

    private ?int $offset = null;

    /**
     * Nama tabel
     */
    public function table(string $table): self
    {
        $this->table = $table;

        return $this;
    }

    /**
     * SELECT column
     */
    public function select(array|string $columns = '*'): self
    {
        if (is_array($columns)) {
            $this->columns = $columns;
        } else {
            $this->columns = [$columns];
        }

        return $this;
    }

    /**
     * JOIN
     */
    public function join(
        string $table,
        string $first,
        string $operator,
        string $second,
        string $type = 'INNER'
    ): self {

        $this->joins[] =
            strtoupper($type)
            . " JOIN {$table}
               ON {$first} {$operator} {$second}";

        return $this;
    }

    /**
     * LEFT JOIN
     */
    public function leftJoin(
        string $table,
        string $first,
        string $operator,
        string $second
    ): self {

        return $this->join(
            $table,
            $first,
            $operator,
            $second,
            'LEFT'
        );

    }

    /**
 * RIGHT JOIN
 */
    public function rightJoin(
        string $table,
        string $first,
        string $operator,
        string $second
    ): self {

        return $this->join(
            $table,
            $first,
            $operator,
            $second,
            'RIGHT'
        );

    }

    /**
     * WHERE
     */
    public function where(
        string $column,
        string $operator,
        $value
    ): self {

        $value = $this->escape($value);

        $this->where[] =
            "{$column} {$operator} '{$value}'";

        return $this;
    }

    /**
     * OR WHERE
     */
    public function orWhere(
        string $column,
        string $operator,
        $value
    ): self {

        $value = $this->escape($value);

        if (empty($this->where)) {

            $this->where[] =
                "{$column} {$operator} '{$value}'";

        } else {

            $last = array_pop($this->where);

            $this->where[] =
                "({$last} OR {$column} {$operator} '{$value}')";

        }

        return $this;
    }

    /**
     * ORDER BY
     */
    public function orderBy(
        string $column,
        string $direction = 'ASC'
    ): self {

        $direction =
            strtoupper($direction) === 'DESC'
                ? 'DESC'
                : 'ASC';

        $this->order[] =
            "{$column} {$direction}";

        return $this;
    }

    /**
     * LIMIT
     */
    public function limit(int $limit): self
    {
        $this->limit = max(1, $limit);

        return $this;
    }

    /**
     * OFFSET
     */
    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);

        return $this;
    }

    /**
     * BUILD SQL
     */
    public function build(): string
    {

        $sql =
            "SELECT "
            . implode(', ', $this->columns)
            . " FROM {$this->table}";

        if (!empty($this->joins)) {

            $sql .= " " . implode(" ", $this->joins);

        }

        if (!empty($this->where)) {

            $sql .=
                " WHERE "
                . implode(" AND ", $this->where);

        }

        if (!empty($this->order)) {

            $sql .=
                " ORDER BY "
                . implode(", ", $this->order);

        }

        if ($this->limit !== null) {

            $sql .=
                " LIMIT {$this->limit}";

        }

        if ($this->offset !== null) {

            $sql .=
                " OFFSET {$this->offset}";

        }

        return $sql;
    }

    /**
     * Reset Builder
     */
    public function reset(): self
    {
        $this->table = '';

        $this->columns = ['*'];

        $this->joins = [];

        $this->where = [];

        $this->order = [];

        $this->limit = null;

        $this->offset = null;

        return $this;
    }

    /**
     * Escape sederhana.
     *
     * Catatan:
     * QueryBuilder ini hanya membangun SQL.
     * Eksekusi tetap disarankan menggunakan
     * prepared statement dari Database class.
     */
    private function escape($value): string
    {
        return addslashes((string)$value);
    }
}