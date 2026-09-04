<?php

namespace Dynart\Micro\Entities\Database;

use Dynart\Micro\Entities\Database;

class MariaDatabase extends Database {

    protected function connect(): void {
        if ($this->connected()) {
            return;
        }
        $this->pdo = $this->pdoBuilder
            ->dsn($this->configValue('dsn'))
            ->username($this->configValue('username'))
            ->password($this->configValue('password'))
            ->options([\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION])
            ->build();
        $this->setConnected(true);
        $dbName = $this->escapeName($this->configValue('name'));
        $this->query("use $dbName");
        // `utf8mb4` and not `utf8`: MySQL's `utf8` is three bytes per character, which is every
        // character except the ones people actually notice - an emoji is four, and on a three byte
        // connection it does not arrive as a broken glyph, it arrives as `????`. The data is gone
        // by the time anybody sees the page. There is no case for the narrow one.
        $this->query("set names 'utf8mb4'");
    }

    public function escapeName(string $name): string {
        $parts = explode('.', $name);
        return '`'.join('`.`', $parts).'`';
    }

    public function escapeLike(string $string): string {
        return str_replace('%', '\\%', $string);
    }
}