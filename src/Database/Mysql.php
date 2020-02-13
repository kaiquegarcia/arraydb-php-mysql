<?php

namespace ArrayDB\Database;

use ArrayDB\Exceptions\DatabaseException;
use ArrayDB\Exceptions\UnexpectedValueException;
use ArrayDB\Exceptions\WrongTypeException;
use ArrayDB\Utils\ArrayHelper;
use mysqli;
use mysqli_result;
use mysqli_stmt;

class Mysql
{
    /** @var mysqli $con */
    private $con = false;
    private $host;
    private $schema;
    private $connected;

    /**
     * @throws DatabaseException
     */
    private function checkDatabaseError(): void
    {
        if ($this->con->errno) {
            throw new DatabaseException($this->con->error);
        }
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function getSchema(): ?string
    {
        return $this->schema;
    }

    public function getHost(): ?string
    {
        return $this->host;
    }

    private function setHost(string $host): void
    {
        $this->host = $host;
    }

    private function setSchema(string $schema): void
    {
        $this->schema = $schema;
    }

    public function useSchema(string $schema): void
    {
        $this->con->select_db($schema);
        $this->setSchema($schema);
    }

    /**
     * @param array $settings
     * @return Mysql
     * @throws DatabaseException
     * @throws UnexpectedValueException
     * @throws WrongTypeException
     */
    public function connect(array $settings): self
    {
        $settings = self::prepareConnectionInput($settings);
        $charset = ArrayHelper::getUnset($settings, "charset") ?? "utf-8";
        $this->con = new mysqli(...array_values($settings));
        $this->checkDatabaseError();
        $this->con->set_charset($charset);
        $this->setHost($settings['host']);
        $this->setSchema($settings['schema']);
        $this->connected = true;
        return $this;
    }

    /**
     * @param string $query
     * @param array $fields
     * @return array|bool
     * @throws DatabaseException
     */
    public function query(string $query, array $fields = [])
    {
        if (!$this->con instanceof mysqli) {
            throw new DatabaseException("You must connect first");
        }
        if (empty($fields)) {
            return $this->simpleQuery($query);
        }
        return $this->preparedStatement($query, $fields);
    }

    /**
     * @param string $query
     * @return array|bool
     * @throws DatabaseException
     */
    private function simpleQuery(string $query)
    {
        $execute = $this->con->query($query);
        $this->checkDatabaseError();
        if (
            $execute instanceof mysqli_result
        ) {
            if (method_exists($execute, "fetch_all")) {
                return (array)$execute->fetch_all(MYSQLI_ASSOC);
            }
            $rows = [];
            while ($row = $execute->fetch_assoc()) {
                $rows[] = $row;
            }
            return $rows;
        }
        return $execute === true;
    }

    /**
     * @param array $fields
     * @return string
     */
    private function getTypesFromFields(array $fields): string
    {
        $types = "";
        foreach ($fields as $field) {
            if (is_int($field)) {
                $types .= "i";
            } elseif (is_float($field)) {
                $types .= "d";
            } else {
                $types .= "s";
            }
        }
        return $types;
    }

    private function getResultWithMysqlDriver(mysqli_stmt $statement)
    {
        $result = $statement->get_result();
        if ($result instanceof mysqli_result && $result->field_count) {
            return (array)$result->fetch_all(MYSQLI_ASSOC);
        }
        return null;
    }

    private function bindResultFields(mysqli_stmt $statement): ?array
    {
        $fields = $anchors = [];
        $metaData = $statement->result_metadata();
        if (!$metaData) {
            return null;
        }
        while ($field = $metaData->fetch_field()) {
            $fields[$field->name] = "";
            $anchors[] = &$fields[$field->name];
        }

        if (empty($fields)) {
            return null;
        }

        $statement->bind_result(...$anchors);
        $statement->store_result();

        return $fields;
    }

    private function fetchAssoc(mysqli_stmt $statement, array &$fields): ?array
    {
        if ($statement->fetch()) {
            return $fields;
        }
        return null;
    }

    private function getResultWithoutMysqlDriver(mysqli_stmt $statement)
    {
        $fields = $this->bindResultFields($statement);
        if ($fields === null) {
            return null;
        }
        $rows = [];
        while ($row = $this->fetchAssoc($statement, $fields)) {
            $rows[] = ArrayHelper::getClone($row);
        }
        return $rows;
    }

    /**
     * @param string $query
     * @param array $fields
     * @return array|bool
     * @throws DatabaseException
     */
    private function preparedStatement(string $query, array $fields)
    {
        $statement = $this->con->prepare($query);
        $this->checkDatabaseError();
        if (!$statement instanceof mysqli_stmt) {
            throw new DatabaseException("Couldn't prepare this query");
        }
        $types = $this->getTypesFromFields($fields);
        $statement->bind_param($types, ...$fields);
        $executed = $statement->execute();
        $this->checkDatabaseError();
        if (!$executed) {
            throw new DatabaseException("Couldn't execute this query");
        }
        if (method_exists($statement, "get_result")) {
            $result = $this->getResultWithMysqlDriver($statement);
        } else {
            $result = $this->getResultWithoutMysqlDriver($statement);
        }
        if ($result !== null) {
            return $result;
        }
        return $this->con->affected_rows > 0;
    }

    public function getLastInsertedId()
    {
        if ($this->con instanceof mysqli) {
            return $this->con->insert_id;
        }
        return null;
    }

    /**
     * @param array $settings
     * @throws UnexpectedValueException
     * @throws WrongTypeException
     */
    private static function validateConnectionInputs(array $settings): void
    {
        if (!isset($settings['host']) || !is_string($settings['host'])) {
            throw new UnexpectedValueException("You must inform a host to connect.");
        } elseif (!isset($settings['username']) || !is_string($settings['username'])) {
            throw new UnexpectedValueException("You must inform an username.");
        } elseif (!isset($settings['password']) || !is_string($settings['password'])) {
            throw new UnexpectedValueException("You must inform a password.");
        } elseif (isset($settings['schema']) && !is_string($settings['schema'])) {
            throw new WrongTypeException("schema should be string.");
        } elseif (isset($settings['charset']) && !is_string($settings['charset'])) {
            throw new WrongTypeException("charset should be string.");
        }
    }

    /**
     * @param array $settings
     * @return array
     * @throws UnexpectedValueException
     * @throws WrongTypeException
     */
    public static function prepareConnectionInput(array $settings): array
    {
        self::validateConnectionInputs($settings);
        $charset = ArrayHelper::getUnset($settings, "charset");
        if (!$charset) {
            $charset = "utf8";
        }
        return [
            "host" => ArrayHelper::getUnset($settings, "host"),
            "username" => ArrayHelper::getUnset($settings, "username"),
            "password" => ArrayHelper::getUnset($settings, "password"),
            "schema" => ArrayHelper::getUnset($settings, "schema"),
            "charset" => $charset,
        ];
    }
}