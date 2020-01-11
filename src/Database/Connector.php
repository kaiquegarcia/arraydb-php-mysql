<?php

namespace ArrayDB\Database;

use ArrayDB\Drivers\DriverInterface;
use ArrayDB\Exceptions\DatabaseException;
use ArrayDB\Exceptions\MissingFieldException;
use ArrayDB\Exceptions\UnexpectedValueException;
use ArrayDB\Exceptions\WorthlessVariableException;
use ArrayDB\Exceptions\WrongTypeException;
use ArrayDB\Utils\ArrayHelper;
use ArrayDB\Utils\StringHelper;

class Connector
{
    const JOIN = "none";
    const LEFT_JOIN = "left";
    const RIGHT_JOIN = "right";
    const OUTER_JOIN = "outer";
    const INNER_JOIN = "inner";
    const JOIN_DIRECTIONS = [
        self::JOIN,
        self::LEFT_JOIN,
        self::RIGHT_JOIN,
        self::OUTER_JOIN,
        self::INNER_JOIN,
    ];
    private static $defaultConnection = null;
    private $connection = null;
    private $table;
    private $alias;
    // todo: create an array definition like fields named "conditions"
    // conditions -> select / join
    // fields -> save / delete
    private $fields = [];
    private $joinContexts = [];
    private $direction = self::JOIN;

    /**
     * Context constructor.
     * @param string $table
     * @param Mysql|null $connection
     * @throws DatabaseException
     * @throws UnexpectedValueException
     */
    public function __construct(string $table, ?Mysql $connection = null)
    {
        $this->table = $table;
        if ($connection) {
            if (!$connection->isConnected()) {
                throw new UnexpectedValueException("You should connect Mysql before creating contexts");
            }
            $this->connection = $connection;
        } else {
            $this->setDefaultConnection();
        }
    }

    /**
     * @throws DatabaseException
     */
    private function setDefaultConnection(): void
    {
        if (static::$defaultConnection) {
            $this->connection = static::$defaultConnection;
            return;
        }
        $config = array_values(Settings::$CONNECTION_CONFIG);
        $this->connection = new Mysql();
        $this->connection->connect(...$config);
        static::$defaultConnection = $this->connection;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }

    protected function getFields(): array
    {
        return $this->fields;
    }

    protected function getDirection(): string
    {
        return $this->direction;
    }

    public function setAlias(string $alias): self
    {
        $this->alias = $alias;
        return $this;
    }

    public function setFields(array $fields): self
    {
        $this->fields = $fields;
        return $this;
    }

    /**
     * @throws WorthlessVariableException
     */
    private function checkFields(): void
    {
        if (empty($this->fields)) {
            throw new WorthlessVariableException("Please populate query fields");
        }
    }

    /**
     * @param string $direction
     * @return $this
     * @throws UnexpectedValueException
     */
    protected function setDirection(string $direction): self
    {
        $direction = strtolower($direction);
        if (!in_array($direction, self::JOIN_DIRECTIONS)) {
            throw new UnexpectedValueException("Invalid direction '$direction'");
        }
        $this->direction = $direction;
        return $this;
    }

    /**
     * @param array $settings
     * @return $this
     * @throws DatabaseException
     * @throws MissingFieldException
     * @throws UnexpectedValueException
     * @throws WrongTypeException
     */
    public function join(array $settings): self
    {
        if (empty($settings)) {
            throw new UnexpectedValueException();
        }
        if (!isset($settings['connection'])) {
            $settings['connection'] = $this->connection;
        }
        $this->joinContexts[] = self::toJoin($settings);
        return $this;
    }

    /**
     * @param array $fields
     * @param string $appendOperator
     * @param bool $usePreparedStatement
     * @param string $stringWrapper
     * @return array
     * @throws UnexpectedValueException
     */
    private function prepareConditionsQuery(
        array $fields,
        string $appendOperator,
        bool $usePreparedStatement,
        string $stringWrapper = "'"
    ): array
    {
        if (empty($fields)) {
            return [
                "query" => "",
                "args" => [],
            ];
        }
        $args = [];
        $conditions = $append = "";
        foreach ($fields as $inputName => $inputValue) {
            if (is_array($inputValue) && ArrayHelper::hasStringIndex($inputValue)) {
                $childAppendOperator = strpos($inputName, OperatorDecoder::FORCED_AND_OPERATOR) !== false
                    ? " AND "
                    : " OR ";
                $subConditions = $this->prepareConditionsQuery($inputValue, $childAppendOperator, $usePreparedStatement);
                $conditions .= "{$append}({$subConditions['query']})";
                $args = array_merge($args, $subConditions['args']);
            } else {
                $operator = OperatorDecoder::get($inputName, $inputValue);
                if ($operator) {
                    $queryData = $operator->getQueryData($inputValue, $usePreparedStatement, $stringWrapper);
                    $conditions .= $append . $inputName . $queryData['query'];
                    $args = array_merge($args, $queryData['args']);
                }
            }
            $append = $appendOperator;
        }
        return [
            "query" => $conditions,
            "args" => $args,
        ];
    }

    /**
     * @return string
     * @throws UnexpectedValueException
     */
    private function getJoinQuery(): string
    {
        if (empty($this->joinContexts)) {
            return "";
        }
        $joinQuery = "";
        foreach ($this->joinContexts as $context) {
            /** @var self $context */
            switch ($context->getDirection()) {
                case self::JOIN:
                    $joinQuery .= " ";
                    break;
                case self::LEFT_JOIN:
                    $joinQuery .= " LEFT ";
                    break;
                case self::RIGHT_JOIN:
                    $joinQuery .= " RIGHT ";
                    break;
                case self::INNER_JOIN:
                    $joinQuery .= " INNER ";
                    break;
                case self::OUTER_JOIN:
                    $joinQuery .= " OUTER ";
                    break;
                default:
                    throw new UnexpectedValueException("Invalid join direction {$context->getDirection()}");
                    break;
            }
            $conditions = $this->prepareConditionsQuery($context->getFields(), " AND ", false, "");
            $joinQuery .= " JOIN `{$context->getTable()}` AS `{$context->getAlias()}` ON {$conditions['query']}";
        }
        return $joinQuery;
    }

    private function prepareSelectors(array $selectors, string $aliasDot, bool $escape): string
    {
        if (empty($selectors)) {
            return $aliasDot . "*";
        }
        $result = $append = "";
        $escape = $escape ? "`" : "";
        foreach ($selectors as $selector) {
            $result .= "{$append}{$escape}{$selector}{$escape}";
            $append = ", ";
        }
        return $result;
    }

    /**
     * @param array $selectors
     * @return array
     * @throws DatabaseException
     * @throws UnexpectedValueException
     */
    public function select(array $selectors = []): array
    {
        $fields = $this->fields;
        $orderBy = ArrayHelper::getUnset($fields, "orderBy");
        $limit = ArrayHelper::getUnset($fields, "limit");
        $joins = $this->getJoinQuery();
        $aliasDot = $aliasAs = "";
        if ($joins) {
            $alias = $this->getAlias();
            if (!$alias) {
                throw new UnexpectedValueException("Alias is required to join selections");
            }
            $alias = "`$alias`";
            $aliasDot = "$alias.";
            $aliasAs = " AS $alias";
        }
        $conditions = $this->prepareConditionsQuery($fields, " AND ", true);
        $where = $conditions['query'];
        $syntax = "SELECT {selectors} FROM {table} {joins} {conditions} {orderBy} {limit}";
        $query = StringHelper::multiReplace($syntax, [
            "selectors" => $this->prepareSelectors($selectors, $aliasDot, $joins === ""),
            "table" => "`{$this->table}`{$aliasAs}",
            "joins" => $joins,
            "conditions" => $where ? " WHERE $where" : "",
            "orderBy" => $orderBy ? " ORDER BY $orderBy" : "",
            "limit" => $limit ? " LIMIT $limit" : "",
        ]);
        $this->joinContexts = [];
        return $this->connection->query($query, $conditions['args']);
    }

    public function getLastInsertedId()
    {
        return $this->connection->getLastInsertedId();
    }

    /**
     * @return array
     * @throws UnexpectedValueException
     */
    private function getFieldValues(): array
    {
        $values = array_values($this->fields);
        $subArrays = array_filter($values, "is_array");
        if (count($subArrays)) {
            throw new UnexpectedValueException("Please don't put any array on query fields for this operation");
        }
        return $values;
    }

    /**
     * @return bool
     * @throws DatabaseException
     * @throws UnexpectedValueException
     * @throws WorthlessVariableException
     */
    public function save(): bool
    {
        $this->checkFields();
        $columns = "`" . implode("`, `", array_keys($this->fields)) . "`";
        $args = $this->getFieldValues();
        $insertStatement = substr(str_repeat("?,", count($args)), 0, -1);
        $updateStatement = "`" . implode("`=?, `", array_keys($this->fields)) . "`=?";
        $query = "INSERT INTO `{$this->table}`
                    ($columns)
                    VALUES ($insertStatement)
                    ON DUPLICATE KEY UPDATE $updateStatement";
        array_push($args, ...$args);
        return $this->connection->query($query, $args);
    }

    public function delete(): bool
    {
        // todo
    }

    /**
     * @param array $settings
     * @throws MissingFieldException
     */
    private static function checkRequiredJoinInputs(array $settings): void
    {
        if (!isset($settings['table']) || !$settings['table']) {
            throw new MissingFieldException("You must inform a table.");
        } elseif (!isset($settings['alias']) || !$settings['alias']) {
            throw new MissingFieldException("You must inform an alias.");
        } elseif (!isset($settings['conditions']) || empty($settings['conditions'])) {
            throw new MissingFieldException("You must inform the conditions (e.g., ['aliasFrom.fromKey' => 'aliasJoin.joinKey']).");
        }
    }

    /**
     * @param array $settings
     * @throws WrongTypeException
     */
    private static function checkRequiredJoinInputsType(array $settings): void
    {
        if (!is_string($settings['table'])) {
            throw new WrongTypeException("Table must be string");
        } elseif (!is_string($settings['alias'])) {
            throw new WrongTypeException("Alias must be string");
        } elseif (!is_array($settings['conditions'])) {
            throw new WrongTypeException("Conditions must be array");
        } elseif ($settings['direction'] != null && !is_string($settings['direction'])) {
            throw new WrongTypeException("Direction must be string");
        }
    }

    /**
     * @param array $settings
     * @return array
     * @throws MissingFieldException
     * @throws WrongTypeException
     */
    private static function prepareJoinSettings(array $settings): array
    {
        self::checkRequiredJoinInputs($settings);
        $result = [];
        $result['table'] = ArrayHelper::getUnset($settings, "table");
        $result['alias'] = ArrayHelper::getUnset($settings, "alias");
        $result['conditions'] = ArrayHelper::getUnset($settings, "conditions");
        $result['direction'] = ArrayHelper::getUnset($settings, "direction");
        $result['connection'] = ArrayHelper::getUnset($settings, "connection");
        self::checkRequiredJoinInputsType($result);
        if (!$result['direction']) {
            $result['direction'] = self::LEFT_JOIN;
        }
        return $result;
    }

    /**
     * @param array $settings
     * @return static
     * @throws DatabaseException
     * @throws MissingFieldException
     * @throws UnexpectedValueException
     * @throws WrongTypeException
     */
    public static function toJoin(array $settings): self
    {
        $settings = self::prepareJoinSettings($settings);
        $instance = new self($settings['table'], $settings['connection']);
        return $instance
            ->setAlias($settings['alias'])
            ->setDirection($settings['direction'])
            ->setFields($settings['conditions']);
    }

    public static function generateJoinSettings(
        string $table,
        string $alias,
        array $conditions,
        string $direction = self::JOIN,
        ?Mysql $connection = null
    ): array
    {
        return [
            "table" => $table,
            "alias" => $alias,
            "conditions" => $conditions,
            "direction" => $direction,
            "connection" => $connection,
        ];
    }
}