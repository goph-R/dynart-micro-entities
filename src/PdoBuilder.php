<?php

namespace Dynart\Micro\Entities;

use PDO;

class PdoBuilder {

    protected string $dsn = '';
    protected string $username = '';
    protected string $password = '';
    protected array $options = [];

    public function dsn(string $value): static {
        $this->dsn = $value;
        return $this;
    }

    public function username(string $value): static {
        $this->username = $value;
        return $this;
    }

    public function password(string $value): static {
        $this->password = $value;
        return $this;
    }

    public function options(array $value): static {
        $this->options = $value;
        return $this;
    }

    public function build(): PDO {
        return new PDO($this->dsn, $this->username, $this->password, $this->options);
    }
}
