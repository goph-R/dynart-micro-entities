<?php

namespace Dynart\Micro\Entities;

use Dynart\Micro\Config;
use Dynart\Micro\Logger;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

abstract class Database
{
    protected $configName = 'default';
    protected $connected = false;

    /** @var PDO */
    protected $pdo;

    /** @var Config */
    protected $config;

    /** @var Logger */
    protected $logger;

    /** @var PdoBuilder */
    protected $pdoBuilder;

    abstract protected function connect(): void;
    abstract public function escapeName(string $name): string;
    abstract public function escapeLike(string $string): string;

    public function __construct(Config $config, Logger $logger, PdoBuilder $pdoBuilder) {
        $this->config = $config;
        $this->logger = $logger;
        $this->pdoBuilder = $pdoBuilder;
    }

    public function connected(): bool {
        return $this->connected;
    }

    protected function setConnected(bool $value): void {
        $this->connected = $value;
    }

    public function query(string $query, array $params = [], bool $closeCursor = false) {
        try {
            $this->connect();
            $query = $this->replaceClassHashNamesWithTableNames($query);
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            if ($this->logger->level() == Logger::DEBUG) { // because of the json_encode
                $this->logger->debug("Query: $query" . $this->getParametersString($params));
            }
        } catch (PDOException $e) {
            $this->logger->error("Error in query: $query" . $this->getParametersString($params));
            throw $e;
        }
        if ($closeCursor) {
            $stmt->closeCursor();
        }
        return $stmt;
    }

    protected function replaceClassHashNamesWithTableNames(string $query) {
        return preg_replace_callback(
            '/(\'[^\'"#]*\')|(#[A-Za-z0-9_]+(?=[\s\n\r\.`]|$))/',
            function ($matches) {
                if ($matches[1]) {
                    return $matches[1]; // Keep content within single quotes unchanged
                } else {
                    return $this->configValue('table_prefix').strtolower(substr($matches[0], 1));
                }
            },
            $query
        );
    }

    protected function getParametersString($params): string {
        return $params ? "\nParameters: " . ($params ? json_encode($params) : '') : "";
    }

    public function configValue(string $name) {
        return $this->config->get("database.{$this->configName}.$name", "db_{$name}_missing");
    }

    public function fetch($query, $params = [], string $className = '') {
        $stmt = $this->query($query, $params);
        $this->setFetchMode($stmt, $className);
        $result = $stmt->fetch();
        $stmt->closeCursor();
        return $result;
    }

    public function fetchAll(string $query, array $params = [], string $className = '') {
        $stmt = $this->query($query, $params);
        $this->setFetchMode($stmt, $className);
        $result = $stmt->fetchAll();
        $stmt->closeCursor();
        return $result;
    }

    protected function setFetchMode(PDOStatement $stmt, string $className) {
        if ($className) {
            $stmt->setFetchMode(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, $className);
        } else {
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
        }
    }

    public function fetchColumn(string $query, array $params = []): array {
        /** @var PDOStatement $stmt */
        $stmt = $this->query($query, $params);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $stmt->closeCursor();
        $result = [];
        foreach ($rows as $row) {
            $result[] = $row;
        }
        return $result;
    }

    public function fetchOne(string $query, array $params = []) {
        $stmt = $this->query($query, $params);
        $result = $stmt->fetchColumn(0);
        $stmt = null;
        return $result;
    }

    public function lastInsertId($name = null) {
        return $this->pdo->lastInsertId($name);
    }

    public function insert(string $tableName, array $data) {
        $tableName = $this->escapeName($tableName);
        $params = [];
        $names = [];
        foreach ($data as $name => $value) {
            $names[] = $this->escapeName($name);
            $params[':' . $name] = $value;
        }
        $namesString = join(', ', $names);
        $paramsString = join(', ', array_keys($params));
        $sql = "insert into $tableName ($namesString) values ($paramsString)";
        $this->query($sql, $params, true);
    }

    public function update(string $tableName, array $data, string $condition = '', array $conditionParams = []) {
        $tableName = $this->escapeName($tableName);
        $params = [];
        $pairs = [];
        foreach ($data as $name => $value) {
            $pairs[] = $this->escapeName($name) . ' = :' . $name;
            $params[':' . $name] = $value;
        }
        $params = array_merge($params, $conditionParams);
        $pairsString = join(', ', $pairs);
        $where = $condition ? ' where ' . $condition : '';
        $sql = "update $tableName set $pairsString$where";
        $this->query($sql, $params, true);
    }

    public function getInConditionAndParams(array $values, $paramNamePrefix = 'in'): array {
        $params = [];
        $in = "";
        foreach ($values as $i => $item) {
            $key = ":" . $paramNamePrefix . $i;
            $in .= "$key,";
            $params[$key] = $item;
        }
        $condition = rtrim($in, ",");
        return [$condition, $params];
    }

    public function beginTransaction(): bool {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool {
        return $this->pdo->commit();
    }

    public function rollBack(): bool {
        return $this->pdo->rollBack();
    }

    public function runInTransaction($callable) {
        $this->beginTransaction();
        try {
            call_user_func($callable); // here the CREATE/DROP table can COMMIT implicitly
            $this->commit(); // here it drops an exception because of that
        } catch (RuntimeException $e) {
            // ignore "There is no active transaction"
            if ($e->getMessage() == "There is no active transaction") {
                return;
            }
            $this->rollBack();
            throw $e;
        }
    }
}