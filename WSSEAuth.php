<?php

class WSSEAuth {

    private $Username;
    private $Password;

    function __construct($username, $password) {
        $this->Username = $username;
        $this->Password = $password;
    }

}
