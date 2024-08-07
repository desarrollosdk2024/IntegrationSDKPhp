<?php

namespace Integration\Util;

class Step {
    public $action;
    public $identifier;
    public $name;
    public $message;
    public $fields;
    public $func;

    public function __construct($action = null, $identifier = null, $name = null, $message = null, $fields = [], $func = null) {
        $this->action = $action;
        $this->identifier = $identifier;
        $this->name = $name;
        $this->message = $message;
        $this->fields = $fields;
        $this->func = $func;
    }
}