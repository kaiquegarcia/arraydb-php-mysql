<?php

namespace ArrayDB\Database;

use ArrayDB\Exceptions\DatabaseException;
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
     * @param string $host
     * @param string $username
     * @param string $password
     * @param string $schema
     * @param string $charset
     * @throws DatabaseException
     */
    public function connect(string $host, string $username, string $password, string $schema, string $charset = "utf-8"): void
    {
        $this->con = new mysqli($host, $username, $password, $schema);
        $this->checkDatabaseError();
        $this->con->set_charset($charset);
        $this->setHost($host);
        $this->setSchema($schema);
        $this->connected = true;
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
        return $execute instanceof mysqli_result
        && $execute->field_count
        && $execute->num_rows
            ? (array)$execute->fetch_all(MYSQLI_ASSOC)
            : $execute === true;
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
        $result = $statement->get_result();
        if ($result instanceof mysqli_result && $result->field_count) {
            return $result->num_rows ? (array)$result->fetch_all(MYSQLI_ASSOC) : [];
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
}