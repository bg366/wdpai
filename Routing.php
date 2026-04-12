<?php

class Routing
{
    public static function run($path)
    {
        $path = explode("/", $path)[0];

        switch ($path) {
            case "dashboard":
                include "public/views/dashboard.html";
                break;
            case "login":
                include "public/views/login.html";
                break;
            default:
                include "public/views/404.html";
                break;
        }
    }
}
