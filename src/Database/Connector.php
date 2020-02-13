<?php

namespace ArrayDB\Database;

use ArrayDB\Drivers\DriverInterface;
use ArrayDB\Exceptions\DatabaseException;
use ArrayDB\Exceptions\InvalidJoinConnectionException;
use ArrayDB\Exceptions\MissingFieldException;
use ArrayDB\Exceptions\UnauthorizedDatabaseMethodException;
use ArrayDB\Exceptions\UnexpectedResultException;
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
    private $conditions = [];
    private $fields = [];
    private $joinContexts = [];
    private $direction = self::JOIN;

    /**
     * Context constructor.
     * @param string $table
     * @param Mysql|null $connection
     * @throws DatabaseException
     * @throws UnexpectedValueException
     * @throws WrongTypeException
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
     * @throws UnexpectedValueException
     * @throws WrongTypeException
     */
    private function setDefaultConnection(): void
    {
        if (static::$defaultConnection) {
            $this->connection = static::$defaultConnection;
            return;
        }
        $this->connection = new Mysql();
        $this->connection->connect(Settings::$CONNECTION_CONFIG);
        static::$defaultConnection = $this->connection;
    }

    public function getSchema(): ?string
    {
        return $this->connection->getSchema();
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }

    public function getConditions(): array
    {
        return $this->conditions;
    }

    protected function getFields(): array
    {
        return $this->fields;
    }

    private function extractPrivileges(string $grant): array
    {
        $grants = explode(" ON ", str_replace("GRANT ", "", $grant));
        $grants = explode(",", $grants[0]);
        $list = [];
        foreach ($grants as $privilege) {
            if ($privilege !== "") {
                $list[] = trim($privilege);
            }
        }
        return $list;
    }

    /**
     * @return array
     * @throws DatabaseException
     */
    private function getPermissions(): array
    {
        $grants = $this->connection->query("SHOW GRANTS FOR CURRENT_USER");
        if (empty($grants)) {
            return [];
        }
        $permissions = [];
        foreach($grants as $grantQuery) {
            $grantQuery = array_values($grantQuery);
            $privileges = $this->extractPrivileges($grantQuery[0]);
            $permissions = array_merge($permissions, $privileges);
        }
        return array_unique($permissions);
    }

    /**
     * @param array $alternatives
     * @throws DatabaseException
     * @throws UnauthorizedDatabaseMethodException
     */
    private function checkPermission(array $alternatives): void
    {
        $permissions = $this->getPermissions();
        if (in_array("ALL PRIVILEGES", $permissions)) {
            return;
        }
        foreach ($alternatives as $permission) {
            $permission = strtoupper(trim($permission));
            if (in_array($permission, $permissions)) {
                return;
            }
        }
        throw new UnauthorizedDatabaseMethodException();
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

    public function setConditions(array $conditions): self
    {
        $this->conditions = $conditions;
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
    private function checkConditions(): void
    {
        if (empty($this->conditions)) {
            throw new WorthlessVariableException("Please populate query conditions");
        }
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
     * @return Mysql
     * @throws DatabaseException
     * @throws InvalidJoinConnectionException
     * @throws UnexpectedValueException
     * @throws WrongTypeException
     */
    private function prepareJoinConnection(array $settings): Mysql
    {
        if (!isset($settings['connection']) || !is_array($settings['connection'])) {
            return $this->connection;
        } elseif (!isset($settings['connection']['host']) || $settings['connection']['host'] !== $this->connection->getHost()) {
            throw new InvalidJoinConnectionException(
                "Joined table's connection must be from {$this->connection->getHost()}. '{$settings['connection']['host']}' received."
            );
        }
        $mysql = new Mysql();
        $mysql->connect($settings['connection']);
        return $mysql;
    }

    /**
     * @param array $settings
     * @return $this
     * @throws DatabaseException
     * @throws InvalidJoinConnectionException
     * @throws MissingFieldException
     * @throws UnexpectedValueException
     * @throws WrongTypeException
     */
    public function join(array $settings): self
    {
        if (empty($settings)) {
            throw new UnexpectedValueException("Join settings can't be empty.");
        }
        $settings['connection'] = $this->prepareJoinConnection($settings);
        $this->joinContexts[] = self::toJoin($settings);
        return $this;
    }

    /**
     * @param array $conditions
     * @param string $appendOperator
     * @param bool $usePreparedStatement
     * @param string $stringWrapper
     * @return array
     * @throws UnexpectedValueException
     */
    private function prepareConditionsQuery(
        array $conditions,
        string $appendOperator,
        bool $usePreparedStatement,
        string $stringWrapper = "'"
    ): array
    {
        if (empty($conditions)) {
            return [
                "query" => "",
                "args" => [],
            ];
        }
        $args = [];
        $query = $append = "";
        foreach ($conditions as $inputName => $inputValue) {
            if (is_array($inputValue) && ArrayHelper::hasStringIndex($inputValue)) {
                $childAppendOperator = strpos($inputName, OperatorDecoder::FORCED_AND_OPERATOR) !== false
                    ? " AND "
                    : " OR ";
                $subConditions = $this->prepareConditionsQuery($inputValue, $childAppendOperator, $usePreparedStatement);
                $query .= "{$append}({$subConditions['query']})";
                $args = array_merge($args, $subConditions['args']);
            } else {
                $operator = OperatorDecoder::get($inputName, $inputValue);
                if ($operator) {
                    $queryData = $operator->getQueryData($inputValue, $usePreparedStatement, $stringWrapper);
                    $query .= $append . $inputName . $queryData['query'];
                    $args = array_merge($args, $queryData['args']);
                }
            }
            $append = $appendOperator;
        }
        return [
            "query" => $query,
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
            $conditions = $this->prepareConditionsQuery($context->getConditions(), " AND ", false, "");
            $joinQuery .= " JOIN `{$context->getSchema()}`.`{$context->getTable()}` AS `{$context->getAlias()}` ON {$conditions['query']}";
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
            $mustEscape = strpos($selector, "(") === false && strpos($selector, "`") === false;
            $selectorEscape = $mustEscape ? $escape : "";
            $result .= "{$append}{$selectorEscape}{$selector}{$selectorEscape}";
            $append = ", ";
        }
        return $result;
    }

    /**
     * @param array $selectors
     * @return array
     * @throws DatabaseException
     * @throws UnauthorizedDatabaseMethodException
     * @throws UnexpectedValueException
     */
    public function select(array $selectors = []): array
    {
        $this->checkPermission(["SELECT"]);
        $conditions = $this->getConditions();
        $orderBy = ArrayHelper::getUnset($conditions, "orderBy");
        $limit = ArrayHelper::getUnset($conditions, "limit");
        $joins = $this->getJoinQuery();
        $schemaDot = $aliasDot = $aliasAs = "";
        if ($joins) {
            $alias = $this->getAlias();
            if (!$alias) {
                throw new UnexpectedValueException("Alias is required to join selections");
            }
            $alias = "`$alias`";
            $aliasDot = "$alias.";
            $aliasAs = " AS $alias";
            $schemaDot = "`{$this->getSchema()}`.";
        }
        $query = $this->prepareConditionsQuery($conditions, " AND ", true);
        $where = $query['query'];
        $args = $query['args'];
        $syntax = "SELECT {selectors} FROM {table} {joins} {conditions} {orderBy} {limit}";
        $query = StringHelper::multiReplace($syntax, [
            "selectors" => $this->prepareSelectors($selectors, $aliasDot, $joins === ""),
            "table" => "{$schemaDot}`{$this->table}`{$aliasAs}",
            "joins" => $joins,
            "conditions" => $where ? " WHERE $where" : "",
            "orderBy" => $orderBy ? " ORDER BY $orderBy" : "",
            "limit" => $limit ? " LIMIT $limit" : "",
        ]);
        $result = $this->connection->query($query, $args);
        $this->conditions = $this->joinContexts = [];
        return $result;
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

    private function getFieldColumns(): string
    {
        return "`" . implode("`, `", array_keys($this->fields)) . "`";
    }

    private function getInsertStatement(array $fieldValues): string
    {
        return substr(str_repeat("?,", count($fieldValues)), 0, -1);
    }

    private function getUpdateStatement(): string
    {
        return "`" . implode("`=?, `", array_keys($this->fields)) . "`=?";
    }

    /**
     * @return bool
     * @throws DatabaseException
     * @throws UnauthorizedDatabaseMethodException
     * @throws UnexpectedValueException
     * @throws WorthlessVariableException
     */
    public function save(): bool
    {
        $this->checkPermission(["INSERT", "UPDATE"]);
        $this->checkFields();
        $args = $this->getFieldValues();
        $query = "INSERT INTO `{$this->table}` ({$this->getFieldColumns()}) VALUES ({$this->getInsertStatement($args)})
                    ON DUPLICATE KEY UPDATE {$this->getUpdateStatement()}";
        array_push($args, ...$args);
        $result = $this->connection->query($query, $args);
        $this->fields = [];
        return $result;
    }

    /**
     * @return bool
     * @throws DatabaseException
     * @throws UnauthorizedDatabaseMethodException
     * @throws UnexpectedValueException
     * @throws WorthlessVariableException
     */
    public function insert(): bool
    {
        // todo: insert + select
        $this->checkPermission(["INSERT"]);
        $this->checkFields();
        $args = $this->getFieldValues();
        $query = "INSERT INTO `{$this->table}` ({$this->getFieldColumns()}) VALUES ({$this->getInsertStatement($args)})";
        $result = $this->connection->query($query, $args);
        $this->fields = [];
        return $result;
    }

    /**
     * @return bool
     * @throws DatabaseException
     * @throws UnauthorizedDatabaseMethodException
     * @throws UnexpectedValueException
     * @throws WorthlessVariableException
     */
    public function update(): bool
    {
        // todo: update + join
        $this->checkPermission(["UPDATE"]);
        $this->checkFields();
        $this->checkConditions();
        $conditions = $this->getConditions();
        $query = $this->prepareConditionsQuery($conditions, " AND ", true);
        $args = array_merge($this->getFieldValues(), $query['args']);
        $query = "UPDATE `{$this->table}` SET {$this->getUpdateStatement()} WHERE {$query['query']}";
        $result = $this->connection->query($query, $args);
        $this->fields = $this->conditions = [];
        return $result;

    }

    /**
     * @return string
     * @throws DatabaseException
     * @throws UnexpectedResultException
     * @throws UnexpectedValueException
     */
    public function getPrimaryKeyColumn(): string
    {
        $result = $this->connection->query(
            "SHOW KEYS FROM `{$this->table}` WHERE Key_name='PRIMARY'"
        );
        $args = $this->getFieldValues();
        if (empty($result)) {
            throw new UnexpectedResultException("Couldn't discover the primary key column name from table '{$this->table}'");
        }
        return $result[0]["Column_name"];
    }

    /**
     * @param bool $safeDelete
     * @return bool
     * @throws DatabaseException
     * @throws MissingFieldException
     * @throws UnauthorizedDatabaseMethodException
     * @throws UnexpectedResultException
     * @throws UnexpectedValueException
     * @throws WorthlessVariableException
     */
    public function delete($safeDelete = true): bool
    {
        // todo: delete with join (selecting which tables to delete)
        $this->checkPermission(["DELETE"]);
        $this->checkConditions();
        if ($safeDelete) {
            $primaryKeyColumn = $this->getPrimaryKeyColumn();
            if (!isset($this->conditions[$primaryKeyColumn])) {
                throw new MissingFieldException("Missing required field '$primaryKeyColumn' (or disable safe delete)");
            }
        }
        $query = $this->prepareConditionsQuery($this->getConditions(), " AND ", true);
        $args = $query['args'];
        $query = "DELETE FROM `{$this->table}` WHERE {$query['query']}";
        $result = $this->connection->query($query, $args);
        $this->conditions = [];
        return $result;
    }

    /**
     * @return bool
     * @throws DatabaseException
     * @throws UnauthorizedDatabaseMethodException
     */
    public function truncate(): bool
    {
        // todo: truncate multiple tables
        $this->checkPermission(["DELETE"]);
        $query = "TRUNCATE `{$this->connection->getSchema()}`.`{$this->getTable()}`";
        return $this->connection->query($query);
    }

    /**
     * @param string $schema
     * @return bool
     * @throws DatabaseException
     * @throws UnauthorizedDatabaseMethodException
     */
    public function createSchema(string $schema): bool
    {
        $this->checkPermission(["CREATE"]);
        $query = "CREATE DATABASE IF NOT EXISTS `$schema`";
        $created = $this->connection->query($query);
        if ($created) {
            $this->connection->useSchema($schema);
        }
        return $created;
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
    private static function toJoin(array $settings): self
    {
        $settings = self::prepareJoinSettings($settings);
        $instance = new self($settings['table'], $settings['connection']);
        return $instance
            ->setAlias($settings['alias'])
            ->setDirection($settings['direction'])
            ->setConditions($settings['conditions']);
    }

    public static function generateJoinSettings(
        string $table,
        string $alias,
        array $conditions,
        string $direction = self::JOIN,
        ?array $connection = null
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