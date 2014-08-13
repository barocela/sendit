<?php

class WSSEToken {

   private $UsernameToken;

   function __construct($innerVal) {
      $this->UsernameToken = $innerVal;
   }

}