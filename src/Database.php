<?php

namespace Dynart\Micro\Entities;

use Dynart\Micro\ConfigInterface;
use Dynart\Micro\LoggerInterface;
use PDO;
use PDOException;
use PDOStatement;
use Psr\Log\LogLevel;
use RuntimeException;

abstract class Database
{
    protected string $configName = 'default';
    protected bool $connected = false;
    protected ?PDO $pdo = null;

    abstract protected function connect(): void;
    abstract public function escapeName(string $name): string;
    abstract public function escapeLike(string $string): string;

    public function __construct(
        protected ConfigInterface $config,
        protected LoggerInterface $logger,
        protected PdoBuilder $pdoBuilder,
    ) {}

    public function connected(): bool {
        return $this->connected;
    }

    protected function setConnected(bool $value): void {
        $this->connected = $value;
    }

    public function query(string $query, array $params = [], bool $closeCursor = false): PDOStatement {
        try {
            $this->connect();
            $query = $this->replaceClassHashNamesWithTableNames($query);
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            if ($this->logger->level() == LogLevel::DEBUG) {
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

    protected function replaceClassHashNamesWithTableNames(string $query): string {
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

    protected function getParametersString(array $params): string {
        return $params ? "\nParameters: " . json_encode($params) : "";
    }

    public function configValue(string $name): mixed {
        return $this->config->get("database.{$this->configName}.$name", "db_{$name}_missing");
    }

    public function fetch(string $query, array $params = [], string $className = ''): mixed {
        $stmt = $this->query($query, $params);
        $this->setFetchMode($stmt, $className);
        $result = $stmt->fetch();
        $stmt->closeCursor();
        return $result;
    }

    public function fetchAll(string $query, array $params = [], string $className = ''): array {
        $stmt = $this->query($query, $params);
        $this->setFetchMode($stmt, $className);
        $result = $stmt->fetchAll();
        $stmt->closeCursor();
        return $result;
    }

    protected function setFetchMode(PDOStatement $stmt, string $className): void {
        if ($className) {
            $stmt->setFetchMode(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, $className);
        } else {
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
        }
    }

    public function fetchColumn(string $query, array $params = []): array {
        $stmt = $this->query($query, $params);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $stmt->closeCursor();
        $result = [];
        foreach ($rows as $row) {
            $result[] = $row;
        }
        return $result;
    }

    public function fetchOne(string $query, array $params = []): mixed {
        $stmt = $this->query($query, $params);
        $result = $stmt->fetchColumn(0);
        $stmt = null;
        return $result;
    }

    public function lastInsertId(?string $name = null): string|false {
        return $this->pdo->lastInsertId($name);
    }

    public function insert(string $tableName, array $data): void {
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

    public function update(string $tableName, array $data, string $condition = '', array $conditionParams = []): void {
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

    public function getInConditionAndParams(array $values, string $paramNamePrefix = 'in'): array {
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

    public function runInTransaction(callable $callable): void {
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
