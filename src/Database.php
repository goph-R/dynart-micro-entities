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

    /** Set by `EntityManager`, so `#ClassName` honours a `#[Table(name: ...)]` override */
    protected mixed $tableNameResolver = null;

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
        $params = $this->bindable($params);
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

    /**
     * Parameters PDO can bind without changing what they mean
     *
     * `PDOStatement::execute()` binds every value in the array as a string, and `false` as a
     * string is `''`. A database in strict mode - which is the default in MySQL since 5.7 and
     * in MariaDB since 10.2 - refuses `''` for an integer column, so a `bool` field on its way
     * into a `tinyint` is an error rather than a zero. Worse, it is an error *only* on the
     * servers that are configured correctly: a lenient one coerces the empty string to 0 and
     * says nothing, so `false` appears to work everywhere it is written and then fails on the
     * first install somewhere else.
     *
     * Here rather than at each call site, because this is the one place every insert, update
     * and condition passes through, and a boolean bound by hand in a `where` has the same
     * problem. `null` is left alone: PDO binds that as SQL NULL, which is what it means.
     */
    protected function bindable(array $params): array {
        foreach ($params as $name => $value) {
            if (is_bool($value)) {
                $params[$name] = (int)$value;
            }
        }
        return $params;
    }

    /**
     * Resolves a `#ClassName` token to a table name, when one is set
     *
     * `EntityManager` registers itself here, so a `#[Table(name: 'user_role')]` override is
     * honoured. Without it the substitution would compute the name itself and quietly disagree
     * with the entity metadata for every renamed table.
     */
    public function setTableNameResolver(?callable $resolver): void {
        $this->tableNameResolver = $resolver;
    }

    protected function replaceClassHashNamesWithTableNames(string $query): string {
        return preg_replace_callback(
            '/(\'[^\'"#]*\')|(#[A-Za-z0-9_]+(?=[\s\n\r\.`]|$))/',
            function ($matches) {
                if ($matches[1]) {
                    return $matches[1]; // Keep content within single quotes unchanged
                }
                $name = substr($matches[0], 1);
                if ($this->tableNameResolver !== null) {
                    $resolved = call_user_func($this->tableNameResolver, $name);
                    if (is_string($resolved) && $resolved !== '') {
                        return $resolved;
                    }
                }
                return $this->configValue('table_prefix').strtolower($name);
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

    /**
     * The prefix of the placeholders generated for the SET clause
     *
     * The condition is written by the caller and can bind whatever names it likes, so the
     * generated ones are namespaced to keep the two apart. Without it a condition binding `:name`
     * while `name` is also being updated would overwrite the new value with the condition's, and
     * the row would be updated to whatever it was being searched by - silently.
     */
    const UPDATE_PARAM_PREFIX = ':set_';

    public function update(string $tableName, array $data, string $condition = '', array $conditionParams = []): void {
        $tableName = $this->escapeName($tableName);
        $params = [];
        $pairs = [];
        foreach ($data as $name => $value) {
            $paramName = self::UPDATE_PARAM_PREFIX . $name;
            $pairs[] = $this->escapeName($name) . ' = ' . $paramName;
            $params[$paramName] = $value;
        }
        foreach ($conditionParams as $name => $value) {
            if (array_key_exists($name, $params)) {
                throw new EntityManagerException(
                    "The condition parameter '$name' collides with a generated one."
                    ." Don't start a condition parameter name with '".self::UPDATE_PARAM_PREFIX."'."
                );
            }
            $params[$name] = $value;
        }
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
